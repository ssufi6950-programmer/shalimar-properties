<?php
// Standalone manual tool: no portal bootstrap, database, .env or vendor dependency.
declare(strict_types=1);

namespace Shalimar\Diagnostics\Whatsapp;

header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-store, private');
header('Referrer-Policy: no-referrer');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
session_name('shalimar_whatsapp_manual');
session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict',
    'cookie_secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'use_strict_mode' => true,
]);

function escape($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function requestPayload(array $fields): array
{
    $version = $fields['version'];
    $sender = $fields['sender'];
    if (!preg_match('/\Av[0-9]{1,3}\.[0-9]{1,2}\z/D', $version)) {
        throw new \InvalidArgumentException('Enter an API version such as v23.0.');
    }
    if (!preg_match('/\A[0-9]{5,25}\z/D', $sender)) {
        throw new \InvalidArgumentException('Enter the Meta Phone Number ID, not the sender phone number.');
    }
    $phone = trim($fields['phone']);
    if (!preg_match('/\A\+?[0-9 ()-]+\z/D', $phone)) {
        throw new \InvalidArgumentException('Enter a valid WhatsApp recipient number.');
    }
    $phone = preg_replace('/[^0-9]/', '', $phone);
    if (preg_match('/\A03[0-9]{9}\z/D', $phone)) {
        $phone = '92' . substr($phone, 1);
    }
    if (!preg_match('/\A[1-9][0-9]{6,14}\z/D', $phone)) {
        throw new \InvalidArgumentException('Use 03XXXXXXXXX for Pakistan or an international number with country code.');
    }
    $payload = ['messaging_product' => 'whatsapp', 'recipient_type' => 'individual', 'to' => $phone];
    if ($fields['mode'] === 'template') {
        if (!preg_match('/\A[a-z0-9_]{1,512}\z/D', $fields['template'])) {
            throw new \InvalidArgumentException('Enter the approved template name.');
        }
        if (!preg_match('/\A[a-z]{2,3}(?:_[A-Z]{2})?\z/D', $fields['language'])) {
            throw new \InvalidArgumentException('Enter the template language, for example en_US.');
        }
        $payload['type'] = 'template';
        $payload['template'] = ['name' => $fields['template'], 'language' => ['code' => $fields['language']]];
        $body = trim($fields['variables']);
        if ($body !== '') {
            $values = preg_split('/\r\n|\r|\n/', $body);
            if (count($values) > 10) {
                throw new \InvalidArgumentException('Enter up to 10 body variables, one per line.');
            }
            $parameters = [];
            foreach ($values as $value) {
                if (trim($value) === '' || strlen($value) > 1024 || preg_match('//u', $value) !== 1) {
                    throw new \InvalidArgumentException('Each body variable must be nonempty UTF-8 text up to 1024 bytes.');
                }
                $parameters[] = ['type' => 'text', 'text' => $value];
            }
            $payload['template']['components'] = [['type' => 'body', 'parameters' => $parameters]];
        }
    } elseif ($fields['mode'] === 'text') {
        $message = trim($fields['message']);
        if ($message === '' || strlen($message) > 4096 || preg_match('//u', $message) !== 1) {
            throw new \InvalidArgumentException('Enter a UTF-8 text message up to 4096 bytes.');
        }
        $payload['type'] = 'text';
        $payload['text'] = ['preview_url' => false, 'body' => $message];
    } else {
        throw new \InvalidArgumentException('Choose Template or Text message.');
    }
    return ['endpoint' => 'https://graph.facebook.com/' . $version . '/' . $sender . '/messages', 'payload' => $payload];
}

$fields = [
    'version' => 'v23.0', 'sender' => '1143501022169125', 'phone' => '03427056415', 'mode' => 'template',
    'template' => 'temp', 'language' => 'en_US',
    'variables' => "Your Shalimar Properties login verification code is 123456. This code expires in 5 minutes.\nshare this code with",
    'message' => 'Shalimar Properties manual WhatsApp connectivity test.', 'family' => 'ipv4',
];
$error = '';
$result = null;
$submitted = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
$token = '';
if ($submitted) {
    foreach ($fields as $key => $default) {
        if (is_string($_POST[$key] ?? null)) {
            $fields[$key] = $_POST[$key];
        }
    }
    try {
        $nonce = $_POST['check_nonce'] ?? null;
        $expected = $_SESSION['whatsapp_check_nonce'] ?? '';
        unset($_SESSION['whatsapp_check_nonce']);
        if (!is_string($nonce) || $expected === '' || !hash_equals($expected, $nonce)) {
            http_response_code(403);
            throw new \InvalidArgumentException('This form expired or was already used. Review the form and try again.');
        }
        $action = $_POST['action'] ?? '';
        if (!in_array($action, ['preview', 'send'], true)) {
            throw new \InvalidArgumentException('Choose Preview request or Send WhatsApp.');
        }
        $request = requestPayload($fields);
        $result = ['action' => 'Preview only; no message sent', 'endpoint' => $request['endpoint'], 'request_payload' => $request['payload']];
        if ($action === 'send') {
            $token = is_string($_POST['access_token'] ?? null) ? trim($_POST['access_token']) : '';
            if ($token === '' || strlen($token) > 4096 || preg_match('/[\x00-\x20\x7f]/', $token)) {
                throw new \InvalidArgumentException('Enter a valid Meta access token. It is not saved or redisplayed.');
            }
            if (($_POST['confirmed'] ?? null) !== 'yes') {
                throw new \InvalidArgumentException('Confirm the recipient before sending.');
            }
            if (!in_array($fields['family'], ['ipv4', 'auto', 'ipv6'], true)) {
                throw new \InvalidArgumentException('Choose a valid IP mode.');
            }
            if (!function_exists('curl_init')) {
                throw new \RuntimeException('PHP cURL is unavailable on this server.');
            }
            $body = '';
            $truncated = false;
            $curl = curl_init($request['endpoint']);
            try {
                curl_setopt_array($curl, [
                    CURLOPT_POST => true, CURLOPT_POSTFIELDS => json_encode($request['payload'], JSON_THROW_ON_ERROR),
                    CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token, 'Content-Type: application/json', 'Accept: application/json'],
                    CURLOPT_FOLLOWLOCATION => false, CURLOPT_PROXY => '', CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
                    CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2,
                    CURLOPT_CONNECTTIMEOUT => 8, CURLOPT_TIMEOUT => 20,
                    CURLOPT_IPRESOLVE => ['ipv4' => CURL_IPRESOLVE_V4, 'auto' => CURL_IPRESOLVE_WHATEVER, 'ipv6' => CURL_IPRESOLVE_V6][$fields['family']],
                    CURLOPT_USERAGENT => 'Shalimar-Manual-WhatsApp-Check/1.0',
                    CURLOPT_WRITEFUNCTION => static function ($handle, string $chunk) use (&$body, &$truncated): int {
                        if (strlen($body) + strlen($chunk) > 32768) { $truncated = true; return 0; }
                        $body .= $chunk;
                        return strlen($chunk);
                    },
                ]);
                $ok = curl_exec($curl);
                $info = curl_getinfo($curl);
                $status = (int) ($info['http_code'] ?? 0);
                $data = json_decode($body, true);
                $accepted = $ok !== false && $status >= 200 && $status < 300 && is_array($data)
                    && !isset($data['error']) && is_string($data['messages'][0]['id'] ?? null) && $data['messages'][0]['id'] !== '';
                $summary = $accepted ? 'Meta accepted the message request. Delivery to the phone is not yet confirmed.'
                    : ($status > 0 ? 'Meta returned HTTP ' . $status . '. Inspect the response below.' : 'No HTTP response received. Inspect the cURL error and timings.');
                $result = [
                    'summary' => $summary, 'action' => 'Send WhatsApp', 'endpoint' => $request['endpoint'],
                    'recipient' => $request['payload']['to'], 'message_type' => $fields['mode'], 'ip_mode' => $fields['family'],
                    'accepted' => $accepted, 'delivered' => null, 'http_status' => $status,
                    'curl_errno' => curl_errno($curl), 'curl_error' => curl_error($curl),
                    'remote_ip' => (string) ($info['primary_ip'] ?? ''), 'ssl_verify_result' => (int) ($info['ssl_verify_result'] ?? 0),
                    'dns_ms' => round((float) ($info['namelookup_time'] ?? 0) * 1000, 1),
                    'tcp_connect_ms' => round((float) ($info['connect_time'] ?? 0) * 1000, 1),
                    'tls_complete_ms' => round((float) curl_getinfo($curl, CURLINFO_APPCONNECT_TIME) * 1000, 1),
                    'total_ms' => round((float) ($info['total_time'] ?? 0) * 1000, 1),
                    'message_id' => $data['messages'][0]['id'] ?? null, 'message_status' => $data['messages'][0]['message_status'] ?? null,
                    'response_truncated' => $truncated, 'response_json' => is_array($data) ? $data : null,
                    'response_body' => $body, 'request_payload' => $request['payload'],
                ];
            } finally {
                curl_close($curl);
            }
        }
    } catch (\Throwable $exception) {
        $error = $exception->getMessage();
        $result = null;
    }
}
$nonce = bin2hex(random_bytes(32));
$_SESSION['whatsapp_check_nonce'] = $nonce;
session_write_close();
$resultText = $result !== null ? json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) : '';
if ($token !== '') {
    $resultText = str_replace([$token, rawurlencode($token)], '[TOKEN REDACTED]', (string) $resultText);
    $error = str_replace($token, '[TOKEN REDACTED]', $error);
}
unset($token);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Manual WhatsApp check — Shalimar Properties</title>
    <style>
        *{box-sizing:border-box}body{margin:0;background:#f3f6f4;color:#19382f;font:15px/1.6 system-ui,sans-serif}main{max-width:900px;margin:30px auto;padding:0 18px}section{background:white;border:1px solid #dde7e1;border-radius:14px;padding:24px;margin-bottom:20px}h1{font-size:26px;line-height:1.25;margin:0 0 10px}h2{font-size:19px;margin:0 0 14px}p{color:#52675f}label{display:block;font-weight:600;margin-bottom:6px}input,select,textarea{width:100%;padding:11px;border:1px solid #cbd8d0;border-radius:8px;font:inherit;color:inherit}input:focus,select:focus,textarea:focus,button:focus-visible{outline:3px solid #8cbdaa;outline-offset:2px}.field{margin-bottom:17px}.row{display:grid;grid-template-columns:1fr 1fr;gap:18px}.buttons{display:flex;flex-wrap:wrap;gap:12px}button{min-height:46px;padding:12px 18px;border:1px solid #17654e;border-radius:8px;background:#17654e;color:white;font:600 14px system-ui,sans-serif;cursor:pointer}.secondary{background:white;color:#17654e}.check{display:flex;gap:10px;align-items:flex-start;font-weight:400}.check input{width:20px;height:20px;flex-shrink:0;margin-top:3px}.error{background:#fff0ed;border:1px solid #f1c8c0;padding:14px;border-radius:8px;color:#8a3026}.result-summary{background:#edf5ef;padding:14px;border-radius:8px;font-weight:600}pre{background:#f3f5f4;padding:16px;border-radius:8px;white-space:pre-wrap;overflow-wrap:anywhere;font-size:13px}small{display:block;color:#60756b;margin-top:5px}code{overflow-wrap:anywhere}[hidden]{display:none!important}@media(max-width:600px){main{margin:18px auto}section{padding:18px}.row{grid-template-columns:1fr;gap:0}.buttons button{width:100%}}
    </style>
</head>
<body>
<main>
    <section>
        <h1>Manual WhatsApp check</h1>
        <p>Send one message directly from this server to Meta. This standalone page uses the recipient entered below; portal test-recipient settings do not apply.</p>
        <?php if ($error !== ''): ?><p class="error" role="alert"><?= escape($error) ?></p><?php endif; ?>
        <form method="post" autocomplete="off" id="whatsappCheck">
            <input type="hidden" name="check_nonce" value="<?= escape($nonce) ?>">
            <div class="field"><label for="access_token">Meta access token</label><input id="access_token" name="access_token" type="password" maxlength="4096" autocomplete="off"><small>Paste your WhatsApp Cloud API token for Send WhatsApp. Never saved or redisplayed; Preview needs no token.</small></div>
            <div class="row">
                <div class="field"><label for="sender">Meta Phone Number ID</label><input id="sender" name="sender" value="<?= escape($fields['sender']) ?>" maxlength="25" inputmode="numeric"><small>The sender's API ID, not its phone number.</small></div>
                <div class="field"><label for="phone">Recipient WhatsApp number</label><input id="phone" name="phone" type="tel" value="<?= escape($fields['phone']) ?>" maxlength="30"><small>03XXXXXXXXX or country code, for example +923XXXXXXXXX.</small></div>
            </div>
            <div class="row">
                <div class="field"><label for="mode">Message type</label><select id="mode" name="mode"><option value="template" <?= $fields['mode']==='template'?'selected':'' ?>>Approved template</option><option value="text" <?= $fields['mode']==='text'?'selected':'' ?>>Text message</option></select></div>
                <div class="field"><label for="version">Graph API version</label><input id="version" name="version" value="<?= escape($fields['version']) ?>" maxlength="8"></div>
            </div>
            <div data-template-fields>
                <div class="row">
                    <div class="field"><label for="template">Template name</label><input id="template" name="template" value="<?= escape($fields['template']) ?>" maxlength="512"></div>
                    <div class="field"><label for="language">Template language</label><input id="language" name="language" value="<?= escape($fields['language']) ?>" maxlength="8"></div>
                </div>
                <div class="field"><label for="variables">Body variables — one value per line</label><textarea id="variables" name="variables" rows="4" maxlength="10250"><?= escape($fields['variables']) ?></textarea><small>First line = variable 1; second line = variable 2. Match the approved template's exact number/order of body variables. Leave blank for a template with no variables. Supports positional text body parameters.</small></div>
            </div>
            <div data-text-fields><div class="field"><label for="message">Message</label><textarea id="message" name="message" rows="4" maxlength="4096"><?= escape($fields['message']) ?></textarea><small>Free-form text requires an open customer-service conversation. Use an approved template when messaging outside that window.</small></div></div>
            <div class="field"><label for="family">Connection mode</label><select id="family" name="family"><?php foreach(['ipv4'=>'IPv4 (matches portal)','auto'=>'Automatic IPv4 / IPv6','ipv6'=>'IPv6 only'] as $value=>$label): ?><option value="<?= $value ?>" <?= $fields['family']===$value?'selected':'' ?>><?= escape($label) ?></option><?php endforeach; ?></select></div>
            <div class="field"><label class="check"><input type="checkbox" name="confirmed" value="yes"><span>I checked the recipient above and want to send this manual test message.</span></label></div>
            <div class="buttons"><button class="secondary" type="submit" name="action" value="preview">Preview request (no send)</button><button type="submit" name="action" value="send">Send WhatsApp</button></div>
            <small>One request per click. No automatic retries. Meta acceptance and phone delivery are separate results.</small>
        </form>
    </section>
    <?php if ($result !== null): ?><section id="result"><h2>Result</h2><p class="result-summary" role="status"><?= escape($result['summary'] ?? 'Preview only: no message was sent.') ?></p><pre><?= escape($resultText) ?></pre><small>HTTP 0 means no HTTP response. cURL 6 = DNS; 7 = connection; 28 = timeout; 60 = certificate verification. A message ID or accepted status does not prove delivery; delivery/failure details arrive through status webhooks.</small></section><?php endif; ?>
</main>
<script>
(() => {
    const form = document.getElementById('whatsappCheck');
    const mode = document.getElementById('mode');
    const update = () => {
        document.querySelector('[data-template-fields]').hidden = mode.value !== 'template';
        document.querySelector('[data-text-fields]').hidden = mode.value !== 'text';
    };
    mode.addEventListener('change', update);
    form.addEventListener('submit', () => { setTimeout(() => form.querySelectorAll('button').forEach(button => button.disabled = true), 0); });
    update();
})();
</script>
</body>
</html>
