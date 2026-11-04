<?php
include '../auth_admin.php';
include '../db.php';

$page_title = 'Admin Dashboard - FinCart';

// Fetch summary metrics
$user_count = 0;
$res = $conn->query("SELECT COUNT(*) AS c FROM users WHERE role != 'admin'");
if ($res) { $r = $res->fetch_assoc(); $user_count = (int)$r['c']; }

$pro_count = 0;
$res = $conn->query("SELECT COUNT(*) AS c FROM professionals WHERE status = 'active'");
if ($res) { $r = $res->fetch_assoc(); $pro_count = (int)$r['c']; }

$pending_apps = 0;
$res = $conn->query("SELECT COUNT(*) AS c FROM professional_applications WHERE status = 'pending'");
if ($res) { $r = $res->fetch_assoc(); $pending_apps = (int)$r['c']; }

$total_tx = 0;
$res = $conn->query("SELECT COUNT(*) AS c FROM transactions");
if ($res) { $r = $res->fetch_assoc(); $total_tx = (int)$r['c']; }

$total_apts = 0;
$res = $conn->query("SELECT COUNT(*) AS c FROM appointments");
if ($res) { $r = $res->fetch_assoc(); $total_apts = (int)$r['c']; }

include '../includes/admin_header.php';
include '../includes/admin_navbar.php';
?>

<div class="admin-container">
    <div class="admin-header-row">
        <div>
            <h1 class="admin-title">Admin Operations Console</h1>
            <p class="admin-subtitle">Welcome back, <strong><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Administrator'); ?></strong>. Platform overview and quick controls.</p>
        </div>
        <div>
            <a href="reports.php" class="btn btn-primary">Generate Reports &rarr;</a>
        </div>
    </div>

    <!— Quick Metrics Grid —>
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-label">Registered Users</div>
            <div class="stat-value"><?php echo number_format($user_count); ?></div>
            <div class="stat-description"><a href="users.php" style="color: var(—accent); text-decoration: none;">Manage users &rarr;</a></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Active Professionals</div>
            <div class="stat-value"><?php echo number_format($pro_count); ?></div>
            <div class="stat-description"><a href="professionals.php" style="color: var(—accent); text-decoration: none;">View directory &rarr;</a></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Pending Applications</div>
            <div class="stat-value" style="color: <?php echo $pending_apps > 0 ? '#e74c3c' : '#27ae60'; ?>;">
                <?php echo number_format($pending_apps); ?>
            </div>
            <div class="stat-description"><a href="requests.php" style="color: var(—accent); text-decoration: none;">Review queue &rarr;</a></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Total Transactions</div>
            <div class="stat-value"><?php echo number_format($total_tx); ?></div>
            <div class="stat-description">Across all active accounts</div>
        </div>
    </div>

    <!— Action Modules —>
    <div class="admin-card">
        <h2 style="font-size: 20px; margin-bottom: 20px;">Platform Management Modules</h2>
        <div class="admin-grid-cards">
            <div class="admin-quick-card">
                <div>
                    <h3 style="font-size: 18px; margin-bottom: 8px;">User Management</h3>
                    <p style="color: #666; font-size: 13px; margin-bottom: 15px;">Search, edit roles, inspect accounts, and control platform access.</p>
                </div>
                <a href="users.php" class="btn btn-secondary btn-sm">Manage Users &rarr;</a>
            </div>
            <div class="admin-quick-card">
                <div>
                    <h3 style="font-size: 18px; margin-bottom: 8px;">Professional Approvals</h3>
                    <p style="color: #666; font-size: 13px; margin-bottom: 15px;">Review CA and financial advisor applications, credentials, and approve/reject.</p>
                </div>
                <a href="requests.php" class="btn btn-secondary btn-sm">Review Applications (<?php echo $pending_apps; ?>) &rarr;</a>
            </div>
            <div class="admin-quick-card">
                <div>
                    <h3 style="font-size: 18px; margin-bottom: 8px;">Active Professionals</h3>
                    <p style="color: #666; font-size: 13px; margin-bottom: 15px;">Maintain expert directory, manage specializations, fees, and revocations.</p>
                </div>
                <a href="professionals.php" class="btn btn-secondary btn-sm">View Professionals &rarr;</a>
            </div>
            <div class="admin-quick-card">
                <div>
                    <h3 style="font-size: 18px; margin-bottom: 8px;">Audit & Reports</h3>
                    <p style="color: #666; font-size: 13px; margin-bottom: 15px;">Comprehensive platform analytics, user engagement, and transaction samples.</p>
                </div>
                <a href="reports.php" class="btn btn-secondary btn-sm">View System Reports &rarr;</a>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/admin_footer.php'; ?>
