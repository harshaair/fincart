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
<?php if (isset($extra_js)): echo $extra_js; endif; ?>
</body>
</html>

