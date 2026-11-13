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
                <li><a href="investments.php" class="<?php echo in_array($current_page, ['investments.php', 'goals.php']) ? 'active' : ''; ?>">Investments</a></li>
                <li><a href="tax_planning.php" class="<?php echo in_array($current_page, ['tax_planning.php', 'tax_tracker.php', 'tax_summary.php', 'itr_preparation.php']) ? 'active' : ''; ?>">Tax Optimizer</a></li>
                <li><a href="professionals.php" class="<?php echo in_array($current_page, ['professionals.php', 'booking.php', 'my_appointments.php']) ? 'active' : ''; ?>">CA Experts</a></li>
            </ul>

            <div class="nav-actions">
                <a href="profile.php" class="btn btn-secondary btn-sm" title="My Profile Settings">
                    👤 <?php echo htmlspecialchars($user_display_name); ?>
                </a>
                <?php if ($user_role === 'admin'): ?>
                    <a href="admin/dashboard.php" class="btn btn-accent btn-sm" data-turbo="false">Admin Portal</a>
                <?php elseif ($user_role === 'professional' || $user_role === 'professional_approved' || $user_role === 'professional_pending'): ?>
                    <a href="professional/dashboard.php" class="btn btn-accent btn-sm" data-turbo="false">Pro Portal</a>
                <?php endif; ?>
                <a href="logout.php" class="btn btn-primary btn-sm" data-turbo="false">Logout</a>
            </div>
        </nav>
    </div>
</header>

<script>
// Responsive Navigation Toggle Handler with Global Event Delegation (Turbo-Resilient)
(function() {
    if (window.__fincartNavDelegated) return;
    window.__fincartNavDelegated = true;

    document.addEventListener('click', function(e) {
        const toggleBtn = document.getElementById('mobileNavToggle');
        const navMenu = document.getElementById('navMenu');
        if (!toggleBtn || !navMenu) return;

        // Hamburger button clicked
        if (toggleBtn.contains(e.target)) {
            e.stopPropagation();
            const isOpen = navMenu.classList.toggle('open');
            toggleBtn.classList.toggle('active', isOpen);
            toggleBtn.setAttribute('aria-expanded', isOpen);
            document.body.classList.toggle('nav-open', isOpen);
            return;
        }

        // Nav link clicked - close mobile drawer smoothly
        if (navMenu.contains(e.target) && e.target.closest('a')) {
            navMenu.classList.remove('open');
            toggleBtn.classList.remove('active');
            toggleBtn.setAttribute('aria-expanded', 'false');
            document.body.classList.remove('nav-open');
            return;
        }

        // Click outside open menu
        if (navMenu.classList.contains('open') && !navMenu.contains(e.target)) {
            navMenu.classList.remove('open');
            toggleBtn.classList.remove('active');
            toggleBtn.setAttribute('aria-expanded', 'false');
            document.body.classList.remove('nav-open');
        }
    });

    // Reset drawer state on every Turbo navigation render
    document.addEventListener('turbo:render', function() {
        document.body.classList.remove('nav-open');
    });
})();
</script>
