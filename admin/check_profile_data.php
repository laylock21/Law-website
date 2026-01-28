<?php
/**
 * Diagnostic: Check what's stored in profile BLOB column
 */

require_once '../config/database.php';

try {
    $pdo = getDBConnection();
    
    echo "<h2>Profile BLOB Data Analysis</h2>";
    echo "<style>
        body { font-family: Arial, sans-serif; padding: 20px; }
        .profile-box { border: 1px solid #ddd; padding: 15px; margin: 10px 0; background: #f9f9f9; }
        .success { color: green; }
        .error { color: red; }
        img { max-width: 200px; border: 2px solid #ddd; }
    </style>";
    
    // Get all lawyers with profile data
    $stmt = $pdo->query("
        SELECT 
            lawyer_id, 
            lp_fullname,
            LENGTH(profile) as blob_size,
            SUBSTRING(profile, 1, 20) as blob_start
        FROM lawyer_profile 
        WHERE profile IS NOT NULL
        LIMIT 10
    ");
    
    $lawyers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($lawyers)) {
        echo "<p class='error'>No profile pictures found in database.</p>";
        exit;
    }
    
    echo "<p>Found " . count($lawyers) . " lawyer(s) with profile data</p>";
    
    foreach ($lawyers as $lawyer) {
        echo "<div class='profile-box'>";
        echo "<h3>Lawyer ID: {$lawyer['lawyer_id']} - {$lawyer['lp_fullname']}</h3>";
        echo "<p><strong>BLOB Size:</strong> " . number_format($lawyer['blob_size']) . " bytes (" . round($lawyer['blob_size']/1024, 2) . " KB)</p>";
        
        // Get the actual BLOB data
        $blob_stmt = $pdo->prepare("SELECT profile FROM lawyer_profile WHERE lawyer_id = ?");
        $blob_stmt->execute([$lawyer['lawyer_id']]);
        $blob_data = $blob_stmt->fetchColumn();
        
        // Check what type of data it is
        $first_bytes = substr($blob_data, 0, 20);
        $hex = bin2hex($first_bytes);
        
        echo "<p><strong>First bytes (hex):</strong> " . $hex . "</p>";
        
        // Detect image type
        $is_jpeg = (substr($hex, 0, 4) === 'ffd8');
        $is_png = (substr($hex, 0, 16) === '89504e470d0a1a0a');
        $is_gif = (substr($hex, 0, 6) === '474946');
        
        if ($is_jpeg) {
            echo "<p class='success'>✓ Detected: JPEG image</p>";
            echo "<p><strong>Preview:</strong></p>";
            echo "<img src='data:image/jpeg;base64," . base64_encode($blob_data) . "' />";
        } elseif ($is_png) {
            echo "<p class='success'>✓ Detected: PNG image</p>";
            echo "<p><strong>Preview:</strong></p>";
            echo "<img src='data:image/png;base64," . base64_encode($blob_data) . "' />";
        } elseif ($is_gif) {
            echo "<p class='success'>✓ Detected: GIF image</p>";
            echo "<p><strong>Preview:</strong></p>";
            echo "<img src='data:image/gif;base64," . base64_encode($blob_data) . "' />";
        } else {
            echo "<p class='error'>⚠ Unknown format - First bytes: " . substr($blob_data, 0, 20) . "</p>";
            echo "<p>Might be: corrupted data, text, or unsupported format</p>";
        }
        
        echo "</div>";
    }
    
    echo "<hr>";
    echo "<h3>Recommendation:</h3>";
    echo "<p>If images display correctly above, you can:</p>";
    echo "<ol>";
    echo "<li>Run <code>php migrations/export_existing_profiles.php</code> to export them to files</li>";
    echo "<li>Change the column to VARCHAR(255)</li>";
    echo "<li>Update code to use file paths instead of BLOB</li>";
    echo "</ol>";
    
} catch (Exception $e) {
    echo "<p class='error'>Error: " . $e->getMessage() . "</p>";
}
?>
