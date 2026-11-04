<?php
session_start();
include "db.php";

if (!isset($_SESSION['user_id'])) {
    header('Location: login.html');
    exit();
}

$appointment_id = intval($_GET['appointment_id'] ?? 0);
if (!$appointment_id) {
    header('Location: dashboard.php');
    exit();
}

// Get appointment details
$stmt = $conn->prepare("SELECT a.*, p.name as prof_name, p.professional_type, p.consultation_fee FROM appointments a JOIN professionals p ON a.professional_id = p.id WHERE a.id = ? AND a.user_id = ?");
$stmt->bind_param('ii', $appointment_id, $_SESSION['user_id']);
$stmt->execute();
$appt = $stmt->get_result()->fetch_assoc();

if (!$appt) {
    header('Location: dashboard.php');
    exit();
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Booking Confirmation - FinCart</title>
    <link rel="stylesheet" href="assets/css/admin.css">
    <style>
        .confirmation-container { max-width: 600px; margin: 60px auto; padding: 20px; text-align: center; }
        .success-icon { font-size: 80px; margin-bottom: 20px; }
        .success-message { color: #2ecc71; font-size: 24px; font-weight: bold; margin-bottom: 20px; }
        .appointment-details { background: #f9f9f9; padding: 20px; border-radius: 8px; text-align: left; margin-bottom: 20px; }
        .detail-row { padding: 12px 0; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; }
        .detail-row:last-child { border-bottom: none; }
        .detail-label { font-weight: 600; color: #666; }
        .detail-value { color: #333; }
        .next-steps { background: #e8f5e9; padding: 15px; border-radius: 8px; margin-bottom: 20px; text-align: left; }
        .next-steps h3 { margin-bottom: 10px; }
        .next-steps ol { margin-left: 20px; }
        .next-steps li { margin-bottom: 8px; }
    </style>
</head>
<body>
<header class="site-header">
    <div class="logo"><h1>Booking Confirmation</h1></div>
    <div class="header-actions">
        <a href="dashboard.php" class="btn btn-secondary">Dashboard</a>
    </div>
</header>

<div class="confirmation-container">
    <div class="success-icon">✅</div>
    <div class="success-message">Appointment Booked Successfully!</div>
    
    <p style="margin-bottom: 30px; color: #666;">
        Your appointment request has been submitted. A confirmation email has been sent to your registered email address.
    </p>

    <div class="appointment-details">
        <h3 style="margin-bottom: 15px;">Appointment Details</h3>
        
        <div class="detail-row">
            <span class="detail-label">Professional:</span>
            <span class="detail-value"><strong><?php echo htmlspecialchars($appt['prof_name']); ?></strong> (<?php echo htmlspecialchars($appt['professional_type']); ?>)</span>
        </div>
        
        <div class="detail-row">
            <span class="detail-label">Date:</span>
            <span class="detail-value"><?php echo date('l, d-M-Y', strtotime($appt['appointment_date'])); ?></span>
        </div>
        
        <div class="detail-row">
            <span class="detail-label">Time:</span>
            <span class="detail-value"><?php echo date('h:i A', strtotime($appt['appointment_time'])); ?></span>
        </div>
        
        <div class="detail-row">
            <span class="detail-label">Consultation Type:</span>
            <span class="detail-value"><?php echo htmlspecialchars($appt['consultation_type']); ?></span>
        </div>
        
        <div class="detail-row">
            <span class="detail-label">Status:</span>
            <span class="detail-value"><strong style="color: #ff9800;">⏳ Pending</strong></span>
        </div>
        
        <div class="detail-row">
            <span class="detail-label">Consultation Fee:</span>
            <span class="detail-value">₹<?php echo number_format($appt['consultation_fee'], 2); ?></span>
        </div>
        
        <?php if (!empty($appt['notes'])): ?>
        <div class="detail-row">
            <span class="detail-label">Your Notes:</span>
            <span class="detail-value"><?php echo nl2br(htmlspecialchars($appt['notes'])); ?></span>
        </div>
        <?php endif; ?>
    </div>

    <div class="next-steps">
        <h3>What Happens Next?</h3>
        <ol>
            <li><strong>Pending Acceptance:</strong> The professional will review your booking and accept or reject it within 24 hours.</li>
            <li><strong>Confirmation Email:</strong> You'll receive an email when the professional accepts your appointment.</li>
            <li><strong>Payment:</strong> Once the appointment is confirmed, you can proceed with payment (₹<?php echo number_format($appt['consultation_fee'], 2); ?>).</li>
            <li><strong>Meeting Details:</strong> Connection details (phone/video link) will be sent before the appointment.</li>
        </ol>
    </div>

    <div style="margin-top: 30px;">
        <a href="dashboard.php" class="btn btn-primary">View My Appointments</a>
        <a href="professionals.php" class="btn btn-secondary" style="margin-left: 10px;">Continue Exploring</a>
    </div>
</div>

</body>
</html>
