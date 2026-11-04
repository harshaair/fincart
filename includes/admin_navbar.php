<?php
// includes/admin_navbar.php
$admin_page = basename($_SERVER['PHP_SELF']);
$admin_name = $_SESSION['user_name'] ?? 'Admin';
?>
<header class="site-header" style="background: #1a1a1a; border-bottom: 2px solid #d4af37;">
    <div class="nav-container">
        <a href="dashboard.php" class="site-log⭐ style="color: #ffffff;">
            Fin<span style="color: #d4af37;">Cart</span> <span style="font-size: 13px; font-weight: 500; background: #333; padding: 3px 8px; border-radius: 4px; color: #d4af37; margin-left: 8px;">ADMIN</span>
        </a>
        <nav>
            <ul class="nav-links">
                <li><a href="dashboard.php" style="color: <?php echo $admin_page === 'dashboard.php' ? '#d4af37' : '#cccccc'; ?>;">Dashboard</a></li>
                <li><a href="users.php" style="color: <?php echo in_array($admin_page, ['users.php', 'edit_user.php']) ? '#d4af37' : '#cccccc'; ?>;">Users</a></li>
                <li><a href="verify_user.php" style="color: <?php echo $admin_page === 'verify_user.php' ? '#d4af37' : '#cccccc'; ?>;">User Verification</a></li>
                <li><a href="professionals.php" style="color: <?php echo $admin_page === 'professionals.php' ? '#d4af37' : '#cccccc'; ?>;">Professionals</a></li>
                <li><a href="requests.php" style="color: <?php echo $admin_page === 'requests.php' ? '#d4af37' : '#cccccc'; ?>;">Applications</a></li>
                <li><a href="reports.php" style="color: <?php echo $admin_page === 'reports.php' ? '#d4af37' : '#cccccc'; ?>;">Reports</a></li>
            </ul>
        </nav>
        <div class="nav-actions">
            <span style="color: #aaaaaa; font-size: 13px;">Logged in as <strong style="color: #fff;"><?php echo htmlspecialchars($admin_name); ?></strong></span>
            <a href="../dashboard.php" class="btn btn-secondary btn-sm" style="color: #fff; border-color: #555;">User View</a>
            <a href="../logout.php" class="btn btn-accent btn-sm">Logout</a>
        </div>
    </div>
</header>
