<?php
/**
 * Test Script: Practice Areas Database Check
 * This script verifies the practice_areas table and displays current data
 */

require_once '../config/database.php';

echo "<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Practice Areas Test</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 1200px; margin: 20px auto; padding: 0 20px; }
        h1 { color: #333; }
        .success { color: #28a745; padding: 10px; background: #d4edda; border: 1px solid #c3e6cb; border-radius: 4px; margin: 10px 0; }
        .error { color: #dc3545; padding: 10px; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 4px; margin: 10px 0; }
        .info { color: #0c5460; padding: 10px; background: #d1ecf1; border: 1px solid #bee5eb; border-radius: 4px; margin: 10px 0; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background-color: #f8f9fa; font-weight: bold; }
        tr:hover { background-color: #f5f5f5; }
        .badge { padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: bold; }
        .badge-success { background: #28a745; color: white; }
        .badge-danger { background: #dc3545; color: white; }
        .btn { display: inline-block; padding: 10px 20px; margin: 10px 5px 10px 0; background: #007bff; color: white; text-decoration: none; border-radius: 4px; }
        .btn:hover { background: #0056b3; }
    </style>
</head>
<body>
    <h1>Practice Areas Database Test</h1>";

try {
    // Test database connection
    echo "<h2>1. Database Connection Test</h2>";
    $pdo = getDBConnection();
    
    if (!$pdo) {
        throw new Exception('Database connection failed');
    }
    
    echo "<div class='success'>✓ Database connection successful!</div>";
    
    // Check if practice_areas table exists
    echo "<h2>2. Table Structure Check</h2>";
    $tableCheck = $pdo->query("SHOW TABLES LIKE 'practice_areas'");
    
    if ($tableCheck->rowCount() === 0) {
        echo "<div class='error'>✗ Table 'practice_areas' does not exist!</div>";
        echo "<div class='info'>Please create the table using the following SQL:</div>";
        echo "<pre style='background: #f5f5f5; padding: 15px; border-radius: 4px; overflow-x: auto;'>
CREATE TABLE practice_areas (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    area_name VARCHAR(100) NOT NULL,
    description TEXT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
</pre>";
    } else {
        echo "<div class='success'>✓ Table 'practice_areas' exists!</div>";
        
        // Show table structure
        $columns = $pdo->query("DESCRIBE practice_areas")->fetchAll(PDO::FETCH_ASSOC);
        echo "<h3>Table Structure:</h3>";
        echo "<table>";
        echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
        foreach ($columns as $col) {
            echo "<tr>";
            echo "<td>{$col['Field']}</td>";
            echo "<td>{$col['Type']}</td>";
            echo "<td>{$col['Null']}</td>";
            echo "<td>{$col['Key']}</td>";
            echo "<td>" . ($col['Default'] ?? 'NULL') . "</td>";
            echo "<td>{$col['Extra']}</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        // Count records
        echo "<h2>3. Data Check</h2>";
        $countStmt = $pdo->query("SELECT COUNT(*) as total FROM practice_areas");
        $totalCount = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        $activeStmt = $pdo->query("SELECT COUNT(*) as active FROM practice_areas WHERE is_active = 1");
        $activeCount = $activeStmt->fetch(PDO::FETCH_ASSOC)['active'];
        
        // Count practice areas with active lawyers
        $withLawyersStmt = $pdo->query("
            SELECT COUNT(DISTINCT pa.id) as with_lawyers
            FROM practice_areas pa
            INNER JOIN lawyer_specializations ls ON pa.id = ls.practice_area_id
            INNER JOIN users u ON ls.user_id = u.id
            WHERE pa.is_active = 1
            AND u.role = 'lawyer'
            AND u.is_active = 1
        ");
        $withLawyersCount = $withLawyersStmt->fetch(PDO::FETCH_ASSOC)['with_lawyers'];
        
        echo "<div class='info'>";
        echo "Total practice areas: <strong>{$totalCount}</strong><br>";
        echo "Active practice areas: <strong>{$activeCount}</strong><br>";
        echo "Inactive practice areas: <strong>" . ($totalCount - $activeCount) . "</strong><br>";
        echo "<strong style='color: #007bff;'>Practice areas with active lawyers (displayed on website): {$withLawyersCount}</strong>";
        echo "</div>";
        
        if ($withLawyersCount < $activeCount) {
            $diff = $activeCount - $withLawyersCount;
            echo "<div class='error'>⚠️ Warning: {$diff} active practice area(s) have no active lawyers assigned and will NOT be displayed on the website!</div>";
        }
        
        if ($totalCount === 0) {
            echo "<div class='error'>✗ No practice areas found in the database!</div>";
            echo "<div class='info'>Run the seed file to populate sample data: <code>migrations/seed_practice_areas.sql</code></div>";
        } else {
            // Display all practice areas
            echo "<h3>Current Practice Areas:</h3>";
            $areas = $pdo->query("
                SELECT 
                    pa.*,
                    COUNT(DISTINCT ls.id) as total_lawyers,
                    COUNT(DISTINCT CASE WHEN u.is_active = 1 AND u.role = 'lawyer' THEN u.id END) as active_lawyers
                FROM practice_areas pa
                LEFT JOIN lawyer_specializations ls ON pa.id = ls.practice_area_id
                LEFT JOIN users u ON ls.user_id = u.id
                GROUP BY pa.id
                ORDER BY pa.area_name
            ")->fetchAll(PDO::FETCH_ASSOC);
            
            echo "<table>";
            echo "<tr><th>ID</th><th>Area Name</th><th>Description</th><th>Status</th><th>Lawyers</th><th>Displayed?</th></tr>";
            foreach ($areas as $area) {
                $statusBadge = $area['is_active'] == 1 
                    ? "<span class='badge badge-success'>Active</span>" 
                    : "<span class='badge badge-danger'>Inactive</span>";
                
                $description = $area['description'] ?? '<em>No description</em>';
                if (strlen($description) > 100) {
                    $description = substr($description, 0, 100) . '...';
                }
                
                $lawyerInfo = "{$area['active_lawyers']} active / {$area['total_lawyers']} total";
                
                // Determine if this practice area will be displayed on website
                $willDisplay = ($area['is_active'] == 1 && $area['active_lawyers'] > 0);
                $displayBadge = $willDisplay
                    ? "<span class='badge badge-success'>✓ Yes</span>"
                    : "<span class='badge badge-danger'>✗ No</span>";
                
                echo "<tr>";
                echo "<td>{$area['id']}</td>";
                echo "<td><strong>{$area['area_name']}</strong></td>";
                echo "<td>{$description}</td>";
                echo "<td>{$statusBadge}</td>";
                echo "<td>{$lawyerInfo}</td>";
                echo "<td>{$displayBadge}</td>";
                echo "</tr>";
            }
            echo "</table>";
            
            echo "<div class='info' style='margin-top: 20px;'>";
            echo "<strong>Note:</strong> A practice area is displayed on the website only if:<br>";
            echo "1. The practice area is active (is_active = 1)<br>";
            echo "2. At least one active lawyer is assigned to it<br>";
            echo "3. The assigned lawyer has role = 'lawyer'";
            echo "</div>";
        }
    }
    
    // Test API endpoint
    echo "<h2>4. API Endpoint Test</h2>";
    $apiUrl = 'http://' . $_SERVER['HTTP_HOST'] . dirname(dirname($_SERVER['PHP_SELF'])) . '/api/get_all_practice_areas.php';
    echo "<div class='info'>API Endpoint: <a href='{$apiUrl}' target='_blank'>{$apiUrl}</a></div>";
    echo "<a href='{$apiUrl}' target='_blank' class='btn'>Test API Endpoint</a>";
    
    echo "<h2>5. Quick Actions</h2>";
    echo "<a href='../index.html#services' class='btn'>View Homepage</a>";
    echo "<a href='../migrations/seed_practice_areas.sql' class='btn' download>Download Seed File</a>";
    
} catch (Exception $e) {
    echo "<div class='error'>✗ Error: " . htmlspecialchars($e->getMessage()) . "</div>";
    echo "<div class='info'>Please check your database configuration in <code>config/database.php</code></div>";
}

echo "</body></html>";
?>
