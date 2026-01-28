<?php
/**
 * Notification System Validation Script
 * Tests DocxGenerator and EmailNotification compatibility with normalized schema
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../vendor/autoload.php'; // Load Composer dependencies (PHPMailer)
require_once __DIR__ . '/../includes/DocxGenerator.php';
require_once __DIR__ . '/../includes/EmailNotification.php';

echo "=================================================\n";
echo "  Notification System Validation\n";
echo "  Testing Schema Compatibility\n";
echo "=================================================\n\n";

$errors = [];
$warnings = [];
$success = [];

// Test 1: Database Connection
echo "[1/8] Testing database connection...\n";
try {
    $pdo = getDBConnection();
    $success[] = "✅ Database connection successful";
} catch (Exception $e) {
    $errors[] = "❌ Database connection failed: " . $e->getMessage();
    die("Cannot proceed without database connection.\n");
}

// Test 2: Check Required Tables
echo "[2/8] Checking required tables...\n";
$required_tables = [
    'consultations',
    'lawyer_profile',
    'users',
    'notification_queue',
    'lawyer_specializations',
    'practice_areas'
];

foreach ($required_tables as $table) {
    try {
        $stmt = $pdo->query("SELECT 1 FROM $table LIMIT 1");
        $success[] = "✅ Table '$table' exists";
    } catch (Exception $e) {
        $errors[] = "❌ Table '$table' missing or inaccessible";
    }
}

// Test 3: Check Required Columns in consultations
echo "[3/8] Validating consultations table structure...\n";
$required_columns = [
    'c_id', 'c_full_name', 'c_email', 'c_phone', 'c_practice_area',
    'c_case_description', 'c_selected_lawyer', 'c_selected_date',
    'lawyer_id', 'c_consultation_date', 'c_consultation_time',
    'c_status', 'c_cancellation_reason', 'created_at', 'updated_at'
];

try {
    $stmt = $pdo->query("DESCRIBE consultations");
    $existing_columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    foreach ($required_columns as $col) {
        if (in_array($col, $existing_columns)) {
            $success[] = "✅ Column 'consultations.$col' exists";
        } else {
            $errors[] = "❌ Column 'consultations.$col' missing";
        }
    }
} catch (Exception $e) {
    $errors[] = "❌ Cannot validate consultations table: " . $e->getMessage();
}

// Test 4: Check notification_queue structure
echo "[4/8] Validating notification_queue table structure...\n";
$nq_columns = [
    'nq_id', 'user_id', 'consultation_id', 'email', 'subject',
    'message', 'notification_type', 'nq_status', 'attempts',
    'created_at', 'sent_at', 'error_message'
];

try {
    $stmt = $pdo->query("DESCRIBE notification_queue");
    $existing_columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    foreach ($nq_columns as $col) {
        if (in_array($col, $existing_columns)) {
            $success[] = "✅ Column 'notification_queue.$col' exists";
        } else {
            $errors[] = "❌ Column 'notification_queue.$col' missing";
        }
    }
} catch (Exception $e) {
    $errors[] = "❌ Cannot validate notification_queue table: " . $e->getMessage();
}

// Test 5: Check Foreign Key Relationships
echo "[5/8] Validating foreign key relationships...\n";
try {
    // Test consultations.lawyer_id -> users.user_id
    $stmt = $pdo->query("
        SELECT COUNT(*) as count 
        FROM consultations c 
        LEFT JOIN users u ON c.lawyer_id = u.user_id 
        WHERE c.lawyer_id IS NOT NULL AND u.user_id IS NULL
    ");
    $orphaned = $stmt->fetch()['count'];
    
    if ($orphaned == 0) {
        $success[] = "✅ consultations.lawyer_id -> users.user_id (valid)";
    } else {
        $warnings[] = "⚠️ Found $orphaned orphaned consultations (invalid lawyer_id)";
    }
    
    // Test notification_queue.user_id -> users.user_id
    $stmt = $pdo->query("
        SELECT COUNT(*) as count 
        FROM notification_queue nq 
        LEFT JOIN users u ON nq.user_id = u.user_id 
        WHERE u.user_id IS NULL
    ");
    $orphaned = $stmt->fetch()['count'];
    
    if ($orphaned == 0) {
        $success[] = "✅ notification_queue.user_id -> users.user_id (valid)";
    } else {
        $warnings[] = "⚠️ Found $orphaned orphaned notifications (invalid user_id)";
    }
    
} catch (Exception $e) {
    $warnings[] = "⚠️ Cannot validate foreign keys: " . $e->getMessage();
}

// Test 6: Check PHP Extensions
echo "[6/8] Checking required PHP extensions...\n";
if (class_exists('ZipArchive')) {
    $success[] = "✅ ZipArchive extension available (required for DOCX)";
} else {
    $errors[] = "❌ ZipArchive extension missing (required for DOCX generation)";
}

if (extension_loaded('pdo')) {
    $success[] = "✅ PDO extension available";
} else {
    $errors[] = "❌ PDO extension missing";
}

if (extension_loaded('openssl')) {
    $success[] = "✅ OpenSSL extension available (required for SMTP)";
} else {
    $warnings[] = "⚠️ OpenSSL extension missing (required for email sending)";
}

// Test 7: Check Upload Directory
echo "[7/8] Checking upload directory permissions...\n";
$upload_dir = __DIR__ . '/../uploads/generated_docs/';

if (!is_dir($upload_dir)) {
    if (mkdir($upload_dir, 0755, true)) {
        $success[] = "✅ Created upload directory: $upload_dir";
    } else {
        $errors[] = "❌ Cannot create upload directory: $upload_dir";
    }
} else {
    $success[] = "✅ Upload directory exists: $upload_dir";
}

if (is_writable($upload_dir)) {
    $success[] = "✅ Upload directory is writable";
} else {
    $errors[] = "❌ Upload directory is not writable: $upload_dir";
}

// Test 8: Test DocxGenerator
echo "[8/8] Testing DocxGenerator functionality...\n";
try {
    $docxGenerator = new DocxGenerator();
    
    $test_data = [
        'client_name' => 'Test Client',
        'lawyer_name' => 'Test Lawyer',
        'practice_area' => 'Test Practice Area',
        'formatted_date' => 'Monday, January 28, 2026',
        'formatted_time' => '2:00 PM',
        'reason' => 'testing'
    ];
    
    $docx_path = $docxGenerator->generateConsultationDocument($test_data, 'confirmation');
    
    if (file_exists($docx_path)) {
        $success[] = "✅ DocxGenerator test successful";
        $success[] = "✅ Generated test file: " . basename($docx_path);
        
        // Cleanup test file
        unlink($docx_path);
        $success[] = "✅ Test file cleaned up";
    } else {
        $errors[] = "❌ DocxGenerator failed to create file";
    }
    
} catch (Exception $e) {
    $errors[] = "❌ DocxGenerator test failed: " . $e->getMessage();
}

// Summary Report
echo "\n=================================================\n";
echo "  VALIDATION SUMMARY\n";
echo "=================================================\n\n";

if (!empty($success)) {
    echo "✅ PASSED (" . count($success) . "):\n";
    foreach ($success as $msg) {
        echo "   $msg\n";
    }
    echo "\n";
}

if (!empty($warnings)) {
    echo "⚠️  WARNINGS (" . count($warnings) . "):\n";
    foreach ($warnings as $msg) {
        echo "   $msg\n";
    }
    echo "\n";
}

if (!empty($errors)) {
    echo "❌ ERRORS (" . count($errors) . "):\n";
    foreach ($errors as $msg) {
        echo "   $msg\n";
    }
    echo "\n";
}

// Final Status
echo "=================================================\n";
if (empty($errors)) {
    echo "✅ VALIDATION PASSED - System is ready!\n";
    echo "=================================================\n";
    exit(0);
} else {
    echo "❌ VALIDATION FAILED - Please fix errors above\n";
    echo "=================================================\n";
    exit(1);
}
?>
