<?php
require_once('includes/security.php');
init_secure_session();
require_once('db.php');
require_once('includes/mailer.php');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.html');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    enforce_csrf();
}

$user_id = $_SESSION['user_id'];
$appointment_id = (int)($_POST['appointment_id'] ?? 0);

if (!$appointment_id) {
    header('Location: my_appointments.php?error=Invalid request');
    exit;
}

try {
    // Start transaction
    $conn->begin_transaction();

    // Fetch appointment and verify it belongs to this user
    $stmt = $conn->prepare("
        SELECT a.*, p.name, p.email as prof_email, u.email 
        FROM appointments a 
        JOIN professionals p ON a.professional_id = p.id
        JOIN users u ON a.user_id = u.id
        WHERE a.id = ? AND a.user_id = ?
    ");
    $stmt->bind_param('ii', $appointment_id, $user_id);
    $stmt->execute();
    $apt = $stmt->get_result()->fetch_assoc();

    if (!$apt) {
        throw new Exception('Appointment not found or unauthorized');
    }

    // Only allow cancellation if appointment is not already rejected/cancelled
    if (in_array($apt['status'], ['rejected', 'cancelled'])) {
        throw new Exception('Cannot cancel this appointment');
    }

    // Update appointment status to cancelled
    $status = 'cancelled';
    $stmt = $conn->prepare("UPDATE appointments SET status = ? WHERE id = ?");
    $stmt->bind_param('si', $status, $appointment_id);
    $stmt->execute();

    // Send cancellation notification to professional
    $to = $apt['prof_email'];
    $subject = "Appointment Cancellation - FinCart";
    $apt_date = date('d-M-Y', strtotime($apt['appointment_date']));
    $apt_time = date('h:i A', strtotime($apt['appointment_time']));

    $message = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; background: #f5f5f5; }
            .email-card { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
            .header { border-bottom: 3px solid #ffc107; padding-bottom: 15px; margin-bottom: 20px; }
            .header h2 { color: #ffc107; margin: 0; }
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
                    <h2>⊘ Appointment Cancelled</h2>
                </div>
                
                <p>Dear " . htmlspecialchars($apt['name']) . ",</p>
                
                <p>An appointment has been cancelled by the client.</p>
                
                <div class='details'>
                    <h3>Cancelled Appointment Details</h3>
                    <div class='detail-row'>
                        <span class='label'>Client:</span>
                        <span class='value'>" . htmlspecialchars($_SESSION['user_name']) . "</span>
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
                </div>
                
                <p>Your calendar slot is now available for other bookings.</p>
                
                <div class='footer'>
                    <p>This is an automated message. Please do not reply to this email.</p>
                    <p>&copy; FinCart - Financial Management Platform</p>
                </div>
            </div>
        </div>
    </body>
    </html>
    ";

    sendFinCartEmail($to, $subject, $message);

    // Commit transaction
    $conn->commit();

    // Redirect with success message
    header("Location: my_appointments.php?notice=" . urlencode('Appointment cancelled successfully. Notification sent to professional.'));
    exit;

} catch (Exception $e) {
    // Rollback on error
    $conn->rollback();
    header('Location: my_appointments.php?error=' . urlencode($e->getMessage()));
    exit;
}
?>
