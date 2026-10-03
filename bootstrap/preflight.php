<?php

// Run by public/index.php before Composer's autoloader. Too old a PHP, or one
// without mbstring, intl, ... fails inside Laravel before the setup wizard's
// server check can say why: the host shows a bare "HTTP ERROR 500". This says
// it in plain words instead. Plain PHP 5 syntax, so an old PHP can parse it.

$requirements = require __DIR__.'/requirements.php';
$checks = array();
$failed = 0;

$versionOk = ! version_compare(PHP_VERSION, $requirements['php'], '<');
$checks[] = array(
    'PHP '.PHP_VERSION,
    $versionOk,
    $versionOk ? 'OK' : 'needs '.preg_replace('/\.0$/', '', $requirements['php']).' or newer',
);

foreach ($requirements['extensions'] as $extension) {
    $loaded = extension_loaded($extension);
    $checks[] = array('PHP extension '.$extension, $loaded, $loaded ? 'OK' : 'missing');
}

$drivers = array_unique(array_values($requirements['database_drivers']));
$found = array_filter($drivers, 'extension_loaded');
$checks[] = array(
    'A database driver ('.implode(', ', $drivers).')',
    (bool) $found,
    $found ? implode(', ', $found) : 'none installed',
);

foreach (array('storage', 'bootstrap/cache') as $directory) {
    $writable = is_writable(dirname(__DIR__).'/'.$directory);
    $checks[] = array($directory.'/ writable', $writable, $writable ? 'OK' : 'not writable by the web server');
}

$missingExtensions = false;

foreach ($checks as $index => $check) {
    if (! $check[1]) {
        $failed++;

        if ($index > 0 && $index <= count($requirements['extensions']) + 1) {
            $missingExtensions = true;
        }
    }
}

if (! $failed) {
    unset($requirements, $checks, $failed, $versionOk, $missingExtensions, $index, $extension, $loaded, $drivers, $found, $directory, $writable, $check);

    return;
}

$e = function ($text) {
    return htmlspecialchars($text, ENT_QUOTES);
};

// The logo travels inside the page: a broken server may not serve files, and
// the page can be reached at any address.
$image = function ($file) {
    $path = __DIR__.'/'.$file;

    return is_file($path) ? 'data:image/png;base64,'.base64_encode(file_get_contents($path)) : '';
};

http_response_code(503);
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>Epesi: server check</title>
<link rel="icon" href="<?php echo $image('preflight-favicon.png'); ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Exo:wght@600;700;800&family=Titillium+Web:wght@400;600;700&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --bg:#F7F6F2;--surface:#fff;--ink:#0F1923;--ink-2:#1A2B4A;--muted:#64748B;
  --green:#2FBF71;--green-d:#1F9D5A;--navy-d:#081B2E;--border:#E2E8EE;
  --bad:#C0362C;--bad-bg:#FDECEA;--bad-line:#F5C6C1;--code:#EEF1F5;
  --font-title:'Exo','Titillium Web',system-ui,sans-serif;
  --font-body:'Titillium Web',system-ui,-apple-system,'Segoe UI',Roboto,sans-serif;
}
body{font-family:var(--font-body);background:var(--bg);color:var(--ink);line-height:1.6;-webkit-font-smoothing:antialiased}
.container{max-width:1120px;margin:0 auto;padding:0 24px}
.narrow{max-width:760px}
nav{position:sticky;top:0;z-index:10;background:rgba(255,255,255,.97);border-bottom:1px solid var(--border)}
.nav-inner{display:flex;align-items:center;height:69px;gap:24px}
.nav-logo{display:inline-flex;align-items:center;gap:10px;text-decoration:none}
.nav-logo img{display:block;width:128px;height:28px}
.tag{font-size:.65rem;font-weight:700;letter-spacing:.04em;color:var(--green-d);background:rgba(47,191,113,.12);border-radius:100px;padding:2px 8px}
.hero{text-align:center;padding:64px 0 36px;background:radial-gradient(ellipse at 50% 0,rgba(47,191,113,.14),transparent 60%)}
h1{font-family:var(--font-title);font-size:clamp(1.8rem,5vw,2.8rem);line-height:1.15;color:var(--ink-2);font-weight:800}
.lead{max-width:640px;margin:18px auto 0;font-size:1.15rem;color:var(--muted)}
h2{font-family:var(--font-title);font-size:1.3rem;color:var(--ink-2);margin-bottom:16px}
section{padding:14px 0}
.card{background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:26px;box-shadow:0 1px 2px rgba(15,25,35,.04)}
.problem{background:var(--bad-bg);border-color:var(--bad-line)}
.problem ul{padding-left:1.2rem}
.problem li{margin:.2rem 0;font-weight:700;color:var(--bad)}
ol{padding-left:1.3rem}
ol li{margin:.5rem 0;color:var(--muted)}
ol li strong{color:var(--ink-2)}
table{width:100%;border-collapse:collapse}
td{padding:.45rem 0;border-top:1px solid var(--border);vertical-align:top}
tr:first-child td{border-top:0}
td.status{text-align:right;white-space:nowrap;padding-left:1rem;font-weight:700}
.ok{color:var(--green-d)}
.bad{color:var(--bad)}
code{font-family:ui-monospace,'JetBrains Mono',monospace;background:var(--code);padding:.1em .35em;border-radius:4px;font-size:.88em}
footer{margin-top:28px;padding:28px 0;border-top:1px solid var(--border);background:var(--surface);text-align:center;color:var(--muted);font-size:.9rem}
footer a{color:var(--green-d);text-decoration:none;font-weight:600}
</style>
</head>
<body>
<nav><div class="container nav-inner">
  <a class="nav-logo" href="https://epesicrm.com/"><img src="<?php echo $image('preflight-logo.png'); ?>" srcset="<?php echo $image('preflight-logo.png'); ?> 1x, <?php echo $image('preflight-logo-2x.png'); ?> 2x" alt="Epesi" width="128" height="28"><span class="tag">Server check</span></a>
