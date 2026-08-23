<?php
/**
 * PHP built-in server router — trailing slash + static files.
 * Usage: php -S 0.0.0.0:8000 router.php
 */
$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/');
$file = __DIR__ . $uri;

// Ders videolarına düz URL ile erişim yok — api/media.php kullanılır
if (preg_match('#^/uploads/courses/.+\.(mp4|webm|mov|m4v|avi|mkv)$#i', $uri)) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo "403 Forbidden\n";
    return true;
}

// Exact file
if ($uri !== '/' && is_file($file)) {
    return false; // serve as-is
}

// Directory → index.php / index.html
if (is_dir($file)) {
    if (substr($uri, -1) !== '/') {
        header('Location: ' . $uri . '/', true, 301);
        exit;
    }
    foreach (['index.php', 'index.html'] as $idx) {
        if (is_file($file . '/' . $idx)) {
            if (substr($idx, -4) === '.php') {
                require $file . '/' . $idx;
                return true;
            }
            return false;
        }
    }
}

http_response_code(404);
header('Content-Type: text/plain; charset=utf-8');
echo "404 Not Found: $uri\n";
return true;
