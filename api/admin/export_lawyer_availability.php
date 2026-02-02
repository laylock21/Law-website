<?php
/**
 * Admin API - Export Lawyer Availability
 * Exports lawyer availability/schedule data
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
$lawyer_id = $_POST['lawyer_id'] ?? null;
$schedule_type = $_POST['schedule_type'] ?? null;
$date_from = $_POST['date_from'] ?? null;
$date_to = $_POST['date_to'] ?? null;

if (!$lawyer_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Lawyer ID is required']);
    exit;
}

try {
    $pdo = getDBConnection();
    
    // Get lawyer details
    $lawyer_stmt = $pdo->prepare("
        SELECT u.user_id, u.username, u.email, lp.lp_fullname
        FROM users u
        LEFT JOIN lawyer_profile lp ON u.user_id = lp.lawyer_id
        WHERE u.user_id = ? AND u.role = 'lawyer'
    ");
    $lawyer_stmt->execute([$lawyer_id]);
    $lawyer = $lawyer_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$lawyer) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Lawyer not found']);
        exit;
    }
    
    // Build query for availability
    $sql = "SELECT 
                la_id,
                schedule_type,
                weekday,
                specific_date,
                start_time,
                end_time,
                max_appointments,
                time_slot_duration,
                la_is_active,
                blocked_reason,
                created_at
            FROM lawyer_availability
            WHERE lawyer_id = ?";
    
    $params = [$lawyer_id];
    
    // Filter by schedule type
    if ($schedule_type && $schedule_type !== 'all') {
        $sql .= " AND schedule_type = ?";
        $params[] = $schedule_type;
    }
    
    // Filter by date range (for all schedule types except weekly)
    if ($date_from && $schedule_type !== 'weekly') {
        if ($schedule_type === 'one_time' || $schedule_type === 'blocked') {
            $sql .= " AND specific_date >= ?";
            $params[] = $date_from;
        } elseif ($schedule_type === 'all') {
            // For "all" types, filter by specific_date for one_time and blocked, and created_at for weekly
            $sql .= " AND ((schedule_type IN ('one_time', 'blocked') AND specific_date >= ?) OR (schedule_type = 'weekly' AND created_at >= ?))";
            $params[] = $date_from;
            $params[] = $date_from . ' 00:00:00';
        }
    }
    
    if ($date_to && $schedule_type !== 'weekly') {
        if ($schedule_type === 'one_time' || $schedule_type === 'blocked') {
            $sql .= " AND specific_date <= ?";
            $params[] = $date_to;
        } elseif ($schedule_type === 'all') {
            // For "all" types, filter by specific_date for one_time and blocked, and created_at for weekly
            $sql .= " AND ((schedule_type IN ('one_time', 'blocked') AND specific_date <= ?) OR (schedule_type = 'weekly' AND created_at <= ?))";
            $params[] = $date_to;
            $params[] = $date_to . ' 23:59:59';
        }
    }
    
    $sql .= " ORDER BY 
        CASE 
            WHEN schedule_type = 'blocked' AND specific_date >= CURDATE() THEN 1
            WHEN schedule_type = 'one_time' AND specific_date >= CURDATE() THEN 2
            WHEN schedule_type = 'weekly' THEN 3
            ELSE 4
        END,
        specific_date ASC,
        FIELD(weekday, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday')";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($schedules)) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'No availability records found']);
        exit;
    }
    
    // Export based on format
    if ($format === 'xlsx') {
        exportExcel($schedules, $lawyer);
    } elseif ($format === 'pdf') {
        exportPDF($schedules, $lawyer);
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid format']);
    }
    
} catch (Exception $e) {
    error_log("Export error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Export failed: ' . $e->getMessage()]);
}

function exportExcel($data, $lawyer) {
    require_once '../../vendor/autoload.php';
    
    $filename = 'lawyer_availability_' . $lawyer['user_id'] . '_' . date('Y-m-d_His') . '.xlsx';
    
    // Create new Spreadsheet
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    
    // Set document properties
    $spreadsheet->getProperties()
        ->setCreator('MD Law Firm')
        ->setTitle('Lawyer Availability Report')
        ->setSubject('Lawyer Schedule Records')
        ->setDescription('Lawyer availability and schedule export');
    
    // Add header information
    $sheet->setCellValue('A1', 'Lawyer Availability Report');
    $sheet->mergeCells('A1:I1');
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(18)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FFFFFF'));
    $sheet->getStyle('A1')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('A1')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB('C5A253');
    $sheet->getRowDimension(1)->setRowHeight(30);
    
    $sheet->setCellValue('A2', 'Lawyer: ' . $lawyer['lp_fullname']);
    $sheet->mergeCells('A2:I2');
    $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(12);
    $sheet->getStyle('A2')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('A2')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB('F8F9FA');
    
    $sheet->setCellValue('A3', 'Email: ' . $lawyer['email']);
    $sheet->mergeCells('A3:I3');
    $sheet->getStyle('A3')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('A3')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB('F8F9FA');
    
    $sheet->setCellValue('A4', 'Generated: ' . date('F d, Y g:i A'));
    $sheet->mergeCells('A4:I4');
    $sheet->getStyle('A4')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('A4')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB('F8F9FA');
    
    $sheet->setCellValue('A5', 'Total Records: ' . count($data));
    $sheet->mergeCells('A5:I5');
    $sheet->getStyle('A5')->getFont()->setBold(true);
    $sheet->getStyle('A5')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('A5')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB('E9ECEF');
    
    // Add column headers
    $headerRow = 7;
    $headers = ['ID', 'Type', 'Day/Date', 'Start Time', 'End Time', 'Max Appts', 'Duration', 'Status', 'Blocked Reason'];
    $column = 'A';
    foreach ($headers as $header) {
        $sheet->setCellValue($column . $headerRow, $header);
        $column++;
    }
    
    // Style header row
    $sheet->getStyle('A' . $headerRow . ':I' . $headerRow)->applyFromArray([
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11],
        'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => '0B1D3A']],
        'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER, 'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER],
        'borders' => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN, 'color' => ['rgb' => '000000']]]
    ]);
    $sheet->getRowDimension($headerRow)->setRowHeight(25);
    
    // Add data rows
    $row = $headerRow + 1;
    foreach ($data as $item) {
        $dayDate = '';
        if ($item['schedule_type'] === 'weekly') {
            $dayDate = $item['weekday'];
        } else {
            $dayDate = $item['specific_date'] ? date('M d, Y', strtotime($item['specific_date'])) : '';
        }
        
        $status = '';
        if ($item['schedule_type'] === 'blocked') {
            $status = 'Unavailable';
        } else {
            $status = $item['la_is_active'] ? 'Active' : 'Inactive';
        }
        
        $startTime = $item['schedule_type'] === 'blocked' ? '—' : date('g:i A', strtotime($item['start_time']));
        $endTime = $item['schedule_type'] === 'blocked' ? '—' : date('g:i A', strtotime($item['end_time']));
        $duration = $item['time_slot_duration'] ? $item['time_slot_duration'] . ' min' : '—';
        
        $sheet->setCellValue('A' . $row, $item['la_id']);
        $sheet->setCellValue('B' . $row, ucfirst(str_replace('_', '-', $item['schedule_type'])));
        $sheet->setCellValue('C' . $row, $dayDate);
        $sheet->setCellValue('D' . $row, $startTime);
        $sheet->setCellValue('E' . $row, $endTime);
        $sheet->setCellValue('F' . $row, $item['max_appointments'] ?? '—');
        $sheet->setCellValue('G' . $row, $duration);
        $sheet->setCellValue('H' . $row, $status);
        $sheet->setCellValue('I' . $row, $item['blocked_reason'] ?? '');
        
        // Color code schedule types
        $typeColors = [
            'weekly' => 'D1ECF1',
            'one_time' => 'FFF3CD',
            'blocked' => 'F8D7DA'
        ];
        $typeColor = $typeColors[strtolower($item['schedule_type'])] ?? 'FFFFFF';
        $sheet->getStyle('B' . $row)->applyFromArray([
            'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => $typeColor]],
            'font' => ['bold' => true]
        ]);
        
        // Color code status
        $statusColors = [
            'Active' => 'D4EDDA',
            'Inactive' => 'F8D7DA',
            'Unavailable' => 'F8D7DA'
        ];
        $statusColor = $statusColors[$status] ?? 'FFFFFF';
        $sheet->getStyle('H' . $row)->applyFromArray([
            'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => $statusColor]]
        ]);
        
        // Alternate row colors
        if ($row % 2 == 0) {
            $sheet->getStyle('A' . $row . ':A' . $row)->applyFromArray([
                'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F9F9F9']]
            ]);
            $sheet->getStyle('C' . $row . ':G' . $row)->applyFromArray([
                'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F9F9F9']]
            ]);
            $sheet->getStyle('I' . $row)->applyFromArray([
                'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F9F9F9']]
            ]);
        }
        
        // Center align specific columns
        $sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('D' . $row . ':H' . $row)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        
        $row++;
    }
    
    // Add borders to all data
    $sheet->getStyle('A' . $headerRow . ':I' . ($row - 1))->applyFromArray([
        'borders' => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN, 'color' => ['rgb' => 'DDDDDD']]]
    ]);
    
    // Auto-size columns
    foreach (range('A', 'I') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }
    
    // Add footer
    $footerRow = $row + 2;
    $sheet->setCellValue('A' . $footerRow, 'MD Law Firm - Confidential Document');
    $sheet->mergeCells('A' . $footerRow . ':I' . $footerRow);
    $sheet->getStyle('A' . $footerRow)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('A' . $footerRow)->getFont()->setSize(9)->setItalic(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('6C757D'));
    
    // Generate Excel file
    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    $writer->save('php://output');
    exit;
}

function exportPDF($data, $lawyer) {
    require_once '../../vendor/autoload.php';
    
    $filename = 'lawyer_availability_' . $lawyer['user_id'] . '_' . date('Y-m-d_His') . '.pdf';
    
    // Create new Spreadsheet
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    
    // Set document properties
    $spreadsheet->getProperties()
        ->setCreator('MD Law Firm')
        ->setTitle('Lawyer Availability Report')
        ->setSubject('Lawyer Schedule Records')
        ->setDescription('Lawyer availability and schedule export');
    
    // Add header information
    $sheet->setCellValue('A1', 'Lawyer Availability Report');
    $sheet->mergeCells('A1:I1');
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
    $sheet->getStyle('A1')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    
    $sheet->setCellValue('A2', 'Lawyer: ' . $lawyer['lp_fullname']);
    $sheet->mergeCells('A2:I2');
    $sheet->getStyle('A2')->getFont()->setBold(true);
    $sheet->getStyle('A2')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    
    $sheet->setCellValue('A3', 'Generated: ' . date('F d, Y g:i A'));
    $sheet->mergeCells('A3:I3');
    $sheet->getStyle('A3')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    
    $sheet->setCellValue('A4', 'Total Records: ' . count($data));
    $sheet->mergeCells('A4:I4');
    $sheet->getStyle('A4')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    
    // Add column headers
    $headerRow = 6;
    $headers = ['ID', 'Type', 'Day/Date', 'Start Time', 'End Time', 'Max Appts', 'Duration', 'Status', 'Blocked Reason'];
    $column = 'A';
    foreach ($headers as $header) {
        $sheet->setCellValue($column . $headerRow, $header);
        $column++;
    }
    
    // Style header row
    $sheet->getStyle('A' . $headerRow . ':I' . $headerRow)->applyFromArray([
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
        'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'C5A253']],
        'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER],
        'borders' => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN]]
    ]);
    
    // Add data rows
    $row = $headerRow + 1;
    foreach ($data as $item) {
        $dayDate = '';
        if ($item['schedule_type'] === 'weekly') {
            $dayDate = $item['weekday'];
        } else {
            $dayDate = $item['specific_date'] ? date('M d, Y', strtotime($item['specific_date'])) : '';
        }
        
        $status = '';
        if ($item['schedule_type'] === 'blocked') {
            $status = 'Unavailable';
        } else {
            $status = $item['la_is_active'] ? 'Active' : 'Inactive';
        }
        
        $startTime = $item['schedule_type'] === 'blocked' ? '—' : date('g:i A', strtotime($item['start_time']));
        $endTime = $item['schedule_type'] === 'blocked' ? '—' : date('g:i A', strtotime($item['end_time']));
        $duration = $item['time_slot_duration'] ? $item['time_slot_duration'] . ' min' : '—';
        
        $sheet->setCellValue('A' . $row, $item['la_id']);
        $sheet->setCellValue('B' . $row, ucfirst(str_replace('_', '-', $item['schedule_type'])));
        $sheet->setCellValue('C' . $row, $dayDate);
        $sheet->setCellValue('D' . $row, $startTime);
        $sheet->setCellValue('E' . $row, $endTime);
        $sheet->setCellValue('F' . $row, $item['max_appointments'] ?? '—');
        $sheet->setCellValue('G' . $row, $duration);
        $sheet->setCellValue('H' . $row, $status);
        $sheet->setCellValue('I' . $row, $item['blocked_reason'] ?? '');
        
        // Color code schedule types
        $typeColors = [
            'weekly' => 'D1ECF1',
            'one_time' => 'FFF3CD',
            'blocked' => 'F8D7DA'
        ];
        $typeColor = $typeColors[strtolower($item['schedule_type'])] ?? 'FFFFFF';
        $sheet->getStyle('B' . $row)->applyFromArray([
            'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => $typeColor]]
        ]);
        
        // Alternate row colors
        if ($row % 2 == 0) {
            $sheet->getStyle('A' . $row . ':A' . $row)->applyFromArray([
                'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F9F9F9']]
            ]);
            $sheet->getStyle('C' . $row . ':I' . $row)->applyFromArray([
                'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F9F9F9']]
            ]);
        }
        
        $row++;
    }
    
    // Add borders to all data
    $sheet->getStyle('A' . $headerRow . ':I' . ($row - 1))->applyFromArray([
        'borders' => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN, 'color' => ['rgb' => 'DDDDDD']]]
    ]);
    
    // Auto-size columns
    foreach (range('A', 'I') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }
    
    // Add footer
    $footerRow = $row + 2;
    $sheet->setCellValue('A' . $footerRow, 'MD Law Firm - Confidential Document');
    $sheet->mergeCells('A' . $footerRow . ':I' . $footerRow);
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
