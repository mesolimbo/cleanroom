<?php

declare(strict_types=1);

require __DIR__ . '/../index.php';

$failures = 0;

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

$sample = <<<'HTML'
<!DOCTYPE html>
<html>
<head><title>Test</title><script src="evil.js"></script></head>
<body onload="alert(1)">
  <div id="ad-container-top" class="x">AD</div>
  <div class="sidebar widget">SIDEBAR</div>
  <div id="content" data-track="123" data-ga="ua">
    <p onclick="track()">Hello <a href="javascript:alert(1)">bad link</a> <a href="/relative">good link</a></p>
    <iframe src="https://tracker.example"></iframe>
    <noscript>no js</noscript>
  </div>
</body>
</html>
HTML;

$out = cleanroom_sanitize($sample, [], 'https://example.com/page');
check(!str_contains($out, '<script'), 'scripts removed');
check(!str_contains($out, '<iframe'), 'iframes removed');
check(!str_contains($out, '<noscript'), 'noscript removed');
check(!str_contains($out, 'onclick'), 'inline handlers removed');
check(!str_contains($out, 'onload'), 'body onload removed');
check(!str_contains($out, 'data-track'), 'tracking attributes removed');
check(!str_contains($out, 'data-ga'), 'data-ga removed');
check(!str_contains($out, 'javascript:'), 'javascript: hrefs removed');
check(str_contains($out, 'Hello'), 'content kept');
check(str_contains($out, 'href="/relative"'), 'normal links kept');
check(str_starts_with($out, '<!DOCTYPE html>'), 'doctype present');
check(str_contains($out, '<base href="https://example.com/page">'), 'base tag injected');

$out = cleanroom_sanitize($sample, ['^ad-container', 'sidebar'], 'https://example.com/page');
check(!str_contains($out, 'AD'), 'filtered div removed by id');
check(!str_contains($out, 'SIDEBAR'), 'filtered div removed by class');
check(str_contains($out, 'Hello'), 'unfiltered content kept');

$out = cleanroom_sanitize('<html><head><base href="https://a.com/"></head><body>x</body></html>', [], 'https://b.com/');
check(substr_count($out, '<base ') === 1, 'existing base tag not duplicated');

$out = cleanroom_sanitize('<html><head></head><body><div id="a">x</div></body></html>', ['[invalid'], 'https://e.com/');
check(str_contains($out, 'x'), 'invalid regex skipped without crashing');

check(cleanroom_parse_filters('["sidebar","^ad-"]') === ['sidebar', '^ad-'], 'filters parsed from JSON');
check(cleanroom_parse_filters('sidebar,^ad-') === ['sidebar', '^ad-'], 'filters parsed from comma list');
check(cleanroom_parse_filters(null) === [], 'missing filters yield empty list');

$lazy = '<html><head></head><body>'
  . '<img src="data:image/gif;base64,R0lGOD" data-src="https://cdn.example/real.jpg" data-srcset="https://cdn.example/real-2x.jpg 2x">'
  . '<img src="https://cdn.example/eager.jpg" data-src="https://cdn.example/wrong.jpg">'
  . '<picture><source data-srcset="https://cdn.example/pic.webp"><img data-src="https://cdn.example/pic.jpg"></picture>'
  . '</body></html>';
$out = cleanroom_sanitize($lazy, [], 'https://example.com/');
check(str_contains($out, 'src="https://cdn.example/real.jpg"'), 'lazy data-src promoted to src');
check(str_contains($out, 'srcset="https://cdn.example/real-2x.jpg 2x"'), 'lazy data-srcset promoted to srcset');
check(str_contains($out, 'src="https://cdn.example/eager.jpg"'), 'real src not clobbered by data-src');
check(!str_contains($out, ' src="https://cdn.example/wrong.jpg"'), 'eager image keeps its own src');
check(str_contains($out, 'srcset="https://cdn.example/pic.webp"'), 'source element srcset promoted');
check(str_contains($out, 'src="https://cdn.example/pic.jpg"'), 'srcless image gets data-src');
check(str_contains($out, 'animation:none!important'), 'animation-freezing style injected');

