<?php
/**
 * Worldwide Charters — enquiry form handler.
 *
 * Receives POST submissions from /contact-us/, validates them server-side and
 * emails them to the company inbox via authenticated SMTP (recommended) or
 * PHP mail(). Configuration is read, in order of priority, from:
 *   1. Environment variables (WWC_SMTP_HOST, WWC_SMTP_PASS, ...), see README.md
 *   2. wwc-config.php placed ONE LEVEL ABOVE the web root (e.g. next to public_html)
 *   3. api/config.php (fallback only; blocked from direct access by .htaccess)
 * See api/config.sample.php for every option. Never commit real credentials.
 */

declare(strict_types=1);

const CATEGORIES = [
    'charter-operations' => 'Charter Operations',
    'aircraft-leasing' => 'Aircraft Leasing & Hiring',
    'fleet-management' => 'Fleet Management',
    'air-transportation' => 'Cargo & Passenger Transportation',
    'aircraft-sales-acquisition' => 'Aircraft Sales & Acquisition',
    'aviation-infrastructure' => 'Aviation Infrastructure Support',
    'partnership' => 'Partnership',
    'general' => 'General Enquiry',
];

const MIN_FILL_SECONDS = 3;
const MAX_FORM_AGE_SECONDS = 86400;
const RATE_LIMIT_MAX = 5;
const RATE_LIMIT_WINDOW = 900;
const MAX_LINKS_IN_MESSAGE = 3;

header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex');

$wantsJson = stripos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false;

function respond(int $status, array $body): void
{
    global $wantsJson;
    if (!$wantsJson) {
        // No-JavaScript fallback: return the visitor to the form with a status flag.
        $query = !empty($body['ok']) ? 'sent=1' : 'error=' . ($status === 422 ? 'validation' : 'send');
        header('Location: /contact-us.html?' . $query . '#enquiry', true, 303);
        exit;
    }
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function load_config(): array
{
    $config = [
        'to_email' => 'worldwidecharterservice@gmail.com',
        'from_email' => '',
        'from_name' => 'Worldwide Charters Website',
        'transport' => 'smtp',
        'smtp_host' => '',
        'smtp_port' => 465,
        'smtp_secure' => 'ssl',
        'smtp_user' => '',
        'smtp_pass' => '',
        'rate_limit_dir' => '',
    ];
    foreach ([dirname(__DIR__, 2) . '/wwc-config.php', __DIR__ . '/config.php'] as $file) {
        if (is_file($file)) {
            $fileConfig = require $file;
            if (is_array($fileConfig)) {
                $config = array_merge($config, $fileConfig);
            }
            break;
        }
    }
    foreach (array_keys($config) as $key) {
        $env = getenv('WWC_' . strtoupper($key));
        if ($env !== false && $env !== '') {
            $config[$key] = $env;
        }
    }
    $config['smtp_port'] = (int) $config['smtp_port'];
    return $config;
}

function clean_line(string $value, int $max): string
{
    $value = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $value) ?? '';
    $value = trim(preg_replace('/\s+/u', ' ', $value) ?? '');
    return mb_substr($value, 0, $max);
}

function clean_text(string $value, int $max): string
{
    $value = str_replace(["\r\n", "\r"], "\n", $value);
    $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? '';
    return mb_substr(trim($value), 0, $max);
}

function client_ip(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function rate_limited(array $config): bool
{
    $dir = $config['rate_limit_dir'] ?: sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'wwc-enquiry';
    if (!is_dir($dir) && !@mkdir($dir, 0700, true)) {
        return false; // Fail open: never block genuine visitors because storage is unavailable.
    }
    $file = $dir . DIRECTORY_SEPARATOR . hash('sha256', client_ip()) . '.json';
    $now = time();
    $fp = @fopen($file, 'c+');
    if (!$fp) {
        return false;
    }
    flock($fp, LOCK_EX);
    $hits = json_decode(stream_get_contents($fp) ?: '[]', true);
    $hits = array_values(array_filter(is_array($hits) ? $hits : [], fn($t) => is_int($t) && $t > $now - RATE_LIMIT_WINDOW));
    $limited = count($hits) >= RATE_LIMIT_MAX;
    if (!$limited) {
        $hits[] = $now;
    }
    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, json_encode($hits));
    flock($fp, LOCK_UN);
    fclose($fp);
    return $limited;
}

function encode_header(string $value): string
{
    return preg_match('/[^\x20-\x7E]/', $value) ? '=?UTF-8?B?' . base64_encode($value) . '?=' : $value;
}

