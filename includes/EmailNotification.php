<?php
/**
 * Email Notification System
 * Ready for Gmail SMTP integration
 * 
 * TODO: Add Gmail SMTP credentials when ready
 */

// Include upload config for dynamic path functions
require_once __DIR__ . '/../config/upload_config.php';

// Include DOCX Generator
require_once __DIR__ . '/DocxGenerator.php';

class EmailNotification {
    
    private $pdo;
    
    // ============================================
    // CONFIGURATION SECTION
    // ============================================
    
    // STEP 1: Enable/Disable SMTP (set to false to queue only, true to send)
    private $smtp_enabled = false; // ENABLED - Ready to send emails!
    
    // STEP 1.5: Enable/Disable debug logging (set to false for production)
    private $debug_enabled = true; // Set to false for better performance
    
    // STEP 2: Fill in your Gmail credentials below
    private $smtp_config = [
        'host' => 'smtp.gmail.com',              // Already set (don't change)
        'port' => 587,                           // Already set (don't change)
        'username' => 'lawfirmemailling@gmail.com',    // REPLACE: Your Gmail address
        'password' => 'dbol uhpr bnyv drjy',     // ⬅️ REPLACE: Your 16-char app password
        'from_email' => 'lawfirmemailling@gmail.com',  // ⬅️ REPLACE: Same as username
        'from_name' => 'MD Law Firm'             // ⬅️ OPTIONAL: Change sender name
    ];
    // ============================================
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Queue a notification for sending
     */
    public function queueNotification($user_id, $email, $subject, $message, $type = 'other', $consultation_id = null) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO notification_queue (user_id, email, subject, message, notification_type, consultation_id, status)
                VALUES (?, ?, ?, ?, ?, ?, 'pending')
            ");
            
            $stmt->execute([$user_id, $email, $subject, $message, $type, $consultation_id]);
            
