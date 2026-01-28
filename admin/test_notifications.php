<?php
/**
 * Test script to check notification_queue data
 */

require_once '../config/database.php';

try {
    $pdo = getDBConnection();
    
    echo "<h2>Testing Notification Queue</h2>";
    
    // Test 1: Check if table exists and has data
    echo "<h3>1. Total records in notification_queue:</h3>";
    $count = $pdo->query("SELECT COUNT(*) FROM notification_queue")->fetchColumn();
    echo "Total: $count<br><br>";
    
    // Test 2: Check structure
    echo "<h3>2. Table structure:</h3>";
    $columns = $pdo->query("DESCRIBE notification_queue")->fetchAll();
    echo "<pre>";
    print_r($columns);
    echo "</pre>";
    
    // Test 3: Get all records
    echo "<h3>3. All records:</h3>";
    $all = $pdo->query("SELECT * FROM notification_queue LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
    echo "<pre>";
    print_r($all);
    echo "</pre>";
    
    // Test 4: Get stats by status
    echo "<h3>4. Stats by status:</h3>";
    $stats = $pdo->query("
        SELECT nq_status, COUNT(*) as count 
        FROM notification_queue 
        GROUP BY nq_status
    ")->fetchAll(PDO::FETCH_ASSOC);
    echo "<pre>";
    print_r($stats);
    echo "</pre>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}
?>
