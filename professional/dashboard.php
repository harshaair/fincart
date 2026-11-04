<?php
include '../auth_professional.php';
include '../db.php';

$uid = $_SESSION['user_id'];

// Check for application
$stmt = $conn->prepare("SELECT * FROM professional_applications WHERE user_id = ? ORDER BY created_at DESC LIMIT 1");
$stmt->bind_param('i', $uid);
$stmt->execute();
$application = $stmt->get_result()->fetch_assoc();

// Check for approved professional profile
$stmt = $conn->prepare("SELECT * FROM professionals WHERE user_id = ? LIMIT 1");
$stmt->bind_param('i', $uid);
$stmt->execute();
$prof = $stmt->get_result()->fetch_assoc();

// If professional is approved, fetch appointments, rating stats, and reviews
$appointments = null;
$reviews = null;
$rating_stats = ['avg_rating' => 0.0, 'total_reviews' => 0];

if ($prof) {
    // Rating metrics
    $r_stmt = $conn->prepare("SELECT COALESCE(AVG(rating), 0) as avg_rating, COUNT(id) as total_reviews FROM professional_ratings WHERE professional_id = ?");
    $r_stmt->bind_param('i', $prof['id']);
    $r_stmt->execute();
    $rating_stats = $r_stmt->get_result()->fetch_assoc();

    // Recent reviews
    $rev_stmt = $conn->prepare("SELECT r.*, u.full_name as client_name FROM professional_ratings r JOIN users u ON r.user_id = u.id WHERE r.professional_id = ? ORDER BY r.created_at DESC LIMIT 10");
    $rev_stmt->bind_param('i', $prof['id']);
    $rev_stmt->execute();
    $reviews = $rev_stmt->get_result();

    // Appointments
    $a_stmt = $conn->prepare("SELECT a.*, u.full_name as client_name, u.email as client_email FROM appointments a JOIN users u ON a.user_id = u.id WHERE a.professional_id = ? ORDER BY a.appointment_date ASC, a.appointment_time ASC");
    $a_stmt->bind_param('i', $prof['id']);
    $a_stmt->execute();
    $appointments = $a_stmt->get_result();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Professional Portal - FinCart</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body style="background: #f8f9fa;">

<header class="site-header" style="background: #ffffff;">
    <div class="nav-container">
        <a href="dashboard.php" class="site-logo">
            Fin<span>Cart</span> <span style="font-size: 13px; font-weight: 500; background: #e8f5e9; color: #2e7d32; padding: 3px 8px; border-radius: 4px; margin-left: 8px;">EXPERT PORTAL</span>
        </a>
        <div class="nav-actions">
            <span style="color: #666; font-size: 14px;">Welcome, <strong><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Professional'); ?></strong></span>
            <a href="../dashboard.php" class="btn btn-secondary btn-sm">Client View</a>
            <a href="../logout.php" class="btn btn-primary btn-sm">Logout</a>
        </div>
    </div>
</header>

<div class="main-content">
    <?php if (isset($_GET['notice'])): ?>
        <div class="alert alert-success">&#10003; <?php echo htmlspecialchars($_GET['notice']); ?></div>
    <?php endif; ?>
    <?php if (isset($_GET['error'])): ?>
        <div class="alert alert-danger">&#9888; <?php echo htmlspecialchars($_GET['error']); ?></div>
    <?php endif; ?>

    <?php if ($_SESSION['role'] === 'professional_pending' && !$prof): ?>
        <div class="card" style="border-left: 6px solid #f39c12;">
            <h2 style="font-size: 22px; margin-bottom: 8px;">Application Status: Pending Admin Review</h2>
            <p style="color: #666; margin-bottom: 20px;">Your credentials have been submitted and are currently in the verification queue. Admin approval is typically processed within 24–48 hours.</p>
            
            <?php if ($application): ?>
                <div style="background: var(--off-white); padding: 18px; border-radius: 8px; font-size: 14px; line-height: 1.8;">
                    <strong>Role Requested:</strong> <?php echo htmlspecialchars($application['professional_type']); ?><br>
                    <strong>Qualification:</strong> <?php echo htmlspecialchars($application['qualification'] ?? 'N/A'); ?><br>
                    <strong>Experience:</strong> <?php echo (int)($application['experience_years'] ?? 0); ?> years<br>
                    <strong>Specialization:</strong> <?php echo htmlspecialchars($application['specialization'] ?? 'N/A'); ?><br>
                    <strong>Submitted On:</strong> <?php echo date('d-M-Y H:i', strtotime($application['created_at'])); ?>
                </div>
            <?php endif; ?>
        </div>
    <?php elseif ($prof): ?>
        <!-- Profile & Ratings Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">Client Rating</div>
                <div class="stat-value" style="color: #f39c12;">
                    ★ <?php echo number_format((float)$rating_stats['avg_rating'], 1); ?>
                </div>
                <div class="stat-description">Average score (<?php echo (int)$rating_stats['total_reviews']; ?> client reviews)</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Consultation Fee</div>
                <div class="stat-value">₹<?php echo number_format((float)($prof['consultation_fee'] ?? 0), 2); ?></div>
                <div class="stat-description">Standard advisory session charge</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Professional Type</div>
                <div class="stat-value" style="font-size: 22px;"><?php echo htmlspecialchars($prof['professional_type']); ?></div>
                <div class="stat-description"><?php echo htmlspecialchars($prof['qualification'] ?? 'Verified Specialist'); ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Profile Settings</div>
                <div class="stat-value" style="font-size: 18px; margin-top: 6px;">
                    <a href="edit_profile.php" class="btn btn-secondary btn-sm">Edit Listing Details &rarr;</a>
                </div>
                <div class="stat-description">Bio, fees, city & languages</div>
            </div>
        </div>

        <!-- Appointment Requests Table -->
        <div class="card">
            <h2 style="font-size: 20px; margin-bottom: 16px;">Scheduled Consultations & Requests</h2>
            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Client Name</th>
                            <th>Date & Time</th>
                            <th>Session Type</th>
                            <th>Mode</th>
                            <th>Status</th>
                            <th style="text-align: right;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($appointments && $appointments->num_rows > 0): ?>
                            <?php while ($apt = $appointments->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($apt['client_name']); ?></strong><br>
                                        <small style="color: #666;"><?php echo htmlspecialchars($apt['client_email']); ?></small>
                                    </td>
                                    <td>
                                        <?php echo date('d-M-Y', strtotime($apt['appointment_date'])); ?><br>
                                        <strong><?php echo date('h:i A', strtotime($apt['appointment_time'])); ?></strong>
                                    </td>
                                    <td><?php echo htmlspecialchars($apt['consultation_type']); ?></td>
                                    <td><?php echo htmlspecialchars(ucfirst($apt['consultation_mode'] ?? 'online')); ?></td>
                                    <td>
                                        <?php
                                        $s = strtolower($apt['status']);
                                        if ($s === 'confirmed') echo '<span class="badge badge-success">&#10003; Confirmed</span>';
                                        elseif ($s === 'pending') echo '<span class="badge badge-warning">Pending</span>';
                                        elseif ($s === 'cancelled') echo '<span class="badge badge-secondary">Cancelled</span>';
                                        else echo '<span class="badge badge-danger">' . ucfirst($s) . '</span>';
                                        ?>
                                    </td>
                                    <td style="text-align: right;">
                                        <?php if ($apt['status'] === 'pending'): ?>
                                            <div style="display: flex; gap: 6px; justify-content: flex-end;">
                                                <form method="POST" action="appointment_action.php" style="display:inline;">
                                                    <input type="hidden" name="appointment_id" value="<?php echo (int)$apt['id']; ?>">
                                                    <input type="hidden" name="action" value="accept">
                                                    <button type="submit" class="btn btn-primary btn-sm" style="background: #27ae60;">Accept</button>
                                                </form>
                                                <form method="POST" action="appointment_action.php" style="display:inline;" onsubmit="return confirm('Reject this appointment?');">
                                                    <input type="hidden" name="appointment_id" value="<?php echo (int)$apt['id']; ?>">
                                                    <input type="hidden" name="action" value="reject">
                                                    <button type="submit" class="btn btn-danger btn-sm">Decline</button>
                                                </form>
                                            </div>
                                        <?php elseif ($apt['status'] === 'confirmed'): ?>
                                            <form method="POST" action="appointment_action.php" style="display:inline;" onsubmit="return confirm('Cancel this confirmed session?');">
                                                <input type="hidden" name="appointment_id" value="<?php echo (int)$apt['id']; ?>">
                                                <input type="hidden" name="action" value="cancel">
                                                <button type="submit" class="btn btn-secondary btn-sm">Cancel</button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" style="text-align: center; padding: 30px; color: #888;">
                                    No appointment requests received yet.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Client Reviews Section -->
        <div class="card">
            <h2 style="font-size: 20px; margin-bottom: 16px;">Client Feedback & Star Ratings</h2>
            <?php if ($reviews && $reviews->num_rows > 0): ?>
                <div style="display: grid; gap: 14px;">
                    <?php while ($rev = $reviews->fetch_assoc()): ?>
                        <div style="background: var(--off-white); padding: 16px; border-radius: var(--radius-sm); border-left: 4px solid #f39c12;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                                <strong><?php echo htmlspecialchars($rev['client_name']); ?></strong>
                                <span style="color: #f39c12; font-size: 15px;">
                                    <?php echo str_repeat('★', (int)$rev['rating']) . str_repeat('☆', 5 - (int)$rev['rating']); ?>
                                </span>
                            </div>
                            <?php if (!empty($rev['review_text'])): ?>
                                <p style="font-size: 13px; color: #555; margin-bottom: 6px;">"<?php echo htmlspecialchars($rev['review_text']); ?>"</p>
                            <?php endif; ?>
                            <small style="color: #999; font-size: 11px;"><?php echo date('d-M-Y', strtotime($rev['created_at'])); ?></small>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <p style="color: #888; text-align: center; padding: 25px;">No client reviews submitted yet. Ratings will appear after consultations are completed.</p>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<footer class="site-footer">
    <div class="footer-content">
        <p>&copy; <?php echo date('Y'); ?> FinCart Professional Portal. All rights reserved.</p>
    </div>
</footer>

</body>
</html>
