<?php

declare(strict_types=1);
$base = realpath(__DIR__.'/../site');
$route = $path === '/' || $path === '/docs' || $path === '/docs/' ? 'index.html' : substr($path, strlen('/docs/'));
$file = $base ? realpath($base.'/'.rawurldecode($route)) : false;
if (! $base || ! $file || ! str_starts_with($file, $base.DIRECTORY_SEPARATOR) || ! is_file($file)) {
    http_response_code(404);
    header('Content-Type: text/plain');
    echo 'Documentation unavailable. Run npm run docs:build.';

    return;
}
$type = match (pathinfo($file, PATHINFO_EXTENSION)) {
    'html' => 'text/html; charset=utf-8','css' => 'text/css','js' => 'text/javascript','png' => 'image/png','svg' => 'image/svg+xml','json' => 'application/json','docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',default => 'text/plain; charset=utf-8'
};
header('Content-Type: '.$type);
header('X-Content-Type-Options: nosniff');
if ($_SERVER['REQUEST_METHOD'] !== 'HEAD') {
    readfile($file);
}
