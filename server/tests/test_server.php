<?php

declare(strict_types=1);

// End-to-end test: serves index.php with the PHP built-in server and checks
// it against a local fixture origin.

$root = dirname(__DIR__);
$appPort = 18091;
$originPort = 18092;
$appUrl = "http://127.0.0.1:{$appPort}";
$originUrl = "http://127.0.0.1:{$originPort}";

$failures = 0;
$procs = [];

function check(bool $condition, string $label): void
{
    global $failures;
    if ($condition) {
        echo "ok - {$label}\n";
    } else {
        echo "FAIL - {$label}\n";
        $failures++;
    }
}

function http_get(string $url, bool $follow = false, string $method = 'GET', ?string $body = null): array
{
    $ch = curl_init($url);
    $options = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => $follow,
        CURLOPT_HEADER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CUSTOMREQUEST => $method,
    ];
    if ($body !== null) {
        $options[CURLOPT_POSTFIELDS] = $body;
        $options[CURLOPT_HTTPHEADER] = ['Content-Type: text/plain;charset=UTF-8'];
    }
    curl_setopt_array($ch, $options);
    $raw = curl_exec($ch);
    if ($raw === false) {
        return ['status' => 0, 'headers' => [], 'body' => ''];
    }
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $headers = [];
    foreach (explode("\r\n", substr($raw, 0, $headerSize)) as $line) {
        if (str_contains($line, ':')) {
            [$name, $value] = explode(':', $line, 2);
            $headers[strtolower(trim($name))] = trim($value);
        }
    }
    return [
        'status' => (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE),
        'headers' => $headers,
        'body' => substr($raw, $headerSize),
    ];
}

function start_server(array $command, string $probeUrl): mixed
{
    $proc = proc_open($command, [], $pipes, null, null, ['bypass_shell' => true]);
    if (!is_resource($proc)) {
        fwrite(STDERR, 'Failed to start: ' . implode(' ', $command) . "\n");
        exit(1);
    }
    for ($i = 0; $i < 50; $i++) {
        usleep(100000);
        if (http_get($probeUrl)['status'] !== 0) {
            return $proc;
        }
    }
    fwrite(STDERR, "Server did not become ready: {$probeUrl}\n");
    proc_terminate($proc);
    exit(1);
}

putenv('CLEANROOM_ALLOW_PRIVATE=1');
$procs[] = start_server([PHP_BINARY, '-S', "127.0.0.1:{$appPort}", '-t', $root, $root . '/router.php'], "{$appUrl}/");
$procs[] = start_server([PHP_BINARY, '-S', "127.0.0.1:{$originPort}", __DIR__ . '/fixture.php'], "{$originUrl}/page");

register_shutdown_function(function () use (&$procs) {
    foreach ($procs as $proc) {
        proc_terminate($proc);
    }
});

$res = http_get("{$appUrl}/");
check($res['status'] === 200, 'landing page returns 200');
check(str_contains($res['body'], '<form'), 'landing page has a form');

$res = http_get("{$appUrl}/?url=" . urlencode('ftp://example.com/x'));
check($res['status'] === 400, 'non-http protocol rejected');

$res = http_get("{$appUrl}/", false, 'PUT');
check($res['status'] === 405, 'unsupported method rejected');

$res = http_get("{$appUrl}/?url=" . urlencode("{$originUrl}/page"));
check($res['status'] === 200, 'fetched page returns 200');
check(str_contains($res['headers']['content-security-policy'] ?? '', 'script-src \'none\''), 'CSP header present');
check(!str_contains($res['body'], '<script'), 'scripts stripped end-to-end');
check(str_contains($res['body'], 'CONTENT'), 'page content kept');
check(str_contains($res['body'], 'SIDEBAR'), 'unfiltered div kept');
check(str_contains($res['body'], "<base href=\"{$originUrl}/page\">"), 'base tag injected');

$res = http_get("{$appUrl}/?url=" . urlencode("{$originUrl}/page") . '&filters=sidebar');
check(!str_contains($res['body'], 'SIDEBAR'), 'filters applied from query string');
check(str_contains($res['body'], 'CONTENT'), 'filtered page keeps other content');

