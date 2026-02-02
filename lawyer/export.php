<?php
/**
 * Lawyer - Export Availability
 * Allows lawyers to export their availability schedule in various formats
 */

session_start();

// Unified authentication check
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true || $_SESSION['user_role'] !== 'lawyer') {
    header('Location: ../login.php');
    exit;
}

require_once '../config/database.php';

$lawyer_id = $_SESSION['lawyer_id'];
$lawyer_name = $_SESSION['lawyer_name'] ?? 'Lawyer';

// Handle export request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['export_format'])) {
    $format = $_POST['export_format'];
    $export_type = $_POST['export_type'] ?? 'availability';
    $schedule_type = $_POST['schedule_type'] ?? 'all';
    $date_from = $_POST['date_from'] ?? null;
    $date_to = $_POST['date_to'] ?? null;
    
    try {
        $pdo = getDBConnection();
        
        if ($export_type === 'availability') {
            // Build query with filters for availability
            $sql = "SELECT 
                        la_id,
                        lawyer_id,
                        schedule_type,
                        weekday,
                        specific_date,
                        start_time,
                        end_time,
                        max_appointments,
                        la_is_active,
                        created_at,
                        updated_at
                    FROM lawyer_availability
                    WHERE lawyer_id = ?";
            
            $params = [$lawyer_id];
            
            if ($schedule_type !== 'all') {
                $sql .= " AND schedule_type = ?";
                $params[] = $schedule_type;
            }
            
            if ($date_from && $schedule_type === 'one_time') {
                $sql .= " AND specific_date >= ?";
                $params[] = $date_from;
            }
            
            if ($date_to && $schedule_type === 'one_time') {
                $sql .= " AND specific_date <= ?";
                $params[] = $date_to;
            }
            
            $sql .= " ORDER BY schedule_type, specific_date, weekday, start_time";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $availability = $stmt->fetchAll();
        } else {
            $availability = [];
        }
        
        if ($export_type === 'consultation') {
            // Get consultations for this lawyer
            $consultations_columns_stmt = $pdo->query("DESCRIBE consultations");
            $consultations_columns_rows = $consultations_columns_stmt ? $consultations_columns_stmt->fetchAll() : [];
            $consultations_columns = array_map(function($r) { return $r['Field']; }, $consultations_columns_rows);
            
            $id_column = in_array('c_id', $consultations_columns, true) ? 'c_id' : 'id';
            $full_name_column = in_array('c_full_name', $consultations_columns, true) ? 'c_full_name' : 'full_name';
            $email_column = in_array('c_email', $consultations_columns, true) ? 'c_email' : 'email';
            $phone_column = in_array('c_phone', $consultations_columns, true) ? 'c_phone' : 'phone';
            $practice_area_column = in_array('c_practice_area', $consultations_columns, true) ? 'c_practice_area' : 'practice_area';
            $date_column = in_array('consultation_date', $consultations_columns, true) ? 'consultation_date' : 'c_consultation_date';
            $time_column = in_array('consultation_time', $consultations_columns, true) ? 'consultation_time' : 'c_consultation_time';
            $status_column = in_array('c_status', $consultations_columns, true) ? 'c_status' : 'status';
            
            $consult_sql = "SELECT 
                                {$id_column} as c_id,
                                {$full_name_column} as c_full_name,
                                {$email_column} as c_email,
                                " . ($phone_column ? "{$phone_column} as c_phone," : "NULL as c_phone,") . "
                                " . ($practice_area_column ? "{$practice_area_column} as c_practice_area," : "NULL as c_practice_area,") . "
                                {$date_column} as consultation_date,
                                " . ($time_column ? "{$time_column} as consultation_time," : "NULL as consultation_time,") . "
                                {$status_column} as c_status,
                                created_at
                            FROM consultations
                            WHERE lawyer_id = ? OR lawyer_id IS NULL
                            ORDER BY created_at DESC";
            
            $consult_stmt = $pdo->prepare($consult_sql);
            $consult_stmt->execute([$lawyer_id]);
            $consultations = $consult_stmt->fetchAll();
        } else {
            $consultations = [];
        }
        
        // Export based on format
        if ($format === 'excel') {
            exportExcel($availability, $consultations, $lawyer_name, $export_type);
        } elseif ($format === 'pdf') {
            exportPDF($availability, $consultations, $lawyer_name, $export_type);
        }
        
    } catch (Exception $e) {
        $error_message = 'Export error: ' . $e->getMessage();
        error_log("Export error: " . $e->getMessage());
    }
}

