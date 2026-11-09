<?php
// includes/admin_navbar.php
$admin_page = basename($_SERVER['PHP_SELF']);
$admin_name = $_SESSION['user_name'] ?? 'Admin';
?>
<header class="site-header" style="background: #111418; border-bottom: 2px solid #d4af37;">
    <div class="nav-container">
        <a href="dashboard.php" class="site-logo" style="color: #ffffff;">
            Fin<span style="color: #d4af37;">Cart</span> <span style="font-size: 11px; font-weight: 700; letter-spacing: 0.05em; background: rgba(212,175,55,0.15); border: 1px solid rgba(212,175,55,0.4); padding: 3px 8px; border-radius: 4px; color: #d4af37; margin-left: 8px;">ADMIN</span>
        </a>

        <!-- Mobile Hamburger Toggle Button -->
        <button type="button" class="mobile-nav-toggle" id="adminNavToggle" aria-label="Toggle navigation" aria-expanded="false" style="border-color: rgba(212,175,55,0.4);">
            <span class="bar" style="background: #d4af37;"></span>
            <span class="bar" style="background: #d4af37;"></span>
            <span class="bar" style="background: #d4af37;"></span>
        </button>

        <nav class="nav-menu" id="adminNavMenu">
            <ul class="nav-links">
                <li><a href="dashboard.php" style="color: <?php echo $admin_page === 'dashboard.php' ? '#d4af37' : '#cccccc'; ?>;">Dashboard</a></li>
                <li><a href="users.php" style="color: <?php echo in_array($admin_page, ['users.php', 'edit_user.php']) ? '#d4af37' : '#cccccc'; ?>;">Users</a></li>
                <li><a href="verify_user.php" style="color: <?php echo $admin_page === 'verify_user.php' ? '#d4af37' : '#cccccc'; ?>;">Verification</a></li>
                <li><a href="professionals.php" style="color: <?php echo $admin_page === 'professionals.php' ? '#d4af37' : '#cccccc'; ?>;">Professionals</a></li>
                <li><a href="requests.php" style="color: <?php echo $admin_page === 'requests.php' ? '#d4af37' : '#cccccc'; ?>;">Applications</a></li>
                <li><a href="reports.php" style="color: <?php echo $admin_page === 'reports.php' ? '#d4af37' : '#cccccc'; ?>;">Reports</a></li>
            </ul>

            <div class="nav-actions">
                <span style="color: #aaaaaa; font-size: 13px;">Admin: <strong style="color: #fff;"><?php echo htmlspecialchars($admin_name); ?></strong></span>
                <a href="../dashboard.php" class="btn btn-secondary btn-sm" style="color: #fff; border-color: #555;" data-turbo="false">Client View</a>
                <a href="../logout.php" class="btn btn-accent btn-sm" data-turbo="false">Logout</a>
            </div>
        </nav>
    </div>
</header>

<script>
(function() {
    const toggleBtn = document.getElementById('adminNavToggle');
    const navMenu = document.getElementById('adminNavMenu');
    if (!toggleBtn || !navMenu) return;

    toggleBtn.addEventListener('click', function(e) {
        e.stopPropagation();
        const isOpen = navMenu.classList.toggle('open');
        toggleBtn.classList.toggle('active', isOpen);
        toggleBtn.setAttribute('aria-expanded', isOpen);
    });

    document.addEventListener('click', function(e) {
        if (navMenu.classList.contains('open') && !navMenu.contains(e.target) && !toggleBtn.contains(e.target)) {
            navMenu.classList.remove('open');
            toggleBtn.classList.remove('active');
            toggleBtn.setAttribute('aria-expanded', 'false');
        }
    });
})();
</script>