            return $this->pdo->lastInsertId();
            
        } catch (Exception $e) {
            error_log("Failed to queue notification: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send appointment cancellation notification
     */
    public function notifyAppointmentCancelled($appointment_id, $reason = 'Unexpected leave') {
        try {
            // Get appointment details with lawyer info in single query (OPTIMIZED)
            $stmt = $this->pdo->prepare("
                SELECT 
                    c.c_id as id,
                    c.c_consultation_date as consultation_date,
                    c.c_consultation_time as consultation_time,
                    c.c_email as email,
                    c.c_full_name as full_name,
                    c.lawyer_id,
                    lp.lp_fullname as lawyer_fullname
                FROM consultations c
                JOIN lawyer_profile lp ON c.lawyer_id = lp.lawyer_id
                WHERE c.c_id = ?
            ");
            
            $stmt->execute([$appointment_id]);
            $appointment = $stmt->fetch();
            
            if (!$appointment) {
                return false;
            }
            
            // Create email content
            $subject = "Appointment Cancellation Notice";
            $message = $this->getAppointmentCancellationTemplate(
                $appointment['full_name'],
                $appointment['lawyer_fullname'] ?: 'Available Lawyer',
                $appointment['consultation_date'],
                $appointment['consultation_time'] ?: '14:00:00', // Use actual time or fallback
                $reason
            );
            
            // Use lawyer_id from the appointment data (no additional query needed)
            return $this->queueNotification(
                $appointment['lawyer_id'], // Using lawyer's user ID from existing data
                $appointment['email'],
                $subject,
                $message,
                'appointment_cancelled',
                $appointment_id
            );
            
        } catch (Exception $e) {
            error_log("Failed to notify appointment cancellation: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get affected appointments for a blocked date
     */
    public function getAffectedAppointments($lawyer_id, $blocked_date) {
        try {
            if ($this->debug_enabled) {
                error_log("DEBUG: getAffectedAppointments called with lawyer_id=$lawyer_id, blocked_date=$blocked_date");
            }
            
            // First, let's see ALL appointments for this lawyer on this date (regardless of status)
            if ($this->debug_enabled) {
                $debug_stmt = $this->pdo->prepare("
                    SELECT c_id as id, c_consultation_date as consultation_date, c_status as status, c_full_name as full_name
                    FROM consultations 
                    WHERE lawyer_id = ? AND c_consultation_date = ?
                ");
                $debug_stmt->execute([$lawyer_id, $blocked_date]);
                $all_appointments = $debug_stmt->fetchAll();
                
                error_log("DEBUG: Found " . count($all_appointments) . " total appointments for lawyer $lawyer_id on $blocked_date");
                foreach ($all_appointments as $apt) {
                    error_log("DEBUG: - Appointment ID {$apt['id']}: {$apt['full_name']}, status: {$apt['status']}");
                }
            }
            
            // Now run the original query
            $stmt = $this->pdo->prepare("
                SELECT 
                    c.c_id as id,
                    c.c_consultation_date as consultation_date,
                    c.c_email as email,
                    c.c_full_name as full_name
                FROM consultations c
                WHERE c.lawyer_id = ?
                AND c.c_consultation_date = ?
                AND c.c_status IN ('pending', 'confirmed')
            ");
            
            $stmt->execute([$lawyer_id, $blocked_date]);
            $affected = $stmt->fetchAll();
            
            if ($this->debug_enabled) {
                error_log("DEBUG: Found " . count($affected) . " affected appointments (pending/confirmed only)");
                foreach ($affected as $apt) {
                    error_log("DEBUG: - Affected appointment ID {$apt['id']}: {$apt['full_name']}");
                }
            }
            
            return $affected;
            
        } catch (Exception $e) {
            error_log("Failed to get affected appointments: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Send appointment confirmation notification
     */
    public function notifyAppointmentConfirmed($appointment_id) {
        try {
            // Get appointment details with lawyer info in single query (LEFT JOIN for NULL lawyer_id)
            $stmt = $this->pdo->prepare("
                SELECT 
                    c.c_id as id,
                    c.c_consultation_date as consultation_date,
                    c.c_consultation_time as consultation_time,
                    c.c_email as email,
                    c.c_full_name as full_name,
                    c.lawyer_id,
                    c.c_practice_area as practice_area,
                    COALESCE(lp.lp_fullname, 'Available Lawyer') as lawyer_fullname
                FROM consultations c
                LEFT JOIN lawyer_profile lp ON c.lawyer_id = lp.lawyer_id
                WHERE c.c_id = ?
            ");
            
            $stmt->execute([$appointment_id]);
            $appointment = $stmt->fetch();
            
            if (!$appointment) {
                return false;
            }
            
            // If lawyer_id is NULL, we can't queue (foreign key constraint)
            // Skip email for appointments without assigned lawyer
            if (!$appointment['lawyer_id']) {
                error_log("Cannot send confirmation email: Appointment $appointment_id has no assigned lawyer");
                return false;
            }
            
            // Prevent duplicate confirmation emails (check if already sent in last 5 minutes)
            $duplicate_check = $this->pdo->prepare("
                SELECT COUNT(*) FROM notification_queue
                WHERE email = ?
                AND notification_type = 'appointment_confirmed'
                AND subject LIKE ?
                AND created_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
            ");
            $duplicate_check->execute([
                $appointment['email'], 
                '%' . $appointment['practice_area'] . '%'
            ]);
            
            if ($duplicate_check->fetchColumn() > 0) {
                error_log("Duplicate confirmation email prevented for appointment $appointment_id");
                return false; // Already sent recently
            }
            
            // Create email content
            $subject = "Appointment Confirmed - " . $appointment['practice_area'];
            $message = $this->getAppointmentConfirmationTemplate(
                $appointment['full_name'],
                $appointment['lawyer_fullname'] ?: 'Available Lawyer',
                $appointment['consultation_date'],
                $appointment['consultation_time'] ?: '14:00:00',
                $appointment['practice_area']
            );
            
            // Use lawyer_id from the appointment data and include consultation_id
            return $this->queueNotification(
                $appointment['lawyer_id'],
                $appointment['email'],
                $subject,
                $message,
                'confirmation',
                $appointment_id
            );
            
        } catch (Exception $e) {
            error_log("Failed to notify appointment confirmation: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send appointment completion notification
     */
    public function notifyAppointmentCompleted($appointment_id) {
        try {
            // Get appointment details with lawyer info in single query
            $stmt = $this->pdo->prepare("
                SELECT 
                    c.c_id as id,
                    c.c_consultation_date as consultation_date,
                    c.c_consultation_time as consultation_time,
                    c.c_email as email,
                    c.c_full_name as full_name,
                    c.lawyer_id,
                    c.c_practice_area as practice_area,
                    COALESCE(lp.lp_fullname, 'Available Lawyer') as lawyer_fullname
                FROM consultations c
                LEFT JOIN lawyer_profile lp ON c.lawyer_id = lp.lawyer_id
                WHERE c.c_id = ?
            ");
            
            $stmt->execute([$appointment_id]);
            $appointment = $stmt->fetch();
            
            if (!$appointment) {
                return false;
            }
            
            // If lawyer_id is NULL, try to get it again (might have been assigned during completion)
            if (!$appointment['lawyer_id']) {
                // Re-fetch to get updated lawyer assignment
                $stmt->execute([$appointment_id]);
                $appointment = $stmt->fetch();
                
                if (!$appointment || !$appointment['lawyer_id']) {
                    error_log("Cannot send completion email: Appointment $appointment_id has no assigned lawyer");
                    return false;
                }
            }
            
            // Create email content
            $subject = "Consultation Completed - Thank You!";
            $message = $this->getAppointmentCompletionTemplate(
                $appointment['full_name'],
                $appointment['lawyer_fullname'] ?: 'Available Lawyer',
                $appointment['consultation_date'],
                $appointment['consultation_time'] ?: '14:00:00',
                $appointment['practice_area']
            );
            
            // Use lawyer_id from the appointment data and include consultation_id
            return $this->queueNotification(
                $appointment['lawyer_id'],
                $appointment['email'],
                $subject,
                $message,
                'appointment_completed',
                $appointment_id
            );
            
        } catch (Exception $e) {
            error_log("Failed to notify appointment completion: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Email template for appointment confirmation
     */
    private function getAppointmentConfirmationTemplate($client_name, $lawyer_name, $date, $time, $practice_area) {
        $formatted_date = date('l, F j, Y', strtotime($date));
        $formatted_time = date('g:i A', strtotime($time));
        
        return "
<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #1a2332; color: white; padding: 20px; text-align: center; }
        .content { background: #f8f9fa; padding: 30px; border-radius: 8px; margin: 20px 0; }
        .success { background: #d4edda; border-left: 4px solid #28a745; padding: 15px; margin: 20px 0; }
        .details { background: white; padding: 15px; border-radius: 5px; margin: 15px 0; }
        .footer { text-align: center; color: #6c757d; font-size: 12px; margin-top: 30px; }
        .btn { display: inline-block; padding: 12px 24px; background: #28a745; color: white; text-decoration: none; border-radius: 5px; margin: 10px 0; }
        .highlight { color: #c5a253; font-weight: bold; }
    </style>
</head>
<body>
    <div class='container'>
        <div class='header'>
            <h2>✅ Appointment Confirmed</h2>
        </div>
        
        <div class='content'>
            <p>Dear {$client_name},</p>
            
            <div class='success'>
                <strong>✅ Great News!</strong> Your consultation appointment has been confirmed.
            </div>
            
            <div class='details'>
                <h3>Appointment Details:</h3>
                <p><strong>Lawyer:</strong> Atty. {$lawyer_name}</p>
                <p><strong>Practice Area:</strong> {$practice_area}</p>
                <p><strong>Date:</strong> <span class='highlight'>{$formatted_date}</span></p>
                <p><strong>Time:</strong> <span class='highlight'>{$formatted_time}</span></p>
            </div>
            
            <p>We're looking forward to meeting with you. Please arrive 10 minutes early to complete any necessary paperwork.</p>
            
            <p><strong>What to Bring:</strong></p>
            <ul>
                <li>Valid government-issued ID</li>
                <li>Any relevant documents related to your case</li>
                <li>List of questions or concerns you'd like to discuss</li>
            </ul>
            
            <p><strong>Important Reminders:</strong></p>
            <ul>
                <li>If you need to reschedule, please contact us at least 24 hours in advance</li>
                <li>Our office is located at [Office Law Firm]</li>
                <li>Free parking is available for clients</li>
            </ul>
            
            <p>If you have any questions before your appointment, please don't hesitate to contact us.</p>
            
            <p>Best regards,<br>
            <strong>MD Law Firm</strong><br>
            Your Trusted Legal Partner</p>
        </div>
        
        <div class='footer'>
            <p>This is an automated confirmation email. Please do not reply to this message.</p>
            <p>&copy; 2025 MD Law Firm. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
        ";
    }
    
    /**
     * Email template for appointment cancellation
     */
    private function getAppointmentCancellationTemplate($client_name, $lawyer_name, $date, $time, $reason) {
        $formatted_date = date('l, F j, Y', strtotime($date));
        $formatted_time = date('g:i A', strtotime($time));
        
        return "
<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #1a2332; color: white; padding: 20px; text-align: center; }
        .content { background: #f8f9fa; padding: 30px; border-radius: 8px; margin: 20px 0; }
        .alert { background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0; }
        .details { background: white; padding: 15px; border-radius: 5px; margin: 15px 0; }
        .footer { text-align: center; color: #6c757d; font-size: 12px; margin-top: 30px; }
        .btn { display: inline-block; padding: 12px 24px; background: #c5a253; color: white; text-decoration: none; border-radius: 5px; margin: 10px 0; }
    </style>
</head>
<body>
    <div class='container'>
        <div class='header'>
            <h2>Appointment Cancellation Notice</h2>
        </div>
        
        <div class='content'>
            <p>Dear {$client_name},</p>
            
            <div class='alert'>
                <strong>⚠️ Important Notice:</strong> Your scheduled appointment has been cancelled due to {$reason}.
            </div>
            
            <div class='details'>
                <h3>Cancelled Appointment Details:</h3>
                <p><strong>Lawyer:</strong> {$lawyer_name}</p>
                <p><strong>Date:</strong> {$formatted_date}</p>
                <p><strong>Time:</strong> {$formatted_time}</p>
            </div>
            
            <p>We sincerely apologize for any inconvenience this may cause. We understand this is unexpected and we're committed to serving you at the earliest available time.</p>
            
            <p><strong>Next Steps:</strong></p>
            <ul>
                <li>Please visit our website to reschedule your appointment</li>
                <li>Choose a new available date that works for you</li>
                <li>Contact us if you have any questions or concerns</li>
            </ul>
            
            <div style='text-align: center;'>
                <a href='http://localhost" . getWebBasePath() . "/index.html' class='btn'>Reschedule Appointment</a>
            </div>
        </div>
        
        <div class='footer'>
            <p>This is an automated notification from the Law Firm Consultation System.</p>
            <p>If you have any questions, please contact us at info@lawfirm.com</p>
        </div>
    </div>
</body>
</html>
        ";
    }
    
    /**
     * Email template for appointment completion
     */
    private function getAppointmentCompletionTemplate($client_name, $lawyer_name, $date, $time, $practice_area) {
        $formatted_date = date('l, F j, Y', strtotime($date));
        $formatted_time = date('g:i A', strtotime($time));
        
        return "
<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #1a2332; color: white; padding: 20px; text-align: center; }
        .content { background: #f8f9fa; padding: 30px; border-radius: 8px; margin: 20px 0; }
        .success { background: #d4edda; border-left: 4px solid #28a745; padding: 15px; margin: 20px 0; }
        .details { background: white; padding: 15px; border-radius: 5px; margin: 15px 0; }
        .footer { text-align: center; color: #6c757d; font-size: 12px; margin-top: 30px; }
        .btn { display: inline-block; padding: 12px 24px; background: #c5a253; color: white; text-decoration: none; border-radius: 5px; margin: 10px 0; }
        .highlight { color: #c5a253; font-weight: bold; }
        .feedback { background: #e7f3ff; border-left: 4px solid #007bff; padding: 15px; margin: 20px 0; }
    </style>
</head>
<body>
    <div class='container'>
        <div class='header'>
            <h1>Consultation Completed</h1>
        </div>
        
        <div class='content'>
            <div class='success'>
                <h2>✅ Thank You for Choosing MD Law Firm!</h2>
                <p>Dear <strong>{$client_name}</strong>,</p>
                <p>We hope your consultation with our legal team was helpful and informative. Your appointment has been successfully completed.</p>
            </div>
            
            <div class='details'>
                <h3>Consultation Summary</h3>
                <p><strong>Lawyer:</strong> <span class='highlight'>{$lawyer_name}</span></p>
                <p><strong>Practice Area:</strong> <span class='highlight'>{$practice_area}</span></p>
                <p><strong>Date:</strong> <span class='highlight'>{$formatted_date}</span></p>
                <p><strong>Time:</strong> <span class='highlight'>{$formatted_time}</span></p>
            </div>
            
            <div class='feedback'>
                <h3>📝 We Value Your Feedback</h3>
                <p>Your experience matters to us. We would appreciate if you could take a moment to share your feedback about today's consultation.</p>
            </div>
            
            <p><strong>Next Steps:</strong></p>
            <ul>
                <li>Review any documents or advice provided during the consultation</li>
                <li>Keep this email for your records</li>
                <li>Contact us if you have any follow-up questions</li>
                <li>Consider scheduling additional consultations if needed</li>
            </ul>
            
            <p><strong>Follow-Up Services:</strong></p>
            <ul>
                <li>Document preparation and review</li>
                <li>Ongoing legal representation</li>
                <li>Additional consultations in related practice areas</li>
                <li>Legal document templates and resources</li>
            </ul>
            
            <div style='text-align: center;'>
                <a href='http://localhost" . getWebBasePath() . "/index.html' class='btn'>Schedule Another Consultation</a>
            </div>
            
            <p>Thank you for trusting MD Law Firm with your legal needs. We look forward to serving you again in the future.</p>
        </div>
        
        <div class='footer'>
            <p>This is an automated notification from the Law Firm Consultation System.</p>
            <p>For questions or follow-up services, contact us at info@lawfirm.com or (555) 123-4567</p>
            <p>&copy; 2025 MD Law Firm. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
        ";
    }
    
    /**
     * Send all pending notifications
     * TODO: Implement actual SMTP sending when Gmail credentials are added
     */
    public function processPendingNotifications() {
        if (!$this->smtp_enabled) {
            // SMTP not configured yet, notifications stay in queue
            return ['status' => 'waiting', 'message' => 'SMTP not configured'];
        }
        
        try {
            // Get pending notifications
            $stmt = $this->pdo->prepare("
                SELECT * FROM notification_queue 
                WHERE status = 'pending' 
                AND attempts < 3
                ORDER BY created_at ASC
                LIMIT 10
            ");
            
            $stmt->execute();
            $notifications = $stmt->fetchAll();
            
            $sent = 0;
            $failed = 0;
            $pending = count($notifications);
            
            // Load PHPMailer once outside the loop
            require_once __DIR__ . '/../vendor/autoload.php';
            
            foreach ($notifications as $notification) {
                $mail = new PHPMailer\PHPMailer\PHPMailer(true);
                
                try {
                    // SMTP Configuration with timeout protection
                    $mail->isSMTP();
                    $mail->Host = $this->smtp_config['host'];
                    $mail->SMTPAuth = true;
                    $mail->Username = $this->smtp_config['username'];
                    $mail->Password = $this->smtp_config['password'];
                    $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                    $mail->Port = $this->smtp_config['port'];
                    
                    // Add timeout settings to prevent hanging
                    $mail->Timeout = 10; // 10 second timeout
                    $mail->SMTPOptions = array(
                        'ssl' => array(
                            'verify_peer' => false,
                            'verify_peer_name' => false,
                            'allow_self_signed' => true
                        )
                    );
                    
                    // Validate email address
                    if (!filter_var($notification['email'], FILTER_VALIDATE_EMAIL)) {
                        throw new Exception("Invalid email address: " . $notification['email']);
                    }
                    
                    // Email Content
                    $mail->setFrom($this->smtp_config['from_email'], $this->smtp_config['from_name']);
                    $mail->addAddress($notification['email']);
                    $mail->isHTML(true);
                    $mail->Subject = $notification['subject'];
                    $mail->Body = $notification['message'];
                    $mail->CharSet = 'UTF-8';
                    
                    // Generate and attach DOCX if it's a consultation notification
                    if (in_array($notification['notification_type'], ['confirmation', 'appointment_cancelled', 'appointment_completed'])) {
                        $this->attachConsultationDocument($mail, $notification);
                    }
                    
                    // Send email
                    $mail->send();
                    
                    // Clean up DOCX files after successful send
                    $this->cleanupDocxFiles($mail);
                    
                    // Clear addresses for next iteration
                    $mail->clearAddresses();
                    $mail->clearAttachments();
                    
                    // Mark as sent
                    $update = $this->pdo->prepare("
                        UPDATE notification_queue 
                        SET status = 'sent', sent_at = NOW() 
                        WHERE id = ?
                    ");
                    $update->execute([$notification['id']]);
                    $sent++;
                    
                } catch (Exception $e) {
                    // Clean up DOCX files even on failed send
                    $this->cleanupDocxFiles($mail);
                    
                    // Mark as failed
                    $update = $this->pdo->prepare("
                        UPDATE notification_queue 
                        SET status = 'failed', attempts = attempts + 1, error_message = ? 
                        WHERE id = ?
                    ");
                    $update->execute([$e->getMessage(), $notification['id']]);
                    $failed++;
                }
            }
            
            // Periodic cleanup of old DOCX files (safety measure)
            $this->cleanupOldDocxFiles();
            
            return [
                'status' => 'processed',
                'sent' => $sent,
                'failed' => $failed,
                'pending' => $pending
            ];
            
        } catch (Exception $e) {
            error_log("Failed to process notifications: " . $e->getMessage());
            return ['status' => 'error', 'message' => $e->getMessage()];
        };
    }
    
    /**
     * Generate and attach DOCX document to email
     */
    private function attachConsultationDocument($mail, $notification) {
        $docx_path = null;
        try {
            // Parse consultation data from notification
            $consultation_data = $this->parseConsultationData($notification);
            
            // Determine template type
            if ($notification['notification_type'] === 'confirmation') {
                $template_type = 'confirmation';
            } elseif ($notification['notification_type'] === 'appointment_completed') {
                $template_type = 'completion';
            } else {
                $template_type = 'cancellation';
            }
            
            // Generate DOCX
            $docxGenerator = new DocxGenerator();
            $docx_path = $docxGenerator->generateConsultationDocument($consultation_data, $template_type);
            
            // Attach to email
            if (file_exists($docx_path)) {
                $filename = basename($docx_path);
                $mail->addAttachment($docx_path, $filename);
                
                // Store path for cleanup after email is sent
                if (!isset($mail->docx_cleanup_files)) {
                    $mail->docx_cleanup_files = [];
                }
                $mail->docx_cleanup_files[] = $docx_path;
                
                // Log successful attachment (production logging)
                if ($this->debug_enabled) {
                    error_log("DOCX attachment added: $filename");
                }
            }
            
        } catch (Exception $e) {
            // Log error but don't fail the email
            error_log("Failed to generate DOCX attachment: " . $e->getMessage());
            
            // Clean up failed DOCX file
            if ($docx_path && file_exists($docx_path)) {
                unlink($docx_path);
            }
        }
    }
    
    /**
     * Parse consultation data from notification for DOCX generation
     */
    private function parseConsultationData($notification) {
        // Get consultation ID from notification record (now stored directly)
        $consultation_id = $notification['consultation_id'] ?? null;
        
        // Fallback: try to extract from subject/message if not stored
        if (!$consultation_id) {
            preg_match('/consultation.*?(\d+)/i', $notification['subject'] . ' ' . $notification['message'], $matches);
            $consultation_id = $matches[1] ?? null;
        }
        
        // Default data structure
        $data = [
            'client_name' => 'Valued Client',
            'lawyer_name' => 'Legal Team',
            'practice_area' => 'Legal Consultation',
            'formatted_date' => date('l, F j, Y'),
            'formatted_time' => '2:00 PM',
            'reason' => 'scheduling conflicts'
        ];
        
        // Try to get actual consultation data if ID is available
        if ($consultation_id) {
            try {
                $stmt = $this->pdo->prepare("
                    SELECT 
                        c.c_full_name as full_name,
                        c.c_practice_area as practice_area,
                        c.c_consultation_date as consultation_date,
                        c.c_consultation_time as consultation_time,
                        lp.lp_fullname as lawyer_name
                    FROM consultations c
                    LEFT JOIN lawyer_profile lp ON c.lawyer_id = lp.lawyer_id
                    WHERE c.c_id = ?
                ");
                $stmt->execute([$consultation_id]);
                $consultation = $stmt->fetch();
                
                if ($consultation) {
                    $data['client_name'] = $consultation['full_name'] ?: $data['client_name'];
                    $data['lawyer_name'] = $consultation['lawyer_name'] ?: $data['lawyer_name'];
                    $data['practice_area'] = $consultation['practice_area'] ?: $data['practice_area'];
                    
                    if ($consultation['consultation_date']) {
                        $data['formatted_date'] = date('l, F j, Y', strtotime($consultation['consultation_date']));
                    }
                    
                    if ($consultation['consultation_time']) {
                        $data['formatted_time'] = date('g:i A', strtotime($consultation['consultation_time']));
                    }
                }
            } catch (Exception $e) {
                error_log("Failed to fetch consultation data for DOCX: " . $e->getMessage());
            }
        }
        
        return $data;
    }
    
    /**
     * Clean up DOCX files after email is sent
     */
    private function cleanupDocxFiles($mail) {
        if (isset($mail->docx_cleanup_files) && is_array($mail->docx_cleanup_files)) {
            foreach ($mail->docx_cleanup_files as $file_path) {
                if (file_exists($file_path)) {
                    try {
                        unlink($file_path);
                        if ($this->debug_enabled) {
                            error_log("DOCX file cleaned up: " . basename($file_path));
                        }
                    } catch (Exception $e) {
                        error_log("Failed to cleanup DOCX file: " . $file_path . " - " . $e->getMessage());
                    }
                }
            }
            // Clear the cleanup array
            $mail->docx_cleanup_files = [];
        }
    }
    
    /**
     * Clean up old DOCX files (older than 1 hour) as a safety measure
     * Call this periodically to prevent file accumulation
     */
    public function cleanupOldDocxFiles() {
        $upload_dir = __DIR__ . '/../uploads/generated_docs/';
        
        if (!is_dir($upload_dir)) {
            return;
        }
        
        $files = glob($upload_dir . '*.{docx,rtf}', GLOB_BRACE);
        $cleaned_count = 0;
        
        foreach ($files as $file) {
            // Delete files older than 1 hour
            if (filemtime($file) < (time() - 3600)) {
                try {
                    unlink($file);
                    $cleaned_count++;
                } catch (Exception $e) {
                    error_log("Failed to cleanup old DOCX file: " . $file . " - " . $e->getMessage());
                }
            }
        }
        
        if ($cleaned_count > 0 && $this->debug_enabled) {
            error_log("Cleaned up $cleaned_count old DOCX files");
        }
    }
    
    /**
     * Notify lawyer about new consultation request
     */
    public function notifyLawyerNewConsultation($consultation_id) {
        try {
            // Get consultation details with lawyer info
            $stmt = $this->pdo->prepare("
                SELECT 
                    c.c_id as id,
                    c.c_full_name as full_name,
                    c.c_email as client_email,
                    c.c_phone as phone,
                    c.c_practice_area as practice_area,
                    c.c_case_description as case_description,
                    c.c_selected_lawyer as selected_lawyer,
                    c.lawyer_id,
                    c.c_consultation_date as consultation_date,
                    c.c_consultation_time as consultation_time,
                    c.created_at,
                    u.email as lawyer_email,
                    lp.lp_fullname as lawyer_fullname
                FROM consultations c
                LEFT JOIN users u ON c.lawyer_id = u.user_id
                LEFT JOIN lawyer_profile lp ON c.lawyer_id = lp.lawyer_id
                WHERE c.c_id = ?
            ");
            
            $stmt->execute([$consultation_id]);
            $consultation = $stmt->fetch();
            
            if (!$consultation) {
                error_log("Consultation not found: $consultation_id");
                return false;
            }
            
            // If no specific lawyer assigned, notify all lawyers in the practice area
            if (!$consultation['lawyer_id'] || !$consultation['lawyer_email']) {
                return $this->notifyAllLawyersNewConsultation($consultation);
            }
            
            // Prevent duplicate notifications (check if already sent in last 10 minutes)
            $duplicate_check = $this->pdo->prepare("
                SELECT COUNT(*) FROM notification_queue
                WHERE email = ?
                AND notification_type = 'new_consultation'
                AND consultation_id = ?
                AND created_at >= DATE_SUB(NOW(), INTERVAL 10 MINUTE)
            ");
            $duplicate_check->execute([
                $consultation['lawyer_email'], 
                $consultation_id
            ]);
            
            if ($duplicate_check->fetchColumn() > 0) {
                error_log("Duplicate new consultation email prevented for consultation $consultation_id");
                return false;
            }
            
            // Create email content
            $subject = "New Consultation Request - " . $consultation['practice_area'];
            $message = $this->getNewConsultationTemplate($consultation);
            
            // Queue notification to the assigned lawyer
            return $this->queueNotification(
                $consultation['lawyer_id'],
                $consultation['lawyer_email'],
                $subject,
                $message,
                'new_consultation',
                $consultation_id
            );
            
        } catch (Exception $e) {
            error_log("Failed to notify lawyer about new consultation: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Notify all active lawyers about new consultation when no specific lawyer assigned
     */
    private function notifyAllLawyersNewConsultation($consultation) {
        try {
            // Get all active lawyers who specialize in this practice area
            $stmt = $this->pdo->prepare("
                SELECT DISTINCT u.user_id as id, u.email, lp.lp_fullname as fullname
                FROM users u
                LEFT JOIN lawyer_profile lp ON u.user_id = lp.lawyer_id
                LEFT JOIN lawyer_specializations ls ON u.user_id = ls.lawyer_id
                LEFT JOIN practice_areas pa ON ls.pa_id = pa.pa_id
                WHERE u.role = 'lawyer' 
                AND u.is_active = 1
                AND (pa.area_name = ? OR ls.pa_id IS NULL)
            ");
            
            $stmt->execute([$consultation['practice_area']]);
            $lawyers = $stmt->fetchAll();
            
            if (empty($lawyers)) {
                // If no specialized lawyers found, get all active lawyers
                $stmt = $this->pdo->prepare("
                    SELECT u.user_id as id, u.email, lp.lp_fullname as fullname
                    FROM users u
                    LEFT JOIN lawyer_profile lp ON u.user_id = lp.lawyer_id
                    WHERE u.role = 'lawyer' AND u.is_active = 1
                ");
                $stmt->execute();
                $lawyers = $stmt->fetchAll();
            }
            
            $success_count = 0;
            foreach ($lawyers as $lawyer) {
                // Check for duplicates for this lawyer
                $duplicate_check = $this->pdo->prepare("
                    SELECT COUNT(*) FROM notification_queue
                    WHERE email = ?
                    AND notification_type = 'new_consultation'
                    AND consultation_id = ?
                    AND created_at >= DATE_SUB(NOW(), INTERVAL 10 MINUTE)
                ");
                $duplicate_check->execute([
                    $lawyer['email'], 
                    $consultation['id']
                ]);
                
                if ($duplicate_check->fetchColumn() > 0) {
                    continue; // Skip duplicate
                }
                
                $subject = "New Consultation Request - " . $consultation['practice_area'];
                $message = $this->getNewConsultationTemplate($consultation, $lawyer);
                
                if ($this->queueNotification(
                    $lawyer['id'],
                    $lawyer['email'],
                    $subject,
                    $message,
                    'new_consultation',
                    $consultation['id']
                )) {
                    $success_count++;
                }
            }
            
            error_log("Notified $success_count lawyers about new consultation {$consultation['id']}");
            return $success_count > 0;
            
        } catch (Exception $e) {
            error_log("Failed to notify all lawyers about new consultation: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get email template for new consultation notification
     */
    private function getNewConsultationTemplate($consultation, $lawyer = null) {
        $lawyer_name = $lawyer ? 
            $lawyer['fullname'] : 
            ($consultation['lawyer_fullname'] ?: 'Available Lawyer');
            
        $consultation_date = $consultation['consultation_date'] ? 
            date('F j, Y', strtotime($consultation['consultation_date'])) : 'Not specified';
            
        $consultation_time = $consultation['consultation_time'] ? 
            date('g:i A', strtotime($consultation['consultation_time'])) : 'Not specified';
        
        return "
<!DOCTYPE html>
<html>
<head>
<style>
    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
    .header { background: #1a2332; color: white; padding: 20px; text-align: center; }
    .content { padding: 30px; background: #f9f9f9; }
    .details-box { background: white; padding: 20px; margin: 15px 0; border-left: 4px solid #c5a253; }
    .btn { display: inline-block; padding: 12px 25px; background: #c5a253; color: white; text-decoration: none; border-radius: 5px; margin: 10px 0; }
    .footer { background: #333; color: white; padding: 15px; text-align: center; font-size: 12px; }
    .highlight { color: #c5a253; font-weight: bold; }
    .section-title { color: #1a2332; font-weight: bold; margin-top: 20px; margin-bottom: 10px; }
</style>
</head>
<body>
<div class='container'>
    <div class='header'>
        <h2>🔔 New Consultation Request</h2>
        <p>MD Law Firm - Lawyer Portal</p>
    </div>
    
    <div class='content'>
        <p>Dear <strong>Atty. $lawyer_name</strong>,</p>
        
        <p>You have received a new consultation request through the MD Law Firm website. Please review the details below and take appropriate action.</p>
        
        <div class='details-box'>
            <div class='section-title'>👤 CLIENT INFORMATION</div>
            <p><strong>Name:</strong> {$consultation['full_name']}</p>
            <p><strong>Email:</strong> {$consultation['client_email']}</p>
            <p><strong>Phone:</strong> {$consultation['phone']}</p>
            <p><strong>Practice Area:</strong> <span class='highlight'>{$consultation['practice_area']}</span></p>
            <p><strong>Preferred Date:</strong> $consultation_date</p>
            <p><strong>Preferred Time:</strong> $consultation_time</p>
        </div>
        
        <div class='details-box'>
            <div class='section-title'>📝 CASE DESCRIPTION</div>
            <p>{$consultation['case_description']}</p>
        </div>
        
        <div class='details-box'>
            <div class='section-title'>📋 NEXT STEPS</div>
            <ol>
                <li>Log in to your lawyer dashboard to review the full consultation details</li>
                <li>Contact the client to confirm the appointment</li>
                <li>Update the consultation status once confirmed</li>
            </ol>
            
            <div style='text-align: center; margin-top: 20px;'>
                <a href='http://localhost" . getWebBasePath() . "/lawyer/dashboard.php' class='btn'>Access Your Dashboard</a>
            </div>
        </div>
        
        <p>If you have any questions or need assistance, please contact the admin team.</p>
        
        <p>Best regards,<br>
        <strong>MD Law Firm</strong><br>
        Administrative Team</p>
    </div>
    
    <div class='footer'>
        <p>This is an automated notification. Please do not reply to this email.</p>
        <p>For support, contact: admin@mdlawfirm.com</p>
        <p>&copy; 2025 MD Law Firm. All rights reserved.</p>
    </div>
</div>
</body>
</html>
        ";
    }
}
?>
