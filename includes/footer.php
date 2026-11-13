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

<!-- Global Interaction & Double-Click Interceptor (Resilient, Non-Blocking) -->
<script>
(function() {
    function resetFormState(form) {
        if (!form) return;
        form.removeAttribute('data-submitting');
        const btns = form.querySelectorAll('button[type="submit"], input[type="submit"]');
        btns.forEach(function(btn) {
            btn.disabled = false;
            btn.style.pointerEvents = '';
            btn.style.opacity = '';
            const orig = btn.getAttribute('data-original-html');
            if (orig) {
                if (btn.tagName === 'INPUT') {
                    btn.value = orig;
                } else {
                    btn.innerHTML = orig;
                }
            }
        });
    }

    function resetAllForms() {
        document.querySelectorAll('form[data-submitting="true"]').forEach(resetFormState);
    }

    function setupFormProtection() {
        // Ensure all forms submit natively without Turbo fetch interception
        document.querySelectorAll('form').forEach(function(f) {
            if (!f.hasAttribute('data-turbo')) {
                f.setAttribute('data-turbo', 'false');
            }
        });

        // If client-side validation fails on any field, immediately unlock the button
        document.addEventListener('invalid', function(e) {
            if (e.target && e.target.form) {
                resetFormState(e.target.form);
            }
        }, true);

        document.addEventListener('submit', function(e) {
            const form = e.target;
            if (!form || form.nodeName !== 'FORM') return;

            // Ensure native submission
            if (!form.hasAttribute('data-turbo')) {
                form.setAttribute('data-turbo', 'false');
            }

            // Respect HTML5 client-side validation
            if (typeof form.checkValidity === 'function' && !form.checkValidity()) {
                resetFormState(form);
                return;
            }

            // Guard against double rapid clicks
            if (form.getAttribute('data-submitting') === 'true') {
                e.preventDefault();
                return false;
            }

            const submitBtn = e.submitter || form.querySelector('button[type="submit"], input[type="submit"]');
            if (submitBtn) {
                form.setAttribute('data-submitting', 'true');
                if (!submitBtn.getAttribute('data-original-html')) {
                    submitBtn.setAttribute('data-original-html', submitBtn.innerHTML || submitBtn.value);
                }
                const loadingText = submitBtn.getAttribute('data-loading-text') || '⏳ Processing...';

                // Provide immediate visual feedback without canceling form data submission
                if (submitBtn.tagName === 'INPUT') {
                    submitBtn.value = loadingText;
                } else {
                    submitBtn.innerHTML = loadingText;
                }
                submitBtn.style.opacity = '0.75';
                submitBtn.style.pointerEvents = 'none';

                // Auto-recovery failsafe: restore submit button after 5 seconds so user is never trapped
                setTimeout(function() {
                    resetFormState(form);
                }, 5000);
            }
        }, true);

        // Reset submit buttons on navigation, back/forward cache, and Turbo events
        window.addEventListener('pageshow', resetAllForms);
        window.addEventListener('turbo:render', resetAllForms);
        window.addEventListener('turbo:load', resetAllForms);
        window.addEventListener('turbo:submit-end', resetAllForms);
        window.addEventListener('beforeunload', resetAllForms);
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