$res = http_get("{$appUrl}/?url=" . urlencode("{$originUrl}/styled"));
check($res['status'] === 200, 'styled page returns 200');
check(str_contains($res['body'], "url(\"{$originUrl}/img/bg.png\")"), 'stylesheet inlined with absolute urls');
check(!str_contains($res['body'], 'href="/style.css"'), 'inlined stylesheet link removed');
check(str_contains($res['body'], 'href="/gone.css"'), 'unfetchable stylesheet link kept as fallback');

$res = http_get("{$appUrl}/?url=" . urlencode("{$originUrl}/challenge"));
check($res['status'] === 502, 'bot challenge page reported as an error');
check(str_contains($res['body'], 'block automated access'), 'bot challenge error explains the block');
check(str_contains($res['body'], "href=\"{$originUrl}/challenge\""), 'bot challenge error links to the original');

$dom = '<html><head><title>Posted</title><script>evil()</script></head><body><div class="sidebar">SIDEBAR</div><p>POSTEDCONTENT</p></body></html>';
$res = http_get("{$appUrl}/?url=" . urlencode('https://news.example/story'), false, 'POST', $dom);
check($res['status'] === 200, 'POST DOM returns 200');
check(($res['headers']['access-control-allow-origin'] ?? '') === '*', 'POST response allows cross-origin reads');
check(str_contains($res['body'], 'POSTEDCONTENT'), 'POST content kept');
check(!str_contains($res['body'], '<script'), 'POST scripts stripped');
check(str_contains($res['body'], '<base href="https://news.example/story">'), 'POST base tag uses supplied url');

$res = http_get("{$appUrl}/?url=" . urlencode('https://news.example/story') . '&filters=sidebar', false, 'POST', $dom);
check(!str_contains($res['body'], 'SIDEBAR'), 'POST filters applied from query string');
check(str_contains($res['body'], 'POSTEDCONTENT'), 'POST filtered page keeps other content');

$res = http_get("{$appUrl}/?url=" . urlencode('ftp://news.example/story'), false, 'POST', $dom);
check($res['status'] === 400, 'POST rejects non-http url');

$res = http_get("{$appUrl}/?url=" . urlencode('https://news.example/story'), false, 'POST', '');
check($res['status'] === 400, 'POST rejects empty body');

$res = http_get("{$appUrl}/", false, 'OPTIONS');
check($res['status'] === 204, 'CORS preflight answered with 204');
check(($res['headers']['access-control-allow-origin'] ?? '') === '*', 'preflight allows any origin');

$res = http_get("{$appUrl}/?url=" . urlencode("{$originUrl}/image"));
check($res['status'] === 302, 'non-HTML content redirects');
check(($res['headers']['location'] ?? '') === "{$originUrl}/image", 'redirect points at the original');

$res = http_get("{$appUrl}/?url=" . urlencode("{$originUrl}/missing"));
check($res['status'] === 502, 'upstream error reported as 502');

$res = http_get("{$appUrl}/{$originUrl}/page");
check($res['status'] === 200, 'path-style target works');
check(str_contains($res['body'], 'CONTENT'), 'path-style target returns the page');

$res = http_get("{$appUrl}/{$originUrl}/page?filters=sidebar");
check(!str_contains($res['body'], 'SIDEBAR'), 'path-style filters applied');
check(str_contains($res['body'], 'CONTENT'), 'path-style filters keep other content');

$res = http_get("{$appUrl}/favicon.ico");
check($res['status'] === 200, 'favicon.ico served as a real file');
check(str_starts_with($res['body'], "\x00\x00\x01\x00"), 'favicon.ico is an ICO file, not a fetched page');

$res = http_get("{$appUrl}/privacy");
check($res['status'] === 200, 'privacy policy page served');
check(str_contains($res['body'], 'Privacy Policy'), 'privacy page has content');

$res = http_get("{$appUrl}/?url=" . urlencode("{$originUrl}/big"));
check($res['status'] === 200, 'page with megabytes of inline script still sanitizes');
check(str_contains($res['body'], 'BIGCONTENT'), 'big page content kept');
check(!str_contains($res['body'], '<script'), 'big page scripts stripped');

if ($failures > 0) {
    echo "\n{$failures} check(s) failed\n";
    exit(1);
}
echo "\nAll server checks passed\n";
