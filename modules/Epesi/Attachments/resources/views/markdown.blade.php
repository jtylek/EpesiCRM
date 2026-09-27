<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $title }}</title>
<meta name="robots" content="noindex, nofollow">
{{-- Same theme as AI-private's local note viewer, so a note's .md file
     reads the same way whether it's opened from a record's Notes tab or
     from the notes archive. --}}
<style>
  :root {
    --bg: #0d1117;
    --bg-alt: #161b22;
    --border: #30363d;
    --text: #c9d1d9;
    --muted: #8b949e;
    --link: #58a6ff;
  }
  * { box-sizing: border-box; }
  body {
    margin: 0;
    font-family: -apple-system, "Segoe UI", Roboto, sans-serif;
    background: var(--bg);
    color: var(--text);
  }
  header {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 18px 8%;
    background: var(--bg-alt);
    border-bottom: 1px solid var(--border);
    position: sticky;
    top: 0;
  }
  header a { color: var(--link); text-decoration: none; font-weight: 500; }
  header a:hover { text-decoration: underline; }
  header .name { color: var(--muted); font-family: "Consolas", monospace; font-size: 14px; }
  main {
    max-width: 1200px;
    margin: 0 auto;
    padding: 40px 4% 80px;
    line-height: 1.65;
  }
  main h1, main h2, main h3, main h4 {
    color: #fff;
    border-bottom: 1px solid var(--border);
    padding-bottom: 6px;
  }
  main h1 { font-size: 1.8em; }
  main h2 { font-size: 1.4em; margin-top: 1.6em; }
  main h3 { font-size: 1.15em; border-bottom: none; }
  main a { color: var(--link); }
  main code {
    background: var(--bg-alt);
    border-radius: 4px;
    padding: 0.15em 0.4em;
    font-family: "Consolas", monospace;
    font-size: 0.9em;
  }
  main pre {
    background: var(--bg-alt);
    border: 1px solid var(--border);
    border-radius: 6px;
    padding: 14px;
    overflow-x: auto;
  }
  main pre code { background: none; padding: 0; }
  main blockquote {
    margin: 0;
    padding: 0 1em;
    color: var(--muted);
    border-left: 4px solid var(--border);
  }
  main table { border-collapse: collapse; width: 100%; margin: 1em 0; }
  main th, main td {
    border: 1px solid var(--border);
    padding: 6px 10px;
    text-align: left;
  }
  main th { background: var(--bg-alt); }
  main ul, main ol { padding-left: 1.4em; }
  main hr { border: none; border-top: 1px solid var(--border); margin: 2em 0; }
</style>
</head>
<body>
<header>
  <a href="{{ $downloadUrl }}">&#8595; {{ __('Download') }}</a>
  <span class="name">{{ $title }}</span>
</header>
<main>
{!! $html !!}
</main>
</body>
</html>