/** Display names are always encoded so characters such as ":" "@" "," can't alter address parsing. */
function encode_name(string $value): string
{
    return '=?UTF-8?B?' . base64_encode($value) . '?=';
}

/** Minimal SMTP client (implicit TLS on 465 or STARTTLS on 587) with AUTH LOGIN. */
function smtp_send(array $c, string $from, string $to, string $data): void
{
    $remote = ($c['smtp_secure'] === 'ssl' ? 'ssl://' : 'tcp://') . $c['smtp_host'] . ':' . $c['smtp_port'];
    $ctx = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true]]);
    $socket = @stream_socket_client($remote, $errno, $errstr, 20, STREAM_CLIENT_CONNECT, $ctx);
    if (!$socket) {
        throw new RuntimeException("SMTP connect failed: $errstr ($errno)");
    }
    stream_set_timeout($socket, 20);

    $read = function () use ($socket): string {
        $response = '';
        while (($line = fgets($socket, 515)) !== false) {
            $response .= $line;
            if (strlen($line) < 4 || $line[3] === ' ') {
                break;
            }
        }
        return $response;
    };
    $cmd = function (?string $line, array $expect) use ($socket, $read): string {
        if ($line !== null) {
            fwrite($socket, $line . "\r\n");
        }
        $response = $read();
        if (!in_array((int) substr($response, 0, 3), $expect, true)) {
            // Never include the command itself: it may carry encoded credentials.
            throw new RuntimeException('SMTP error (expected ' . implode('/', $expect) . '): ' . trim($response));
        }
        return $response;
    };

    $host = preg_replace('/[^A-Za-z0-9.\-]/', '', $_SERVER['SERVER_NAME'] ?? 'localhost') ?: 'localhost';
    $cmd(null, [220]);
    $cmd("EHLO $host", [250]);
    if ($c['smtp_secure'] === 'tls') {
        $cmd('STARTTLS', [220]);
        if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT)) {
            throw new RuntimeException('SMTP STARTTLS negotiation failed');
        }
        $cmd("EHLO $host", [250]);
    }
    if ($c['smtp_user'] !== '') {
        $cmd('AUTH LOGIN', [334]);
        $cmd(base64_encode($c['smtp_user']), [334]);
        $cmd(base64_encode($c['smtp_pass']), [235]);
    }
    $cmd("MAIL FROM:<$from>", [250]);
    $cmd("RCPT TO:<$to>", [250, 251]);
    $cmd('DATA', [354]);
    // Dot-stuffing per RFC 5321.
    $cmd(preg_replace('/^\./m', '..', $data) . "\r\n.", [250]);
    $cmd('QUIT', [221]);
    fclose($socket);
}

// ---------------------------------------------------------------- request checks
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    $wantsJson = true;
    respond(405, ['ok' => false, 'message' => 'Method not allowed.']);
}

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin !== '' && parse_url($origin, PHP_URL_HOST) !== parse_url('//' . ($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST)) {
    respond(403, ['ok' => false, 'message' => 'This request was not accepted.']);
}

$config = load_config();
$configured = filter_var($config['from_email'], FILTER_VALIDATE_EMAIL)
    && filter_var($config['to_email'], FILTER_VALIDATE_EMAIL)
    && ($config['transport'] === 'mail' || $config['smtp_host'] !== '');
if (!$configured) {
    error_log('[wwc-enquiry] Form is not configured: set from_email and SMTP settings.');
    respond(503, ['ok' => false, 'message' => 'Our enquiry form is temporarily unavailable.']);
}

// Honeypot: bots that fill the hidden field get a neutral response and nothing is sent.
if (trim((string) ($_POST['website'] ?? '')) !== '') {
    respond(200, ['ok' => true]);
}

$started = (int) ($_POST['form_started'] ?? 0);
if ($started > 0) {
    $age = time() - $started;
    if ($age < MIN_FILL_SECONDS) {
        respond(422, ['ok' => false, 'message' => 'Please wait a few seconds and submit the form again.']);
    }
    if ($age > MAX_FORM_AGE_SECONDS) {
        respond(422, ['ok' => false, 'message' => 'This page has been open for a long time. Please refresh it and try again.']);
    }
}

foreach (['name', 'company', 'email', 'phone', 'category', 'message'] as $field) {
    if (isset($_POST[$field]) && (!is_string($_POST[$field]) || !mb_check_encoding($_POST[$field], 'UTF-8'))) {
        respond(422, ['ok' => false, 'message' => 'Your enquiry contains characters we could not read. Please check it and try again.']);
    }
}

