<?php
/**
 * Lightweight Sentry error reporting — no Composer required.
 * Only active on production (not localhost).
 */
if (!defined('IS_LOCAL') || IS_LOCAL) return;

define('SENTRY_DSN', 'https://04f15ffda1b7c2e5af186e9a1fb13430@o4511370836705280.ingest.de.sentry.io/4511370838685904');

function _sentry_send(array $payload): void {
    $dsn     = parse_url(SENTRY_DSN);
    $key     = $dsn['user'];
    $host    = $dsn['host'];
    $project = ltrim($dsn['path'], '/');
    $url     = "https://{$host}/api/{$project}/store/";

    $body = json_encode(array_merge([
        'platform'   => 'php',
        'server_name' => $_SERVER['HTTP_HOST'] ?? 'healthsphere.info',
        'request'    => [
            'url'    => (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '') . ($_SERVER['REQUEST_URI'] ?? ''),
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'GET',
        ],
    ], $payload));

    $ctx = stream_context_create(['http' => [
        'method'  => 'POST',
        'timeout' => 3,
        'header'  => implode("\r\n", [
            'Content-Type: application/json',
            "X-Sentry-Auth: Sentry sentry_version=7, sentry_client=healthsphere/1.0, sentry_key={$key}",
            'Content-Length: ' . strlen($body),
        ]),
        'content' => $body,
        'ignore_errors' => true,
    ]]);
    @file_get_contents($url, false, $ctx);
}

set_error_handler(function(int $errno, string $errstr, string $errfile, int $errline): bool {
    if (!(error_reporting() & $errno)) return false;
    $types = [E_ERROR=>'error',E_WARNING=>'warning',E_NOTICE=>'info',E_USER_ERROR=>'error',E_USER_WARNING=>'warning'];
    _sentry_send([
        'level'   => $types[$errno] ?? 'error',
        'message' => $errstr,
        'culprit' => "{$errfile}:{$errline}",
        'exception' => ['values' => [[
            'type'  => "PHP Error ({$errno})",
            'value' => $errstr,
            'stacktrace' => ['frames' => [['filename' => $errfile, 'lineno' => $errline]]],
        ]]],
    ]);
    return false;
});

set_exception_handler(function(Throwable $e): void {
    _sentry_send([
        'level'   => 'error',
        'message' => $e->getMessage(),
        'culprit' => $e->getFile() . ':' . $e->getLine(),
        'exception' => ['values' => [[
            'type'  => get_class($e),
            'value' => $e->getMessage(),
            'stacktrace' => ['frames' => array_map(fn($f) => [
                'filename' => $f['file'] ?? 'unknown',
                'lineno'   => $f['line'] ?? 0,
                'function' => ($f['class'] ?? '') . ($f['type'] ?? '') . ($f['function'] ?? ''),
            ], array_reverse($e->getTrace()))],
        ]]],
    ]);
});