function exportExcel($availability, $consultations, $lawyer_name, $export_type) {
    require_once '../vendor/autoload.php';
    
    $filename = $export_type . '_export_' . date('Y-m-d_His') . '.xlsx';
    
    // Create new Spreadsheet
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    
    $currentRow = 1;
    
    // Export Availability
    if ($export_type === 'availability') {
        // Add header information
        $sheet->setCellValue('A' . $currentRow, 'AVAILABILITY SCHEDULE');
        $sheet->mergeCells('A' . $currentRow . ':J' . $currentRow);
        $sheet->getStyle('A' . $currentRow)->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A' . $currentRow)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $currentRow += 2;
        
        // Headers
        $headers = ['ID', 'Schedule Type', 'Weekday', 'Specific Date', 'Start Time', 'End Time', 'Max Appointments', 'Status', 'Created At', 'Updated At'];
        $column = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($column . $currentRow, $header);
            $sheet->getStyle($column . $currentRow)->getFont()->setBold(true);
            $sheet->getStyle($column . $currentRow)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB('C5A253');
            $sheet->getStyle($column . $currentRow)->getFont()->getColor()->setRGB('FFFFFF');
            $column++;
        }
        $currentRow++;
        
        // Data rows
        foreach ($availability as $row) {
            $sheet->setCellValue('A' . $currentRow, $row['la_id']);
            $sheet->setCellValue('B' . $currentRow, ucfirst(str_replace('_', ' ', $row['schedule_type'])));
            $sheet->setCellValue('C' . $currentRow, $row['weekday'] ?? 'N/A');
            $sheet->setCellValue('D' . $currentRow, $row['specific_date'] ?? 'N/A');
            $sheet->setCellValue('E' . $currentRow, date('g:i A', strtotime($row['start_time'])));
            $sheet->setCellValue('F' . $currentRow, date('g:i A', strtotime($row['end_time'])));
            $sheet->setCellValue('G' . $currentRow, $row['max_appointments']);
            $sheet->setCellValue('H' . $currentRow, $row['la_is_active'] ? 'Active' : 'Inactive');
            $sheet->setCellValue('I' . $currentRow, $row['created_at']);
            $sheet->setCellValue('J' . $currentRow, $row['updated_at'] ?? '');
            $currentRow++;
        }
    }
    
    // Export Consultations
    if ($export_type === 'consultation') {
        // Add header information
        $sheet->setCellValue('A' . $currentRow, 'CONSULTATIONS');
        $sheet->mergeCells('A' . $currentRow . ':I' . $currentRow);
        $sheet->getStyle('A' . $currentRow)->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A' . $currentRow)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $currentRow += 2;
        
        // Headers
        $headers = ['ID', 'Client Name', 'Email', 'Phone', 'Practice Area', 'Consultation Date', 'Consultation Time', 'Status', 'Created At'];
        $column = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($column . $currentRow, $header);
            $sheet->getStyle($column . $currentRow)->getFont()->setBold(true);
            $sheet->getStyle($column . $currentRow)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB('C5A253');
            $sheet->getStyle($column . $currentRow)->getFont()->getColor()->setRGB('FFFFFF');
            $column++;
        }
        $currentRow++;
        
        // Data rows
        foreach ($consultations as $row) {
            $sheet->setCellValue('A' . $currentRow, $row['c_id']);
            $sheet->setCellValue('B' . $currentRow, $row['c_full_name']);
            $sheet->setCellValue('C' . $currentRow, $row['c_email']);
            $sheet->setCellValue('D' . $currentRow, $row['c_phone'] ?? 'N/A');
            $sheet->setCellValue('E' . $currentRow, $row['c_practice_area'] ?? 'N/A');
            $sheet->setCellValue('F' . $currentRow, $row['consultation_date'] ? date('M d, Y', strtotime($row['consultation_date'])) : 'N/A');
            $sheet->setCellValue('G' . $currentRow, $row['consultation_time'] ? date('g:i A', strtotime($row['consultation_time'])) : 'N/A');
            $sheet->setCellValue('H' . $currentRow, ucfirst($row['c_status']));
            $sheet->setCellValue('I' . $currentRow, $row['created_at']);
            $currentRow++;
        }
    }
    
    // Auto-size all columns
    foreach (range('A', 'J') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }
    
    // Generate Excel file
    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    $writer->save('php://output');
    exit;
}

