<?php
/**
 * API Endpoint: Get Lawyers by Specialization
 * Returns lawyers who specialize in a specific practice area
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

// Cache headers for performance
header('Cache-Control: public, max-age=300'); // Cache for 5 minutes
header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 300) . ' GMT');

require_once '../config/database.php';

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get practice area from query parameter
$practice_area = $_GET['specialization'] ?? '';

if (empty($practice_area)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Practice area is required']);
    exit;
}

try {
    $pdo = getDBConnection();
    
    if (!$pdo) {
        throw new Exception('Database connection failed');
    }
    
    // Get lawyers by specialization
    $lawyers_stmt = $pdo->prepare("
        SELECT u.user_id as id, lp.lp_fullname, lp.lawyer_prefix, u.email, u.phone
        FROM users u
        INNER JOIN lawyer_profile lp ON u.user_id = lp.lawyer_id
        JOIN lawyer_specializations ls ON u.user_id = ls.lawyer_id
        JOIN practice_areas pa ON ls.pa_id = pa.pa_id
        WHERE pa.area_name = ? 
        AND u.role = 'lawyer' 
        AND u.is_active = 1
        ORDER BY lp.lp_fullname
    ");
    $lawyers_stmt->execute([$practice_area]);
    $lawyers = $lawyers_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format lawyer names for frontend
    $formatted_lawyers = [];
    foreach ($lawyers as $lawyer) {
        // Build full name with prefix if available
        $prefix = !empty($lawyer['lawyer_prefix']) ? $lawyer['lawyer_prefix'] . ' ' : '';
        $fullName = $prefix . $lawyer['lp_fullname'];
        
        $formatted_lawyers[] = [
            'id' => $lawyer['id'],
            'name' => $fullName,
            'prefix' => $lawyer['lawyer_prefix'],
            'full_name' => $lawyer['lp_fullname'],
            'email' => $lawyer['email'],
            'phone' => $lawyer['phone']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'practice_area' => $practice_area,
        'lawyers' => $formatted_lawyers
    ]);
    
} catch (Exception $e) {
    error_log("Get lawyers by specialization error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'An error occurred while fetching lawyers'
    ]);
}
?>
