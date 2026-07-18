<?php

// Router for the PHP built-in server, mimicking Apache's FallbackResource:
// serve real files as-is, hand everything else to index.php
$docRoot = $_SERVER['DOCUMENT_ROOT'] ?? __DIR__;
$path = explode('?', $_SERVER['REQUEST_URI'] ?? '/', 2)[0];
if ($path !== '/' && is_file($docRoot . $path)) {
    return false;
}
require $docRoot . '/index.php';
