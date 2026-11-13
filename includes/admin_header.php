<?php
// includes/admin_header.php
if (!isset($page_title)) {
    $page_title = 'FinCart Admin Control Panel';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/main.css">
    <link rel="stylesheet" href="/assets/css/admin.css">
    <!-- Turbo Drive for Instant SPA Transitions -->
    <meta name="turbo-cache-control" content="no-cache">
    <script src="https://cdn.jsdelivr.net/npm/@hotwired/turbo@8.0.12/dist/turbo.es2017-umd.js"></script>
    <script>
        // Prevent Turbo from intercepting HTML forms (forms submit natively for reliable PRG redirects)
        document.addEventListener('turbo:submit-start', function(e) {
            e.preventDefault();
        });
    </script>
    <style>
        .turbo-progress-bar {
            height: 3px;
            background: linear-gradient(90deg, #d4af37, #f3e5ab, #d4af37);
            box-shadow: 0 0 10px rgba(212, 175, 55, 0.6);
            z-index: 99999;
        }
    </style>
    <?php if (isset($extra_css)): echo $extra_css; endif; ?>
</head>
<body style="background: #f4f6f9;">

