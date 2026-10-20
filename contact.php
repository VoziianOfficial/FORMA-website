<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
function respond(int $code, bool $success, string $message): void {
    http_response_code($code);
    echo json_encode(['success' => $success, 'message' => $message]);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') respond(405, false, 'Please submit the contact form.');
if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 20000) respond(413, false, 'Your message is too long.');
$source = @file_get_contents(__DIR__ . '/config/config.js');
if ($source === false) respond(503, false, 'The enquiry service is not configured yet.');
$json = preg_replace('/^\s*window\.SITE_CONFIG\s*=\s*/', '', $source);
$config = json_decode(rtrim(trim($json), ';'), true);
if (!is_array($config) || empty($config['form']['enabled'])) respond(503, false, 'The enquiry service is not connected yet. Please try again later.');
$recipient = $config['email'] ?? '';
if (!filter_var($recipient, FILTER_VALIDATE_EMAIL) || preg_match('/[\r\n]/', $recipient) || substr($recipient, -8) === '.example') respond(503, false, 'The enquiry service is not configured yet.');
function field(string $key): string {
    $v = $_POST[$key] ?? '';
    if (!is_string($v)) respond(422, false, 'Please check the submitted fields.');
    return trim($v);
}
$name = field('name');
$email = field('email');
$subject = field('subject');
$message = field('message');
if (field('website') !== '') respond(422, false, 'Your request could not be accepted.');
if ($name === '' || strlen($name) > 200 || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 200 || preg_match('/[\r\n]/', $email) || strlen($message) < 2 || strlen($message) > 10000 || field('consent') !== 'on') respond(422, false, 'Please enter your name, a valid email and message, and accept the Privacy Policy.');
$subjects = $config['form']['subjects'] ?? [];
if (!is_array($subjects)) respond(503, false, 'The enquiry service is not configured yet.');
if (!in_array($subject, $subjects, true)) respond(422, false, 'Please choose a valid subject.');
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$lockPath = sys_get_temp_dir() . '/website-contact-' . hash('sha256', __DIR__ . $ip);
$lock = fopen($lockPath, 'c+');
if (!$lock || !flock($lock, LOCK_EX)) respond(503, false, 'Please try again later.');
$last = (int)stream_get_contents($lock);
$interval = max(10, min(3600, (int)($config['form']['rateLimitSeconds'] ?? 60)));
if ($last > time() - $interval) { flock($lock, LOCK_UN); fclose($lock); respond(429, false, 'Please wait a minute before sending another request.'); }
$body = "New website enquiry\n\nName: " . $name . "\nEmail: " . $email . "\nSubject: " . $subject . "\n\n" . $message . "\n\nPrivacy consent: confirmed\n";
$headers = ['From: ' . $recipient, 'Reply-To: ' . $email, 'MIME-Version: 1.0', 'Content-Type: text/plain; charset=UTF-8'];
$accepted = false;
try { $accepted = function_exists('mail') && mail($recipient, '=?UTF-8?B?' . base64_encode(($config['name'] ?? 'Website') . ' enquiry: ' . $subject) . '?=', $body, implode("\r\n", $headers)); }
catch (Throwable $e) { error_log('Contact transport unavailable.'); }
if ($accepted) { ftruncate($lock, 0); rewind($lock); fwrite($lock, (string)time()); }
flock($lock, LOCK_UN); fclose($lock);
if (!$accepted) respond(502, false, 'Your message could not be sent. Please try again later.');
respond(200, true, (string)($config['form']['successMessage'] ?? 'Thank you! We have successfully received your request. Our team will review your information and get back to you shortly.'));
