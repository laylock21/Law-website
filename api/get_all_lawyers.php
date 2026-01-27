<?php
/**
 * API Endpoint: Get All Lawyers
 * Returns all active lawyers with their specializations for homepage display
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

// Cache headers for performance
header('Cache-Control: public, max-age=300'); // Cache for 5 minutes
header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 300) . ' GMT');

require_once '../config/database.php';
require_once '../config/upload_config.php';

/**
 * Format description from database
 */
function formatDescription($description) {
    return !empty(trim($description)) ? trim($description) : 'No description available';
}

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    $pdo = getDBConnection();
    
    if (!$pdo) {
        throw new Exception('Database connection failed');
    }
    
    // Get all active lawyers with their specializations, descriptions, and profile pictures
    // Only include lawyers who have at least one 'weekly' or 'one_time' schedule
    $lawyers_stmt = $pdo->prepare("
        SELECT 
            u.user_id,
            lp.lp_fullname,
            lp.lawyer_prefix,
            u.email,
            u.phone,
            lp.lp_description,
            lp.profile,
            GROUP_CONCAT(DISTINCT pa.area_name SEPARATOR ', ') as specializations
        FROM users u
        INNER JOIN lawyer_profile lp ON u.user_id = lp.lawyer_id
        LEFT JOIN lawyer_specializations ls ON u.user_id = ls.lawyer_id
        LEFT JOIN practice_areas pa ON ls.pa_id = pa.pa_id
        INNER JOIN lawyer_availability la ON u.user_id = la.lawyer_id
        WHERE u.role = 'lawyer' 
        AND u.is_active = 1
        AND la.la_is_active = 1
        AND la.schedule_type IN ('weekly', 'one_time')
        GROUP BY u.user_id, lp.lp_fullname, lp.lawyer_prefix, u.email, u.phone, lp.lp_description, lp.profile
        ORDER BY lp.lp_fullname
    ");
    $lawyers_stmt->execute();
    $lawyers = $lawyers_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format lawyers data for frontend
    $formatted_lawyers = [];
    foreach ($lawyers as $lawyer) {
        // Build full name with prefix if available
        $prefix = !empty($lawyer['lawyer_prefix']) ? $lawyer['lawyer_prefix'] . ' ' : '';
        $fullName = $prefix . $lawyer['lp_fullname'];
        
        // Convert BLOB to base64 for profile picture
        $profile_picture_url = null;
        if (!empty($lawyer['profile'])) {
            $profile_picture_url = 'data:image/jpeg;base64,' . base64_encode($lawyer['profile']);
        }
        
        $formatted_lawyers[] = [
            'id' => $lawyer['user_id'],
            'name' => $fullName,
            'prefix' => $lawyer['lawyer_prefix'],
            'full_name' => $lawyer['lp_fullname'],
            'email' => $lawyer['email'],
            'phone' => $lawyer['phone'],
            'specializations' => $lawyer['specializations'] ? explode(', ', $lawyer['specializations']) : [],
            'primary_specialization' => $lawyer['specializations'] ? explode(', ', $lawyer['specializations'])[0] : 'General Practice',
            'description' => formatDescription($lawyer['lp_description']),
            'profile_picture_url' => $profile_picture_url
        ];
    }
    
    echo json_encode([
        'success' => true,
        'lawyers' => $formatted_lawyers,
        'total' => count($formatted_lawyers)
    ]);
    
} catch (Exception $e) {
    error_log("Get all lawyers error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'An error occurred while fetching lawyers'
    ]);
}
?>