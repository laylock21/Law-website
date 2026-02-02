<?php
/**
 * Admin - Export Consultations
 * Dedicated page for exporting consultation data with filters
 */

session_start();

// Unified authentication check
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

require_once '../config/database.php';

// Get statistics for display
try {
    $pdo = getDBConnection();
    
    $stats_stmt = $pdo->query("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN c_status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN c_status = 'confirmed' THEN 1 ELSE 0 END) as confirmed,
            SUM(CASE WHEN c_status = 'completed' THEN 1 ELSE 0 END) as completed,
            SUM(CASE WHEN c_status = 'cancelled' THEN 1 ELSE 0 END) as cancelled
        FROM consultations
    ");
    $stats = $stats_stmt->fetch();
} catch (Exception $e) {
    $stats = ['total' => 0, 'pending' => 0, 'confirmed' => 0, 'completed' => 0, 'cancelled' => 0];
}

$page_title = "Export Consultations";
$active_page = "export";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Export Consultations | MD Law Firm</title>
    <link rel="stylesheet" href="../src/admin/css/styles.css">
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
            color: #3a3a3a;
            margin: 0 0 20px 0;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .export-card h2 i {
            color: #C5A253;
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
            border-color: #C5A253;
        }
        
        .stat-number {
            font-size: 32px;
            font-weight: 700;
            color: #3a3a3a;
            margin-bottom: 8px;
        }
        
        .stat-label {
            font-size: 14px;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            font-weight: 600;
            color: #3a3a3a;
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
            transition: all 0.3s ease;
        }
        
        .form-group select:focus,
        .form-group input:focus {
            outline: none;
            border-color: #C5A253;
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
            border-color: #C5A253;
            background: linear-gradient(135deg, #fff9e6, #fffbf0);
            box-shadow: 0 4px 12px rgba(197, 162, 83, 0.2);
        }
        
        .format-label:hover {
            border-color: #C5A253;
            transform: translateY(-2px);
        }
        
        .format-icon {
            font-size: 28px;
            color: #C5A253;
        }
        
        .format-info h4 {
            margin: 0 0 4px 0;
            font-size: 16px;
            color: #3a3a3a;
        }
        
        .format-info p {
            margin: 0;
            font-size: 12px;
            color: #6c757d;
        }
        
        .export-btn {
            background: linear-gradient(135deg, #C5A253, #d4af37);
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
        
        .export-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
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
        
        .toast {
            position: fixed;
            top: 20px;
            right: 20px;
            background: #333;
            color: white;
            padding: 1rem 1.5rem;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
            display: flex;
            align-items: center;
            gap: 0.75rem;
            z-index: 9999;
            animation: slideIn 0.3s ease-out;
            min-width: 300px;
            max-width: 500px;
        }
        
        .toast.success {
            background: #28a745;
        }
        
        .toast.error {
            background: #dc3545;
        }
        
        .toast i {
            font-size: 1.25rem;
        }
        
        @keyframes slideIn {
            from {
                transform: translateX(400px);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        
        @keyframes slideOut {
            from {
                transform: translateX(0);
                opacity: 1;
            }
            to {
                transform: translateX(400px);
                opacity: 0;
            }
        }
        
        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: repeat(1, 1fr);
                justify-content: center;
            }
        }
    </style>
</head>
<body class="admin-page">
    <?php include 'partials/sidebar.php'; ?>
    
    <main class="admin-main-content">
        <div class="container">
            <!-- Statistics Card -->
            <div class="export-card">
                <h2><i class="fas fa-chart-bar"></i> Consultation Statistics</h2>
                <div class="stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 20px; margin-bottom: 30px;">
                    <div class="stat-box" style="background: white; border-radius: 12px; padding: 24px; text-align: center; box-shadow: 0 2px 8px rgba(0,0,0,0.1); border: 1px solid #e9ecef;">
                        <div class="stat-number" style="font-size: 48px; font-weight: 700; color: #3a3a3a; margin-bottom: 8px;"><?php echo $stats['total']; ?></div>
                        <div class="stat-label" style="color: #666; font-size: 14px; font-weight: 500; text-transform: uppercase; letter-spacing: 0.5px;">TOTAL</div>
                    </div>
                    <div class="stat-box" style="background: white; border-radius: 12px; padding: 24px; text-align: center; box-shadow: 0 2px 8px rgba(0,0,0,0.1); border: 1px solid #e9ecef;">
                        <div class="stat-number" style="font-size: 48px; font-weight: 700; color: #3a3a3a; margin-bottom: 8px;"><?php echo $stats['pending']; ?></div>
                        <div class="stat-label" style="color: #666; font-size: 14px; font-weight: 500; text-transform: uppercase; letter-spacing: 0.5px;">PENDING</div>
                    </div>
                    <div class="stat-box" style="background: white; border-radius: 12px; padding: 24px; text-align: center; box-shadow: 0 2px 8px rgba(0,0,0,0.1); border: 1px solid #e9ecef;">
                        <div class="stat-number" style="font-size: 48px; font-weight: 700; color: #3a3a3a; margin-bottom: 8px;"><?php echo $stats['confirmed']; ?></div>
                        <div class="stat-label" style="color: #666; font-size: 14px; font-weight: 500; text-transform: uppercase; letter-spacing: 0.5px;">CONFIRMED</div>
                    </div>
                    <div class="stat-box" style="background: white; border-radius: 12px; padding: 24px; text-align: center; box-shadow: 0 2px 8px rgba(0,0,0,0.1); border: 1px solid #e9ecef;">
                        <div class="stat-number" style="font-size: 48px; font-weight: 700; color: #3a3a3a; margin-bottom: 8px;"><?php echo $stats['completed']; ?></div>
                        <div class="stat-label" style="color: #666; font-size: 14px; font-weight: 500; text-transform: uppercase; letter-spacing: 0.5px;">COMPLETED</div>
                    </div>
                    <div class="stat-box" style="background: white; border-radius: 12px; padding: 24px; text-align: center; box-shadow: 0 2px 8px rgba(0,0,0,0.1); border: 1px solid #e9ecef;">
                        <div class="stat-number" style="font-size: 48px; font-weight: 700; color: #3a3a3a; margin-bottom: 8px;"><?php echo $stats['cancelled']; ?></div>
                        <div class="stat-label" style="color: #666; font-size: 14px; font-weight: 500; text-transform: uppercase; letter-spacing: 0.5px;">CANCELLED</div>
                    </div>
                </div>
            </div>
            
            <!-- Export Form Card -->
            <div class="export-card">
                <h2><i class="fas fa-file-export"></i> Export Consultations</h2>
                
                <form id="exportForm" onsubmit="handleExport(event)">
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
                        <label for="status_filter">Filter by Status</label>
                        <select name="status_filter" id="status_filter">
                            <option value="all">All Statuses</option>
                            <option value="pending">Pending</option>
                            <option value="confirmed">Confirmed</option>
                            <option value="completed">Completed</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="date_from">From Date</label>
                            <input type="date" name="date_from" id="date_from">
                        </div>
                        <div class="form-group">
                            <label for="date_to">To Date</label>
                            <input type="date" name="date_to" id="date_to">
                        </div>
                    </div>
                    
                    <button type="submit" class="export-btn" id="exportBtn">
                        <i class="fas fa-download"></i>
                        Export Data
                    </button>
                </form>
                
                <div class="info-box">
                    <p><i class="fas fa-info-circle"></i> <strong>Note:</strong> The export will include all consultations matching your filter criteria. Use the status and date filters to narrow down your export.</p>
                </div>
            </div>
        </div>
    </main>
    
    <script>
    function showToast(message, type = 'success', duration = 5000) {
        const existingToasts = document.querySelectorAll('.toast');
        existingToasts.forEach(toast => toast.remove());
        
        const toast = document.createElement('div');
        toast.className = `toast ${type}`;
        
        const icon = type === 'success' ? '<i class="fas fa-check-circle"></i>' : '<i class="fas fa-exclamation-circle"></i>';
        toast.innerHTML = `
            ${icon}
            <span>${message}</span>
        `;
        
        document.body.appendChild(toast);
        
        setTimeout(() => {
            toast.style.animation = 'slideOut 0.3s ease-out';
            setTimeout(() => toast.remove(), 300);
        }, duration);
    }
    
    function handleExport(event) {
        event.preventDefault();
        
        const format = document.querySelector('input[name="export_format"]:checked').value;
        const statusFilter = document.getElementById('status_filter').value;
        const dateFrom = document.getElementById('date_from').value;
        const dateTo = document.getElementById('date_to').value;
        
        // Validate date range
        if (dateFrom && dateTo && dateFrom > dateTo) {
            showToast('Invalid date range: "From" date must be before "To" date', 'error');
            return;
        }
        
        // Create form data
        const formData = new FormData();
        formData.append('format', format);
        
        if (statusFilter !== 'all') {
            formData.append('status_filter', statusFilter);
        }
        
        if (dateFrom) formData.append('date_from', dateFrom);
        if (dateTo) formData.append('date_to', dateTo);
        
        // Show loading state
        const exportBtn = document.getElementById('exportBtn');
        const originalText = exportBtn.innerHTML;
        exportBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Exporting...';
        exportBtn.disabled = true;
        
        // Submit export request
        fetch('../api/admin/export_consultations.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            if (!response.ok) {
                return response.json().then(data => {
                    throw new Error(data.message || 'Export failed');
                });
            }
            return response.blob();
        })
        .then(blob => {
            // Create download link
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `consultations_export_${new Date().getTime()}.${format}`;
            document.body.appendChild(a);
            a.click();
            window.URL.revokeObjectURL(url);
            document.body.removeChild(a);
            
            showToast('Export completed successfully!', 'success');
        })
        .catch(error => {
            showToast(error.message || 'Export failed', 'error');
        })
        .finally(() => {
            exportBtn.innerHTML = originalText;
            exportBtn.disabled = false;
        });
    }
    </script>
</body>
</html>
