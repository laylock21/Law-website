<?php
/**
 * Dashboard Test Script
 * Run this to verify database connectivity and column names
 * URL: http://localhost/Law-website/test_dashboard.php
 */

require_once 'config/database.php';

echo "<h1>Lawyer Dashboard Database Test</h1>";
echo "<style>
    body { font-family: Arial, sans-serif; padding: 20px; }
    .success { color: green; }
    .error { color: red; }
    .info { color: blue; }
    table { border-collapse: collapse; margin: 10px 0; }
    td, th { border: 1px solid #ddd; padding: 8px; text-align: left; }
    th { background: #f2f2f2; }
</style>";

try {
    $pdo = getDBConnection();
    echo "<p class='success'>✓ Database connection successful!</p>";
    
    // Test 1: Check users table
    echo "<h2>Test 1: Users Table</h2>";
    $stmt = $pdo->query("SELECT user_id, username, email, role FROM users LIMIT 5");
    $users = $stmt->fetchAll();
    if ($users) {
        echo "<p class='success'>✓ Users table accessible</p>";
        echo "<table><tr><th>user_id</th><th>username</th><th>email</th><th>role</th></tr>";
        foreach ($users as $user) {
            echo "<tr><td>{$user['user_id']}</td><td>{$user['username']}</td><td>{$user['email']}</td><td>{$user['role']}</td></tr>";
        }
        echo "</table>";
    }
    
    // Test 2: Check lawyer_profile table
    echo "<h2>Test 2: Lawyer Profile Table</h2>";
    $stmt = $pdo->query("SELECT lawyer_id, lawyer_prefix, lp_fullname FROM lawyer_profile LIMIT 5");
    $profiles = $stmt->fetchAll();
    if ($profiles) {
        echo "<p class='success'>✓ Lawyer profile table accessible</p>";
        echo "<table><tr><th>lawyer_id</th><th>prefix</th><th>fullname</th></tr>";
        foreach ($profiles as $profile) {
            echo "<tr><td>{$profile['lawyer_id']}</td><td>{$profile['lawyer_prefix']}</td><td>{$profile['lp_fullname']}</td></tr>";
        }
        echo "</table>";
    } else {
        echo "<p class='info'>ℹ No lawyer profiles found (run setup_test_data.sql)</p>";
    }
    
    // Test 3: Check consultations table
    echo "<h2>Test 3: Consultations Table</h2>";
    $stmt = $pdo->query("SELECT c_id, c_full_name, c_email, c_status FROM consultations LIMIT 5");
    $consultations = $stmt->fetchAll();
    if ($consultations) {
        echo "<p class='success'>✓ Consultations table accessible</p>";
        echo "<table><tr><th>c_id</th><th>name</th><th>email</th><th>status</th></tr>";
        foreach ($consultations as $c) {
            echo "<tr><td>{$c['c_id']}</td><td>{$c['c_full_name']}</td><td>{$c['c_email']}</td><td>{$c['c_status']}</td></tr>";
        }
        echo "</table>";
    } else {
        echo "<p class='info'>ℹ No consultations found</p>";
    }
    
    // Test 4: Check lawyer_availability table
    echo "<h2>Test 4: Lawyer Availability Table</h2>";
    $stmt = $pdo->query("SELECT la_id, lawyer_id, schedule_type, weekday, la_is_active FROM lawyer_availability LIMIT 5");
    $availability = $stmt->fetchAll();
    if ($availability) {
        echo "<p class='success'>✓ Lawyer availability table accessible</p>";
        echo "<table><tr><th>la_id</th><th>lawyer_id</th><th>type</th><th>weekday</th><th>active</th></tr>";
        foreach ($availability as $a) {
            echo "<tr><td>{$a['la_id']}</td><td>{$a['lawyer_id']}</td><td>{$a['schedule_type']}</td><td>{$a['weekday']}</td><td>{$a['la_is_active']}</td></tr>";
        }
        echo "</table>";
    } else {
        echo "<p class='info'>ℹ No availability schedules found</p>";
    }
    
    // Test 5: Check practice_areas table
    echo "<h2>Test 5: Practice Areas Table</h2>";
    $stmt = $pdo->query("SELECT pa_id, area_name, is_active FROM practice_areas LIMIT 5");
    $areas = $stmt->fetchAll();
    if ($areas) {
        echo "<p class='success'>✓ Practice areas table accessible</p>";
        echo "<table><tr><th>pa_id</th><th>area_name</th><th>active</th></tr>";
        foreach ($areas as $area) {
            echo "<tr><td>{$area['pa_id']}</td><td>{$area['area_name']}</td><td>{$area['is_active']}</td></tr>";
        }
        echo "</table>";
    } else {
        echo "<p class='info'>ℹ No practice areas found (run setup_test_data.sql)</p>";
    }
    
    // Test 6: Check lawyer_specializations table
    echo "<h2>Test 6: Lawyer Specializations Table</h2>";
    $stmt = $pdo->query("
        SELECT ls.lawyer_id, pa.area_name 
        FROM lawyer_specializations ls 
        JOIN practice_areas pa ON ls.pa_id = pa.pa_id 
        LIMIT 5
    ");
    $specs = $stmt->fetchAll();
    if ($specs) {
        echo "<p class='success'>✓ Lawyer specializations table accessible</p>";
        echo "<table><tr><th>lawyer_id</th><th>specialization</th></tr>";
        foreach ($specs as $spec) {
            echo "<tr><td>{$spec['lawyer_id']}</td><td>{$spec['area_name']}</td></tr>";
        }
        echo "</table>";
    } else {
        echo "<p class='info'>ℹ No specializations found</p>";
    }
    
    echo "<hr>";
    echo "<h2>Summary</h2>";
    echo "<p class='success'>✓ All database tables are accessible with correct column names!</p>";
    echo "<p class='info'>If you see 'No data found' messages, run: <code>mysql -u root -p test < setup_test_data.sql</code></p>";
    echo "<p><a href='login.php'>Go to Login Page</a></p>";
    
} catch (Exception $e) {
    echo "<p class='error'>✗ Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p>Check your database configuration in config/database.php</p>";
}
?>
