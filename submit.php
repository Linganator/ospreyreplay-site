<?php
// Founding member form: emails each submission to Osprey and keeps a backup copy
// outside the public web folder. No passwords or keys live in this file.

$to   = 'greg@ospreyreplay.com';
$from = 'website@ospreyreplay.com';

$wantsJson = isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false;

function finish($ok, $message, $wantsJson) {
    if ($wantsJson) {
        header('Content-Type: application/json');
        http_response_code($ok ? 200 : 400);
        echo json_encode(['ok' => $ok, 'message' => $message]);
    } else {
        header('Location: /?' . ($ok ? 'sent=1' : 'error=1') . '#founding', true, 303);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /#founding', true, 303);
    exit;
}

// Spam checks: a hidden field people never see, and a minimum time on the page.
if (!empty($_POST['website'])) { finish(true, 'Thanks.', $wantsJson); }
$elapsed = isset($_POST['elapsed']) ? (int) $_POST['elapsed'] : -1;
if ($elapsed >= 0 && $elapsed < 3) { finish(true, 'Thanks.', $wantsJson); }

function field($key, $max) {
    $v = isset($_POST[$key]) ? trim((string) $_POST[$key]) : '';
    $v = str_replace(["\r", "\0"], '', $v);
    return mb_substr($v, 0, $max);
}
function oneLine($v) { return trim(preg_replace('/\s+/', ' ', $v)); }

$name    = oneLine(field('name', 120));
$email   = oneLine(field('email', 160));
$org     = oneLine(field('organization', 160));
$role    = oneLine(field('role', 120));
$phone   = oneLine(field('phone', 40));
$message = field('message', 2000);

if ($name === '' || $org === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    finish(false, 'Please add your name, a valid email, and your conference or school.', $wantsJson);
}

$subject = 'Founding member interest: ' . $org;
$body  = "New founding member interest from ospreyreplay.com\n\n";
$body .= "Name: $name\n";
$body .= "Role: " . ($role !== '' ? $role : '-') . "\n";
$body .= "Conference or school: $org\n";
$body .= "Email: $email\n";
$body .= "Phone: " . ($phone !== '' ? $phone : '-') . "\n\n";
$body .= "Message:\n" . ($message !== '' ? $message : '-') . "\n\n";
$body .= "Sent " . date('M j, Y g:i A T') . "\n";

$headers  = "From: Osprey website <$from>\r\n";
$headers .= "Reply-To: $email\r\n";
$headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

$sent = @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, $headers, '-f' . $from);

// Backup copy, one row per submission, kept above the public web folder.
$backupDir = dirname($_SERVER['DOCUMENT_ROOT']);
if (is_dir($backupDir) && is_writable($backupDir)) {
    $row = [date('c'), $name, $role, $org, $email, $phone, str_replace("\n", ' / ', $message), $sent ? 'emailed' : 'email failed'];
    $fh = @fopen($backupDir . '/osprey-founding-leads.csv', 'a');
    if ($fh) { fputcsv($fh, $row); fclose($fh); }
}

if (!$sent) {
    finish(false, 'Something went wrong sending your details. Please email greg@ospreyreplay.com instead.', $wantsJson);
}
finish(true, 'Thanks, ' . $name . '. Your details are on their way to Greg, and he will be in touch.', $wantsJson);
