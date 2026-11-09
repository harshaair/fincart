<?php
include '../auth_admin.php';
include '../db.php';

$page_title = 'User Management - FinCart Admin';

// Optional search filter
$search = trim($_GET['q'] ?? '');
if ($search !== '') {
    $stmt = $conn->prepare("SELECT id, full_name, email, role, verified, created_at FROM users WHERE full_name LIKE ? OR email LIKE ? ORDER BY created_at DESC");
    $term = "%{$search}%";
    $stmt->bind_param("ss", $term, $term);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $sql = "SELECT id, full_name, email, role, verified, created_at FROM users ORDER BY created_at DESC";
    $result = $conn->query($sql);
}

include '../includes/admin_header.php';
include '../includes/admin_navbar.php';
?>

<div class="admin-container">
    <div class="admin-header-row">
        <div>
            <h1 class="admin-title">User Accounts Directory</h1>
            <p class="admin-subtitle">Inspect user accounts, manage permission roles, and maintain security credentials.</p>
        </div>
        <div>
            <a href="dashboard.php" class="btn btn-secondary btn-sm">← Back← Back to Dashboard</a>
        </div>
    </div>

    <div class="admin-card">
        <form method="GET" style="display: flex; gap: 12px; margin-bottom: 20px;">
            <input type="text" name="q" class="form-control" placeholder="Search users by name or email..." value="<?php echo htmlspecialchars($search); ?>" style="max-width: 380px;">
            <button type="submit" class="btn btn-primary btn-sm">Search</button>
            <?php if ($search !== ''): ?>
                <a href="users.php" class="btn btn-secondary btn-sm">Clear</a>
            <?php endif; ?>
        </form>

        <div class="table-responsive">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th style="width: 60px;">ID</th>
                        <th>Full Name</th>
                        <th>Email Address</th>
                        <th>Role</th>
                        <th>Verified</th>
                        <th>Created At</th>
                        <th style="text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result && $result->num_rows > 0): ?>
                        <?php while ($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td>#<?php echo (int)$row['id']; ?></td>
                                <td><strong><?php echo htmlspecialchars($row['full_name'] ?? 'N/A'); ?></strong></td>
                                <td><?php echo htmlspecialchars($row['email']); ?></td>
                                <td>
                                    <?php
                                    $role = $row['role'];
                                    if ($role === 'admin') echo '<span class="badge badge-info">Admin</span>';
                                    elseif ($role === 'professional') echo '<span class="badge badge-success">Professional</span>';
                                    elseif ($role === 'professional_pending') echo '<span class="badge badge-warning">Pending Approval</span>';
                                    else echo '<span class="badge badge-secondary" style="background:#eee; color:#444;">User</span>';
                                    ?>
                                </td>
                                <td>
                                    <?php echo !empty($row['verified']) ? '<span style="color:#27ae60; font-weight:600;">&#10003; Yes</span>' : '<span style="color:#999;">No</span>'; ?>
                                </td>
                                <td><?php echo date('d-M-Y', strtotime($row['created_at'])); ?></td>
                                <td style="text-align: right;">
                                    <div class="admin-actions-cell" style="justify-content: flex-end;">
                                        <a href="edit_user.php?id=<?php echo (int)$row['id']; ?>" class="btn btn-secondary btn-sm">Edit</a>
                                        <?php if ($row['role'] !== 'admin'): ?>
                                            <form method="POST" action="delete_user.php" style="display:inline;" onsubmit="return confirm('Are you sure you want to permanently delete user <?php echo htmlspecialchars($row['full_name'] ?? $row['email']); ?>?');">
                                                <?php echo csrf_field(); ?>
                                                <input type="hidden" name="id" value="<?php echo (int)$row['id']; ?>">
                                                <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 30px; color: #888;">
                                No user accounts matching the specified query.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../includes/admin_footer.php'; ?>
