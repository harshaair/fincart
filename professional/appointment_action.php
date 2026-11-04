<?php
require_once __DIR__ . '/../includes/security.php';
init_secure_session();
enforce_csrf();
require_once('../db.php');
require_once('../includes/mailer.php');

// Check if professional is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'professional') {
    die('Unauthorized access');
}

$user_id = $_SESSION['user_id'];

// Get the professional ID from the professionals table using user_id
$stmt = $conn->prepare("SELECT id FROM professionals WHERE user_id = ?");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$prof_result = $stmt->get_result()->fetch_assoc();

if (!$prof_result) {
    die('Professional profile not found');
}

$professional_id = $prof_result['id'];
$action = $_POST['action'] ?? null;
$appointment_id = (int)($_POST['appointment_id'] ?? 0);

if (!$appointment_id || !$action) {
    header('Location: dashboard.php?error=Invalid request');
    exit;
}

try {
    // Start transaction
    $conn->begin_transaction();

    // Fetch appointment and verify it belongs to this professional
    $stmt = $conn->prepare("
        SELECT a.*, u.email, u.full_name, p.consultation_fee 
        FROM appointments a 
        JOIN users u ON a.user_id = u.id
        JOIN professionals p ON a.professional_id = p.id
        WHERE a.id = ? AND a.professional_id = ?
    ");
    $stmt->bind_param('ii', $appointment_id, $professional_id);
    $stmt->execute();
    $apt = $stmt->get_result()->fetch_assoc();

    if (!$apt) {
        throw new Exception('Appointment not found or unauthorized');
    }

    $status_message = '';

    if ($action === 'accept') {
        // Update appointment status to confirmed
        $status = 'confirmed';
        $stmt = $conn->prepare("UPDATE appointments SET status = ? WHERE id = ?");
        $stmt->bind_param('si', $status, $appointment_id);
        $stmt->execute();

        // Get professional name
        $prof_stmt = $conn->prepare("SELECT name, phone FROM professionals WHERE id = ?");
        $prof_stmt->bind_param('i', $professional_id);
        $prof_stmt->execute();
        $prof_info = $prof_stmt->get_result()->fetch_assoc();

        // Send confirmation email to user
        $to = $apt['email'];
        $subject = "Appointment Confirmed - FinCart";
        $apt_date = date('d-M-Y', strtotime($apt['appointment_date']));
        $apt_time = date('h:i A', strtotime($apt['appointment_time']));

        $message = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; background: #f5f5f5; }
                .email-card { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
                .header { border-bottom: 3px solid #28a745; padding-bottom: 15px; margin-bottom: 20px; }
                .header h2 { color: #28a745; margin: 0; }
                .details { margin: 20px 0; }
                .detail-row { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #eee; }
                .label { font-weight: bold; color: #333; }
                .value { color: #666; }
                .footer { margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee; font-size: 12px; color: #999; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='email-card'>
                    <div class='header'>
                        <h2>✓ Appointment Confirmed!</h2>
                    </div>
                    
                    <p>Dear " . htmlspecialchars($apt['full_name']) . ",</p>
                    
                    <p>Great news! Your appointment request has been accepted by the professional.</p>
                    
                    <div class='details'>
                        <h3>Appointment Details</h3>
                        <div class='detail-row'>
                            <span class='label'>Professional:</span>
                            <span class='value'>" . htmlspecialchars($prof_info['name']) . "</span>
                        </div>
                        <div class='detail-row'>
                            <span class='label'>Date:</span>
                            <span class='value'>$apt_date</span>
                        </div>
                        <div class='detail-row'>
                            <span class='label'>Time:</span>
                            <span class='value'>$apt_time</span>
                        </div>
                        <div class='detail-row'>
                            <span class='label'>Type:</span>
                            <span class='value'>" . htmlspecialchars($apt['consultation_type']) . "</span>
                        </div>
                        <div class='detail-row'>
                            <span class='label'>Mode:</span>
                            <span class='value'>" . htmlspecialchars($apt['consultation_mode']) . "</span>
                        </div>
                        <div class='detail-row'>
                            <span class='label'>Fee:</span>
                            <span class='value'>₹" . number_format($apt['consultation_fee'], 2) . "</span>
                        </div>
                        <div class='detail-row'>
                            <span class='label'>Contact:</span>
                            <span class='value'>" . htmlspecialchars($prof_info['phone'] ?? 'N/A') . "</span>
                        </div>
                    </div>
                    
                    <p>Please proceed with the payment. You will receive further details regarding the meeting link or location.</p>
                    
                    <div class='footer'>
                        <p>This is an automated message. Please do not reply to this email.</p>
                        <p>&copy; FinCart - Financial Management Platform</p>
                    </div>
                </div>
            </div>
        </body>
        </html>
        ";

        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type: text/html; charset=UTF-8" . "\r\n";
        $headers .= "From: noreply@fincart.local" . "\r\n";

        sendFinCartEmail($to, $subject, $message);

        $status_message = 'Appointment accepted! Confirmation email sent to client.';

    } else if ($action === 'reject') {
        // Update appointment status to rejected
        $status = 'rejected';
        $rejection_reason = $_POST['reason'] ?? 'Not provided';
        
        $stmt = $conn->prepare("UPDATE appointments SET status = ?, rejection_reason = ? WHERE id = ?");
        $stmt->bind_param('ssi', $status, $rejection_reason, $appointment_id);
        $stmt->execute();

        // Send rejection email to user
        $to = $apt['email'];
        $subject = "Appointment Request Rejected - FinCart";

        $message = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; background: #f5f5f5; }
                .email-card { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
                .header { border-bottom: 3px solid #dc3545; padding-bottom: 15px; margin-bottom: 20px; }
                .header h2 { color: #dc3545; margin: 0; }
                .footer { margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee; font-size: 12px; color: #999; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='email-card'>
                    <div class='header'>
                        <h2>Appointment Request Rejected</h2>
                    </div>
                    
                    <p>Dear " . htmlspecialchars($apt['full_name']) . ",</p>
                    
                    <p>Unfortunately, your appointment request has been rejected by the professional.</p>
                    
                    <p><strong>Reason:</strong> $rejection_reason</p>
                    
                    <p>You can try booking another appointment or contact the professional directly for more information.</p>
                    
                    <div class='footer'>
                        <p>This is an automated message. Please do not reply to this email.</p>
                        <p>&copy; FinCart - Financial Management Platform</p>
                    </div>
                </div>
            </div>
        </body>
        </html>
        ";

        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type: text/html; charset=UTF-8" . "\r\n";
        $headers .= "From: noreply@fincart.local" . "\r\n";

        sendFinCartEmail($to, $subject, $message);

        $status_message = 'Appointment rejected. Notification email sent to client.';

    } else if ($action === 'cancel') {
        // Cancel appointment (only if not already rejected/cancelled)
        if (!in_array($apt['status'], ['rejected', 'cancelled'])) {
            $status = 'cancelled';
            $stmt = $conn->prepare("UPDATE appointments SET status = ? WHERE id = ?");
            $stmt->bind_param('si', $status, $appointment_id);
            $stmt->execute();

            // Send cancellation email to user
            $to = $apt['email'];
            $subject = "Appointment Cancelled - FinCart";

            $message = "
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; background: #f5f5f5; }
                    .email-card { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
                    .header { border-bottom: 3px solid #ffc107; padding-bottom: 15px; margin-bottom: 20px; }
                    .header h2 { color: #ffc107; margin: 0; }
                    .footer { margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee; font-size: 12px; color: #999; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='email-card'>
                        <div class='header'>
                            <h2>Appointment Cancelled</h2>
                        </div>
                        
                        <p>Dear " . htmlspecialchars($apt['full_name']) . ",</p>
                        
                        <p>Your confirmed appointment has been cancelled by the professional.</p>
                        
                        <p>You may contact them directly for rescheduling or book another appointment.</p>
                        
                        <div class='footer'>
                            <p>This is an automated message. Please do not reply to this email.</p>
                            <p>&copy; FinCart - Financial Management Platform</p>
                        </div>
                    </div>
                </div>
            </body>
            </html>
            ";

            $headers = "MIME-Version: 1.0" . "\r\n";
            $headers .= "Content-type: text/html; charset=UTF-8" . "\r\n";
            $headers .= "From: noreply@fincart.local" . "\r\n";

            sendFinCartEmail($to, $subject, $message);

            $status_message = 'Appointment cancelled. Notification email sent to client.';
        }
    }

    // Commit transaction
    $conn->commit();

    // Redirect with success message
    header("Location: dashboard.php?notice=" . urlencode($status_message));
    exit;

} catch (Exception $e) {
    // Rollback on error
    $conn->rollback();
    header('Location: dashboard.php?error=' . urlencode($e->getMessage()));
    exit;
}
?>
