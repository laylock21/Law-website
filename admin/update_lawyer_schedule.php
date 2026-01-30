<?php
/**
 * Update Lawyer Weekly Schedule
 * AJAX endpoint for updating lawyer availability schedule
 */

session_start();

// Authentication check
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true || $_SESSION['user_role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once '../config/database.php';

header('Content-Type: application/json');

try {
    // Get JSON input
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (!$data || !isset($data['lawyer_id']) || !isset($data['schedule'])) {
        throw new Exception('Invalid request data');
    }
    
    $lawyer_id = (int)$data['lawyer_id'];
    $schedule = $data['schedule'];
    
    if ($lawyer_id <= 0) {
        throw new Exception('Invalid lawyer ID');
    }
    
    $pdo = getDBConnection();
    $pdo->beginTransaction();
    
    foreach ($schedule as $day_schedule) {
        $schedule_id = $day_schedule['id'] ?? '';
        $day = $day_schedule['day'];
        $start_time = $day_schedule['start_time'];
        $end_time = $day_schedule['end_time'];
        $max_appointments = (int)$day_schedule['max_appointments'];
        $slot_duration = (int)$day_schedule['time_slot_duration'];
        $is_active = (int)$day_schedule['is_active'];
        
        // Validate times
        if (strtotime($start_time) >= strtotime($end_time)) {
            throw new Exception("Invalid time range for $day: start time must be before end time");
        }
        
        if ($schedule_id) {
            // Update existing schedule
            $update_stmt = $pdo->prepare("
                UPDATE lawyer_availability 
                SET start_time = ?, end_time = ?, max_appointments = ?, 
                    time_slot_duration = ?, la_is_active = ?
                WHERE la_id = ? AND lawyer_id = ? AND schedule_type = 'weekly'
            ");
            $update_stmt->execute([
                $start_time, $end_time, $max_appointments, 
                $slot_duration, $is_active, $schedule_id, $lawyer_id
            ]);
        } else {
            // Insert new schedule
            $insert_stmt = $pdo->prepare("
                INSERT INTO lawyer_availability 
                (lawyer_id, schedule_type, weekday, start_time, end_time, max_appointments, time_slot_duration, la_is_active)
                VALUES (?, 'weekly', ?, ?, ?, ?, ?, ?)
            ");
            $insert_stmt->execute([
                $lawyer_id, $day, $start_time, $end_time, 
                $max_appointments, $slot_duration, $is_active
            ]);
        }
    }
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Schedule updated successfully'
    ]);
    
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
