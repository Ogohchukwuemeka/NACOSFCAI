<?php
if (ob_get_level() === 0) {
    ob_start();
}

ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.cookie_httponly', '1');
ini_set('session.gc_maxlifetime', '1800');

$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
session_start([
    'cookie_httponly' => true,
    'cookie_secure' => $secure,
    'cookie_samesite' => 'Lax',
]);

if (!headers_sent()) {
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
}

$root = dirname(__DIR__);
if (file_exists($root . '/.env')) {
    $lines = file($root . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        [$name, $value] = array_pad(explode('=', $line, 2), 2, '');
        $_ENV[trim($name)] = trim($value);
        putenv(trim($name) . '=' . trim($value));
    }
}

$sessionTimeout = (int)(getenv('SESSION_TIMEOUT') ?: 1800);
if ($sessionTimeout < 60) {
    $sessionTimeout = 1800;
}

if (!empty($_SESSION['user_id']) && !empty($_SESSION['last_activity'])) {
    if (time() - (int)$_SESSION['last_activity'] >= $sessionTimeout) {
        $_SESSION = [];
        session_regenerate_id(true);
        $_SESSION['session_expired'] = true;
    }
}

if (!defined('APP_ROOT')) {
    define('APP_ROOT', $root);
}
if (!defined('APP_URL')) {
    define('APP_URL', getenv('APP_URL') ?: 'http://localhost/NACOS');
}

spl_autoload_register(function ($class) {
    $paths = [
        APP_ROOT . '/classes/' . $class . '.php',
        APP_ROOT . '/config/' . $class . '.php'
    ];
    foreach ($paths as $path) {
        if (file_exists($path)) {
            require_once $path;
            return;
        }
    }
});

if (file_exists(APP_ROOT . '/vendor/autoload.php')) {
    require_once APP_ROOT . '/vendor/autoload.php';
}

require_once APP_ROOT . '/config/database.php';
require_once APP_ROOT . '/includes/functions.php';

set_error_handler(function ($severity, $message, $file, $line) {
    if (error_reporting() === 0) {
        return false; // Respect the @ operator suppression.
    }
    error_log(sprintf('[%s] %s in %s:%s', $severity, $message, $file, $line));
    throw new ErrorException($message, 0, $severity, $file, $line);
});
