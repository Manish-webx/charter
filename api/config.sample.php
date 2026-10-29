<?php
/**
 * Enquiry form configuration — SAMPLE. Contains no real credentials.
 *
 * 1. Copy this file to wwc-config.php in the folder ABOVE your web root
 *    (on Hostinger: the folder that contains public_html), so it can never be
 *    downloaded. As a fallback it may be saved as api/config.php, which the
 *    site's .htaccess blocks from direct access.
 * 2. Fill in the values below.
 * Environment variables (WWC_TO_EMAIL, WWC_SMTP_PASS, ...) override these values.
 */
return [
    // Inbox that receives enquiries.
    'to_email' => 'worldwidecharterservice@gmail.com',

    // Sender address. Must be a mailbox on your own domain (e.g. enquiries@yourdomain.com)
    // so SPF/DKIM pass; visitors' addresses go in Reply-To automatically.
    'from_email' => '',
    'from_name' => 'Worldwide Charters Website',

    // 'smtp' (recommended) or 'mail' (PHP mail(), less reliable delivery).
    'transport' => 'smtp',

    // Hostinger email: smtp.hostinger.com, port 465 + 'ssl' (or 587 + 'tls').
    'smtp_host' => 'smtp.hostinger.com',
    'smtp_port' => 465,
    'smtp_secure' => 'ssl',
    'smtp_user' => '',   // usually the same as from_email
    'smtp_pass' => '',

    // Optional: private writable folder for rate-limit counters (defaults to the system temp dir).
    'rate_limit_dir' => '',
];
