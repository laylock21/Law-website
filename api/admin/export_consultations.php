<?php
/**
 * Admin API - Export Consultations
 * Exports consultation data based on selected IDs and date range
 */

session_start();

// Authentication check
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true || $_SESSION['user_role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once '../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$format = $_POST['format'] ?? 'csv';
$selected_ids = $_POST['selected_ids'] ?? [];
$status_filter = $_POST['status_filter'] ?? null;
$date_from = $_POST['date_from'] ?? null;
$date_to = $_POST['date_to'] ?? null;

try {
    $pdo = getDBConnection();
    
    // Build query
    $sql = "SELECT 
                c.c_id,
                c.c_full_name,
                c.c_email,
                c.c_phone,
                c.c_practice_area,
                c.c_case_description,
                c.c_consultation_date,
                c.c_consultation_time,
                c.c_status,
                c.c_cancellation_reason,
                c.created_at,
                lp.lp_fullname as lawyer_name
            FROM consultations c
            LEFT JOIN lawyer_profile lp ON c.lawyer_id = lp.lawyer_id
            WHERE 1=1";
    
    $params = [];
    
    // Filter by selected IDs if provided
    if (!empty($selected_ids)) {
        $placeholders = str_repeat('?,', count($selected_ids) - 1) . '?';
        $sql .= " AND c.c_id IN ($placeholders)";
        $params = array_merge($params, $selected_ids);
    }
    
    // Filter by status
    if ($status_filter) {
        $sql .= " AND c.c_status = ?";
        $params[] = $status_filter;
    }
    
    // Filter by date range
    if ($date_from) {
        $sql .= " AND c.created_at >= ?";
        $params[] = $date_from . ' 00:00:00';
    }
    
    if ($date_to) {
        $sql .= " AND c.created_at <= ?";
        $params[] = $date_to . ' 23:59:59';
    }
    
    $sql .= " ORDER BY c.created_at DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $consultations = $stmt->fetchAll();
    
    if (empty($consultations)) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'No consultations found matching the criteria']);
        exit;
    }
    
    // Export based on format
    if ($format === 'csv') {
        exportCSV($consultations);
    } elseif ($format === 'json') {
        exportJSON($consultations);
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid format']);
    }
    
} catch (Exception $e) {
    error_log("Export error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Export failed: ' . $e->getMessage()]);
}

function exportCSV($data) {
    $filename = 'consultations_export_' . date('Y-m-d_His') . '.csv';
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    $output = fopen('php://output', 'w');
    
    // Add BOM for Excel UTF-8 support
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Headers
    fputcsv($output, [
        'ID',
        'Client Name',
        'Email',
        'Phone',
        'Practice Area',
        'Case Description',
        'Consultation Date',
        'Consultation Time',
        'Status',
        'Cancellation Reason',
        'Assigned Lawyer',
        'Created At'
    ]);
    
    // Data rows
    foreach ($data as $row) {
        fputcsv($output, [
            $row['c_id'],
            $row['c_full_name'],
            $row['c_email'],
            $row['c_phone'] ?? '',
            $row['c_practice_area'] ?? '',
            $row['c_case_description'] ?? '',
            $row['c_consultation_date'] ?? '',
            $row['c_consultation_time'] ?? '',
            ucfirst($row['c_status']),
            $row['c_cancellation_reason'] ?? '',
            $row['lawyer_name'] ?? 'Not assigned',
            $row['created_at']
        ]);
    }
    
    fclose($output);
    exit;
}

function exportJSON($data) {
    $filename = 'consultations_export_' . date('Y-m-d_His') . '.json';
    
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    $export_data = [
        'exported_at' => date('Y-m-d H:i:s'),
        'total_records' => count($data),
        'consultations' => $data
    ];
    
    echo json_encode($export_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}
