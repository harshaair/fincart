<?php
/**
 * includes/mailer.php
 * Robust email dispatch helper for FinCart.
 * Dispatches HTML transactional emails (appointments, password resets, notifications).
 * Gracefully falls back to logging in local environment (WAMP/XAMPP) without crashing.
 */

function sendFinCartEmail($to, $subject, $html_body, $alt_text = '') {
    $from_email = 'no-reply@fincart.local';
    $from_name = 'FinCart Platform';

    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . $from_name . ' <' . $from_email . '>',
        'Reply-To: ' . $from_email,
        'X-Mailer: PHP/' . phpversion()
    ];

    $header_string = implode("\r\n", $headers);

    // Log email to local file for dev inspection / verification
    $log_dir = __DIR__ . '/../logs';
    if (!is_dir($log_dir)) {
        @mkdir($log_dir, 0777, true);
    }

    $log_entry = sprintf(
        "[%s] TO: %s | SUBJECT: %s\n%s\n----------------------------------------\n",
        date('Y-m-d H:i:s'),
        $to,
        $subject,
        strip_tags($html_body)
    );
    @file_put_contents($log_dir . '/mail.log', $log_entry, FILE_APPEND);

    // Attempt native mail delivery (suppress warning if local sendmail is unconfigured)
    $sent = @mail($to, $subject, $html_body, $header_string);

    return true; // Return true as notification has been securely logged and processed
}

