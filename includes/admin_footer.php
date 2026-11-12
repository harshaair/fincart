<?php
// includes/admin_footer.php
?>
<footer class="site-footer" style="background: #ffffff; border-top: 1px solid #e5e5e0; margin-top: auto;">
    <div class="footer-content">
        <p>&copy; <?php echo date('Y'); ?> FinCart Administrative System. Authorized Personnel Only.</p>
        <p><a href="dashboard.php" style="color: inherit; text-decoration: none;">Admin Hub</a> &bull; <a href="../index.html" style="color: inherit; text-decoration: none;">Public Site</a></p>
    </div>
</footer>

<!-- Global Admin Interaction & Double-Click Interceptor -->
<script>
(function() {
    function setupFormProtection() {
        document.addEventListener('submit', function(e) {
            const form = e.target;
            if (!form || form.nodeName !== 'FORM') return;
            if (typeof form.checkValidity === 'function' && !form.checkValidity()) return;
            if (form.getAttribute('data-submitting') === 'true') {
                e.preventDefault();
                return false;
            }
            const submitBtn = form.querySelector('button[type="submit"], input[type="submit"]');
            if (submitBtn) {
                form.setAttribute('data-submitting', 'true');
                if (!submitBtn.getAttribute('data-original-html')) {
                    submitBtn.setAttribute('data-original-html', submitBtn.innerHTML || submitBtn.value);
                }
                const loadingText = submitBtn.getAttribute('data-loading-text') || '⏳ Processing...';
                setTimeout(function() {
                    submitBtn.disabled = true;
                    if (submitBtn.tagName === 'INPUT') {
                        submitBtn.value = loadingText;
                    } else {
                        submitBtn.innerHTML = loadingText;
                    }
                }, 10);
            }
        }, true);
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', setupFormProtection);
    } else {
        setupFormProtection();
    }
})();
</script>

<?php if (isset($extra_js)): echo $extra_js; endif; ?>
</body>
</html>

