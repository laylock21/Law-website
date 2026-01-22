<?php
/**
 * Run Database Migration for DOCX Attachment Feature
 * Adds consultation_id column to notification_queue table
 */

require_once 'config/database.php';

echo "<h2>🔧 Running Database Migration</h2>";

try {
    $pdo = getDBConnection();
    
    if (!$pdo) {
        throw new Exception("Database connection failed");
    }
    
    echo "<h3>Adding consultation_id column to notification_queue table...</h3>";
    
    // Read and execute migration
    $migration_sql = file_get_contents(__DIR__ . '/migrations/006_add_consultation_id_to_notifications.sql');
    
    // Split by semicolon and execute each statement
    $statements = array_filter(array_map('trim', explode(';', $migration_sql)));
    
    foreach ($statements as $statement) {
        if (empty($statement) || strpos($statement, '--') === 0) {
            continue;
        }
        
        try {
            $pdo->exec($statement);
            echo "✅ Executed: " . substr($statement, 0, 50) . "...<br>";
        } catch (Exception $e) {
            echo "⚠️ Statement failed (might be expected): " . $e->getMessage() . "<br>";
        }
    }
    
    // Verify the column was added
    echo "<h3>Verifying Migration:</h3>";
    
    $check_stmt = $pdo->prepare("
        SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE 
        FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_SCHEMA = DATABASE() 
        AND TABLE_NAME = 'notification_queue' 
        AND COLUMN_NAME = 'consultation_id'
    ");
    $check_stmt->execute();
    $column_info = $check_stmt->fetch();
    
    if ($column_info) {
        echo "✅ <strong>SUCCESS!</strong> consultation_id column exists<br>";
        echo "📋 Type: {$column_info['DATA_TYPE']}<br>";
        echo "📋 Nullable: {$column_info['IS_NULLABLE']}<br>";
    } else {
        echo "❌ <strong>FAILED!</strong> consultation_id column not found<br>";
    }
    
    // Show current table structure
    echo "<h3>Current notification_queue Table Structure:</h3>";
    $desc_stmt = $pdo->prepare("DESCRIBE notification_queue");
    $desc_stmt->execute();
    $columns = $desc_stmt->fetchAll();
    
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    foreach ($columns as $col) {
        echo "<tr>";
        echo "<td>{$col['Field']}</td>";
        echo "<td>{$col['Type']}</td>";
        echo "<td>{$col['Null']}</td>";
        echo "<td>{$col['Key']}</td>";
        echo "<td>{$col['Default']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<br><h3>✅ Migration Complete!</h3>";
    echo "<p>The notification_queue table now supports linking to consultations for DOCX generation.</p>";
    
} catch (Exception $e) {
    echo "<h3>❌ Migration Failed</h3>";
    echo "<p><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>

<style>
body { font-family: Arial, sans-serif; margin: 40px; }
h2 { color: #1a2332; }
h3 { color: #c5a253; }
table { margin: 10px 0; }
th, td { padding: 8px; text-align: left; border: 1px solid #ddd; }
th { background-color: #f8f9fa; }
</style>
