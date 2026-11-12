<?php
// includes/footer.php
?>
<footer class="site-footer">
    <div class="footer-content">
        <p>&copy; <?php echo date('Y'); ?> FinCart Technologies. All rights reserved. Secure Personal Finance & Indian Tax Management.</p>
        <div>
            <a href="aboutus.html" style="color: inherit; text-decoration: none; margin-right: 15px;">About</a>
            <a href="service.html" style="color: inherit; text-decoration: none; margin-right: 15px;">Services</a>
            <a href="tax_planning.php" style="color: inherit; text-decoration: none;">Tax Module</a>
        </div>
    </div>
</footer>

<!-- Global Interaction & Double-Click Interceptor -->
<script>
(function() {
    function setupFormProtection() {
        document.addEventListener('submit', function(e) {
            const form = e.target;
            if (!form || form.nodeName !== 'FORM') return;

            // Respect HTML5 client-side validation
            if (typeof form.checkValidity === 'function' && !form.checkValidity()) {
                return;
            }

            // Guard against duplicate submissions
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
                
                // Allow current submit cycle to proceed before disabling
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

        // Reset submit buttons on Turbo navigation or browser back/forward
        window.addEventListener('pageshow', function() {
            document.querySelectorAll('form[data-submitting="true"]').forEach(function(f) {
                f.removeAttribute('data-submitting');
                const btn = f.querySelector('button[type="submit"], input[type="submit"]');
                if (btn && btn.getAttribute('data-original-html')) {
                    btn.disabled = false;
                    if (btn.tagName === 'INPUT') {
                        btn.value = btn.getAttribute('data-original-html');
                    } else {
                        btn.innerHTML = btn.getAttribute('data-original-html');
                    }
                }
            });
        });

        document.addEventListener('turbo:render', function() {
            document.querySelectorAll('form[data-submitting="true"]').forEach(function(f) {
                f.removeAttribute('data-submitting');
            });
        });
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