if (rate_limited($config)) {
    respond(429, ['ok' => false, 'message' => 'Too many enquiries have been sent from your connection. Please try again later.']);
}

// ---------------------------------------------------------------- validation
$name = clean_line((string) ($_POST['name'] ?? ''), 100);
$company = clean_line((string) ($_POST['company'] ?? ''), 150);
$email = clean_line((string) ($_POST['email'] ?? ''), 254);
$phone = clean_line((string) ($_POST['phone'] ?? ''), 30);
$category = (string) ($_POST['category'] ?? '');
$message = clean_text((string) ($_POST['message'] ?? ''), 5000);
$consent = ($_POST['consent'] ?? '') === 'yes';

$errors = [];
if (mb_strlen($name) < 2) {
    $errors['name'] = 'Please enter your full name.';
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !preg_match('/@[^@]+\.[^@]{2,}$/', $email)) {
    $errors['email'] = 'Please enter a valid email address, for example name@company.com.';
}
if ($phone !== '' && !preg_match('/^[0-9+()\-.\s]{6,30}$/', $phone)) {
    $errors['phone'] = 'Please enter a valid phone number using digits, spaces and + ( ) - only.';
}
if (!array_key_exists($category, CATEGORIES)) {
    $errors['category'] = 'Please choose the category that best fits your enquiry.';
}
if (mb_strlen($message) < 10) {
    $errors['message'] = 'Please enter a message of at least 10 characters.';
} elseif (preg_match_all('#https?://|www\.#i', $message) > MAX_LINKS_IN_MESSAGE) {
    $errors['message'] = 'Please remove some of the links from your message.';
}
if (!$consent) {
    $errors['consent'] = 'Please confirm that we may use your details to respond.';
}
if ($errors) {
    respond(422, ['ok' => false, 'message' => 'Please correct the highlighted fields and try again.', 'errors' => $errors]);
}

// ---------------------------------------------------------------- compose & send
$categoryTitle = CATEGORIES[$category];
$subject = encode_header("Website enquiry: $categoryTitle – $name");
$fromEmail = $config['from_email'];
$toEmail = $config['to_email'];
$fromName = encode_name(clean_line($config['from_name'], 80));
$replyName = encode_name($name);

$body = "A new enquiry was submitted through the website contact form.\n\n"
    . "Category: $categoryTitle\n"
    . "Name:     $name\n"
    . 'Company:  ' . ($company !== '' ? $company : '—') . "\n"
    . "Email:    $email\n"
    . 'Phone:    ' . ($phone !== '' ? $phone : '—') . "\n"
    . 'Consent:  Visitor agreed that their details may be used to respond to this enquiry.' . "\n"
    . 'Received: ' . gmdate('Y-m-d H:i') . " UTC\n\n"
    . "Message:\n" . str_repeat('-', 40) . "\n$message\n" . str_repeat('-', 40) . "\n\n"
    . "Reply directly to this email to respond to the visitor.\n";

$domain = substr(strrchr($fromEmail, '@') ?: '@localhost', 1);
$headers = [
    'Date: ' . date('r'),
    "From: $fromName <$fromEmail>",
    "Reply-To: $replyName <$email>",
    'Message-ID: <' . bin2hex(random_bytes(16)) . "@$domain>",
    'MIME-Version: 1.0',
    'Content-Type: text/plain; charset=UTF-8',
    'Content-Transfer-Encoding: base64',
    'X-Mailer: WWC-Website',
];
$encodedBody = chunk_split(base64_encode($body), 76, "\r\n");

try {
    if ($config['transport'] === 'mail') {
        $sent = mail($toEmail, $subject, $encodedBody, implode("\r\n", $headers), '-f' . $fromEmail);
        if (!$sent) {
            throw new RuntimeException('mail() returned false');
        }
    } else {
        $data = implode("\r\n", array_merge($headers, ["To: <$toEmail>", "Subject: $subject"])) . "\r\n\r\n" . $encodedBody;
        smtp_send($config, $fromEmail, $toEmail, $data);
    }
} catch (Throwable $e) {
    error_log('[wwc-enquiry] Delivery failed: ' . $e->getMessage());
    respond(502, ['ok' => false, 'message' => 'Sorry, your enquiry could not be sent right now.']);
}

respond(200, ['ok' => true]);
