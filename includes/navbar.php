<?php
// includes/navbar.php
$current_page = basename($_SERVER['PHP_SELF']);
$user_display_name = $_SESSION['user_name'] ?? 'User';
$user_role = $_SESSION['role'] ?? 'user';
?>
<header class="site-header">
    <div class="nav-container">
        <a href="dashboard.php" class="site-logo">
            Fin<span>Cart</span>
        </a>

        <!-- Mobile Hamburger Toggle Button -->
        <button type="button" class="mobile-nav-toggle" id="mobileNavToggle" aria-label="Toggle navigation" aria-expanded="false">
            <span class="bar"></span>
            <span class="bar"></span>
            <span class="bar"></span>
        </button>

        <nav class="nav-menu" id="navMenu">
            <ul class="nav-links">
                <li><a href="dashboard.php" class="<?php echo $current_page === 'dashboard.php' ? 'active' : ''; ?>">Dashboard</a></li>
                <li><a href="transactions.php" class="<?php echo in_array($current_page, ['transactions.php', 'add_transaction.php']) ? 'active' : ''; ?>">Transactions</a></li>
                <li><a href="budget.php" class="<?php echo $current_page === 'budget.php' ? 'active' : ''; ?>">Budgets</a></li>
                <li><a href="investments.php" class="<?php echo $current_page === 'investments.php' ? 'active' : ''; ?>">Investments</a></li>
                <li><a href="goals.php" class="<?php echo $current_page === 'goals.php' ? 'active' : ''; ?>">Goals</a></li>
                <li><a href="analytics.php" class="<?php echo $current_page === 'analytics.php' ? 'active' : ''; ?>">Analytics</a></li>
                <li><a href="tax_planning.php" class="<?php echo in_array($current_page, ['tax_planning.php', 'tax_tracker.php', 'tax_summary.php', 'itr_preparation.php']) ? 'active' : ''; ?>">Tax Planning</a></li>
                <li><a href="professionals.php" class="<?php echo in_array($current_page, ['professionals.php', 'booking.php']) ? 'active' : ''; ?>">Experts</a></li>
                <li><a href="my_appointments.php" class="<?php echo $current_page === 'my_appointments.php' ? 'active' : ''; ?>">Appointments</a></li>
            </ul>

            <div class="nav-actions">
                <a href="profile.php" class="btn btn-secondary btn-sm" title="My Profile Settings">
                    👤 <?php echo htmlspecialchars($user_display_name); ?>
                </a>
                <?php if ($user_role === 'admin'): ?>
                    <a href="admin/dashboard.php" class="btn btn-accent btn-sm">Admin Portal</a>
                <?php elseif ($user_role === 'professional' || $user_role === 'professional_approved' || $user_role === 'professional_pending'): ?>
                    <a href="professional/dashboard.php" class="btn btn-accent btn-sm">Pro Portal</a>
                <?php endif; ?>
                <a href="logout.php" class="btn btn-primary btn-sm">Logout</a>
            </div>
        </nav>
    </div>
</header>

<script>
// Responsive Navigation Toggle Handler
(function() {
    const toggleBtn = document.getElementById('mobileNavToggle');
    const navMenu = document.getElementById('navMenu');
    if (!toggleBtn || !navMenu) return;

    toggleBtn.addEventListener('click', function(e) {
        e.stopPropagation();
        const isOpen = navMenu.classList.toggle('open');
        toggleBtn.classList.toggle('active', isOpen);
        toggleBtn.setAttribute('aria-expanded', isOpen);
        document.body.classList.toggle('nav-open', isOpen);
    });

    // Close on navigation link click (mobile)
    navMenu.querySelectorAll('a').forEach(function(link) {
        link.addEventListener('click', function() {
            navMenu.classList.remove('open');
            toggleBtn.classList.remove('active');
            toggleBtn.setAttribute('aria-expanded', 'false');
            document.body.classList.remove('nav-open');
        });
    });

    // Close on outside click
    document.addEventListener('click', function(e) {
        if (navMenu.classList.contains('open') && !navMenu.contains(e.target) && !toggleBtn.contains(e.target)) {
            navMenu.classList.remove('open');
            toggleBtn.classList.remove('active');
            toggleBtn.setAttribute('aria-expanded', 'false');
            document.body.classList.remove('nav-open');
        }
    });
})();
</script>
