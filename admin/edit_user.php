<?php
include '../auth_admin.php';
include '../db.php';

if (!isset($_GET['id'])) {
    header('Location: users.php');
    exit();
}

$id = intval($_GET['id']);
$stmt = $conn->prepare("SELECT id, full_name, email, role, verified FROM users WHERE id = ? LIMIT 1");
$stmt->bind_param('i', $id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) {
    header('Location: users.php');
    exit();
}

$page_title = 'Edit User #' . $user['id'] . ' - FinCart Admin';

include '../includes/admin_header.php';
include '../includes/admin_navbar.php';
?>

<div class="admin-container">
    <div class="admin-header-row">
        <div>
            <h1 class="admin-title">Edit User Account #<?php echo $user['id']; ?></h1>
            <p class="admin-subtitle">Update user profile, permissions, and account verification status.</p>
        </div>
        <div>
            <a href="users.php" class="btn btn-secondary btn-sm">← Back← Back to Users</a>
        </div>
    </div>

    <div class="admin-card" style="max-width: 640px;">
        <form method="POST" action="update_user.php">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="id" value="<?php echo $user['id']; ?>">
            
            <div class="form-group">
                <label>Full Name</label>
                <input type="text" name="full_name" class="form-control" value="<?php echo htmlspecialchars($user['full_name'] ?? ''); ?>" required>
            </div>

            <div class="form-group">
                <label>Email Address</label>
                <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" required>
            </div>

            <div class="form-group">
                <label>Access Role</label>
                <select name="role" class="form-control">
                    <option value="user" <?php echo $user['role'] === 'user' ? 'selected' : ''; ?>>Standard User</option>
                    <option value="professional_pending" <?php echo $user['role'] === 'professional_pending' ? 'selected' : ''; ?>>Professional (Pending Approval)</option>
                    <option value="professional" <?php echo $user['role'] === 'professional' ? 'selected' : ''; ?>>Verified Professional (CA/Advisor)</option>
                    <option value="admin" <?php echo $user['role'] === 'admin' ? 'selected' : ''; ?>>System Administrator</option>
                </select>
            </div>

            <div class="form-group">
                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                    <input type="checkbox" name="verified" value="1" <?php echo !empty($user['verified']) ? 'checked' : ''; ?>>
                    <span>Account Verified</span>
                </label>
            </div>

            <div style="display: flex; gap: 12px; margin-top: 25px;">
                <button type="submit" class="btn btn-primary">Save Changes</button>
                <a href="users.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php include '../includes/admin_footer.php'; ?>
