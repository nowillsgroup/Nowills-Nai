<?php

/**
 * NAi direct cPanel installer. This file is added only to the prebuilt ZIP.
 * It deletes itself, SETUP-TOKEN.txt, and its token-hash config after success.
 */
declare(strict_types=1);

@set_time_limit(300);
@ini_set('display_errors', '0');
error_reporting(E_ALL);

$root = dirname(__DIR__);
$class = $root.'/app/Support/WebInstaller.php';
if (! is_file($class)) {
    http_response_code(500);
    exit('NAi package is incomplete: app/Support/WebInstaller.php is missing. Re-extract the full ZIP.');
}
require_once $class;

$installer = new \App\Support\WebInstaller($root);
$https = (! empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') || (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443;
session_name('nai_install');
session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => $https, 'httponly' => true, 'samesite' => 'Strict']);
session_start();
$_SESSION['csrf'] ??= bin2hex(random_bytes(32));

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, max-age=0');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");

function h(mixed $value): string { return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

function page_top(string $title): void
{
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofilter,noarchive"><title>'.h($title).' · NAi</title><style>
:root{color-scheme:dark;font-family:Inter,ui-sans-serif,system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;background:#080d18;color:#e8eefb}*{box-sizing:border-box}body{margin:0;background:radial-gradient(ellipse at 15% 0%,#142b4a 0,transparent 48%),#080d18;min-height:100vh}.wrap{max-width:980px;margin:0 auto;padding:40px 20px 64px}.brand{display:flex;align-items:center;gap:12px;margin-bottom:28px}.mark{display:grid;place-items:center;width:42px;height:42px;border-radius:13px;background:linear-gradient(135deg,#5eead4,#38bdf8);color:#06202b;font-weight:900;font-size:20px}.brand strong{font-size:20px;letter-spacing:.02em}.brand span{color:#8fa3c2;font-size:13px}.card{background:rgba(14,23,40,.94);border:1px solid #243653;border-radius:18px;padding:28px;box-shadow:0 24px 80px rgba(0,0,0,.32)}h1{font-size:clamp(24px,4vw,34px);margin:0 0 10px}h2{font-size:17px;margin:28px 0 14px;color:#bfe9ff}p{color:#a9b8d0;line-height:1.6;margin:8px 0}.lead{font-size:15px;max-width:760px}.grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}.field{display:flex;flex-direction:column;gap:7px}.field.full{grid-column:1/-1}label{font-size:13px;font-weight:650;color:#d7e3f7}input{width:100%;border:1px solid #314866;border-radius:10px;background:#0a1322;color:#f3f7ff;padding:12px 13px;font:inherit;outline:none}input:focus{border-color:#38bdf8;box-shadow:0 0 0 3px rgba(56,189,248,.15)}small{color:#8195b4;line-height:1.45}.alert{border-radius:12px;padding:14px 16px;margin:16px 0;line-height:1.55}.alert.error{background:#3a1722;border:1px solid #7f2d42;color:#ffd7df}.alert.ok{background:#103129;border:1px solid #246b52;color:#c9f7e5}.alert.warn{background:#3a2d14;border:1px solid #7c6126;color:#ffe7ad}.checks{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px;margin:14px 0}.check{font-size:12px;border:1px solid #243653;border-radius:9px;padding:9px 10px;background:#0b1525}.pass{color:#86efac}.fail{color:#fda4af}button,.button{display:inline-block;border:0;border-radius:11px;background:linear-gradient(135deg,#2dd4bf,#38bdf8);color:#04202b;font:inherit;font-weight:800;padding:13px 22px;cursor:pointer;text-decoration:none;margin-top:24px}button:disabled{opacity:.45;cursor:not-allowed}code,pre{font-family:ui-monospace,SFMono-Regular,Consolas,monospace;background:#070e1a;border:1px solid #253957;border-radius:10px;color:#c9f7e5}code{padding:2px 6px}pre{padding:14px;white-space:pre-wrap;overflow-wrap:anywhere;line-height:1.55}.footer{margin-top:22px;color:#7186a6;font-size:12px;text-align:center}ol{color:#a9b8d0;line-height:1.7}@media(max-width:700px){.grid,.checks{grid-template-columns:1fr}.card{padding:20px}.wrap{padding:24px 14px 40px}}
</style></head><body><main class="wrap"><div class="brand"><div class="mark">N</div><div><strong>NAi</strong><br><span>Nowills Group · cPanel installation</span></div></div><section class="card"><h1>'.h($title).'</h1>';
}

function page_bottom(): void { echo '</section><p class="footer">AI development is powered by Nowills Group.</p></main></body></html>'; }

if ($installer->isInstalled()) {
    http_response_code(410);
    page_top('Installation already complete');
    echo '<div class="alert ok">NAi is installed and locked. This installer cannot create another administrator or change the database.</div><p>If this file is still visible, delete <code>public/install.php</code> in cPanel File Manager. Also delete <code>SETUP-TOKEN.txt</code> and <code>build/installer-config.php</code> if they remain.</p><p><a class="button" href="/login">Open NAi sign-in</a></p>';
    page_bottom(); exit;
}

$checks = $installer->requirements();
$ready = ! in_array(false, array_column($checks, 'ok'), true);
$errors = [];
$result = null;
$old = ['site_url' => 'https://nai.nowills.com', 'workspace' => 'Nowills Group', 'db_host' => 'localhost', 'db_port' => '3306', 'db_name' => '', 'db_user' => '', 'admin_name' => '', 'admin_email' => ''];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    foreach (['site_url', 'workspace', 'db_host', 'db_port', 'db_name', 'db_user', 'admin_name', 'admin_email'] as $key) {
        if (isset($_POST[$key]) && is_string($_POST[$key])) $old[$key] = trim($_POST[$key]);
    }
    $csrf = (string) ($_POST['csrf'] ?? '');
    if (! hash_equals((string) $_SESSION['csrf'], $csrf)) {
        $errors[] = 'The installation form expired. Reload this page and try again.';
    } elseif ((int) ($_SESSION['blocked_until'] ?? 0) > time()) {
        $errors[] = 'Too many setup attempts. Wait 15 minutes before trying again.';
    } else {
        $result = $installer->install($_POST);
        if (! $result['ok']) {
            $_SESSION['attempts'] = (int) ($_SESSION['attempts'] ?? 0) + 1;
            if ($_SESSION['attempts'] >= 10) { $_SESSION['blocked_until'] = time() + 900; $_SESSION['attempts'] = 0; }
            $errors = $result['errors'];
        } else {
            unset($_SESSION['csrf'], $_SESSION['attempts'], $_SESSION['blocked_until']);
            session_destroy();
        }
    }
}

if ($result && $result['ok']) {
    page_top('NAi is ready');
    echo '<div class="alert ok"><strong>Installation completed.</strong> The database schema, workspace, and administrator account were created. Production caches were built and demo mode is off.</div>';
    if ($result['warnings']) { echo '<div class="alert warn"><strong>Manual cleanup required:</strong><br>'.implode('<br>', array_map('h', $result['warnings'])).'</div>'; }
    else { echo '<p>The installer, setup token, and token configuration were removed automatically.</p>'; }
    echo '<h2>Sign in</h2><p><a class="button" href="'.h($result['login_url']).'">Open '.h($result['login_url']).'</a></p><h2>Add the cPanel cron job</h2><p>cPanel → Cron Jobs → Once Per Minute. Use the exact command below; confirm the PHP path matches your host’s CLI PHP for the selected PHP version.</p><pre>'.h($result['cron']).'</pre><h2>Finish the domain settings</h2><ol><li>Point the domain Document Root at <code>'.h($root.'/public').'</code> if you have not already.</li><li>Enable AutoSSL and Force HTTPS Redirect.</li><li>Visit <code>/up</code> for the health check, then sign in and configure the real NAi engine connection in Settings if you have one.</li></ol><p>Full guide: <code>docs/INSTALL-CPANEL.md</code>. Module list: <code>docs/MODULES.md</code>.</p>';
    page_bottom(); exit;
}

page_top('Install NAi on cPanel');
echo '<p class="lead">This package already contains the production packages in <code>build/vendor</code>. You do not need Composer or SSH. Create an empty MySQL database in cPanel, then complete this form once.</p>';
if (! $https) echo '<div class="alert warn">This installer is currently open over HTTP. Use HTTPS after AutoSSL is active, especially before entering production passwords.</div>';
if ($errors) echo '<div class="alert error"><strong>Installation was not completed:</strong><br>'.implode('<br>', array_map('h', $errors)).'</div>';
echo '<h2>Server check</h2><div class="checks">';
foreach ($checks as $check) echo '<div class="check '.($check['ok'] ? 'pass' : 'fail').'">'.($check['ok'] ? '✓ ' : '✕ ').h($check['label']).($check['ok'] ? '' : '<br><small>'.h($check['hint']).'</small>').'</div>';
echo '</div>';
if (! $ready) echo '<div class="alert error">Fix the failed server checks above, then reload this page. The install button is disabled until the package and permissions are ready.</div>';
?>
<form method="post" action="install.php" autocomplete="off">
<input type="hidden" name="csrf" value="<?= h($_SESSION['csrf'] ?? '') ?>">
<h2>1. Setup authorization</h2>
<div class="grid"><div class="field full"><label for="setup_token">One-time setup token</label><input id="setup_token" name="setup_token" type="password" required autocomplete="off" spellcheck="false"><small>Open <code>SETUP-TOKEN.txt</code> in the project root with cPanel File Manager. This private token prevents strangers from claiming a freshly uploaded site. It is deleted after installation.</small></div></div>
<h2>2. Site and workspace</h2>
<div class="grid">
<div class="field"><label for="site_url">Site URL</label><input id="site_url" name="site_url" type="url" required value="<?= h($old['site_url']) ?>" placeholder="https://nai.nowills.com"></div>
<div class="field"><label for="workspace">Workspace / property name</label><input id="workspace" name="workspace" required maxlength="120" value="<?= h($old['workspace']) ?>"><small>Shown as the NAi workspace name.</small></div>
</div>
<h2>3. MySQL database</h2>
<p>cPanel → MySQL Databases: create the database, create a user with a strong password, and add the user with ALL PRIVILEGES. Copy the <strong>full prefixed names</strong>, not just the suffix you typed.</p>
<div class="grid">
<div class="field"><label for="db_host">Database host</label><input id="db_host" name="db_host" required value="<?= h($old['db_host']) ?>"></div>
<div class="field"><label for="db_port">Database port</label><input id="db_port" name="db_port" type="number" min="1" max="65535" required value="<?= h($old['db_port']) ?>"></div>
<div class="field"><label for="db_name">Full database name</label><input id="db_name" name="db_name" required spellcheck="false" value="<?= h($old['db_name']) ?>" placeholder="myacct_nai"></div>
<div class="field"><label for="db_user">Full database user</label><input id="db_user" name="db_user" required spellcheck="false" value="<?= h($old['db_user']) ?>" placeholder="myacct_naiuser"></div>
<div class="field full"><label for="db_password">Database password</label><input id="db_password" name="db_password" type="password" required autocomplete="new-password"><small>Stored only in the private <code>.env</code> file on this server.</small></div>
</div>
<h2>4. Administrator account</h2>
<div class="grid">
<div class="field"><label for="admin_name">Administrator name</label><input id="admin_name" name="admin_name" required maxlength="120" value="<?= h($old['admin_name']) ?>"></div>
<div class="field"><label for="admin_email">Administrator email</label><input id="admin_email" name="admin_email" type="email" required value="<?= h($old['admin_email']) ?>"></div>
<div class="field"><label for="admin_password">Administrator password</label><input id="admin_password" name="admin_password" type="password" required minlength="12" maxlength="256" autocomplete="new-password"><small>At least 12 characters.</small></div>
<div class="field"><label for="admin_password_confirm">Confirm administrator password</label><input id="admin_password_confirm" name="admin_password_confirm" type="password" required minlength="12" maxlength="256" autocomplete="new-password"></div>
</div>
<button type="submit" <?= $ready ? '' : 'disabled' ?>>Install NAi</button>
</form>
<?php page_bottom();
