<?php
// Request router for the built-in PHP server.
//
// The site runs `php -S` from the repository root, which means every file in the
// image is a URL, including ones that are not part of the site (the build files).
//
// Deny a fixed set of paths and hand everything else back to the server
// unchanged by returning false. That keeps the server's fallback to index.php
// for unknown paths, which is how short links like /XdACBmg3lx6RG reach the
// page, and leaves data/goats.json, goats/*.gif and admin/ working as before.

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$path = is_string($path) ? rawurldecode($path) : '/';

// Collapse repeated separators so //router.php cannot slip past a test.
$path = preg_replace('#/+#', '/', $path);

$internal = [
    // Any dot-prefixed segment: .git, .info, .env.
    '#/\.#',

    // Build and deployment files.
    '#^/(Dockerfile|router\.php)$#i',
    '#^/docker(/|$)#i',
];

foreach ($internal as $pattern) {
    if (preg_match($pattern, $path)) {
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        echo "Not Found\n";
        return true;
    }
}

return false;
