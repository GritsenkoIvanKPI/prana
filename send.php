<?php
/* ============================================================================
   PRANA — receive the contact form and forward it to Telegram (PHP hosting)
   ============================================================================
   For Vercel/Node hosting there's api/lead.js — it does the same thing.
   This file is for regular PHP hosting (Webuzo, cPanel, etc).

   WHERE THE TOKEN LIVES (never in this file, never in git):

     1) Environment variables TELEGRAM_BOT_TOKEN and TELEGRAM_CHAT_ID, if your
        hosting panel lets you set them per-site. The safer option.
     2) Otherwise — telegram-config.php next to this file. It's in .gitignore
        so it never reaches the repository. Template: telegram-config.example.php

   The token is never visible in the browser: the page only calls this file,
   and this file is the only thing that talks to Telegram. Never move the
   token into HTML or JS.

   DIAGNOSTICS: open send.php?selftest=1 directly in a browser to check the
   setup without sending anything to the chat. See TELEGRAM_SETUP.md.
   ============================================================================ */

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

function fail($code, $error) {
    http_response_code($code);
    echo json_encode(array('ok' => false, 'error' => $error), JSON_UNESCAPED_UNICODE);
    exit;
}

/* --- credentials ------------------------------------------------------------
   Loaded first (and before the POST-only check below) so that ?selftest=1
   can report on them with a plain GET request. */
$BOT_TOKEN = getenv('TELEGRAM_BOT_TOKEN');
$CHAT_ID   = getenv('TELEGRAM_CHAT_ID');
$CONFIG_SOURCE = 'none found';
if ($BOT_TOKEN) {
    $CONFIG_SOURCE = 'environment variables';
} elseif (is_readable(__DIR__ . '/telegram-config.php')) {
    $cfg = require __DIR__ . '/telegram-config.php';
    if (is_array($cfg)) {
        $BOT_TOKEN = isset($cfg['token'])   ? $cfg['token']   : '';
        $CHAT_ID   = isset($cfg['chat_id']) ? $cfg['chat_id'] : '';
        $CONFIG_SOURCE = 'telegram-config.php';
    }
}

/* --- self-test: open send.php?selftest=1 in a browser --------------------
   Never reveals the token itself — only whether one is present and how long
   it is, plus a real (harmless) call to Telegram's getMe to confirm it's
   valid. Does NOT send a message to the chat, so it's safe to reload freely. */
if (isset($_GET['selftest'])) {
    $result = array(
        'php_ok'             => true,
        'config_source'      => $CONFIG_SOURCE,
        'token_present'      => $BOT_TOKEN !== '' && $BOT_TOKEN !== false && $BOT_TOKEN !== null,
        'token_length'       => $BOT_TOKEN ? strlen($BOT_TOKEN) : 0,
        'chat_id'            => $CHAT_ID ? $CHAT_ID : null,
        'curl_available'     => function_exists('curl_init'),
        'can_reach_telegram' => false,
    );
    if ($result['token_present'] && $result['curl_available']) {
        $ch = curl_init('https://api.telegram.org/bot' . $BOT_TOKEN . '/getMe');
        curl_setopt_array($ch, array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 8,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ));
        $res = curl_exec($ch);
        curl_close($ch);
        $decoded = json_decode($res, true);
        $result['can_reach_telegram'] = is_array($decoded) && !empty($decoded['ok']);
        if (is_array($decoded) && empty($decoded['ok'])) {
            $result['telegram_error'] = isset($decoded['description']) ? $decoded['description'] : 'unknown';
        }
    }
    echo json_encode($result, JSON_PRETTY_PRINT);
    exit;
}

if ((isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : '') !== 'POST') fail(405, 'method_not_allowed');

if (!$BOT_TOKEN || !$CHAT_ID) {
    error_log('send.php: TELEGRAM_BOT_TOKEN / TELEGRAM_CHAT_ID not set');
    fail(500, 'not_configured');
}

/* --- basic per-IP throttle --------------------------------------------------
   Stops a burst of hundreds of submissions in a minute. Stored in temp files,
   so it's the best available without a database, not a strict guarantee. */
