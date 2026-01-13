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
    $lawyers_stmt = $pdo->prepare("
        SELECT 
            u.id,
            u.first_name,
            u.last_name,
            u.email,
            u.phone,
            u.description,
            u.profile_picture,
            GROUP_CONCAT(pa.area_name SEPARATOR ', ') as specializations
        FROM users u
        LEFT JOIN lawyer_specializations ls ON u.id = ls.user_id
        LEFT JOIN practice_areas pa ON ls.practice_area_id = pa.id
        WHERE u.role = 'lawyer' 
        AND u.is_active = 1
        GROUP BY u.id, u.first_name, u.last_name, u.email, u.phone, u.description, u.profile_picture
        ORDER BY u.first_name, u.last_name
    ");
    $lawyers_stmt->execute();
    $lawyers = $lawyers_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format lawyers data for frontend
    $formatted_lawyers = [];
    foreach ($lawyers as $lawyer) {
        $formatted_lawyers[] = [
            'id' => $lawyer['id'],
            'name' => 'Atty. ' . $lawyer['first_name'] . ' ' . $lawyer['last_name'],
            'first_name' => $lawyer['first_name'],
            'last_name' => $lawyer['last_name'],
            'email' => $lawyer['email'],
            'phone' => $lawyer['phone'],
            'specializations' => $lawyer['specializations'] ? explode(', ', $lawyer['specializations']) : [],
            'primary_specialization' => $lawyer['specializations'] ? explode(', ', $lawyer['specializations'])[0] : 'General Practice',
            'description' => formatDescription($lawyer['description']),
            'profile_picture' => $lawyer['profile_picture'],
            'profile_picture_url' => getProfilePictureUrl($lawyer['profile_picture'])
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