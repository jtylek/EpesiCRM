<?php

// Reached only when the web server did not apply the top-level .htaccess: with
// mod_rewrite and AllowOverride working, every request is rewritten into
// public/ and this file is never served. Explain what to fix instead of
// leaving a bare "403 Forbidden" / directory listing. nginx never reads
// .htaccess, so it always lands here until its document root is public/:
// it gets its own page, with the settings to paste filled in for this server.

http_response_code(503);

$folder = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/\\');
$hasHtaccess = is_file(__DIR__.'/.htaccess');
$publicUrl = htmlspecialchars($folder.'/public/', ENT_QUOTES);
$isNginx = stripos($_SERVER['SERVER_SOFTWARE'] ?? '', 'nginx') !== false;
$publicDir = htmlspecialchars(str_replace('\\', '/', __DIR__).'/public', ENT_QUOTES);
$php = PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION;

// The Roundcube download and cron need these; panels like aaPanel switch them off.
$disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));
$blocked = array_values(array_filter(['exec', 'proc_open', 'putenv', 'symlink'], fn ($f) => in_array($f, $disabled, true) || ! function_exists($f)));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Epesi: web server setup needed</title>
    <style>body{font:16px/1.5 system-ui,sans-serif;max-width:46rem;margin:3rem auto;padding:0 1rem;color:#222}code{background:#eee;padding:.1em .3em}pre{background:#eee;padding:.75rem 1rem;overflow-x:auto}ol li,ul li{margin-bottom:.4rem}</style>
</head>
<body>
<?php if ($isNginx) { ?>
    <h1>Epesi is unpacked. nginx needs two settings</h1>
    <p>nginx doesn't read the <code>.htaccess</code> file that sets up Epesi on Apache, so set these yourself:</p>
    <ol>
        <li>Set the site's <strong>document root</strong> to Epesi's <code>public/</code> folder:
            <pre>root <?= $publicDir ?>;</pre>
            On aaPanel: Website → this site → Settings → Site directory → <strong>Running directory</strong> <code>/public</code>.
            Also untick <strong>Anti-XSS attack (open_basedir)</strong> there.</li>
        <li>Send every address that isn't a file to Epesi:
            <pre>location / {
    try_files $uri $uri/ /index.php?$query_string;
}</pre>
            On aaPanel: Website → this site → Settings → <strong>URL rewrite</strong>, choose <strong>laravel5</strong> or paste the block above, and save.</li>
    </ol>
    <p>Then open the site's address with nothing after it, and no <code>/public</code>. The setup wizard starts there.</p>
<?php if ($blocked !== []) { ?>
    <p><strong>Also:</strong> PHP has <code><?= htmlspecialchars(implode(', ', $blocked)) ?></code> disabled. Setup works without them, but the Roundcube webmail download doesn't. On aaPanel, go to App Store → PHP <?= $php ?> → Settings → Disabled functions, remove them, and restart PHP.</p>
<?php } ?>
    <p><code>config/nginx.conf.example</code> in this folder is a complete server block for a server without a control panel. See <code>INSTALL.md</code> for details.</p>
<?php } else { ?>
    <h1>Epesi is unpacked, but the web server isn't serving it yet</h1>
    <p>Epesi is served through the <code>.htaccess</code> file in this folder, which rewrites requests into <code>public/</code>. The server did not apply it.</p>
    <ul>
        <li><code>.htaccess</code> in this folder: <strong><?= $hasHtaccess ? 'present' : 'MISSING (some unzip tools skip hidden files; extract again)' ?></strong></li>
        <li>Apache needs <code>mod_rewrite</code> enabled (Debian/Ubuntu: <code>sudo a2enmod rewrite</code>).</li>
        <li>The web root needs <code>AllowOverride All</code>, then restart Apache.</li>
        <li>Or make a (sub)domain / virtual host whose document root is this folder's <code>public/</code>. Nothing else is needed then.</li>
    </ul>
    <p>See <code>INSTALL.md</code> for details. Once this is fixed, this page is replaced by the setup wizard.</p>
    <p>(Opening <a href="<?= $publicUrl ?>"><?= $publicUrl ?></a> directly only shows the first page; the rest still needs the rewrite.)</p>
<?php } ?>
</body>
</html>
