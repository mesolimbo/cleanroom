<?php

declare(strict_types=1);

// Large pages need headroom; shared hosts usually allow raising this per-script
@ini_set('memory_limit', '256M');

const CLEANROOM_MAX_HTML_BYTES = 20971520;
const CLEANROOM_FETCH_TIMEOUT = 15;
// A realistic browser UA: many sites refuse obvious bots outright
const CLEANROOM_USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36';
const CLEANROOM_CSP = "default-src * data: blob: 'unsafe-inline'; script-src 'none'; frame-src 'none'; object-src 'none'";
const CLEANROOM_REMOVED_TAGS = ['script', 'iframe', 'noscript', 'object', 'embed'];
const CLEANROOM_TRACKING_ATTRIBUTES = ['data-track', 'data-analytics', 'data-ga'];
const CLEANROOM_MAX_STYLESHEETS = 10;
const CLEANROOM_MAX_CSS_BYTES = 2097152;
const CLEANROOM_CSS_FETCH_TIMEOUT = 5;
// Page titles used by bot-protection vendors; these challenges arrive with
// HTTP 200 and render blank once their scripts are stripped
const CLEANROOM_CHALLENGE_TITLES = [
    'Client Challenge',                    // F5 Distributed Cloud
    'Just a moment',                       // Cloudflare
    'Attention Required!',                 // Cloudflare block page
    'Access to this page has been denied', // HUMAN / PerimeterX
    'Pardon Our Interruption',             // Imperva / Distil
    'Verifying you are human',
];

