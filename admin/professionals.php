<?php
include '../auth_admin.php';
include '../db.php';

$page_title = 'Manage Professionals - FinCart Admin';

$sql = "SELECT p.*, u.email AS user_email, u.full_name AS user_name 
        FROM professionals p 
        LEFT JOIN users u ON p.user_id = u.id 
        ORDER BY p.created_at DESC";
$result = $conn->query($sql);

include '../includes/admin_header.php';
include '../includes/admin_navbar.php';
?>

<div class="admin-container">
    <div class="admin-header-row">
        <div>
            <h1 class="admin-title">Professional Directory Management</h1>
            <p class="admin-subtitle">Manage registered financial experts, chartered accountants, and tax advisors.</p>
        </div>
        <div>
            <a href="dashboard.php" class="btn btn-secondary btn-sm">← Back← Back to Dashboard</a>
        </div>
    </div>

    <div class="admin-card">
        <div class="table-responsive">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th style="width: 50px;">ID</th>
                        <th>Professional</th>
                        <th>Type & Specialization</th>
                        <th>Linked Account</th>
                        <th>Fee</th>
                        <th>Status</th>
                        <th style="text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result && $result->num_rows > 0): ?>
                        <?php while ($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td>#<?php echo (int)$row['id']; ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($row['name']); ?></strong><br>
                                    <small style="color: #666;"><?php echo htmlspecialchars($row['qualification'] ?? ''); ?> &bull; <?php echo (int)($row['experience_years'] ?? 0); ?> yrs exp</small>
                                </td>
                                <td>
                                    <span class="badge badge-info"><?php echo htmlspecialchars($row['professional_type']); ?></span><br>
                                    <small style="color:#777;"><?php echo htmlspecialchars($row['specialization'] ?? ''); ?></small>
                                </td>
                                <td>
                                    <?php if ($row['user_id']): ?>
                                        <a href="edit_user.php?id=<?php echo (int)$row['user_id']; ?>" style="color: var(--accent); text-decoration: none;">
                                            <?php echo htmlspecialchars($row['user_name'] ?? $row['user_email']); ?>
                                        </a>
                                    <?php else: ?>
                                        <span style="color: #aaa;">Independent Profile</span>
                                    <?php endif; ?>
                                </td>
                                <td>₹<?php echo number_format($row['consultation_fee'] ?? 0, 2); ?></td>
                                <td>
                                    <?php if ($row['status'] === 'active'): ?>
                                        <span class="badge badge-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge badge-warning"><?php echo htmlspecialchars($row['status']); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: right;">
                                    <div class="admin-actions-cell" style="justify-content: flex-end;">
                                        <?php if ($row['status'] === 'active'): ?>
                                            <form method="POST" action="revoke_professional.php" style="display:inline;" onsubmit="return confirm('Revoke active status for this professional?');">
                                            <?php echo csrf_field(); ?>
                                                <input type="hidden" name="id" value="<?php echo (int)$row['id']; ?>">
                                                <button type="submit" class="btn btn-secondary btn-sm">Revoke</button>
                                            </form>
                                        <?php else: ?>
                                            <form method="POST" action="verify_professional.php" style="display:inline;">
                                            <?php echo csrf_field(); ?>
                                                <input type="hidden" name="id" value="<?php echo (int)$row['id']; ?>">
                                                <button type="submit" class="btn btn-primary btn-sm" style="background: #27ae60;">Activate</button>
                                            </form>
                                        <?php endif; ?>
                                        <form method="POST" action="delete_professional.php" style="display:inline;" onsubmit="return confirm('Delete this professional directory listing?');">
                                            <?php echo csrf_field(); ?>
                                            <input type="hidden" name="id" value="<?php echo (int)$row['id']; ?>">
                                            <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 30px; color: #888;">
                                No professionals currently listed in directory.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../includes/admin_footer.php'; ?>
