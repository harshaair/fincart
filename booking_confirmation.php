<?php
require_once "includes/security.php";
init_secure_session();
include "db.php";

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
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

$page_title = 'Booking Confirmation - FinCart';
include "includes/header.php";
include "includes/navbar.php";
?>

<div class="main-content">
    <div style="max-width: 640px; margin: 30px auto; padding: 20px;">
        <div class="card" style="text-align: center; padding: 36px 28px;">
            <div style="font-size: 64px; margin-bottom: 16px;">✅</div>
            <h1 style="color: var(--success, #27ae60); font-size: 26px; font-weight: 700; margin-bottom: 10px;">Booking Confirmed!</h1>
            <p style="color: var(--text-muted); font-size: 14px; margin-bottom: 28px;">
                Your appointment request is saved and confirmed (Booking Reference #<?php echo htmlspecialchars((string)$appt['id']); ?>). A notification has been dispatched to the advisor.
            </p>

            <div style="background: var(--off-white, #fafaf9); border: 1px solid var(--border-light, #eee); border-radius: 12px; padding: 20px; text-align: left; margin-bottom: 24px;">
                <h3 style="font-size: 16px; margin-bottom: 14px; color: var(--primary);">Appointment Summary</h3>
                
                <div style="display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid rgba(0,0,0,0.05); font-size: 14px;">
                    <span style="color: var(--text-muted); font-weight: 500;">Reference ID:</span>
                    <span><strong style="color: var(--primary);">#<?php echo htmlspecialchars((string)$appt['id']); ?></strong></span>
                </div>

                <div style="display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid rgba(0,0,0,0.05); font-size: 14px;">
                    <span style="color: var(--text-muted); font-weight: 500;">Advisor:</span>
                    <span><strong><?php echo htmlspecialchars($appt['prof_name']); ?></strong> (<?php echo htmlspecialchars($appt['professional_type']); ?>)</span>
                </div>
                
                <div style="display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid rgba(0,0,0,0.05); font-size: 14px;">
                    <span style="color: var(--text-muted); font-weight: 500;">Date:</span>
                    <span><strong><?php echo date('l, d-M-Y', strtotime($appt['appointment_date'])); ?></strong></span>
                </div>
                
                <div style="display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid rgba(0,0,0,0.05); font-size: 14px;">
                    <span style="color: var(--text-muted); font-weight: 500;">Time Slot:</span>
                    <span><strong><?php echo date('h:i A', strtotime($appt['appointment_time'])); ?></strong></span>
                </div>
                
                <div style="display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid rgba(0,0,0,0.05); font-size: 14px;">
                    <span style="color: var(--text-muted); font-weight: 500;">Session Type:</span>
                    <span><?php echo htmlspecialchars($appt['consultation_type']); ?></span>
                </div>
                
                <div style="display: flex; justify-content: space-between; align-items: center; padding: 10px 0; border-bottom: 1px solid rgba(0,0,0,0.05); font-size: 14px;">
                    <span style="color: var(--text-muted); font-weight: 500;">Booking Status:</span>
                    <?php if ($appt['status'] === 'confirmed'): ?>
                        <span class="badge" style="background: #27ae60; color: #fff; padding: 4px 10px; border-radius: 12px; font-weight: 600; font-size: 12.5px;">✓ Confirmed</span>
                    <?php else: ?>
                        <span class="badge" style="background: #f39c12; color: #fff; padding: 4px 10px; border-radius: 12px; font-weight: 600; font-size: 12.5px;">⏳ <?php echo ucfirst(htmlspecialchars($appt['status'])); ?></span>
                    <?php endif; ?>
                </div>
                
                <div style="display: flex; justify-content: space-between; padding: 10px 0; font-size: 14px;">
                    <span style="color: var(--text-muted); font-weight: 500;">Consultation Fee:</span>
                    <span style="font-weight: 700; color: var(--accent, #d4af37);">₹<?php echo number_format($appt['consultation_fee'], 2); ?></span>
                </div>
                
                <?php if (!empty($appt['notes'])): ?>
                <div style="padding: 10px 0; border-top: 1px solid rgba(0,0,0,0.05); font-size: 13.5px;">
                    <span style="color: var(--text-muted); font-weight: 500; display: block; margin-bottom: 4px;">Your Notes:</span>
                    <span style="color: var(--text-dark);"><?php echo nl2br(htmlspecialchars($appt['notes'])); ?></span>
                </div>
                <?php endif; ?>
            </div>

            <div style="background: rgba(39, 174, 96, 0.08); border-left: 4px solid var(--success, #27ae60); padding: 16px; border-radius: 6px; margin-bottom: 28px; text-align: left; font-size: 13.5px; line-height: 1.6;">
                <strong style="color: var(--primary); display: block; margin-bottom: 6px;">Next Steps:</strong>
                <ul style="margin: 0; padding-left: 18px; color: var(--text-dark);">
                    <li>The professional will review your consultation request and confirm within 24 hours.</li>
                    <li>You will receive an email update as soon as the appointment is confirmed.</li>
                    <li>Meeting access details will be visible in your <a href="my_appointments.php" style="color: var(--accent); font-weight: 600;">Appointments Hub</a>.</li>
                </ul>
            </div>

            <div style="display: flex; gap: 12px; justify-content: center; flex-wrap: wrap;">
                <a href="my_appointments.php" class="btn btn-primary">Go to My Appointments &rarr;</a>
                <a href="professionals.php" class="btn btn-secondary">&larr; Back to Directory</a>
            </div>
        </div>
    </div>
</div>

<?php include "includes/footer.php"; ?>
