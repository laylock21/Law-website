<?php
/**
 * API Endpoint: Get Lawyer Availability
 * Returns available dates for a specific lawyer
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

// Cache headers for performance (shorter cache for availability data)
header('Cache-Control: public, max-age=60'); // Cache for 1 minute
header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 60) . ' GMT');

require_once '../config/database.php';

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get lawyer name from query parameter
$lawyer_name = $_GET['lawyer'] ?? '';

if (empty($lawyer_name)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Lawyer name is required']);
    exit;
}

try {
    $pdo = getDBConnection();
    
    if (!$pdo) {
        throw new Exception('Database connection failed');
    }
    
    // Get lawyer ID and date preferences from name
    // Note: Frontend already strips "Atty." prefix before sending, but double-check for safety
    $lawyer_name_clean = preg_replace('/^Atty\.\s*/i', '', $lawyer_name);
    
    $lawyer_stmt = $pdo->prepare("
        SELECT id, default_booking_weeks, max_booking_weeks, booking_window_enabled
        FROM users 
        WHERE CONCAT(first_name, ' ', last_name) = ? 
        AND role = 'lawyer' 
        AND is_active = 1
    ");
    $lawyer_stmt->execute([$lawyer_name_clean]);
    $lawyer = $lawyer_stmt->fetch();
    
    if (!$lawyer) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Lawyer not found']);
        exit;
    }
    
    // Get ALL lawyer's schedules (weekly, one-time, and blocked)
    $availability_stmt = $pdo->prepare("
        SELECT schedule_type, specific_date, start_date, end_date, weekdays, start_time, end_time, max_appointments, blocked_reason 
        FROM lawyer_availability 
        WHERE user_id = ? 
        AND is_active = 1
        ORDER BY schedule_type, specific_date
    ");
    $availability_stmt->execute([$lawyer['id']]);
    $schedules = $availability_stmt->fetchAll();
    
    if (count($schedules) > 0) {
        $available_dates = [];
        
        // Allow customizable date range via query parameters
        // Use lawyer's individual preferences as defaults
        $lawyer_default_weeks = (int)($lawyer['default_booking_weeks'] ?? 52);
        $lawyer_max_weeks = (int)($lawyer['max_booking_weeks'] ?? 104);
        $booking_enabled = (bool)($lawyer['booking_window_enabled'] ?? true);
        
        $start_date_param = $_GET['start_date'] ?? '';
        $end_date_param = $_GET['end_date'] ?? '';
        $weeks_ahead = (int)($_GET['weeks'] ?? $lawyer_default_weeks); // Use lawyer's default
        
        // Enforce lawyer's maximum booking window
        if ($weeks_ahead > $lawyer_max_weeks) {
            $weeks_ahead = $lawyer_max_weeks;
        }
        
        // Validate and set start date
        if (!empty($start_date_param) && strtotime($start_date_param)) {
            $start_date = new DateTime($start_date_param);
            // Ensure start date is not in the past
            $today = new DateTime();
            if ($start_date < $today) {
                $start_date = $today;
            }
        } else {
            $start_date = new DateTime(); // Default to today
        }
        
        // Validate and set end date
        if (!empty($end_date_param) && strtotime($end_date_param)) {
            $end_date = new DateTime($end_date_param);
            // Ensure end date is after start date
            if ($end_date <= $start_date) {
                $end_date = (clone $start_date)->modify("+{$weeks_ahead} weeks");
            }
        } else {
            // Default: use weeks_ahead parameter or 1 year
            $end_date = (clone $start_date)->modify("+{$weeks_ahead} weeks");
        }
        
        // Safety limits to prevent performance issues
        // Use lawyer's max_booking_weeks as the limit
        $max_days = $lawyer_max_weeks * 7; // Convert weeks to days
        $date_diff = $start_date->diff($end_date)->days;
        if ($date_diff > $max_days) {
            $end_date = (clone $start_date)->modify("+{$max_days} days");
        }
        
        // Separate weekly, one-time, and blocked schedules
        $weekly_schedules = [];
        $one_time_dates = [];
        $blocked_dates = []; // Single blocked dates
        $blocked_ranges = []; // Date ranges that are blocked
        
        foreach ($schedules as $schedule) {
            if ($schedule['schedule_type'] === 'weekly') {
                $weekly_schedules[] = $schedule;
            } else if ($schedule['schedule_type'] === 'one_time') {
                $one_time_dates[$schedule['specific_date']] = $schedule;
            } else if ($schedule['schedule_type'] === 'blocked') {
                // Check if it's a single date block or range block
                if (!empty($schedule['specific_date'])) {
                    // Single date block
                    $blocked_dates[$schedule['specific_date']] = $schedule['blocked_reason'];
                } else if (!empty($schedule['start_date']) && !empty($schedule['end_date'])) {
                    // Date range block
                    $blocked_ranges[] = [
                        'start' => new DateTime($schedule['start_date']),
                        'end' => new DateTime($schedule['end_date']),
                        'reason' => $schedule['blocked_reason']
                    ];
                }
            }
        }
        
        // Helper function to check if a date is blocked
        $isDateBlocked = function($date_str) use ($blocked_dates, $blocked_ranges) {
            // Check single blocked dates
            if (isset($blocked_dates[$date_str])) {
                return true;
            }
            
            // Check blocked ranges
            $check_date = new DateTime($date_str);
            foreach ($blocked_ranges as $range) {
                if ($check_date >= $range['start'] && $check_date <= $range['end']) {
                    return true;
                }
            }
            
            return false;
        };
        
        // Generate dates from weekly schedules
        foreach ($weekly_schedules as $weekly) {
            $weekdays = explode(',', $weekly['weekdays']);
            $max_appointments = $weekly['max_appointments'];
            
            $current_date = clone $start_date;
            while ($current_date <= $end_date) {
                $weekday_name = $current_date->format('l'); // Get day name: Monday, Tuesday, etc.
                $date_str = $current_date->format('Y-m-d');
                
                // Check if this date is blocked
                if ($isDateBlocked($date_str)) {
                    $current_date->modify('+1 day');
                    continue;
                }
                
                // Check if this date has a one-time override
                if (isset($one_time_dates[$date_str])) {
                    // Skip - one-time schedule will handle this date
                    $current_date->modify('+1 day');
                    continue;
                }
                
                // Check if this weekday is in the lawyer's weekly schedule
                if (in_array($weekday_name, $weekdays)) {
                    // Check current appointment count for this date
                    $count_stmt = $pdo->prepare("
                        SELECT COUNT(*) as appointment_count 
                        FROM consultations 
                        WHERE lawyer_id = ? 
                        AND consultation_date = ? 
                        AND status IN ('pending', 'confirmed')
                    ");
                    $count_stmt->execute([$lawyer['id'], $date_str]);
                    $current_count = $count_stmt->fetch()['appointment_count'];
                    
                    // Only include date if not fully booked
                    if ($current_count < $max_appointments) {
                        $available_dates[$date_str] = [
                            'date' => $date_str,
                            'type' => 'weekly',
                            'start_time' => $weekly['start_time'],
                            'end_time' => $weekly['end_time'],
                            'max_appointments' => $max_appointments,
                            'booked' => $current_count
                        ];
                    }
                }
                $current_date->modify('+1 day');
            }
        }
        
        // Add one-time schedules
        foreach ($one_time_dates as $date_str => $one_time) {
            $schedule_date = new DateTime($date_str);
            
            // Check if this date is blocked
            if ($isDateBlocked($date_str)) {
                continue;
            }
            
            // Skip if this is a blocked date (max_appointments = 0)
            if ($one_time['max_appointments'] == 0) {
                // Remove this date from available_dates if it was added by weekly schedule
                unset($available_dates[$date_str]);
                continue;
            }
            
            // Only include if within date range
            if ($schedule_date >= $start_date && $schedule_date <= $end_date) {
                $max_appointments = $one_time['max_appointments'];
                
                // Check current appointment count
                $count_stmt = $pdo->prepare("
                    SELECT COUNT(*) as appointment_count 
                    FROM consultations 
                    WHERE lawyer_id = ? 
                    AND consultation_date = ? 
                    AND status IN ('pending', 'confirmed')
                ");
                $count_stmt->execute([$lawyer['id'], $date_str]);
                $current_count = $count_stmt->fetch()['appointment_count'];
                
                // Only include if not fully booked
                if ($current_count < $max_appointments) {
                    $available_dates[$date_str] = [
                        'date' => $date_str,
                        'type' => 'one_time',
                        'start_time' => $one_time['start_time'],
                        'end_time' => $one_time['end_time'],
                        'max_appointments' => $max_appointments,
                        'booked' => $current_count
                    ];
                }
            }
        }
        
        // Sort dates chronologically
        ksort($available_dates);
        
        // Extract just the date strings for backward compatibility
        $date_list = array_keys($available_dates);
        
        // Build comprehensive date status map including blocked and fully booked dates
        $date_status_map = [];
        
        // Add available dates
        foreach ($available_dates as $date_str => $details) {
            $date_status_map[$date_str] = [
                'status' => 'available',
                'type' => $details['type'],
                'start_time' => $details['start_time'],
                'end_time' => $details['end_time'],
                'max_appointments' => $details['max_appointments'],
                'booked' => $details['booked'],
                'slots_remaining' => $details['max_appointments'] - $details['booked']
            ];
        }
        
        // Add blocked dates (single dates and ranges)
        foreach ($blocked_dates as $date_str => $reason) {
            if (!isset($date_status_map[$date_str])) {
                $date_status_map[$date_str] = [
                    'status' => 'blocked',
                    'reason' => $reason
                ];
            }
        }
        
        // Add blocked date ranges
        foreach ($blocked_ranges as $range) {
            $current = clone $range['start'];
            while ($current <= $range['end']) {
                $date_str = $current->format('Y-m-d');
                if (!isset($date_status_map[$date_str])) {
                    $date_status_map[$date_str] = [
                        'status' => 'blocked',
                        'reason' => $range['reason']
                    ];
                }
                $current->modify('+1 day');
            }
        }
        
        // Add fully booked dates (dates that are in schedule but have no slots)
        // Check weekly schedules for fully booked dates
        foreach ($weekly_schedules as $weekly) {
            $weekdays = explode(',', $weekly['weekdays']);
            $max_appointments = $weekly['max_appointments'];
            
            $current_date = clone $start_date;
            while ($current_date <= $end_date) {
                $weekday_name = $current_date->format('l');
                $date_str = $current_date->format('Y-m-d');
                
                // Skip if already in map or blocked
                if (isset($date_status_map[$date_str]) || $isDateBlocked($date_str)) {
                    $current_date->modify('+1 day');
                    continue;
                }
                
                // Skip if one-time override exists
                if (isset($one_time_dates[$date_str])) {
                    $current_date->modify('+1 day');
                    continue;
                }
                
                // Check if this weekday is in schedule and fully booked
                if (in_array($weekday_name, $weekdays)) {
                    $count_stmt = $pdo->prepare("
                        SELECT COUNT(*) as appointment_count 
                        FROM consultations 
                        WHERE lawyer_id = ? 
                        AND consultation_date = ? 
                        AND status IN ('pending', 'confirmed')
                    ");
                    $count_stmt->execute([$lawyer['id'], $date_str]);
                    $current_count = $count_stmt->fetch()['appointment_count'];
                    
                    // If fully booked, add to map
                    if ($current_count >= $max_appointments) {
                        $date_status_map[$date_str] = [
                            'status' => 'fully_booked',
                            'type' => 'weekly',
                            'max_appointments' => $max_appointments,
                            'booked' => $current_count
                        ];
                    }
                }
                $current_date->modify('+1 day');
            }
        }
        
        // Check one-time dates for fully booked
        foreach ($one_time_dates as $date_str => $one_time) {
            $schedule_date = new DateTime($date_str);
            
            // Skip if already in map or blocked or outside range
            if (isset($date_status_map[$date_str]) || $isDateBlocked($date_str) || 
                $schedule_date < $start_date || $schedule_date > $end_date) {
                continue;
            }
            
            $max_appointments = $one_time['max_appointments'];
            
            $count_stmt = $pdo->prepare("
                SELECT COUNT(*) as appointment_count 
                FROM consultations 
                WHERE lawyer_id = ? 
                AND consultation_date = ? 
                AND status IN ('pending', 'confirmed')
            ");
            $count_stmt->execute([$lawyer['id'], $date_str]);
            $current_count = $count_stmt->fetch()['appointment_count'];
            
            // If fully booked, add to map
            if ($current_count >= $max_appointments) {
                $date_status_map[$date_str] = [
                    'status' => 'fully_booked',
                    'type' => 'one_time',
                    'max_appointments' => $max_appointments,
                    'booked' => $current_count
                ];
            }
        }
        
        echo json_encode([
            'success' => true,
            'lawyer' => $lawyer_name,
            'available_dates' => $date_list,
            'detailed_availability' => array_values($available_dates),
            'date_status_map' => $date_status_map, // NEW: Complete status map
            'date_range' => [
                'start_date' => $start_date->format('Y-m-d'),
                'end_date' => $end_date->format('Y-m-d'),
                'total_days' => $start_date->diff($end_date)->days,
                'requested_weeks' => $weeks_ahead
            ],
            'lawyer_preferences' => [
                'default_booking_weeks' => $lawyer_default_weeks,
                'max_booking_weeks' => $lawyer_max_weeks,
                'booking_window_enabled' => $booking_enabled
            ],
            'schedule_summary' => [
                'weekly_schedules' => count($weekly_schedules),
                'one_time_schedules' => count($one_time_dates),
                'blocked_dates' => count($blocked_dates),
                'blocked_ranges' => count($blocked_ranges),
                'total_available_dates' => count($available_dates),
                'fully_booked_dates' => count(array_filter($date_status_map, function($d) { return $d['status'] === 'fully_booked'; })),
                'blocked_dates_total' => count(array_filter($date_status_map, function($d) { return $d['status'] === 'blocked'; }))
            ]
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'No availability schedules set for this lawyer'
        ]);
    }
    
} catch (Exception $e) {
    error_log("Get lawyer availability error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'An error occurred while fetching availability'
    ]);
}
?>
