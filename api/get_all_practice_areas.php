<?php
/**
 * API Endpoint: Get All Practice Areas
 * Returns all practice areas available in the system
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

// Cache headers for performance
header('Cache-Control: public, max-age=600'); // Cache for 10 minutes
header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 600) . ' GMT');

require_once '../config/database.php';

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
    
    // Get all practice areas that have at least one active lawyer
    $areas_stmt = $pdo->prepare("
        SELECT DISTINCT pa.id, pa.area_name, pa.description
        FROM practice_areas pa
        INNER JOIN lawyer_specializations ls ON pa.id = ls.practice_area_id
        INNER JOIN users u ON ls.user_id = u.id
        WHERE u.role = 'lawyer' 
        AND u.is_active = 1
        ORDER BY pa.area_name
    ");
    $areas_stmt->execute();
    $areas = $areas_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'practice_areas' => $areas,
        'total' => count($areas)
    ]);
    
} catch (Exception $e) {
    error_log("Get all practice areas error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'An error occurred while fetching practice areas'
    ]);
}
?>
