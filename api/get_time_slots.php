<?php
/**
 * API Endpoint: Get Available Time Slots
 * Returns available time slots for a specific lawyer on a specific date
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/database.php';

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get and validate parameters
$lawyer_name = $_GET['lawyer'] ?? '';
$date = $_GET['date'] ?? '';
// Optional, prefer precise ID if provided
$lawyer_id_param = $_GET['lawyer_id'] ?? '';

// Validate and sanitize lawyer name to prevent injection
$lawyer_name = trim($lawyer_name);
$lawyer_name = preg_replace('/[^a-zA-Z\s\'-]/', '', $lawyer_name); // Allow only letters, spaces, apostrophes, hyphens
$lawyer_name = substr($lawyer_name, 0, 100); // Limit length

// Validate lawyer_id if provided
$lawyer_id_param = trim($lawyer_id_param);
if ($lawyer_id_param !== '' && !ctype_digit($lawyer_id_param)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid lawyer_id']);
    exit;
}

// Validate date format
if (!empty($date) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid date format. Use YYYY-MM-DD']);
    exit;
}

if (empty($lawyer_name) || empty($date)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Lawyer name and date are required']);
    exit;
}

// Additional validation - check if date is valid
if (!strtotime($date)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid date provided']);
    exit;
}

try {
    $pdo = getDBConnection();
    
    if (!$pdo) {
        throw new Exception('Database connection failed');
    }
    
    // Resolve lawyer by ID if provided; fallback to name matching
    if ($lawyer_id_param !== '') {
        // Use ID directly
        $lawyer_stmt = $pdo->prepare("
            SELECT u.user_id as id, lp.lp_fullname, lp.lawyer_prefix 
            FROM users u
            INNER JOIN lawyer_profile lp ON u.user_id = lp.lawyer_id
            WHERE u.role = 'lawyer' AND u.user_id = ? AND u.is_active = 1
        ");
        $lawyer_stmt->execute([$lawyer_id_param]);
        $lawyer = $lawyer_stmt->fetch();
        if (!$lawyer) {
            throw new Exception('Lawyer not found');
        }
        $lawyer_id = (int)$lawyer['id'];
    } else {
        // Get lawyer ID from name
        $lawyer_stmt = $pdo->prepare("
            SELECT u.user_id as id, lp.lp_fullname, lp.lawyer_prefix
            FROM users u
            INNER JOIN lawyer_profile lp ON u.user_id = lp.lawyer_id
            WHERE lp.lp_fullname = ?
            AND u.role = 'lawyer' 
            AND u.is_active = 1
        ");
        $lawyer_stmt->execute([$lawyer_name]);
        $lawyer = $lawyer_stmt->fetch();

        if (!$lawyer) {
            throw new Exception('Lawyer not found');
        }

        $lawyer_id = (int)$lawyer['id'];
    }

    // Get the day name (Monday, Tuesday, etc.) for the requested date
    $day_name = date('l', strtotime($date)); // Returns full day name like "Monday"
    
    // Get lawyer's availability for this date
    // Priority: one_time > blocked > weekly
    $availability_stmt = $pdo->prepare("
        SELECT 
            start_time,
            end_time,
            max_appointments,
            time_slot_duration,
            schedule_type,
            blocked_reason
        FROM lawyer_availability
        WHERE lawyer_id = ?
        AND la_is_active = 1
        AND (
            (schedule_type = 'one_time' AND specific_date = ?)
            OR (schedule_type = 'blocked' AND specific_date = ?)
            OR (schedule_type = 'weekly')
        )
        ORDER BY 
            CASE schedule_type
                WHEN 'one_time' THEN 1
                WHEN 'blocked' THEN 2
                WHEN 'weekly' THEN 3
            END
        LIMIT 1
    ");
    
    $availability_stmt->execute([$lawyer_id, $date, $date]);
    $availability = $availability_stmt->fetch();
    
    if (!$availability) {
        echo json_encode([
            'success' => true,
            'time_slots' => [],
            'message' => 'No availability for this date'
        ]);
        exit;
    }
    
    // Check if date is blocked
    if ($availability['schedule_type'] === 'blocked') {
        echo json_encode([
            'success' => true,
            'time_slots' => [],
            'message' => 'Date is blocked: ' . ($availability['blocked_reason'] ?? 'Unavailable'),
            'blocked' => true,
            'blocked_reason' => $availability['blocked_reason'] ?? 'Unavailable'
        ]);
        exit;
    }
    
    // Check if date is blocked (max_appointments = 0) - legacy check
    if ($availability['max_appointments'] == 0) {
        echo json_encode([
            'success' => true,
            'time_slots' => [],
            'message' => 'Date is blocked',
            'blocked' => true
        ]);
        exit;
    }
    
    // Generate time slots
    $start_time = new DateTime($availability['start_time']);
    $end_time = new DateTime($availability['end_time']);
    $slot_duration = $availability['time_slot_duration'] ?? 60; // Default 60 minutes
    $max_appointments = $availability['max_appointments']; // Total slots for the ENTIRE DAY
    
    // Get total bookings for this lawyer on this date (across all time slots)
    $total_booked_stmt = $pdo->prepare("
        SELECT COUNT(*) as count
        FROM consultations
        WHERE lawyer_id = ?
        AND consultation_date = ?
        AND c_status IN ('pending', 'confirmed')
    ");
    $total_booked_stmt->execute([$lawyer_id, $date]);
    $total_booked = $total_booked_stmt->fetch()['count'];
    
    // Calculate remaining slots for the entire day
    $total_slots_remaining = max(0, $max_appointments - $total_booked);
    
    $time_slots = [];
    $current_time = clone $start_time;
    
    while ($current_time < $end_time) {
        $slot_end = clone $current_time;
        $slot_end->modify("+{$slot_duration} minutes");
        
        // Don't add slot if it goes beyond end time
        if ($slot_end > $end_time) {
            break;
        }
        
        $slot_time = $current_time->format('H:i:s');
        
        // Check if this specific time slot is already booked
        $slot_booked_stmt = $pdo->prepare("
            SELECT COUNT(*) as count
            FROM consultations
            WHERE lawyer_id = ?
            AND consultation_date = ?
            AND consultation_time = ?
            AND c_status IN ('pending', 'confirmed')
        ");
        $slot_booked_stmt->execute([$lawyer_id, $date, $slot_time]);
        $slot_booked = $slot_booked_stmt->fetch()['count'];
        
        // Slot is available if:
        // 1. This specific time slot is not booked (only 1 appointment per time slot)
        // 2. AND there are still slots remaining for the day
        $is_available = ($slot_booked == 0) && ($total_slots_remaining > 0);
        
        $time_slots[] = [
            'time' => $current_time->format('H:i'),
            'time_24h' => $slot_time,
            'display' => $current_time->format('g:i A') . ' - ' . $slot_end->format('g:i A'),
            'available' => $is_available,
            'booked_count' => (int)$total_booked,
            'max_appointments' => (int)$max_appointments,
            'slots_remaining' => (int)$total_slots_remaining // Keep consistent for all slots
        ];
        
        $current_time->modify("+{$slot_duration} minutes");
    }
    
    echo json_encode([
        'success' => true,
        'time_slots' => $time_slots,
        'date' => $date,
        'lawyer' => $lawyer['lp_fullname'],
        'slot_duration' => $slot_duration,
        'max_appointments' => (int)$max_appointments,
        'total_booked' => (int)$total_booked,
        'slots_remaining' => (int)$total_slots_remaining
    ]);
    
} catch (PDOException $e) {
    error_log("Database error in get_time_slots.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'A database error occurred while fetching time slots'
    ]);
} catch (Exception $e) {
    error_log("Get time slots error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
