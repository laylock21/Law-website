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
        $lawyer_stmt = $pdo->prepare("SELECT id, first_name, last_name FROM users WHERE role = 'lawyer' AND id = ? AND is_active = 1");
        $lawyer_stmt->execute([$lawyer_id_param]);
        $lawyer = $lawyer_stmt->fetch();
        if (!$lawyer) {
            throw new Exception('Lawyer not found');
        }
        $lawyer_id = (int)$lawyer['id'];
    } else {
        // Get lawyer ID from name
        // Note: Frontend already strips "Atty." prefix before sending, but double-check for safety
        $lawyer_name_clean = preg_replace('/^Atty\.\s*/i', '', $lawyer_name);
        $lawyer_parts = explode(' ', $lawyer_name_clean, 2); // Split into max 2 parts
        $first_name = $lawyer_parts[0] ?? '';
        $last_name = $lawyer_parts[1] ?? '';

        // Try exact match first
        $lawyer_stmt = $pdo->prepare("
            SELECT id, first_name, last_name 
            FROM users 
            WHERE role = 'lawyer' 
            AND first_name = ? 
            AND last_name = ?
            AND is_active = 1
        ");
        $lawyer_stmt->execute([$first_name, $last_name]);
        $lawyer = $lawyer_stmt->fetch();

        // If not found, try matching by concatenated full name
        if (!$lawyer) {
            $lawyer_stmt = $pdo->prepare("
                SELECT id, first_name, last_name 
                FROM users 
                WHERE role = 'lawyer' 
                AND CONCAT(first_name, ' ', last_name) = ?
                AND is_active = 1
            ");
            $lawyer_stmt->execute([$lawyer_name_clean]);
            $lawyer = $lawyer_stmt->fetch();
        }

        if (!$lawyer) {
            throw new Exception('Lawyer not found');
        }

        $lawyer_id = (int)$lawyer['id'];
    }

    $day_of_week = date('w', strtotime($date)); // 0 = Sunday, 6 = Saturday
    
    // Get lawyer's availability for this date
    $availability_stmt = $pdo->prepare("
        SELECT 
            start_time,
            end_time,
            max_appointments,
            time_slot_duration,
            schedule_type
        FROM lawyer_availability
        WHERE user_id = ?
        AND is_active = 1
        AND (
            (schedule_type = 'weekly' AND FIND_IN_SET(?, weekdays) > 0)
            OR (schedule_type = 'one_time' AND specific_date = ?)
        )
        ORDER BY schedule_type DESC
        LIMIT 1
    ");
    
    $availability_stmt->execute([$lawyer_id, $day_of_week, $date]);
    $availability = $availability_stmt->fetch();
    
    if (!$availability) {
        echo json_encode([
            'success' => true,
            'time_slots' => [],
            'message' => 'No availability for this date'
        ]);
        exit;
    }
    
    // Check if date is blocked (max_appointments = 0)
    if ($availability['max_appointments'] == 0) {
        echo json_encode([
            'success' => true,
            'time_slots' => [],
            'message' => 'Date is blocked'
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
        AND status IN ('pending', 'confirmed')
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
            AND status IN ('pending', 'confirmed')
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
        'lawyer' => $lawyer['first_name'] . ' ' . $lawyer['last_name'],
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