// Palette lifted from the Cleanroom icon: steel blue frame, green broom,
// ink screen, off-white chrome. Pages are pure CSS (our CSP forbids scripts).
const CLEANROOM_STYLE = <<<'CSS'
*, *::before, *::after { box-sizing: border-box; }
:root {
  color-scheme: light dark;
  --blue: #3571a0;
  --green: #2f8445;
  --green-deep: #256b38;
  --bg: light-dark(#f3f3f3, #191d21);
  --card: light-dark(#ffffff, #23282e);
  --text: light-dark(#231f20, #e8eaed);
  --muted: light-dark(#5f6b76, #9aa4ae);
  --border: light-dark(#d9dee4, #3a424a);
  --link: light-dark(#3571a0, #8ab9dc);
  accent-color: var(--green);
}
body {
  margin: 0;
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 1.5rem;
  font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
  line-height: 1.6;
  background: var(--bg);
  color: var(--text);
}
main {
  position: relative;
  overflow: hidden;
  width: 100%;
  max-width: var(--card-width, 26rem);
  padding: 2.75rem 2.25rem 2.25rem;
  background: var(--card);
  border: 1px solid var(--border);
  border-radius: 1rem;
  box-shadow: 0 16px 40px light-dark(rgb(35 31 32 / 0.10), rgb(0 0 0 / 0.45));
}
main::before {
  content: "";
  position: absolute;
  inset: 0 0 auto 0;
  height: 0.3rem;
  background: linear-gradient(90deg, var(--blue), var(--green));
}
.logo { display: block; margin: 0 auto 1.25rem; }
h1 {
  margin: 0 0 0.25rem;
  font-size: 1.75rem;
  text-align: center;
  letter-spacing: 0.02em;
}
.tagline {
  margin: 0 0 1.75rem;
  text-align: center;
  color: var(--muted);
}
form { display: flex; flex-direction: column; gap: 0.75rem; }
input[type=url] {
  width: 100%;
  padding: 0.75rem 1rem;
  font: inherit;
  color: var(--text);
  background: light-dark(#ffffff, #1b2026);
  border: 1px solid var(--border);
  border-radius: 0.6rem;
}
input[type=url]:focus-visible {
  outline: 2px solid var(--blue);
  outline-offset: 1px;
  border-color: var(--blue);
}
button {
  padding: 0.75rem 1rem;
  font: inherit;
  font-weight: 600;
  color: #ffffff;
  background: var(--green);
  border: none;
  border-radius: 0.6rem;
  cursor: pointer;
}
button:hover { background: var(--green-deep); }
button:focus-visible { outline: 2px solid var(--blue); outline-offset: 2px; }
a { color: var(--link); }
code {
  padding: 0.1rem 0.35rem;
  border-radius: 0.3rem;
  font-size: 0.9em;
  background: light-dark(rgb(53 113 160 / 0.10), rgb(53 113 160 / 0.35));
}
a:has(> code) { text-decoration: none; }
a:hover > code, a:focus-visible > code {
  background: light-dark(rgb(53 113 160 / 0.22), rgb(53 113 160 / 0.55));
}
.hint {
  margin: 1.5rem 0 0;
  font-size: 0.85rem;
  text-align: center;
  color: var(--muted);
}
.hint + .hint { margin-top: 0.5rem; }
.prose h1 { text-align: left; }
.prose h2 { font-size: 1.1rem; margin: 1.5rem 0 0.5rem; color: var(--link); }
.prose ul { margin: 0; padding-left: 1.25rem; }
.prose li + li { margin-top: 0.5rem; }
CSS;

// Shared page shell for landing, privacy, and error pages
function cleanroom_page(string $title, string $content, string $cardWidth = '26rem'): string
{
    $style = CLEANROOM_STYLE;
    $safeTitle = htmlspecialchars($title, ENT_QUOTES);
    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="color-scheme" content="light dark">
  <title>{$safeTitle}</title>
  <link rel="icon" href="/favicon.ico">
  <style>{$style}</style>
</head>
<body>
  <main style="--card-width: {$cardWidth}">
{$content}
  </main>
</body>
</html>
HTML;
}

function cleanroom_landing_page(): string
{
    return cleanroom_page('Cleanroom', <<<'HTML'
    <img class="logo" src="/logo.svg" alt="" width="96" height="96">
    <h1>Cleanroom</h1>
    <p class="tagline">A clean, script-free reading view of any webpage.</p>
    <form method="get" action="">
      <input type="url" name="url" placeholder="https://example.com/article" required autofocus>
      <button type="submit">Sanitize</button>
    </form>
    <p class="hint">Shortcut: append the address to this page's URL, e.g. <a href="/cnn.com"><code>/cnn.com</code></a>, or use <a href="/?url=cnn.com"><code>?url=cnn.com</code></a>.</p>
    <p class="hint"><a href="/privacy">Privacy policy</a></p>
HTML);
}

function cleanroom_privacy_page(): string
{
    return cleanroom_page('Cleanroom Privacy Policy', <<<'HTML'
    <div class="prose">
      <img class="logo" src="/logo.svg" alt="" width="64" height="64" style="margin: 0 0 1rem">
      <h1>Cleanroom Privacy Policy</h1>
      <p>Cleanroom (this website and the Cleanroom browser extension) sanitizes a webpage you explicitly request, removing scripts and trackers, and shows you the result. What reaches this server depends on how you ask for a page.</p>
      <h2>What is collected</h2>
      <ul>
        <li>When you submit a URL (on this website, or by choosing "Open in Cleanroom" on a link), that URL is sent to the server so it can fetch and sanitize the page.</li>
        <li>When you click the Cleanroom icon on a page you are viewing, the extension sends that page's already-loaded HTML to the server to sanitize. This lets the reading view match what your browser actually loaded, and it happens only on that click, never while you browse.</li>
        <li>In both cases the page is sanitized as the request is served and returned to you; its content is not stored or retained afterward.</li>
        <li>Standard web server logs (IP address, requested URL, timestamp) are kept briefly by the hosting provider for operational purposes.</li>
      </ul>
      <h2>What is not collected</h2>
      <ul>
        <li>No accounts, no cookies, no analytics, no advertising, and no tracking of any kind.</li>
        <li>Your filter patterns and settings are stored locally in your browser, never on the server.</li>
        <li>Nothing is sold or shared with third parties.</li>
      </ul>
      <p>Questions: [redacted]</p>
      <p class="hint" style="text-align: left"><a href="/">&larr; Back to Cleanroom</a></p>
    </div>
HTML, '40rem');
}

// Accepts hand-crafted targets: no scheme defaults to https, and a scheme
// whose double slash was collapsed by the web server is repaired
function cleanroom_normalize_target(string $raw): string
{
    $raw = trim($raw);
    if (str_starts_with($raw, '//')) {
        return 'https:' . $raw;
    }
    if (preg_match('~^https?:/+~i', $raw)) {
        return preg_replace('~^(https?):/+~i', '$1://', $raw);
    }
    if (!preg_match('~^[a-z][a-z0-9+.-]*://~i', $raw)) {
        return 'https://' . $raw;
    }
    return $raw;
}

// Supports path-style requests like /cnn.com: everything after the leading
// slash is the target; a filters param is peeled off, the rest of the query
// string belongs to the target. Returns [target, filters] or null.
function cleanroom_target_from_path(): ?array
{
    $uri = $_SERVER['REQUEST_URI'] ?? '/';
    $parts = explode('?', $uri, 2);
    $path = ltrim($parts[0], '/');
    if ($path === '' || $path === 'index.php') {
        return null;
    }
    $filters = null;
    if (isset($parts[1]) && $parts[1] !== '') {
        parse_str($parts[1], $params);
        if (isset($params['filters']) && is_string($params['filters'])) {
            $filters = $params['filters'];
            unset($params['filters']);
        }
        if ($params !== []) {
            $path .= '?' . http_build_query($params);
        }
    }
    return [$path, $filters];
}

function cleanroom_is_private_ip(string $ip): bool
{
    return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
}

function cleanroom_assert_public_target(string $url): void
{
    $parts = parse_url($url);
    $scheme = strtolower($parts['scheme'] ?? '');
    if ($scheme !== 'http' && $scheme !== 'https') {
        throw new RuntimeException('Only http and https URLs are supported');
    }
    $host = strtolower($parts['host'] ?? '');
    if ($host === '') {
        throw new RuntimeException('URL has no host');
    }
    // Escape hatch for tests and local experiments (fetching from localhost)
    if (getenv('CLEANROOM_ALLOW_PRIVATE') === '1') {
        return;
    }
    if (preg_match('/^(localhost|.*\.local|.*\.internal)$/', $host)) {
        throw new RuntimeException('Host is not allowed');
    }
    $bare = trim($host, '[]');
    if (filter_var($bare, FILTER_VALIDATE_IP) !== false) {
        if (cleanroom_is_private_ip($bare)) {
            throw new RuntimeException('Host is not allowed');
        }
        return;
    }
    $address = gethostbyname($host);
    if ($address === $host) {
        throw new RuntimeException('Could not resolve host');
    }
    if (cleanroom_is_private_ip($address)) {
        throw new RuntimeException('Host is not allowed');
    }
}

function cleanroom_fetch(string $url, int $timeout = CLEANROOM_FETCH_TIMEOUT): array
{
    $body = '';
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_USERAGENT => CLEANROOM_USER_AGENT,
        CURLOPT_ENCODING => '',
        // A WAF that sees "Chrome" over HTTP/1.1 with no client hints or
        // Sec-Fetch metadata challenges instantly; match a real top-level
        // navigation instead. Client hints must agree with the UA version.
        CURLOPT_HTTP_VERSION => defined('CURL_HTTP_VERSION_2TLS') ? CURL_HTTP_VERSION_2TLS : CURL_HTTP_VERSION_1_1,
        CURLOPT_HTTPHEADER => [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7',
            'Accept-Language: en-US,en;q=0.9',
            'Upgrade-Insecure-Requests: 1',
            'sec-ch-ua: "Chromium";v="149", "Google Chrome";v="149", "Not.A/Brand";v="24"',
            'sec-ch-ua-mobile: ?0',
            'sec-ch-ua-platform: "Windows"',
            'Sec-Fetch-Site: none',
            'Sec-Fetch-Mode: navigate',
            'Sec-Fetch-User: ?1',
            'Sec-Fetch-Dest: document',
        ],
        CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
        CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
        CURLOPT_WRITEFUNCTION => function ($ch, $chunk) use (&$body) {
            $body .= $chunk;
            if (strlen($body) > CLEANROOM_MAX_HTML_BYTES) {
                return -1;
            }
            return strlen($chunk);
        },
    ]);
    curl_exec($ch);
    if (curl_errno($ch) !== 0) {
        if (strlen($body) > CLEANROOM_MAX_HTML_BYTES) {
            throw new RuntimeException('Page is too large to sanitize');
        }
        throw new RuntimeException('Could not fetch the page: ' . curl_error($ch));
    }
    return [
        'status' => (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE),
        'contentType' => (string) (curl_getinfo($ch, CURLINFO_CONTENT_TYPE) ?: ''),
        'effectiveUrl' => (string) curl_getinfo($ch, CURLINFO_EFFECTIVE_URL),
        'body' => $body,
    ];
}

function cleanroom_looks_like_challenge(string $html): bool
{
    if (!preg_match('~<title[^>]*>([^<]*)</title>~i', substr($html, 0, 8192), $match)) {
        return false;
    }
    $title = html_entity_decode(trim($match[1]), ENT_QUOTES | ENT_HTML5);
    foreach (CLEANROOM_CHALLENGE_TITLES as $marker) {
        if (stripos($title, $marker) !== false) {
            return true;
        }
    }
    return false;
}

// Resolves a reference against a base URL. Returns null for refs that should
// be left alone (data:, fragments, non-http schemes).
function cleanroom_resolve_url(string $ref, string $base): ?string
{
    $ref = trim($ref);
    if ($ref === '' || str_starts_with($ref, '#')) {
        return null;
    }
    if (preg_match('~^[a-z][a-z0-9+.-]*:~i', $ref)) {
        return preg_match('~^https?://~i', $ref) ? $ref : null;
    }
    $parts = parse_url($base);
    if (!isset($parts['scheme'], $parts['host'])) {
        return null;
    }
    if (str_starts_with($ref, '//')) {
        return $parts['scheme'] . ':' . $ref;
    }
    $origin = $parts['scheme'] . '://' . $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '');
    $suffix = '';
    if (preg_match('~^([^?#]*)([?#].*)$~', $ref, $match)) {
        [, $ref, $suffix] = $match;
    }
    $dir = str_starts_with($ref, '/') ? '' : preg_replace('~[^/]*$~', '', $parts['path'] ?? '/');
    $segments = [];
    foreach (explode('/', $dir . $ref) as $segment) {
        if ($segment === '' || $segment === '.') {
            continue;
        }
        if ($segment === '..') {
            array_pop($segments);
            continue;
        }
        $segments[] = $segment;
    }
    return $origin . '/' . implode('/', $segments) . $suffix;
}

// Inlined CSS loses its own URL, so stylesheet-relative references (fonts,
// images, @imports) must become absolute before embedding
function cleanroom_absolutize_css(string $css, string $cssUrl): string
{
    $css = preg_replace_callback(
        '~url\(\s*(["\']?)([^"\')\s]+)\1\s*\)~i',
        function ($m) use ($cssUrl) {
            $resolved = cleanroom_resolve_url($m[2], $cssUrl);
            return $resolved === null ? $m[0] : 'url("' . $resolved . '")';
        },
        $css
    ) ?? $css;
    return preg_replace_callback(
        '~@import\s+(["\'])([^"\']+)\1~i',
        function ($m) use ($cssUrl) {
            $resolved = cleanroom_resolve_url($m[2], $cssUrl);
            return $resolved === null ? $m[0] : '@import "' . $resolved . '"';
        },
        $css
    ) ?? $css;
}

function cleanroom_fetch_stylesheet(string $url): ?string
{
    try {
        cleanroom_assert_public_target($url);
        $response = cleanroom_fetch($url, CLEANROOM_CSS_FETCH_TIMEOUT);
    } catch (RuntimeException $e) {
        return null;
    }
    if ($response['status'] < 200 || $response['status'] >= 300) {
        return null;
    }
    if (strlen($response['body']) > CLEANROOM_MAX_CSS_BYTES) {
        return null;
    }
    // An HTML response here is an error or challenge page, not a stylesheet
    if (str_contains(strtolower($response['contentType']), 'html')) {
        return null;
    }
    return $response['body'];
}

// Browsers fetch <link> stylesheets from the original host, where hotlink
// and CORS rules can reject them; fetching server-side and embedding the
// CSS keeps styling intact. Fetch failures leave the <link> as a fallback.
function cleanroom_inline_stylesheets(DOMDocument $doc, string $baseUrl, callable $fetchCss): void
{
    $baseNode = $doc->getElementsByTagName('base')->item(0);
    if ($baseNode !== null) {
        $declared = trim($baseNode->getAttribute('href'));
        if ($declared !== '') {
            $baseUrl = cleanroom_resolve_url($declared, $baseUrl) ?? $baseUrl;
        }
    }
    $inlined = 0;
    foreach (iterator_to_array($doc->getElementsByTagName('link')) as $link) {
        if ($inlined >= CLEANROOM_MAX_STYLESHEETS) {
            break;
        }
        $rel = strtolower(trim($link->getAttribute('rel')));
        $href = trim($link->getAttribute('href'));
        if ($rel !== 'stylesheet' || $href === '') {
            continue;
        }
        $url = cleanroom_resolve_url($href, $baseUrl);
        if ($url === null) {
            continue;
        }
        $css = $fetchCss($url);
        if ($css === null) {
            continue;
        }
        $style = $doc->createElement('style');
        $media = trim($link->getAttribute('media'));
        if ($media !== '') {
            $style->setAttribute('media', $media);
        }
        $style->appendChild($doc->createTextNode(cleanroom_absolutize_css($css, $url)));
        $link->parentNode?->replaceChild($style, $link);
        $inlined++;
    }
}

function cleanroom_parse_filters(?string $raw): array
{
    if ($raw === null || $raw === '') {
        return [];
    }
    $parsed = json_decode($raw);
    if (is_array($parsed)) {
        return array_values(array_filter($parsed, fn ($p) => is_string($p) && $p !== ''));
    }
    // Not JSON: treat as comma-separated patterns (hand-edited URLs)
    return array_values(array_filter(array_map('trim', explode(',', $raw)), fn ($p) => $p !== ''));
}

// Lazy-loading normally needs JS; promote the deferred attributes so
// images render on a script-free page
function cleanroom_promote_lazy_media(DOMElement $node): void
{
    $src = trim($node->getAttribute('src'));
    if ($src === '' || $src === 'about:blank' || str_starts_with($src, 'data:')) {
        foreach (['data-src', 'data-lazy-src', 'data-original'] as $attr) {
            $value = trim($node->getAttribute($attr));
            if ($value !== '') {
                $node->setAttribute('src', $value);
                break;
            }
        }
    }
    if (trim($node->getAttribute('srcset')) === '') {
        foreach (['data-srcset', 'data-lazy-srcset'] as $attr) {
            $value = trim($node->getAttribute($attr));
            if ($value !== '') {
                $node->setAttribute('srcset', $value);
                break;
            }
        }
    }
}

function cleanroom_matches_filter(DOMElement $node, array $regexes): bool
{
    $id = $node->getAttribute('id');
    if ($id !== '') {
        foreach ($regexes as $regex) {
            if (preg_match($regex, $id)) {
                return true;
            }
        }
    }
    $classes = preg_split('/\s+/', $node->getAttribute('class'), -1, PREG_SPLIT_NO_EMPTY);
    foreach ($classes as $class) {
        foreach ($regexes as $regex) {
            if (preg_match($regex, $class)) {
                return true;
            }
        }
    }
    return false;
}

function cleanroom_sanitize(string $html, array $filterPatterns, string $baseUrl, ?callable $fetchCss = null): string
{
    // News sites embed megabytes of JSON in inline scripts; dropping script
    // blocks before parsing keeps DOMDocument's memory use reasonable.
    // Anything this regex misses is removed again in the DOM pass below.
    ini_set('pcre.backtrack_limit', '10000000');
    $html = preg_replace('~<script\b[^>]*>.*?</script>~is', '', $html) ?? $html;

    $doc = new DOMDocument();
    libxml_use_internal_errors(true);
    // The XML PI forces libxml to treat the input as UTF-8; it is removed below
    $doc->loadHTML('<?xml encoding="UTF-8">' . $html);
    libxml_clear_errors();
    foreach (iterator_to_array($doc->childNodes) as $node) {
        if ($node->nodeType === XML_PI_NODE) {
            $doc->removeChild($node);
        }
    }
    $doc->encoding = 'UTF-8';
    if ($doc->documentElement === null) {
        return "<!DOCTYPE html>\n<html><body></body></html>";
    }

    foreach (CLEANROOM_REMOVED_TAGS as $tag) {
        foreach (iterator_to_array($doc->getElementsByTagName($tag)) as $node) {
            $node->parentNode?->removeChild($node);
        }
    }

    $regexes = [];
    foreach ($filterPatterns as $pattern) {
        $regex = '~' . str_replace('~', '\~', $pattern) . '~';
        if (@preg_match($regex, '') === false) {
            error_log('Invalid regex pattern: ' . $pattern);
            continue;
        }
        $regexes[] = $regex;
    }
    if ($regexes !== []) {
        foreach (iterator_to_array($doc->getElementsByTagName('div')) as $div) {
            if (cleanroom_matches_filter($div, $regexes)) {
                $div->parentNode?->removeChild($div);
            }
        }
    }

    foreach (iterator_to_array($doc->getElementsByTagName('*')) as $node) {
        foreach (iterator_to_array($node->attributes) as $attr) {
            $name = strtolower($attr->name);
            if (str_starts_with($name, 'on') || in_array($name, CLEANROOM_TRACKING_ATTRIBUTES, true)) {
                $node->removeAttribute($attr->name);
            }
        }
        $href = $node->getAttribute('href');
        if ($href !== '' && str_starts_with(strtolower(trim($href)), 'javascript:')) {
            $node->removeAttribute('href');
        }

        if ($node->tagName === 'img' || $node->tagName === 'source') {
            cleanroom_promote_lazy_media($node);
        }
    }

    if ($fetchCss !== null) {
        cleanroom_inline_stylesheets($doc, $baseUrl, $fetchCss);
    }

    // The page is served from cleanroom's origin, so relative URLs need a base
    $head = $doc->getElementsByTagName('head')->item(0);
    if ($head !== null && $doc->getElementsByTagName('base')->length === 0) {
        $base = $doc->createElement('base');
        $base->setAttribute('href', $baseUrl);
        $head->insertBefore($base, $head->firstChild);
    }

    // Without JS, skeleton placeholders would shimmer forever; freeze them
    if ($head !== null) {
        $style = $doc->createElement('style');
        $style->appendChild($doc->createTextNode('*,*::before,*::after{animation:none!important;transition:none!important}'));
        $head->appendChild($style);
    }

    return "<!DOCTYPE html>\n" . $doc->saveHTML($doc->documentElement);
}

function cleanroom_send_html(int $status, string $body): void
{
    http_response_code($status);
    header('Content-Type: text/html; charset=utf-8');
    header('Content-Security-Policy: ' . CLEANROOM_CSP);
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: no-referrer');
    header('Cache-Control: private, max-age=300');
    echo $body;
}

function cleanroom_send_error(int $status, string $message, string $extraHtml = ''): void
{
    $safe = htmlspecialchars($message, ENT_QUOTES);
    $content = <<<HTML
    <img class="logo" src="/logo.svg" alt="" width="64" height="64">
    <h1>Cleanroom</h1>
    <p class="tagline">{$safe}</p>
{$extraHtml}
    <p class="hint"><a href="/">&larr; Back to Cleanroom</a></p>
HTML;
    cleanroom_send_html($status, cleanroom_page('Cleanroom', $content));
}

// The extension POSTs the DOM its own browser already rendered (at the user's
// IP, past any bot wall) and we only sanitize it: no server-side fetch,
// challenge check, or stylesheet inlining. url and filters ride the query
// string; the raw HTML is the request body.
function cleanroom_handle_post(): void
{
    header('Access-Control-Allow-Origin: *');
    $rawUrl = cleanroom_normalize_target(trim($_GET['url'] ?? ''));
    $scheme = strtolower(parse_url($rawUrl, PHP_URL_SCHEME) ?: '');
    if ($scheme !== 'http' && $scheme !== 'https') {
        cleanroom_send_error(400, 'A valid http or https url parameter is required');
        return;
    }
    $html = file_get_contents('php://input');
    if ($html === false || $html === '') {
        cleanroom_send_error(400, 'Request body is empty');
        return;
    }
    if (strlen($html) > CLEANROOM_MAX_HTML_BYTES) {
        cleanroom_send_error(413, 'Page is too large to sanitize');
        return;
    }
    $filterPatterns = cleanroom_parse_filters($_GET['filters'] ?? null);
    cleanroom_send_html(200, cleanroom_sanitize($html, $filterPatterns, $rawUrl));
}

function cleanroom_handle(): void
{
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if ($method === 'OPTIONS') {
        http_response_code(204);
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type');
        header('Access-Control-Max-Age: 86400');
        return;
    }
    if ($method === 'POST') {
        cleanroom_handle_post();
        return;
    }
    if ($method !== 'GET' && $method !== 'HEAD') {
        http_response_code(405);
        header('Allow: GET, HEAD, POST, OPTIONS');
        return;
    }

    $requestPath = explode('?', $_SERVER['REQUEST_URI'] ?? '/', 2)[0];
    if ($requestPath === '/favicon.ico' || $requestPath === '/robots.txt') {
        http_response_code(404);
        return;
    }
    if ($requestPath === '/privacy') {
        cleanroom_send_html(200, cleanroom_privacy_page());
        return;
    }

    $rawUrl = trim($_GET['url'] ?? '');
    $filtersRaw = $_GET['filters'] ?? null;
    if ($rawUrl === '') {
        $fromPath = cleanroom_target_from_path();
        if ($fromPath !== null) {
            [$rawUrl, $pathFilters] = $fromPath;
            if ($pathFilters !== null) {
                $filtersRaw = $pathFilters;
            }
        }
    }
    if ($rawUrl === '') {
        cleanroom_send_html(200, cleanroom_landing_page());
        return;
    }
    $rawUrl = cleanroom_normalize_target($rawUrl);

    try {
        cleanroom_assert_public_target($rawUrl);
    } catch (RuntimeException $e) {
        cleanroom_send_error(400, 'Invalid URL: ' . $e->getMessage());
        return;
    }

    try {
        $response = cleanroom_fetch($rawUrl);
    } catch (RuntimeException $e) {
        cleanroom_send_error(502, $e->getMessage());
        return;
    }

    // Redirects may have moved us; re-check the final URL
    try {
        cleanroom_assert_public_target($response['effectiveUrl']);
    } catch (RuntimeException $e) {
        cleanroom_send_error(400, 'Invalid URL after redirect: ' . $e->getMessage());
        return;
    }

    if ($response['status'] < 200 || $response['status'] >= 300) {
        $message = 'The page returned status ' . $response['status'];
        if (in_array($response['status'], [401, 403, 429], true)) {
            $message .= '. This site appears to block automated access (bot protection or a paywall),'
                . ' so Cleanroom cannot fetch it server-side.';
        }
        $safeUrl = htmlspecialchars($response['effectiveUrl'], ENT_QUOTES);
        cleanroom_send_error(502, $message, '    <p class="hint"><a href="' . $safeUrl . '">Open the original page</a></p>');
        return;
    }

    $contentType = strtolower($response['contentType']);
    if (!str_contains($contentType, 'text/html') && !str_contains($contentType, 'application/xhtml+xml')) {
        // Not a page (image, PDF, ...): just send the user to the original
        http_response_code(302);
        header('Location: ' . $response['effectiveUrl']);
        return;
    }

    // Bot walls often serve their challenge with HTTP 200; stripped of its
    // scripts it would render as a blank page, so report it as a block instead
    if (cleanroom_looks_like_challenge($response['body'])) {
        $safeUrl = htmlspecialchars($response['effectiveUrl'], ENT_QUOTES);
        cleanroom_send_error(
            502,
            'This site appears to block automated access (bot protection or a paywall), so Cleanroom cannot fetch it server-side.',
            '    <p class="hint"><a href="' . $safeUrl . '">Open the original page</a></p>'
        );
        return;
    }

    $filterPatterns = cleanroom_parse_filters($filtersRaw);
    cleanroom_send_html(200, cleanroom_sanitize($response['body'], $filterPatterns, $response['effectiveUrl'], 'cleanroom_fetch_stylesheet'));
}

if (PHP_SAPI !== 'cli') {
    cleanroom_handle();
}