function exportPDF($availability, $consultations, $lawyer_name, $export_type) {
    require_once '../vendor/autoload.php';
    
    $filename = $export_type . '_export_' . date('Y-m-d_His') . '.pdf';
    
    // Create new Spreadsheet
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    
    // Set document properties
    $spreadsheet->getProperties()
        ->setCreator('MD Law Firm')
        ->setTitle(ucfirst($export_type) . ' Report')
        ->setSubject('Lawyer ' . ucfirst($export_type))
        ->setDescription(ucfirst($export_type) . ' export for ' . $lawyer_name);
    
    $currentRow = 1;
    
    // Export Availability
    if ($export_type === 'availability' || $export_type === 'both') {
        // Add header information
        $sheet->setCellValue('A' . $currentRow, 'Availability Schedule Report');
        $sheet->mergeCells('A' . $currentRow . ':E' . $currentRow);
        $sheet->getStyle('A' . $currentRow)->getFont()->setBold(true)->setSize(16);
        $sheet->getStyle('A' . $currentRow)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $currentRow++;
        
        $sheet->setCellValue('A' . $currentRow, 'Lawyer: ' . $lawyer_name);
        $sheet->mergeCells('A' . $currentRow . ':E' . $currentRow);
        $sheet->getStyle('A' . $currentRow)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $currentRow++;
        
        $sheet->setCellValue('A' . $currentRow, 'Generated: ' . date('F d, Y g:i A'));
        $sheet->mergeCells('A' . $currentRow . ':E' . $currentRow);
        $sheet->getStyle('A' . $currentRow)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $currentRow++;
        
        $sheet->setCellValue('A' . $currentRow, 'Total Records: ' . count($availability));
        $sheet->mergeCells('A' . $currentRow . ':E' . $currentRow);
        $sheet->getStyle('A' . $currentRow)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $currentRow += 2;
        
        // Add column headers
        $headerRow = $currentRow;
        $headers = ['Schedule Type', 'Day/Date', 'Time', 'Max Appointments', 'Status'];
        $column = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($column . $headerRow, $header);
            $column++;
        }
        
        // Style header row
        $sheet->getStyle('A' . $headerRow . ':E' . $headerRow)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'C5A253']],
            'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN]]
        ]);
        
        // Add data rows
        $currentRow++;
        foreach ($availability as $item) {
            $dayDate = $item['specific_date'] ?? ($item['weekday'] ?? 'N/A');
            $timeRange = date('g:i A', strtotime($item['start_time'])) . ' - ' . date('g:i A', strtotime($item['end_time']));
            $status = $item['la_is_active'] ? 'Active' : 'Inactive';
            $scheduleType = ucfirst(str_replace('_', ' ', $item['schedule_type']));
            
            $sheet->setCellValue('A' . $currentRow, $scheduleType);
            $sheet->setCellValue('B' . $currentRow, $dayDate);
            $sheet->setCellValue('C' . $currentRow, $timeRange);
            $sheet->setCellValue('D' . $currentRow, $item['max_appointments']);
            $sheet->setCellValue('E' . $currentRow, $status);
            
            // Alternate row colors
            if ($currentRow % 2 == 0) {
                $sheet->getStyle('A' . $currentRow . ':E' . $currentRow)->applyFromArray([
                    'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F9F9F9']]
                ]);
            }
            
            $currentRow++;
        }
        
        // Add borders to all data
        $sheet->getStyle('A' . $headerRow . ':E' . ($currentRow - 1))->applyFromArray([
            'borders' => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN, 'color' => ['rgb' => 'DDDDDD']]]
        ]);
        
        // Auto-size columns
        foreach (range('A', 'E') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
        
        if ($export_type === 'both') {
            $currentRow += 3;
        }
    }
    
    // Export Consultations
    if ($export_type === 'consultation' || $export_type === 'both') {
        // Add header information
        $sheet->setCellValue('A' . $currentRow, 'Consultations Report');
        $sheet->mergeCells('A' . $currentRow . ':G' . $currentRow);
        $sheet->getStyle('A' . $currentRow)->getFont()->setBold(true)->setSize(16);
        $sheet->getStyle('A' . $currentRow)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $currentRow++;
        
        $sheet->setCellValue('A' . $currentRow, 'Lawyer: ' . $lawyer_name);
        $sheet->mergeCells('A' . $currentRow . ':G' . $currentRow);
        $sheet->getStyle('A' . $currentRow)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $currentRow++;
        
        $sheet->setCellValue('A' . $currentRow, 'Generated: ' . date('F d, Y g:i A'));
        $sheet->mergeCells('A' . $currentRow . ':G' . $currentRow);
        $sheet->getStyle('A' . $currentRow)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $currentRow++;
        
        $sheet->setCellValue('A' . $currentRow, 'Total Records: ' . count($consultations));
        $sheet->mergeCells('A' . $currentRow . ':G' . $currentRow);
        $sheet->getStyle('A' . $currentRow)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $currentRow += 2;
        
        // Add column headers
        $headerRow = $currentRow;
        $headers = ['ID', 'Client Name', 'Email', 'Practice Area', 'Date', 'Time', 'Status'];
        $column = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($column . $headerRow, $header);
            $column++;
        }
        
        // Style header row
        $sheet->getStyle('A' . $headerRow . ':G' . $headerRow)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'C5A253']],
            'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN]]
        ]);
        
        // Add data rows
        $currentRow++;
        foreach ($consultations as $item) {
            $sheet->setCellValue('A' . $currentRow, '#' . $item['c_id']);
            $sheet->setCellValue('B' . $currentRow, $item['c_full_name']);
            $sheet->setCellValue('C' . $currentRow, $item['c_email']);
            $sheet->setCellValue('D' . $currentRow, $item['c_practice_area'] ?? 'N/A');
            $sheet->setCellValue('E' . $currentRow, $item['consultation_date'] ? date('M d, Y', strtotime($item['consultation_date'])) : 'N/A');
            $sheet->setCellValue('F' . $currentRow, $item['consultation_time'] ? date('g:i A', strtotime($item['consultation_time'])) : 'N/A');
            $sheet->setCellValue('G' . $currentRow, ucfirst($item['c_status']));
            
            // Alternate row colors
            if ($currentRow % 2 == 0) {
                $sheet->getStyle('A' . $currentRow . ':G' . $currentRow)->applyFromArray([
                    'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F9F9F9']]
                ]);
            }
            
            $currentRow++;
        }
        
        // Add borders to all data
        $sheet->getStyle('A' . $headerRow . ':G' . ($currentRow - 1))->applyFromArray([
            'borders' => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN, 'color' => ['rgb' => 'DDDDDD']]]
        ]);
        
        // Auto-size columns
        foreach (range('A', 'G') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
    }
    
    // Add footer
    $footerRow = $currentRow + 2;
    $maxCol = ($export_type === 'consultation' || $export_type === 'both') ? 'G' : 'E';
    $sheet->setCellValue('A' . $footerRow, 'MD Law Firm - Confidential Document');
    $sheet->mergeCells('A' . $footerRow . ':' . $maxCol . $footerRow);
    $sheet->getStyle('A' . $footerRow)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('A' . $footerRow)->getFont()->setSize(9)->setItalic(true);
    
    // Generate PDF
    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Pdf\Dompdf($spreadsheet);
    
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    $writer->save('php://output');
    exit;
}

