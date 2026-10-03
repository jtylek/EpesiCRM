<?php

// Reached only when the web server did not apply the top-level .htaccess: with
// mod_rewrite and AllowOverride working, every request is rewritten into
// public/ and this file is never served. Explain what to fix instead of
// leaving a bare "403 Forbidden" / directory listing.

http_response_code(503);

$folder = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/\\');
$hasHtaccess = is_file(__DIR__.'/.htaccess');
$publicUrl = htmlspecialchars($folder.'/public/', ENT_QUOTES);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Epesi: web server setup needed</title>
    <style>body{font:16px/1.5 system-ui,sans-serif;max-width:46rem;margin:3rem auto;padding:0 1rem;color:#222}code{background:#eee;padding:.1em .3em}</style>
</head>
<body>
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
</body>
</html>