function rate_limited($max = 5, $window = 600) {
    $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'unknown';
    $file = sys_get_temp_dir() . '/prana_lead_' . md5($ip) . '.txt';
    $now = time();
    $hits = array();
    if (is_readable($file)) {
        $raw = @file_get_contents($file);
        foreach (explode(',', (string)$raw) as $t) {
            $t = (int)$t;
            if ($t && $now - $t < $window) $hits[] = $t;
        }
    }
    if (count($hits) >= $max) return true;
    $hits[] = $now;
    @file_put_contents($file, implode(',', $hits), LOCK_EX);
    return false;
}
if (rate_limited()) fail(429, 'too_many_requests');

/* --- parse the incoming request --------------------------------------------- */
$raw_input = file_get_contents('php://input');
$body = json_decode($raw_input ? $raw_input : '', true);
if (!is_array($body)) $body = array();

/** Trim whatever the browser sent, cap length against both accidents and abuse. */
function prana_clean($v, $max = 2000) {
    $s = trim((string)($v === null ? '' : $v));
    return function_exists('mb_substr') ? mb_substr($s, 0, $max) : substr($s, 0, $max);
}

// Honeypot: hidden from real visitors, so only a spam bot fills it in.
// Reply "ok" anyway so the bot doesn't notice and keep retrying.
if (prana_clean(isset($body['website']) ? $body['website'] : '') !== '') {
    echo json_encode(array('ok' => true));
    exit;
}

$fields = array(
    'name'    => 'Name',
    'phone'   => 'Phone / WhatsApp',
    'email'   => 'Email',
    'service' => 'What they need',
    'message' => 'Message',
);
$required = array('name', 'phone', 'email');

$missing = array();
foreach ($required as $key) {
    if (prana_clean(isset($body[$key]) ? $body[$key] : '') === '') $missing[] = $key;
}
if ($missing) {
    http_response_code(400);
    echo json_encode(array('ok' => false, 'error' => 'missing_fields', 'missing' => $missing), JSON_UNESCAPED_UNICODE);
    exit;
}

$rows = array();
foreach ($fields as $key => $title) {
    $val = prana_clean(isset($body[$key]) ? $body[$key] : '');
    if ($val === '') continue;
    $rows[] = '<b>' . $title . ':</b> ' . htmlspecialchars($val, ENT_NOQUOTES, 'UTF-8');
}

$source = prana_clean(isset($body['source']) ? $body['source'] : '', 80);
if ($source === '') $source = 'PRANA website';
$page = prana_clean(isset($body['page']) ? $body['page'] : '', 300);
$when = prana_clean(isset($body['submittedAt']) ? $body['submittedAt'] : '', 60);

$lines = array_merge(
    array('🌿 <b>New enquiry — PRANA</b>', ''),
    $rows,
    array('', '<i>Source:</i> ' . htmlspecialchars($source, ENT_NOQUOTES, 'UTF-8'))
);
if ($when !== '') $lines[] = '<i>When:</i> ' . htmlspecialchars($when, ENT_NOQUOTES, 'UTF-8');
if ($page !== '') $lines[] = '<i>Page:</i> ' . htmlspecialchars($page, ENT_NOQUOTES, 'UTF-8');

$payload = json_encode(array(
    'chat_id' => $CHAT_ID,
    'text'    => implode("\n", $lines),
    'parse_mode' => 'HTML',
    'disable_web_page_preview' => true,
), JSON_UNESCAPED_UNICODE);

$ch = curl_init('https://api.telegram.org/bot' . $BOT_TOKEN . '/sendMessage');
curl_setopt_array($ch, array(
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_HTTPHEADER     => array('Content-Type: application/json'),
    CURLOPT_TIMEOUT        => 12,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_SSL_VERIFYHOST => 2,
));
$response = curl_exec($ch);
$curlErr  = curl_error($ch);
curl_close($ch);

if ($response === false) {
    error_log('send.php: could not reach Telegram: ' . $curlErr);
    fail(502, 'telegram_unreachable');
}

$result = json_decode($response, true);
if (!is_array($result) || empty($result['ok'])) {
    error_log('send.php: Telegram rejected the message: ' . (isset($result['description']) ? $result['description'] : $response));
    fail(502, 'telegram_rejected');
}

echo json_encode(array('ok' => true));