</div></nav>

<header class="hero"><div class="container">
  <h1>Epesi can't run on this server yet</h1>
  <p class="lead">The server check found <?php echo $failed; ?> problem<?php echo $failed === 1 ? '' : 's'; ?>. Fix <?php echo $failed === 1 ? 'it' : 'them'; ?> and reload this page: the setup wizard opens by itself once everything is in place.</p>
</div></header>

<section><div class="container narrow">
  <div class="card problem">
    <ul>
<?php foreach ($checks as $check) { if (! $check[1]) { ?>
      <li><?php echo $e($check[0]); ?>: <?php echo $e($check[2]); ?></li>
<?php } } ?>
    </ul>
  </div>
</div></section>

<section><div class="container narrow">
  <div class="card">
    <h2>How to fix it</h2>
    <ol>
      <li>On shared hosting, open the control panel's PHP settings for <strong>this (sub)domain</strong>: cPanel's <em>Select PHP Version</em> or <em>MultiPHP Manager</em>, DirectAdmin's <em>PHP Settings</em> or <em>Subdomain Management</em>, Plesk's <em>PHP Settings</em>.</li>
<?php if (! $versionOk) { ?>
      <li>Choose <strong>PHP <?php echo $e(preg_replace('/\.0$/', '', $requirements['php'])); ?> or newer</strong>. The other checks below are only meaningful once the version is right, so fix this one first.</li>
      <li>On your own server, install a newer PHP (e.g. <code>php8.3</code> with its <code>mbstring</code>, <code>intl</code> and <code>mysql</code> packages) and restart the web server.</li>
<?php } elseif ($missingExtensions) { ?>
      <li>Tick the missing extensions for this PHP version. Some PHP builds a host offers come without them; then pick another version.</li>
      <li>On your own server, install the packages (e.g. <code>php8.3-mbstring php8.3-intl php8.3-mysql</code>) and restart the web server.</li>
<?php } else { ?>
      <li>Make the folders listed above writable for the web server (usually permissions 755, owned by the account the site runs as).</li>
<?php } ?>
    </ol>
  </div>
</div></section>

<section><div class="container narrow">
  <div class="card">
    <h2>Everything checked</h2>
    <table>
<?php foreach ($checks as $check) { ?>
      <tr>
        <td><?php echo $e($check[0]); ?></td>
        <td class="status <?php echo $check[1] ? 'ok' : 'bad'; ?>"><?php echo $check[1] ? '&#10003; ' : '&#10007; '; ?><?php echo $e($check[2]); ?></td>
      </tr>
<?php } ?>
    </table>
  </div>
</div></section>

<footer><div class="container">&copy; Epesi &middot; <a href="https://epe.si/">epe.si</a> &middot; served by <code><?php echo $e(PHP_SAPI); ?></code> <code><?php echo $e(PHP_BINARY ? PHP_BINARY : 'PHP '.PHP_VERSION); ?></code></div></footer>
</body>
</html>
<?php
exit;