// Get statistics for display
try {
    $pdo = getDBConnection();
    
    $stats_stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN schedule_type = 'weekly' THEN 1 ELSE 0 END) as weekly,
            SUM(CASE WHEN schedule_type = 'one_time' THEN 1 ELSE 0 END) as one_time,
            SUM(CASE WHEN la_is_active = 1 THEN 1 ELSE 0 END) as active,
            SUM(CASE WHEN la_is_active = 0 THEN 1 ELSE 0 END) as inactive
        FROM lawyer_availability
        WHERE lawyer_id = ?
    ");
    $stats_stmt->execute([$lawyer_id]);
    $stats = $stats_stmt->fetch();
} catch (Exception $e) {
    $stats = ['total' => 0, 'weekly' => 0, 'one_time' => 0, 'active' => 0, 'inactive' => 0];
}

$page_title = "Export Availability";
$active_page = "export";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Export Availability - <?php echo htmlspecialchars($lawyer_name); ?></title>
    <link rel="stylesheet" href="../src/lawyer/css/styles.css">
    <link rel="stylesheet" href="../src/lawyer/css/mobile-responsive.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .export-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            padding: 30px;
            margin-bottom: 30px;
        }
        
        .export-card h2 {
            font-size: 24px;
            color: var(--navy);
            margin: 0 0 20px 0;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .export-card h2 i {
            color: var(--gold);
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-box {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            border: 2px solid #dee2e6;
        }
        
        .stat-box.highlight {
            background: linear-gradient(135deg, #fff3cd, #ffeaa7);
            border-color: var(--gold);
        }
        
        .stat-number {
            font-size: 32px;
            font-weight: 700;
            color: var(--navy);
            margin-bottom: 8px;
        }
        
        .stat-label {
            font-size: 14px;
            color: var(--text-light);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            font-weight: 600;
            color: var(--navy);
            margin-bottom: 8px;
            font-size: 14px;
        }
        
        .form-group select,
        .form-group input {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 14px;
            font-family: var(--font-sans);
            transition: all 0.3s ease;
        }
        
        .form-group select:focus,
        .form-group input:focus {
            outline: none;
            border-color: var(--gold);
            box-shadow: 0 0 0 3px rgba(197, 162, 83, 0.1);
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .export-format-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .format-option {
            position: relative;
        }
        
        .format-option input[type="radio"] {
            position: absolute;
            opacity: 0;
        }
        
        .format-label {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 20px;
            border: 2px solid #e9ecef;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s ease;
            background: white;
        }
        
        .format-option input[type="radio"]:checked + .format-label {
            border-color: var(--gold);
            background: linear-gradient(135deg, #fff9e6, #fffbf0);
            box-shadow: 0 4px 12px rgba(197, 162, 83, 0.2);
        }
        
        .format-label:hover {
            border-color: var(--gold);
            transform: translateY(-2px);
        }
        
        .format-icon {
            font-size: 28px;
            color: var(--gold);
        }
        
        .format-info h4 {
            margin: 0 0 4px 0;
            font-size: 16px;
            color: var(--navy);
        }
        
        .format-info p {
            margin: 0;
            font-size: 12px;
            color: var(--text-light);
        }
        
        .export-btn {
            background: linear-gradient(135deg, var(--gold), #d4af37);
            color: white;
            border: none;
            padding: 16px 32px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        .export-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(197, 162, 83, 0.4);
        }
        
        .export-btn:active {
            transform: translateY(0);
        }
        
        .info-box {
            background: #e7f3ff;
            border-left: 4px solid #2196F3;
            padding: 16px;
            border-radius: 8px;
            margin-top: 20px;
        }
        
        .info-box p {
            margin: 0;
            color: #1976D2;
            font-size: 14px;
            line-height: 1.6;
        }
        
        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</head>
<body class="lawyer-page">
    <?php include 'partials/sidebar.php'; ?>
    
    <main class="lawyer-main-content">
        <div class="container">
            <?php if (isset($error_message)): ?>
                <div class="error-message" style="background: #f8d7da; color: #721c24; padding: 16px; border-radius: 8px; margin-bottom: 20px;">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>
            
            <!-- Statistics Card -->
            <div class="export-card">
                <h2><i class="fas fa-chart-bar"></i> Availability Statistics</h2>
                <div class="stats-grid">
                    <div class="stat-box highlight">
                        <div class="stat-number"><?php echo $stats['total']; ?></div>
                        <div class="stat-label">Total Schedules</div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-number"><?php echo $stats['weekly']; ?></div>
                        <div class="stat-label">Weekly</div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-number"><?php echo $stats['one_time']; ?></div>
                        <div class="stat-label">One-Time</div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-number"><?php echo $stats['active']; ?></div>
                        <div class="stat-label">Active</div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-number"><?php echo $stats['inactive']; ?></div>
                        <div class="stat-label">Inactive</div>
                    </div>
                </div>
            </div>
            
            <!-- Export Form Card -->
            <div class="export-card">
                <h2><i class="fas fa-file-export"></i> Export Availability Schedule</h2>
                
                <form method="POST" id="exportForm">
                    <!-- Export Type Selection -->
                    <div class="form-group">
                        <label>What to Export</label>
                        <div class="export-format-grid">
                            <div class="format-option">
                                <input type="radio" name="export_type" id="type_availability" value="availability" checked>
                                <label for="type_availability" class="format-label">
                                    <div class="format-icon"><i class="fas fa-calendar-alt"></i></div>
                                    <div class="format-info">
                                        <h4>Availability</h4>
                                        <p>Schedule only</p>
                                    </div>
                                </label>
                            </div>
                            <div class="format-option">
                                <input type="radio" name="export_type" id="type_consultation" value="consultation">
                                <label for="type_consultation" class="format-label">
                                    <div class="format-icon"><i class="fas fa-users"></i></div>
                                    <div class="format-info">
                                        <h4>Consultations</h4>
                                        <p>Client requests</p>
                                    </div>
                                </label>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Export Format -->
                    <div class="form-group">
                        <label>Export Format</label>
                        <div class="export-format-grid">
                            <div class="format-option">
                                <input type="radio" name="export_format" id="format_excel" value="excel" checked>
                                <label for="format_excel" class="format-label">
                                    <div class="format-icon"><i class="fas fa-file-excel"></i></div>
                                    <div class="format-info">
                                        <h4>Excel</h4>
                                        <p>Spreadsheet format</p>
                                    </div>
                                </label>
                            </div>
                            <div class="format-option">
                                <input type="radio" name="export_format" id="format_pdf" value="pdf">
                                <label for="format_pdf" class="format-label">
                                    <div class="format-icon"><i class="fas fa-file-pdf"></i></div>
                                    <div class="format-info">
                                        <h4>PDF</h4>
                                        <p>Professional reports</p>
                                    </div>
                                </label>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Filters (Only for Availability) -->
                    <div id="availability-filters">
                        <div class="form-group">
                            <label for="schedule_type">Filter by Schedule Type</label>
                            <select name="schedule_type" id="schedule_type">
                                <option value="all">All Types</option>
                                <option value="weekly">Weekly Recurring</option>
                                <option value="one_time">One-Time</option>
                            </select>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="date_from">From Date (One-Time only)</label>
                                <input type="date" name="date_from" id="date_from">
                            </div>
                            <div class="form-group">
                                <label for="date_to">To Date (One-Time only)</label>
                                <input type="date" name="date_to" id="date_to">
                            </div>
                        </div>
                    </div>
                    
                    <button type="submit" class="export-btn">
                        <i class="fas fa-download"></i>
                        Export Data
                    </button>
                </form>
                
                <div class="info-box">
                    <p><i class="fas fa-info-circle"></i> <strong>Note:</strong> Choose what data to export. Availability filters only apply when exporting availability schedules. Consultations include all your assigned consultation requests.</p>
                </div>
            </div>
        </div>
    </main>
    
    <script>
    document.getElementById('exportForm').addEventListener('submit', function(e) {
        const btn = this.querySelector('.export-btn');
        const originalHTML = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Exporting...';
        btn.disabled = true;
        
        // Reset button after 3 seconds (export should complete by then)
        setTimeout(function() {
            btn.innerHTML = originalHTML;
            btn.disabled = false;
        }, 3000);
    });
    
    // Enable/disable date filters based on schedule type
    document.getElementById('schedule_type').addEventListener('change', function() {
        const dateFrom = document.getElementById('date_from');
        const dateTo = document.getElementById('date_to');
        
        if (this.value === 'weekly') {
            dateFrom.disabled = true;
            dateTo.disabled = true;
            dateFrom.value = '';
            dateTo.value = '';
        } else {
            dateFrom.disabled = false;
            dateTo.disabled = false;
        }
    });
    
    // Show/hide availability filters based on export type checkboxes
    const availabilityCheckbox = document.getElementById('type_availability');
    const consultationCheckbox = document.getElementById('type_consultation');
    const availabilityFilters = document.getElementById('availability-filters');
    
    function updateFiltersVisibility() {
        if (availabilityCheckbox.checked) {
            availabilityFilters.style.display = 'block';
        } else {
            availabilityFilters.style.display = 'none';
        }
    }
    
    availabilityCheckbox.addEventListener('change', updateFiltersVisibility);
    consultationCheckbox.addEventListener('change', updateFiltersVisibility);
    
    // Style checkbox options on change
    const checkboxOptions = document.querySelectorAll('.checkbox-option');
    checkboxOptions.forEach(option => {
        const checkbox = option.querySelector('input[type="checkbox"]');
        
        function updateStyle() {
            if (checkbox.checked) {
                option.style.borderColor = 'var(--gold)';
                option.style.background = 'linear-gradient(135deg, #fff9e6, #fffbf0)';
                option.style.boxShadow = '0 4px 12px rgba(197, 162, 83, 0.2)';
            } else {
                option.style.borderColor = '#e9ecef';
                option.style.background = 'white';
                option.style.boxShadow = 'none';
            }
        }
        
        checkbox.addEventListener('change', updateStyle);
        updateStyle(); // Initialize
        
        // Make the whole div clickable
        option.addEventListener('click', function(e) {
            if (e.target !== checkbox) {
                checkbox.checked = !checkbox.checked;
                updateStyle();
                updateFiltersVisibility();
            }
        });
    });
    </script>
</body>
</html>
