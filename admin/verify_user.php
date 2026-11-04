<?php
include '../auth_admin.php';
include '../db.php';

$page_title = 'Verify Users - FinCart Admin';

// Handle POST verification actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'], $_POST['action'])) {
    $id = (int)$_POST['id'];
    $val = ($_POST['action'] === 'verify') ? 1 : 0;
    $stmt = $conn->prepare("UPDATE users SET verified = ? WHERE id = ?");
    $stmt->bind_param('ii', $val, $id);
    $stmt->execute();
    header('Location: verify_user.php?msg=success');
    exit();
}

$result = $conn->query("SELECT id, full_name, email, role, verified, created_at FROM users WHERE role != 'admin' ORDER BY verified ASC, created_at DESC");

include '../includes/admin_header.php';
include '../includes/admin_navbar.php';
?>

<div class="admin-container">
    <div class="admin-header-row">
        <div>
            <h1 class="admin-title">User Account Verification</h1>
            <p class="admin-subtitle">Verify user identity documents and mark accounts as trusted.</p>
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
                        <th style="width: 60px;">ID</th>
                        <th>User Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Registered Date</th>
                        <th style="text-align: right;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result && $result->num_rows > 0): ?>
                        <?php while ($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td>#<?php echo (int)$row['id']; ?></td>
                                <td><strong><?php echo htmlspecialchars($row['full_name'] ?? 'N/A'); ?></strong></td>
                                <td><?php echo htmlspecialchars($row['email']); ?></td>
                                <td><span class="badge badge-inf⭐><?php echo htmlspecialchars($row['role']); ?></span></td>
                                <td>
                                    <?php if (!empty($row['verified'])): ?>
                                        <span class="badge badge-success">&#10003; Verified</span>
                                    <?php else: ?>
                                        <span class="badge badge-warning">Unverified</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo date('d-M-Y', strtotime($row['created_at'])); ?></td>
                                <td style="text-align: right;">
                                    <?php if (empty($row['verified'])): ?>
                                        <form method="POST" action="verify_user.php" style="display:inline;">
                                            <?php echo csrf_field(); ?>
                                            <input type="hidden" name="id" value="<?php echo (int)$row['id']; ?>">
                                            <input type="hidden" name="action" value="verify">
                                            <button type="submit" class="btn btn-accent btn-sm">Mark Verified</button>
                                        </form>
                                    <?php else: ?>
                                        <form method="POST" action="verify_user.php" style="display:inline;">
                                            <?php echo csrf_field(); ?>
                                            <input type="hidden" name="id" value="<?php echo (int)$row['id']; ?>">
                                            <input type="hidden" name="action" value="unverify">
                                            <button type="submit" class="btn btn-secondary btn-sm">Revoke</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 30px; color: #888;">
                                No users currently require verification.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../includes/admin_footer.php'; ?>
