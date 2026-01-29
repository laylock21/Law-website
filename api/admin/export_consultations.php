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
    } elseif ($format === 'pdf') {
        exportPDF($consultations);
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

function exportPDF($data) {
    require_once '../../vendor/autoload.php';
    
    $filename = 'consultations_export_' . date('Y-m-d_His') . '.pdf';
    
    // Create new Spreadsheet
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    
    // Set document properties
    $spreadsheet->getProperties()
        ->setCreator('MD Law Firm')
        ->setTitle('Consultations Report')
        ->setSubject('Consultation Records')
        ->setDescription('Consultation records export');
    
    // Add header information
    $sheet->setCellValue('A1', 'Consultations Report');
    $sheet->mergeCells('A1:H1');
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
    $sheet->getStyle('A1')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    
    $sheet->setCellValue('A2', 'Generated: ' . date('F d, Y g:i A'));
    $sheet->mergeCells('A2:H2');
    $sheet->getStyle('A2')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    
    $sheet->setCellValue('A3', 'Total Records: ' . count($data));
    $sheet->mergeCells('A3:H3');
    $sheet->getStyle('A3')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    
    // Add column headers
    $headerRow = 5;
    $headers = ['ID', 'Client Name', 'Email', 'Phone', 'Practice Area', 'Date & Time', 'Lawyer', 'Status'];
    $column = 'A';
    foreach ($headers as $header) {
        $sheet->setCellValue($column . $headerRow, $header);
        $column++;
    }
    
    // Style header row
    $sheet->getStyle('A' . $headerRow . ':H' . $headerRow)->applyFromArray([
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
        'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'C5A253']],
        'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER],
        'borders' => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN]]
    ]);
    
    // Add data rows
    $row = $headerRow + 1;
    foreach ($data as $item) {
        $consultationDateTime = '';
        if ($item['c_consultation_date'] && $item['c_consultation_time']) {
            $consultationDateTime = date('M d, Y', strtotime($item['c_consultation_date'])) . ' ' . 
                                   date('g:i A', strtotime($item['c_consultation_time']));
        } else {
            $consultationDateTime = 'Not scheduled';
        }
        
        $sheet->setCellValue('A' . $row, $item['c_id']);
        $sheet->setCellValue('B' . $row, $item['c_full_name']);
        $sheet->setCellValue('C' . $row, $item['c_email']);
        $sheet->setCellValue('D' . $row, $item['c_phone'] ?? '');
        $sheet->setCellValue('E' . $row, $item['c_practice_area'] ?? 'N/A');
        $sheet->setCellValue('F' . $row, $consultationDateTime);
        $sheet->setCellValue('G' . $row, $item['lawyer_name'] ?? 'Not assigned');
        $sheet->setCellValue('H' . $row, ucfirst($item['c_status']));
        
        // Color code status
        $statusColors = [
            'pending' => 'FFF3CD',
            'confirmed' => 'D1ECF1',
            'completed' => 'D4EDDA',
            'cancelled' => 'F8D7DA'
        ];
        $statusColor = $statusColors[strtolower($item['c_status'])] ?? 'FFFFFF';
        $sheet->getStyle('H' . $row)->applyFromArray([
            'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => $statusColor]]
        ]);
        
        // Alternate row colors
        if ($row % 2 == 0) {
            $sheet->getStyle('A' . $row . ':G' . $row)->applyFromArray([
                'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F9F9F9']]
            ]);
        }
        
        $row++;
    }
    
    // Add borders to all data
    $sheet->getStyle('A' . $headerRow . ':H' . ($row - 1))->applyFromArray([
        'borders' => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN, 'color' => ['rgb' => 'DDDDDD']]]
    ]);
    
    // Auto-size columns
    foreach (range('A', 'H') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }
    
    // Add footer
    $footerRow = $row + 2;
    $sheet->setCellValue('A' . $footerRow, 'MD Law Firm - Confidential Document');
    $sheet->mergeCells('A' . $footerRow . ':H' . $footerRow);
    $sheet->getStyle('A' . $footerRow)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('A' . $footerRow)->getFont()->setSize(9)->setItalic(true);
    
    // Set page orientation to landscape
    $sheet->getPageSetup()->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE);
    $sheet->getPageSetup()->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_A4);
    
    // Generate PDF
    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Pdf\Dompdf($spreadsheet);
    
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    $writer->save('php://output');
    exit;
}