check(cleanroom_resolve_url('/a/b.css', 'https://ex.com/page/x.html') === 'https://ex.com/a/b.css', 'root-relative URL resolved');
check(cleanroom_resolve_url('b.css?v=2', 'https://ex.com/page/x.html') === 'https://ex.com/page/b.css?v=2', 'relative URL keeps query');
check(cleanroom_resolve_url('../up.css', 'https://ex.com/a/b/x.html') === 'https://ex.com/a/up.css', 'dot segments collapsed');
check(cleanroom_resolve_url('//cdn.ex.com/c.css', 'https://ex.com/x') === 'https://cdn.ex.com/c.css', 'protocol-relative URL resolved');
check(cleanroom_resolve_url('https://cdn.ex.com/c.css', 'https://ex.com/x') === 'https://cdn.ex.com/c.css', 'absolute URL untouched');
check(cleanroom_resolve_url('data:text/css,x', 'https://ex.com/x') === null, 'data URL left alone');
check(cleanroom_resolve_url('style.css', 'https://ex.com:8080/d/x') === 'https://ex.com:8080/d/style.css', 'port preserved');

$css = 'body{background:url(../img/bg.png)} @font-face{src:url("fonts/a.woff2")} @import "extra.css"; .x{background:url(data:image/gif;base64,AA)}';
$abs = cleanroom_absolutize_css($css, 'https://ex.com/assets/css/main.css');
check(str_contains($abs, 'url("https://ex.com/assets/img/bg.png")'), 'css url() absolutized');
check(str_contains($abs, 'url("https://ex.com/assets/css/fonts/a.woff2")'), 'quoted css url() absolutized');
check(str_contains($abs, '@import "https://ex.com/assets/css/extra.css"'), 'css @import absolutized');
check(str_contains($abs, 'url(data:image/gif;base64,AA)'), 'data url() untouched');

$linked = '<html><head>'
  . '<link rel="stylesheet" href="/css/ok.css">'
  . '<link rel="stylesheet" href="/css/missing.css" media="print">'
  . '<link rel="preload" as="style" href="/css/pre.css">'
  . '</head><body>x</body></html>';
$fakeFetch = fn (string $url) => str_contains($url, 'ok.css') ? 'h1>span{background:url(i.png)}' : null;
$out = cleanroom_sanitize($linked, [], 'https://ex.com/page', $fakeFetch);
check(str_contains($out, '<style>h1>span{background:url("https://ex.com/css/i.png")}</style>'), 'stylesheet inlined with absolutized urls');
check(!str_contains($out, 'href="/css/ok.css"'), 'inlined link element removed');
check(str_contains($out, 'href="/css/missing.css"'), 'failed fetch leaves link as fallback');
check(str_contains($out, 'href="/css/pre.css"'), 'preload link untouched');

$out = cleanroom_sanitize($linked, [], 'https://ex.com/page');
check(str_contains($out, 'href="/css/ok.css"'), 'no fetcher means no inlining');

check(cleanroom_looks_like_challenge('<html><head><title>Client Challenge</title></head></html>'), 'F5 challenge page detected');
check(cleanroom_looks_like_challenge('<html><head><title>Just a moment...</title></head></html>'), 'Cloudflare challenge page detected');
check(!cleanroom_looks_like_challenge('<html><head><title>Real Article</title></head><body>Just a moment ago</body></html>'), 'ordinary page not flagged as challenge');

check(cleanroom_normalize_target('cnn.com') === 'https://cnn.com', 'schemeless target defaults to https');
check(cleanroom_normalize_target('cnn.com/politics?a=b') === 'https://cnn.com/politics?a=b', 'schemeless target keeps path and query');
check(cleanroom_normalize_target('http://cnn.com/') === 'http://cnn.com/', 'explicit http kept');
check(cleanroom_normalize_target('https:/cnn.com/x') === 'https://cnn.com/x', 'collapsed scheme slash repaired');
check(cleanroom_normalize_target('//cnn.com') === 'https://cnn.com', 'protocol-relative target defaults to https');
check(cleanroom_normalize_target('ftp://cnn.com') === 'ftp://cnn.com', 'other schemes left for the guard to reject');

$blocked = 0;
foreach (['ftp://example.com/x', 'http://localhost/x', 'http://127.0.0.1/x', 'http://169.254.169.254/x', 'http://10.1.2.3/x', 'http://[::1]/x'] as $bad) {
    try {
        cleanroom_assert_public_target($bad);
    } catch (RuntimeException $e) {
        $blocked++;
    }
}
check($blocked === 6, 'private and non-http targets are all blocked');

if ($failures > 0) {
    echo "\n{$failures} check(s) failed\n";
    exit(1);
}
echo "\nAll sanitize checks passed\n";
