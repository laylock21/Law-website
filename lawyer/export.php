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
    $schedule_type = $_POST['schedule_type'] ?? 'all';
    $status_filter = $_POST['status_filter'] ?? 'all';
    $date_from = $_POST['date_from'] ?? null;
    $date_to = $_POST['date_to'] ?? null;
    
    try {
        $pdo = getDBConnection();
        
        // Build query with filters
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
        
        if ($status_filter !== 'all') {
            $sql .= " AND la_is_active = ?";
            $params[] = ($status_filter === 'active') ? 1 : 0;
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
        
        // Export based on format
        if ($format === 'csv') {
            exportCSV($availability, $lawyer_name);
        } elseif ($format === 'pdf') {
            exportPDF($availability, $lawyer_name);
        }
        
    } catch (Exception $e) {
        $error_message = 'Export error: ' . $e->getMessage();
        error_log("Export error: " . $e->getMessage());
    }
}

function exportCSV($data, $lawyer_name) {
    $filename = 'availability_' . date('Y-m-d_His') . '.csv';
    
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
        'Schedule Type',
        'Weekday',
        'Specific Date',
        'Start Time',
        'End Time',
        'Max Appointments',
        'Status',
        'Created At',
        'Updated At'
    ]);
    
    // Data rows
    foreach ($data as $row) {
        fputcsv($output, [
            $row['la_id'],
            ucfirst(str_replace('_', ' ', $row['schedule_type'])),
            $row['weekday'] ?? 'N/A',
            $row['specific_date'] ?? 'N/A',
            date('g:i A', strtotime($row['start_time'])),
            date('g:i A', strtotime($row['end_time'])),
            $row['max_appointments'],
            $row['la_is_active'] ? 'Active' : 'Inactive',
            $row['created_at'],
            $row['updated_at'] ?? ''
        ]);
    }
    
    fclose($output);
    exit;
}

function exportPDF($data, $lawyer_name) {
    require_once '../vendor/autoload.php';
    
    $filename = 'availability_' . date('Y-m-d_His') . '.pdf';
    
    // Create new Spreadsheet
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    
    // Set document properties
    $spreadsheet->getProperties()
        ->setCreator('MD Law Firm')
        ->setTitle('Availability Schedule Report')
        ->setSubject('Lawyer Availability')
        ->setDescription('Availability schedule export for ' . $lawyer_name);
    
    // Add header information
    $sheet->setCellValue('A1', 'Availability Schedule Report');
    $sheet->mergeCells('A1:E1');
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
    $sheet->getStyle('A1')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    
    $sheet->setCellValue('A2', 'Lawyer: ' . $lawyer_name);
    $sheet->mergeCells('A2:E2');
    $sheet->getStyle('A2')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    
    $sheet->setCellValue('A3', 'Generated: ' . date('F d, Y g:i A'));
    $sheet->mergeCells('A3:E3');
    $sheet->getStyle('A3')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    
    $sheet->setCellValue('A4', 'Total Records: ' . count($data));
    $sheet->mergeCells('A4:E4');
    $sheet->getStyle('A4')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    
    // Add column headers
    $headerRow = 6;
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
    $row = $headerRow + 1;
    foreach ($data as $item) {
        $dayDate = $item['specific_date'] ?? ($item['weekday'] ?? 'N/A');
        $timeRange = date('g:i A', strtotime($item['start_time'])) . ' - ' . date('g:i A', strtotime($item['end_time']));
        $status = $item['la_is_active'] ? 'Active' : 'Inactive';
        $scheduleType = ucfirst(str_replace('_', ' ', $item['schedule_type']));
        
        $sheet->setCellValue('A' . $row, $scheduleType);
        $sheet->setCellValue('B' . $row, $dayDate);
        $sheet->setCellValue('C' . $row, $timeRange);
        $sheet->setCellValue('D' . $row, $item['max_appointments']);
        $sheet->setCellValue('E' . $row, $status);
        
        // Alternate row colors
        if ($row % 2 == 0) {
            $sheet->getStyle('A' . $row . ':E' . $row)->applyFromArray([
                'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F9F9F9']]
            ]);
        }
        
        $row++;
    }
    
    // Add borders to all data
    $sheet->getStyle('A' . $headerRow . ':E' . ($row - 1))->applyFromArray([
        'borders' => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN, 'color' => ['rgb' => 'DDDDDD']]]
    ]);
    
    // Auto-size columns
    foreach (range('A', 'E') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }
    
    // Add footer
    $footerRow = $row + 2;
    $sheet->setCellValue('A' . $footerRow, 'MD Law Firm - Confidential Document');
    $sheet->mergeCells('A' . $footerRow . ':E' . $footerRow);
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
                    <!-- Export Format -->
                    <div class="form-group">
                        <label>Export Format</label>
                        <div class="export-format-grid">
                            <div class="format-option">
                                <input type="radio" name="export_format" id="format_csv" value="csv" checked>
                                <label for="format_csv" class="format-label">
                                    <div class="format-icon"><i class="fas fa-file-csv"></i></div>
                                    <div class="format-info">
                                        <h4>CSV</h4>
                                        <p>Excel compatible</p>
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
                    
                    <!-- Filters -->
                    <div class="form-group">
                        <label for="schedule_type">Filter by Schedule Type</label>
                        <select name="schedule_type" id="schedule_type">
                            <option value="all">All Types</option>
                            <option value="weekly">Weekly Recurring</option>
                            <option value="one_time">One-Time</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="status_filter">Filter by Status</label>
                        <select name="status_filter" id="status_filter">
                            <option value="all">All Statuses</option>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
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
                    
                    <button type="submit" class="export-btn">
                        <i class="fas fa-download"></i>
                        Export Data
                    </button>
                </form>
                
                <div class="info-box">
                    <p><i class="fas fa-info-circle"></i> <strong>Note:</strong> The export will include all your availability schedules (weekly recurring and one-time). Use filters to narrow down your export. Date filters only apply to one-time schedules.</p>
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
    </script>
</body>
</html>
