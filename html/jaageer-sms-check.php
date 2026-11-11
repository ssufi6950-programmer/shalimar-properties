<?php
// Standalone diagnostic: no portal bootstrap, database, .env or vendor files.
declare(strict_types=1);
header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-store, private');
header('Referrer-Policy: no-referrer');
header('X-Content-Type-Options: nosniff');

$endpoint = 'https://www.jaageer.com/jazz-sms-api.php';
$result = null;
$validationError = '';
$phone = '03427056415';
$message = 'Shalimar Properties server SMS connectivity test.';
$family = 'ipv4';
$escape = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$postedString = static fn(string $key, string $fallback = ''): string => is_string($_POST[$key] ?? null) ? $_POST[$key] : $fallback;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $action = $postedString('action');
    $family = $postedString('family', 'ipv4');
    $phone = trim($postedString('phone', $phone));
    $message = trim($postedString('message', $message));
    $key = trim($postedString('gateway_key'));
    $nationalPhone = '';

    if (!in_array($action, ['probe', 'send'], true)) {
        $validationError = 'Choose Check connection or Send test SMS.';
    } elseif (!in_array($family, ['ipv4', 'auto', 'ipv6'], true)) {
        $validationError = 'Choose a valid IP mode.';
    } elseif (!function_exists('curl_init')) {
        $validationError = 'PHP cURL is not available on this server.';
    } elseif ($action === 'send') {
        $digits = preg_replace('/[^0-9]/', '', $phone);
        if ($key === '' || strlen($key) > 512 || preg_match('/[\x00-\x1f\x7f]/', $key)) {
            $validationError = 'Enter the SMS gateway key.';
        } elseif (!preg_match('/\A[+0-9 ()-]+\z/D', $phone) || !preg_match('/\A(?:03[0-9]{9}|923[0-9]{9})\z/D', $digits)) {
            $validationError = 'Enter the test mobile as 03XXXXXXXXX or +923XXXXXXXXX.';
        } elseif ($message === '' || strlen($message) > 500) {
            $validationError = 'Enter a test message of up to 500 bytes.';
        } else {
            $nationalPhone = str_starts_with($digits, '92') ? '0'.substr($digits, 2) : $digits;
        }
    }

    if ($validationError === '') {
        // A probe sends no key, phone or message. A send uses the existing API contract.
        $url = $endpoint;
        if ($action === 'send') {
            $url .= '?'.http_build_query([
                'key' => $key,
                'phone' => $nationalPhone,
                'msg' => str_replace(' ', '<<>>', $message),
            ], '', '&', PHP_QUERY_RFC3986);
        }
        $redact = static function (string $value) use ($key, $phone, $nationalPhone): string {
            foreach ([$key, $phone, $nationalPhone] as $secret) {
                if ($secret === '') continue;
                $value = str_replace(array_unique([$secret, rawurlencode($secret), urlencode($secret)]), '[hidden]', $value);
            }
            return $value;
        };
        $body = '';
        $truncated = false;
        $curl = null;
        try {
            $curl = curl_init($url);
            if ($curl === false) throw new RuntimeException('PHP could not create a cURL request.');
            curl_setopt_array($curl, [
                CURLOPT_HTTPGET => true,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_PROXY => '',
                CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_CONNECTTIMEOUT => 8,
                CURLOPT_TIMEOUT => 20,
                CURLOPT_IPRESOLVE => ['ipv4' => CURL_IPRESOLVE_V4, 'auto' => CURL_IPRESOLVE_WHATEVER, 'ipv6' => CURL_IPRESOLVE_V6][$family],
                CURLOPT_USERAGENT => 'Shalimar-SMS-Connectivity-Check/1.0',
                CURLOPT_WRITEFUNCTION => static function ($handle, string $chunk) use (&$body, &$truncated): int {
                    $length = strlen($chunk);
                    if (strlen($body) + $length > 16384) {
                        $body .= substr($chunk, 0, max(0, 16384 - strlen($body)));
                        $truncated = true;
                        return 0;
                    }
                    $body .= $chunk;
                    return $length;
                },
            ]);
            $ok = curl_exec($curl);
            $info = curl_getinfo($curl);
            $errno = curl_errno($curl);
            $error = $redact(curl_error($curl));
            $status = (int) ($info['http_code'] ?? 0);
            $accepted = $ok !== false && $status === 200 && $action === 'send' && trim($body) === 'Message Sent Successfully!';
            if ($accepted) {
                $summary = 'Jaageer accepted the SMS request. Check the test phone for delivery.';
            } elseif ($status > 0) {
                $summary = 'An HTTP response was received from Jaageer (HTTP '.$status.').';
                if ($action === 'send') $summary .= ' SMS acceptance was not confirmed; inspect the response below.';
            } else {
                $summary = 'No HTTP response was received. Inspect the cURL error and timings below.';
            }
            $result = [
                'summary' => $summary,
                'action' => $action === 'send' ? 'Send test SMS' : 'Connection check; no SMS requested',
                'endpoint' => $endpoint,
                'ip_mode' => $family,
                'http_status' => $status,
                'curl_errno' => $errno,
                'curl_error' => $error,
                'remote_ip' => (string) ($info['primary_ip'] ?? ''),
                'ssl_verify_result' => (int) ($info['ssl_verify_result'] ?? 0),
                'dns_ms' => round((float) ($info['namelookup_time'] ?? 0) * 1000, 1),
                'tcp_connect_ms' => round((float) ($info['connect_time'] ?? 0) * 1000, 1),
                'tls_complete_ms' => round((float) curl_getinfo($curl, CURLINFO_APPCONNECT_TIME) * 1000, 1),
                'first_response_ms' => round((float) ($info['starttransfer_time'] ?? 0) * 1000, 1),
                'total_ms' => round((float) ($info['total_time'] ?? 0) * 1000, 1),
                'connect_timeout_seconds' => 8,
                'total_timeout_seconds' => 20,
                'response_truncated' => $truncated,
                'response_body' => $redact($body),
            ];
        } catch (Throwable $exception) {
            $validationError = $redact($exception->getMessage());
        } finally {
            if ($curl !== null && $curl !== false) curl_close($curl);
        }
        // Never put the credential or full authenticated gateway URL into HTML.
        unset($key, $url);
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Jaageer SMS connection check</title>
    <style>
        *{box-sizing:border-box}body{margin:0;background:#f3f6f4;color:#19382f;font:15px/1.6 system-ui,sans-serif}main{max-width:860px;margin:32px auto;padding:0 18px}section{background:#fff;border:1px solid #dde7e1;border-radius:14px;padding:24px;margin-bottom:20px}h1{font-size:25px;line-height:1.3;margin:0 0 10px}h2{font-size:19px;margin-top:0}p{margin:8px 0 16px;color:#52675f}label{display:block;font-weight:600;margin-bottom:6px}input,select,textarea{width:100%;padding:12px;border:1px solid #cbd8d0;border-radius:8px;font:inherit;color:inherit}input:focus,select:focus,textarea:focus,button:focus-visible{outline:3px solid #8cbdaa;outline-offset:2px}.field{margin-bottom:18px}.row{display:grid;grid-template-columns:1fr 1fr;gap:18px}.buttons{display:flex;gap:12px;flex-wrap:wrap}button{padding:12px 18px;border:1px solid #17654e;border-radius:8px;background:#17654e;color:white;font:600 14px system-ui,sans-serif;cursor:pointer;min-height:46px}.secondary{background:#fff;color:#17654e}.error{background:#fff0ed;border:1px solid #f1c8c0;padding:14px;border-radius:8px;color:#8a3026}.result-summary{background:#edf5ef;padding:14px;border-radius:8px;font-weight:600}pre{background:#f3f5f4;padding:16px;border-radius:8px;white-space:pre-wrap;overflow-wrap:anywhere;font-size:13px}small{display:block;color:#60756b;margin-top:5px}code{overflow-wrap:anywhere}@media(max-width:600px){main{margin:18px auto}section{padding:18px}.row{grid-template-columns:1fr;gap:0}.buttons button{width:100%}}
    </style>
</head>
<body>
<main>
    <section>
        <h1>Jaageer SMS connection check</h1>
        <p>This PHP file calls <code><?= $escape($endpoint) ?></code> directly from this server. It does not load the portal, database or .env.</p>
        <?php if ($validationError !== ''): ?><p class="error" role="alert"><?= $escape($validationError) ?></p><?php endif; ?>
        <form method="post" autocomplete="off">
            <div class="field">
                <label for="family">IP mode</label>
                <select id="family" name="family">
                    <?php foreach (['ipv4' => 'IPv4 (matches the portal)', 'auto' => 'Automatic IPv4 / IPv6', 'ipv6' => 'IPv6 only'] as $value => $label): ?>
                        <option value="<?= $value ?>" <?= $family === $value ? 'selected' : '' ?>><?= $escape($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="row">
                <div class="field">
                    <label for="gateway_key">Gateway key</label>
                    <input id="gateway_key" name="gateway_key" type="password" maxlength="512" autocomplete="off">
                    <small>Needed only for Send test SMS. Not saved or redisplayed.</small>
                </div>
                <div class="field">
                    <label for="phone">Test mobile</label>
                    <input id="phone" name="phone" type="tel" maxlength="24" value="<?= $escape($phone) ?>">
                </div>
            </div>
            <div class="field">
                <label for="message">Test message</label>
                <textarea id="message" name="message" rows="2" maxlength="160"><?= $escape($message) ?></textarea>
            </div>
            <div class="buttons">
                <button class="secondary" type="submit" name="action" value="probe">Check connection (no SMS)</button>
                <button type="submit" name="action" value="send">Send test SMS</button>
            </div>
            <small>One request per click; no automatic retry. HTTPS certificate verification stays enabled.</small>
        </form>
    </section>
    <?php if ($result !== null): ?>
    <section>
        <h2>Result</h2>
        <p class="result-summary" role="status"><?= $escape($result['summary']) ?></p>
        <pre><?= $escape(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE)) ?></pre>
        <p>cURL 6: DNS lookup failed. cURL 7: connection failed. cURL 28: timeout. cURL 60: certificate verification failed. HTTP 0 means no HTTP response was received.</p>
        <small>Timings are cumulative milestones from the beginning of this request. An HTTP response proves the gateway answered; SMS acceptance and delivery are separate results.</small>
    </section>
    <?php endif; ?>
</main>
</body>
</html>
