<?php

/**
 * NAi direct cPanel installer — project-root launcher.
 *
 * The actual installer lives at public/install.php so it is reachable when the
 * domain Document Root is correctly set to public/. This root-level copy makes
 * the PHP installer obvious immediately after extracting the ZIP and provides a
 * fallback entry point when the project root itself is being served.
 *
 * Both files are removed automatically after a successful installation.
 */
declare(strict_types=1);

$target = __DIR__.'/public/install.php';
if (! is_file($target)) {
    http_response_code(410);
    header('Content-Type: text/plain; charset=utf-8');
    exit('NAi is already installed or the installer was removed. Open /login instead.');
}

require $target;
