<?php
include '../auth_professional.php';
include '../db.php';

$uid = $_SESSION['user_id'];

// Check for pending application
$stmt = $conn->prepare("SELECT * FROM professional_applications WHERE user_id = ? ORDER BY created_at DESC LIMIT 1");
$stmt->bind_param('i', $uid);
$stmt->execute();
$application = $stmt->get_result()->fetch_assoc();

// Check for approved professional profile
$stmt = $conn->prepare("SELECT * FROM professionals WHERE user_id = ? LIMIT 1");
$stmt->bind_param('i', $uid);
$stmt->execute();
$prof = $stmt->get_result()->fetch_assoc();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Professional Dashboard</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <style>
        .dashboard-container { max-width:900px; margin:20px auto; padding:20px; }
        .status-card { padding:20px; border-radius:8px; margin:20px 0; }
        .status-pending { background-color:#fffbea; border-left:4px solid #ffa600; }
        .status-approved { background-color:#e8f5e9; border-left:4px solid #4caf50; }
        .status-rejected { background-color:#ffebee; border-left:4px solid #f44336; }
        .profile-details { margin-top:20px; }
        .profile-row { margin:10px 0; }
        .profile-row strong { display:inline-block; width:150px; }
        .appointments-table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        .appointments-table th, .appointments-table td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        .appointments-table th { background-color: #f5f5f5; font-weight: 600; }
        .status-badge { padding: 4px 12px; border-radius: 20px; display: inline-block; font-size: 12px; font-weight: 600; }
        .status-pending { background-color: #fff3cd; color: #856404; }
        .status-confirmed { background-color: #d4edda; color: #155724; }
        .status-rejected { background-color: #f8d7da; color: #721c24; }
        .status-cancelled { background-color: #e2e3e5; color: #383d41; }
        .action-buttons { display: flex; gap: 5px; }
        .action-buttons form { display: inline; }
        .action-buttons button { padding: 6px 12px; font-size: 12px; cursor: pointer; }
        .btn-accept { background-color: #28a745; color: white; border: none; border-radius: 4px; }
        .btn-accept:hover { background-color: #218838; }
        .btn-reject { background-color: #dc3545; color: white; border: none; border-radius: 4px; }
        .btn-reject:hover { background-color: #c82333; }
    </style>
</head>
<body>
<header class="site-header">
    <div class="logo"><h1>Professional Dashboard - FinCart</h1></div>
    <div class="header-actions">
        <span>Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
        <a href="../logout.php" class="btn btn-primary">Logout</a>
    </div>
</header>

<div class="dashboard-container">
    <?php
    // Display notifications
    if (isset($_GET['notice'])):
    ?>
        <div style="padding: 15px; background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; border-radius: 4px; margin-bottom: 20px;">
            ✓ <?php echo htmlspecialchars($_GET['notice']); ?>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_GET['error'])): ?>
        <div style="padding: 15px; background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; border-radius: 4px; margin-bottom: 20px;">
            ✗ <?php echo htmlspecialchars($_GET['error']); ?>
        </div>
    <?php endif; ?>
    
    <?php
    if ($_SESSION['role'] === 'professional_pending' || $_SESSION['role'] === 'professional_approved'):
        if ($application):
    ?>
        <div class="status-card status-<?php echo $application['status']; ?>">
            <h2>Application Status: <strong><?php echo ucfirst(htmlspecialchars($application['status'])); ?></strong></h2>
            
            <?php if ($application['status'] === 'pending'): ?>
                <p style="color:#f57f17; margin:10px 0;">⏳ Your professional application is under review by our admin team. This usually takes 24-48 hours.</p>
            <?php elseif ($application['status'] === 'approved'): ?>
                <p style="color:#2e7d32; margin:10px 0;">✓ Congratulations! Your professional application has been approved!</p>
            <?php elseif ($application['status'] === 'rejected'): ?>
                <p style="color:#c62828; margin:10px 0;">✗ Your application was rejected.</p>
                <?php if ($application['rejection_reason']): ?>
                    <p><strong>Reason:</strong> <?php echo htmlspecialchars($application['rejection_reason']); ?></p>
                    <p><a href="../become_professional.php" class="btn btn-secondary">Reapply</a></p>
                <?php endif; ?>
            <?php endif; ?>

            <div class="profile-details">
                <h3>Your Application Details</h3>
                <div class="profile-row"><strong>Professional Type:</strong> <?php echo htmlspecialchars($application['professional_type']); ?></div>
                <div class="profile-row"><strong>Qualification:</strong> <?php echo htmlspecialchars($application['qualification'] ?? 'N/A'); ?></div>
                <div class="profile-row"><strong>Experience:</strong> <?php echo htmlspecialchars($application['experience_years'] ?? 'N/A'); ?> years</div>
                <div class="profile-row"><strong>Specialization:</strong> <?php echo htmlspecialchars($application['specialization'] ?? 'N/A'); ?></div>
                <div class="profile-row"><strong>City:</strong> <?php echo htmlspecialchars($application['city'] ?? 'N/A'); ?></div>
                <div class="profile-row"><strong>State:</strong> <?php echo htmlspecialchars($application['state'] ?? 'N/A'); ?></div>
                <div class="profile-row"><strong>Consultation Fee:</strong> ₹<?php echo number_format($application['consultation_fee'] ?? 0, 2); ?></div>
                <div class="profile-row"><strong>Applied On:</strong> <?php echo date('d-M-Y H:i', strtotime($application['created_at'])); ?></div>
                <?php if ($application['reviewed_at']): ?>
                    <div class="profile-row"><strong>Reviewed On:</strong> <?php echo date('d-M-Y H:i', strtotime($application['reviewed_at'])); ?></div>
                <?php endif; ?>
            </div>
        </div>
    <?php else: ?>
        <div class="status-card">
            <h2>No Application Found</h2>
            <p>You haven't submitted a professional application yet. <a href="../become_professional.php" class="btn btn-primary">Submit Application</a></p>
        </div>
    <?php endif; ?>

    <?php endif; ?>

    <?php if ($prof && $prof['status'] === 'active'): ?>
        <div class="status-card status-approved">
            <h2>Your Professional Profile</h2>
            <div class="profile-details">
                <div class="profile-row"><strong>Name:</strong> <?php echo htmlspecialchars($prof['name']); ?></div>
                <div class="profile-row"><strong>Type:</strong> <?php echo htmlspecialchars($prof['professional_type']); ?></div>
                <div class="profile-row"><strong>Experience:</strong> <?php echo htmlspecialchars($prof['experience_years'] ?? 'N/A'); ?> years</div>
                <div class="profile-row"><strong>Contact:</strong> <?php echo htmlspecialchars($prof['phone'] ?? 'N/A'); ?></div>
                <div class="profile-row"><strong>Consultation Fee:</strong> ₹<?php echo number_format($prof['consultation_fee'] ?? 0, 2); ?></div>
                <p><a href="edit_profile.php" class="btn btn-secondary">Edit Profile</a></p>
            </div>
        </div>

        <!-- Appointments Section -->
        <div class="status-card">
            <h2>📅 Appointment Requests</h2>
            
            <?php
            // Fetch pending and confirmed appointments
            $stmt = $conn->prepare("SELECT a.*, u.full_name, u.email FROM appointments a JOIN users u ON a.user_id = u.id WHERE a.professional_id = ? ORDER BY a.appointment_date ASC, a.appointment_time ASC");
            $stmt->bind_param('i', $prof['id']);
            $stmt->execute();
            $appointments = $stmt->get_result();
            ?>

            <?php if ($appointments->num_rows > 0): ?>
                <table class="appointments-table">
                    <thead>
                        <tr>
                            <th>Client Name</th>
                            <th>Date & Time</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($apt = $appointments->fetch_assoc()): ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($apt['full_name']); ?></strong><br>
                                <small style="color: #666;"><?php echo htmlspecialchars($apt['email']); ?></small>
                            </td>
                            <td>
                                <?php echo date('d-M-Y', strtotime($apt['appointment_date'])); ?><br>
                                <strong><?php echo date('h:i A', strtotime($apt['appointment_time'])); ?></strong>
                            </td>
                            <td><?php echo htmlspecialchars($apt['consultation_type']); ?></td>
                            <td>
                                <span class="status-badge status-<?php echo strtolower($apt['status']); ?>">
                                    <?php 
                                    $status_icons = ['pending' => '⏳', 'confirmed' => '✓', 'rejected' => '✗', 'cancelled' => '⊘'];
                                    echo ($status_icons[$apt['status']] ?? '') . ' ' . ucfirst($apt['status']);
                                    ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($apt['status'] === 'pending'): ?>
                                <div class="action-buttons">
                                    <form method="post" action="./appointment_action.php" style="display: inline;">
                                        <input type="hidden" name="appointment_id" value="<?php echo $apt['id']; ?>">
                                        <input type="hidden" name="action" value="accept">
                                        <button type="submit" class="btn-accept">Accept</button>
                                    </form>
                                    <form method="post" action="./appointment_action.php" style="display: inline;">
                                        <input type="hidden" name="appointment_id" value="<?php echo $apt['id']; ?>">
                                        <input type="hidden" name="action" value="reject">
                                        <button type="submit" class="btn-reject" onclick="return confirm('Are you sure?')">Reject</button>
                                    </form>
                                </div>
                                <?php else: ?>
                                <form method="post" action="./appointment_action.php" style="display: inline;">
                                    <input type="hidden" name="appointment_id" value="<?php echo $apt['id']; ?>">
                                    <input type="hidden" name="action" value="cancel">
                                    <button type="submit" class="btn-reject" onclick="return confirm('Cancel this appointment?')">Cancel</button>
                                </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p style="text-align: center; color: #666; padding: 20px;">No appointment requests yet.</p>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

</body>
</html>
