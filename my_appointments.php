<?php
session_start();
include 'db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.html');
    exit;
}

$user_id = $_SESSION['user_id'];

// Fetch user's appointments
$stmt = $conn->prepare("
    SELECT a.*, p.name as professional_name, p.professional_type 
    FROM appointments a 
    JOIN professionals p ON a.professional_id = p.id
    WHERE a.user_id = ?
    ORDER BY a.appointment_date DESC, a.appointment_time DESC
");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$appointments = $stmt->get_result();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>My Appointments - FinCart</title>
    <link rel="stylesheet" href="assets/css/admin.css">
    <style>
        .page-container { max-width: 900px; margin: 20px auto; padding: 20px; }
        .header-section { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .header-section h1 { margin: 0; }
        .btn-group { display: flex; gap: 10px; }
        .btn { padding: 10px 20px; border-radius: 4px; text-decoration: none; cursor: pointer; border: none; font-size: 14px; }
        .btn-primary { background-color: #007bff; color: white; }
        .btn-primary:hover { background-color: #0056b3; }
        .btn-secondary { background-color: #6c757d; color: white; }
        .btn-secondary:hover { background-color: #545b62; }
        .notification { padding: 15px; border-radius: 4px; margin-bottom: 20px; }
        .notification-success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .notification-error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .appointments-table { width: 100%; border-collapse: collapse; }
        .appointments-table th { background-color: #f5f5f5; padding: 12px; text-align: left; font-weight: 600; border-bottom: 2px solid #ddd; }
        .appointments-table td { padding: 12px; border-bottom: 1px solid #ddd; }
        .appointments-table tr:hover { background-color: #f9f9f9; }
        .status-badge { display: inline-block; padding: 6px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; }
        .status-pending { background-color: #fff3cd; color: #856404; }
        .status-confirmed { background-color: #d4edda; color: #155724; }
        .status-rejected { background-color: #f8d7da; color: #721c24; }
        .status-cancelled { background-color: #e2e3e5; color: #383d41; }
        .action-buttons { display: flex; gap: 5px; }
        .action-buttons form { display: inline; }
        .action-buttons button { padding: 6px 12px; font-size: 12px; cursor: pointer; border: none; border-radius: 4px; }
        .btn-cancel { background-color: #dc3545; color: white; }
        .btn-cancel:hover { background-color: #c82333; }
        .btn-rebook { background-color: #28a745; color: white; }
        .btn-rebook:hover { background-color: #218838; }
        .empty-state { text-align: center; padding: 40px; color: #666; }
        .empty-state h3 { margin-bottom: 15px; }
        .appointment-card { background: white; padding: 20px; margin-bottom: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .appointment-header { display: flex; justify-content: space-between; align-items: start; margin-bottom: 15px; }
        .appointment-header h3 { margin: 0; }
        .appointment-details { display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px; margin-bottom: 15px; }
        .detail-item { }
        .detail-item strong { display: block; color: #333; margin-bottom: 5px; }
        .detail-item span { color: #666; }
        .appointment-footer { display: flex; justify-content: space-between; align-items: center; }
        .rejection-reason { background-color: #ffeaa7; padding: 10px; border-left: 4px solid #f39c12; margin-top: 10px; border-radius: 4px; }
    </style>
</head>
<body>
<header class="site-header">
    <div class="logo"><h1>My Appointments - FinCart</h1></div>
    <div class="header-actions">
        <span>Welcome, <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'User'); ?></span>
        <a href="logout.php" class="btn btn-primary">Logout</a>
    </div>
</header>

<div class="page-container">
    <?php if (isset($_GET['notice'])): ?>
        <div class="notification notification-success">
            ✓ <?php echo htmlspecialchars($_GET['notice']); ?>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_GET['error'])): ?>
        <div class="notification notification-error">
            ✗ <?php echo htmlspecialchars($_GET['error']); ?>
        </div>
    <?php endif; ?>

    <div class="header-section">
        <h2>📅 Your Appointments</h2>
        <div class="btn-group">
            <a href="professionals.php" class="btn btn-secondary">Browse Professionals</a>
            <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
        </div>
    </div>

    <?php if ($appointments->num_rows > 0): ?>
        <div style="display: grid; gap: 15px;">
            <?php while ($apt = $appointments->fetch_assoc()): ?>
            <div class="appointment-card">
                <div class="appointment-header">
                    <div>
                        <h3><?php echo htmlspecialchars($apt['professional_name']); ?></h3>
                        <small><?php echo htmlspecialchars($apt['professional_type']); ?></small>
                    </div>
                    <span class="status-badge status-<?php echo strtolower($apt['status']); ?>">
                        <?php 
                        $status_icons = ['pending' => '⏳', 'confirmed' => '✓', 'rejected' => '✗', 'cancelled' => '⊘'];
                        echo ($status_icons[$apt['status']] ?? '') . ' ' . ucfirst($apt['status']);
                        ?>
                    </span>
                </div>

                <div class="appointment-details">
                    <div class="detail-item">
                        <strong>📅 Date & Time</strong>
                        <span><?php echo date('d-M-Y', strtotime($apt['appointment_date'])); ?>, <?php echo date('h:i A', strtotime($apt['appointment_time'])); ?></span>
                    </div>
                    <div class="detail-item">
                        <strong>📝 Type</strong>
                        <span><?php echo htmlspecialchars($apt['consultation_type']); ?></span>
                    </div>
                    <div class="detail-item">
                        <strong>💻 Mode</strong>
                        <span><?php echo htmlspecialchars(ucfirst($apt['consultation_mode'])); ?></span>
                    </div>
                    <div class="detail-item">
                        <strong>🗓️ Booked On</strong>
                        <span><?php echo date('d-M-Y H:i', strtotime($apt['created_at'])); ?></span>
                    </div>
                </div>

                <?php if ($apt['notes']): ?>
                <div style="background-color: #f0f0f0; padding: 10px; border-radius: 4px; margin-bottom: 10px;">
                    <strong>Notes:</strong><br>
                    <?php echo htmlspecialchars($apt['notes']); ?>
                </div>
                <?php endif; ?>

                <?php if ($apt['status'] === 'rejected' && $apt['rejection_reason']): ?>
                <div class="rejection-reason">
                    <strong>Rejection Reason:</strong><br>
                    <?php echo htmlspecialchars($apt['rejection_reason']); ?>
                </div>
                <?php endif; ?>

                <div class="appointment-footer">
                    <div>
                        <?php if ($apt['status'] === 'pending'): ?>
                        <small style="color: #f39c12;">⏳ Waiting for professional to confirm...</small>
                        <?php elseif ($apt['status'] === 'confirmed'): ?>
                        <small style="color: #27ae60;">✓ Confirmed - Payment pending</small>
                        <?php elseif ($apt['status'] === 'rejected'): ?>
                        <small style="color: #e74c3c;">✗ Appointment rejected</small>
                        <?php elseif ($apt['status'] === 'cancelled'): ?>
                        <small style="color: #95a5a6;">⊘ Appointment cancelled</small>
                        <?php endif; ?>
                    </div>
                    <div class="action-buttons">
                        <?php if ($apt['status'] === 'pending'): ?>
                        <form method="post" action="cancel_appointment.php" style="display: inline;">
                            <input type="hidden" name="appointment_id" value="<?php echo $apt['id']; ?>">
                            <button type="submit" class="btn-cancel" onclick="return confirm('Cancel this appointment?')">Cancel</button>
                        </form>
                        <?php elseif ($apt['status'] === 'rejected'): ?>
                        <a href="professionals.php?id=<?php echo $apt['professional_id']; ?>" class="btn-rebook">Book Again</a>
                        <?php elseif ($apt['status'] === 'cancelled'): ?>
                        <a href="professionals.php?id=<?php echo $apt['professional_id']; ?>" class="btn-rebook">Rebook</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
    <?php else: ?>
        <div class="empty-state">
            <h3>No Appointments Yet</h3>
            <p>You haven't booked any appointments yet. Browse our professionals and book your first consultation!</p>
            <a href="professionals.php" class="btn btn-primary">Browse Professionals</a>
        </div>
    <?php endif; ?>
</div>

</body>
</html>
