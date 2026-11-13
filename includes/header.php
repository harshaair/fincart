<?php
// includes/header.php
if (!isset($page_title)) {
    $page_title = 'FinCart - Personal Finance & Tax Management';
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
    <link rel="stylesheet" href="<?php echo isset($css_path) ? $css_path : '/assets/css/main.css'; ?>">
    <!-- Global Chart.js (Cached Once) -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
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
    <script>
        // Universal collision-safe Chart mounting helper for Turbo SPA page transitions
        window.renderChartSafe = function(canvasIdOrElem, config) {
            const elem = typeof canvasIdOrElem === 'string' ? document.getElementById(canvasIdOrElem) : canvasIdOrElem;
            if (!elem || typeof Chart === 'undefined') return null;
            try {
                const existing = Chart.getChart(elem);
                if (existing) {
                    existing.destroy();
                }
            } catch (err) {
                // Ignore cleanup error if canvas was fresh
            }
            return new Chart(elem, config);
        };
    </script>
    <?php if (isset($extra_css)): echo $extra_css; endif; ?>
</head>
<body>

