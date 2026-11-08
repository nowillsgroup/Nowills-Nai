<?php

/**
 * NAi .env writer — set or append a single key safely.
 *
 * Usage: php deploy/env-set.php <file> <key> <value>
 *
 * The value is written literally (never interpreted as a regex replacement),
 * and dotenv-quoted when it contains characters that would otherwise change its
 * meaning ($, \, #, spaces, quotes). This keeps generated database passwords
 * intact. Existing keys are replaced in place; unknown keys are appended.
 */
if ($argc < 4) {
    fwrite(STDERR, "usage: env-set.php <file> <key> <value>\n");
    exit(1);
}

[$script, $file, $key, $value] = $argv;

if (! preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $key)) {
    fwrite(STDERR, "invalid key: {$key}\n");
    exit(1);
}

// Choose a safe dotenv representation of the value.
if ($value === '' || preg_match('/^[A-Za-z0-9_.\/:@-]+$/', $value)) {
    // Plain scalar — no quoting needed.
    $encoded = $value;
} elseif (strpos($value, "'") === false) {
    // Single quotes are literal in dotenv (no interpolation, no escapes).
    $encoded = "'".$value."'";
} else {
    // Value contains a single quote: use double quotes and escape \ and ".
    // Escape $ as well so dotenv does not interpolate ${...} sequences.
    $encoded = '"'.str_replace(['\\', '"', '$'], ['\\\\', '\\"', '\\$'], $value).'"';
}

$line = $key.'='.$encoded;
$lines = is_file($file) ? file($file, FILE_IGNORE_NEW_LINES) : [];
$found = false;
foreach ($lines as $i => $l) {
    if (preg_match('/^\s*'.preg_quote($key, '/').'\s*=/', $l)) {
        $lines[$i] = $line;
        $found = true;
    }
}
if (! $found) {
    $lines[] = $line;
}

file_put_contents($file, implode("\n", $lines)."\n");
