<?php

// Router for the fixture origin server used by test_server.php
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($path === '/page') {
    header('Content-Type: text/html');
    echo '<html><head><title>Fixture</title></head><body><script>evil()</script><div class="sidebar">SIDEBAR</div><p>CONTENT</p></body></html>';
} elseif ($path === '/big') {
    // Mimics news sites: a normal page bloated by megabytes of inline script
    header('Content-Type: text/html');
    $blob = str_repeat('{"data":"xxxxxxxxxxxxxxxx"},', 300000);
    echo "<html><head><title>Big</title></head><body><script>var d=[{$blob}];</script><p>BIGCONTENT</p></body></html>";
} elseif ($path === '/styled') {
    header('Content-Type: text/html');
    echo '<html><head><title>Styled</title><link rel="stylesheet" href="/style.css"><link rel="stylesheet" href="/gone.css"></head><body><p>STYLEDCONTENT</p></body></html>';
} elseif ($path === '/style.css') {
    header('Content-Type: text/css');
    echo 'h1{background:url(img/bg.png)}';
} elseif ($path === '/challenge') {
    // Bot walls answer 200 with a script-driven interstitial
    header('Content-Type: text/html');
    echo '<html><head><title>Client Challenge</title></head><body><script>solve()</script><div style="display:none">A required part of this site could not load.</div></body></html>';
} elseif ($path === '/image') {
    header('Content-Type: image/png');
    echo 'notreallyapng';
} else {
    http_response_code(404);
    header('Content-Type: text/html');
    echo '<html><body>nope</body></html>';
}
