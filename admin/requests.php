<?php
include '../auth_admin.php';
include '../db.php';

$page_title = 'Professional Applications - FinCart Admin';

$sql = "SELECT pa.*, u.email AS user_email, u.full_name AS user_name 
        FROM professional_applications pa 
        LEFT JOIN users u ON pa.user_id = u.id 
        WHERE pa.status = 'pending' 
        ORDER BY pa.created_at DESC";
$result = $conn->query($sql);

include '../includes/admin_header.php';
include '../includes/admin_navbar.php';
?>

<div class="admin-container">
    <div class="admin-header-row">
        <div>
            <h1 class="admin-title">Pending Professional Applications</h1>
            <p class="admin-subtitle">Review credential submissions from CAs, financial advisors, and tax consultants.</p>
        </div>
        <div>
            <a href="dashboard.php" class="btn btn-secondary btn-sm">← Back to Dashboard</a>
        </div>
    </div>

    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success" style="background: #dcfce7; border: 1px solid #86efac; color: #166534; padding: 12px 16px; border-radius: 8px; margin-bottom: 20px;">
            &#10003; <?php echo htmlspecialchars($_GET['success']); ?>
        </div>
    <?php endif; ?>
    <?php if (isset($_GET['error'])): ?>
        <div class="alert alert-danger" style="background: #fee2e2; border: 1px solid #fca5a5; color: #991b1b; padding: 12px 16px; border-radius: 8px; margin-bottom: 20px;">
            &#9888; <?php echo htmlspecialchars($_GET['error']); ?>
        </div>
    <?php endif; ?>

    <div class="admin-card">
        <div class="table-responsive">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th style="width: 50px;">ID</th>
                        <th>Applicant</th>
                        <th>Type & Qualification</th>
                        <th>Experience</th>
                        <th>Specialization</th>
                        <th>Contact</th>
                        <th>Applied On</th>
                        <th style="text-align: right;">Review Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result && $result->num_rows > 0): ?>
                        <?php while ($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td>#<?php echo (int)$row['id']; ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($row['user_name'] ?? 'N/A'); ?></strong><br>
                                    <small style="color: #666;"><?php echo htmlspecialchars($row['user_email'] ?? 'N/A'); ?></small>
                                </td>
                                <td>
                                    <span class="badge badge-info"><?php echo htmlspecialchars($row['professional_type']); ?></span><br>
                                    <small><?php echo htmlspecialchars($row['qualification'] ?? 'Not specified'); ?></small>
                                </td>
                                <td><?php echo htmlspecialchars($row['experience_years'] ?? '0'); ?> years</td>
                                <td><?php echo htmlspecialchars($row['specialization'] ?? 'General'); ?></td>
                                <td>
                                    <?php echo htmlspecialchars($row['phone'] ?? 'N/A'); ?><br>
                                    <small style="color:#888;"><?php echo htmlspecialchars(($row['city'] ?? '') . ', ' . ($row['state'] ?? '')); ?></small>
                                </td>
                                <td><?php echo date('d-M-Y', strtotime($row['created_at'])); ?></td>
                                <td style="text-align: right;">
                                    <div class="admin-actions-cell" style="justify-content: flex-end;">
                                        <form method="POST" action="verify_professional.php" style="display:inline;">
                                            <?php echo csrf_field(); ?>
                                            <input type="hidden" name="application_id" value="<?php echo (int)$row['id']; ?>">
                                            <input type="hidden" name="user_id" value="<?php echo (int)$row['user_id']; ?>">
                                            <button type="submit" class="btn btn-primary btn-sm" style="background: #27ae60;">Approve</button>
                                        </form>
                                        <form method="POST" action="reject_professional.php" style="display:inline;" onsubmit="return confirm('Reject this application?');">
                                            <?php echo csrf_field(); ?>
                                            <input type="hidden" name="application_id" value="<?php echo (int)$row['id']; ?>">
                                            <button type="submit" class="btn btn-danger btn-sm">Reject</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 40px; color: #888;">
                                &#10003; No pending professional applications in queue.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../includes/admin_footer.php'; ?>
