<?php
// includes/admin_footer.php
?>
<footer class="site-footer" style="background: #ffffff; border-top: 1px solid #e5e5e0; margin-top: auto;">
    <div class="footer-content">
        <p>&copy; <?php echo date('Y'); ?> FinCart Administrative System. Authorized Personnel Only.</p>
        <p><a href="dashboard.php" style="color: inherit; text-decoration: none;">Admin Hub</a> &bull; <a href="../index.html" style="color: inherit; text-decoration: none;">Public Site</a></p>
    </div>
</footer>
<?php if (isset($extra_js)): echo $extra_js; endif; ?>
</body>
</html>

