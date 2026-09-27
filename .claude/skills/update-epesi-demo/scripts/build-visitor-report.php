<?php

/**
 * Merges every demo-visitors/raw/*.csv snapshot (php artisan demo:audit
 * --raw, one per deploy.sh visitors run) into one deduped login_audits.csv,
 * and renders login_audit_report.html from it — see
 * demo-visitors/login_audit_analysis.md for the design this implements.
 *
 * Plain PHP, no Composer/Laravel: this runs against a directory outside any
 * checkout, from deploy.sh, with only the PHP already on the machine.
 *
 * Usage: php build-visitor-report.php <demo-visitors-dir>
 */

const COLUMNS = ['id', 'user_id', 'login', 'impersonated_by', 'started_at', 'ended_at', 'ip_address', 'host_name', 'device'];

// The "day" a session is grouped under, and what the report's dates read as
// — matches config('demo.timezone') in epesi-laravel, which is what
// demo:audit's own human-readable table has always used.
const DISPLAY_TIMEZONE = 'Europe/Warsaw';

function fail(string $message): never
{
    fwrite(STDERR, $message.PHP_EOL);
    exit(1);
}

/**
 * The current public IP, so a home/office connection whose ISP reassigns it
 * doesn't quietly fall out of "own testing" and start looking like a
 * returning visitor. Never fatal: an offline run just skips this.
 */
function detect_and_record_own_ip(string $dir): void
{
    $ip = @file_get_contents('https://api.ipify.org', false, stream_context_create([
        'http' => ['timeout' => 3],
    ]));

    if ($ip === false || filter_var($ip, FILTER_VALIDATE_IP) === false) {
        echo "Could not detect this machine's public IP (offline?) — own-ips.txt left as is.".PHP_EOL;

        return;
    }

    $path = $dir.'/own-ips.txt';
    $known = is_file($path) ? file($path, FILE_IGNORE_NEW_LINES) : [];
    $alreadyListed = false;

    foreach ($known as $line) {
        $existing = trim(explode('#', $line, 2)[0]);

        if ($existing === $ip) {
            $alreadyListed = true;

            break;
        }
    }

    if ($alreadyListed) {
        return;
    }

    file_put_contents($path, $ip.' # auto-detected '.date('Y-m-d').PHP_EOL, FILE_APPEND);
    echo "own-ips.txt: added this machine's current public IP ($ip).".PHP_EOL;
}

/**
 * @return array<int, string>
 */
function load_own_ips(string $dir): array
{
    $path = $dir.'/own-ips.txt';

    if (! is_file($path)) {
        return [];
    }

    return array_values(array_filter(array_map(
        fn (string $line): string => trim(explode('#', $line, 2)[0]),
        file($path, FILE_IGNORE_NEW_LINES) ?: [],
    ), fn (string $ip): bool => $ip !== ''));
}

/**
 * Every raw/*.csv, oldest first so a later snapshot's row (the same session,
 * with ended_at moved on) overwrites an earlier one for the same id — a
 * session isn't done growing just because it was already pulled once.
 *
 * @return array<int, array<string, string>>
 */
function merge_raw_csvs(string $dir): array
{
    $files = glob($dir.'/raw/*.csv') ?: [];
    sort($files);

    $byId = [];

    foreach ($files as $file) {
        $handle = fopen($file, 'r');
        $header = fgetcsv($handle);

        if ($header !== COLUMNS) {
            fwrite(STDERR, "Skipping $file: header doesn't match (needs demo:audit --raw from a demo that has shipped it).".PHP_EOL);
            fclose($handle);

            continue;
        }

        while (($row = fgetcsv($handle)) !== false) {
            $byId[$row[0]] = array_combine(COLUMNS, $row);
        }

        fclose($handle);
    }

    $rows = array_values($byId);
    usort($rows, fn (array $a, array $b): int => $a['started_at'] <=> $b['started_at']);

    $out = fopen($dir.'/login_audits.csv', 'w');
    fputcsv($out, COLUMNS);

    foreach ($rows as $row) {
        fputcsv($out, $row);
    }

    fclose($out);

    return $rows;
}

function local_date(string $utcDateTime): string
{
    $date = new DateTime($utcDateTime, new DateTimeZone('UTC'));
    $date->setTimezone(new DateTimeZone(DISPLAY_TIMEZONE));

    return $date->format('Y-m-d');
}

function local_datetime(string $utcDateTime): string
{
    $date = new DateTime($utcDateTime, new DateTimeZone('UTC'));
    $date->setTimezone(new DateTimeZone(DISPLAY_TIMEZONE));

    return $date->format('Y-m-d H:i');
}

/**
 * @param  array<int, array<string, string>>  $rows
 * @param  array<int, string>  $ownIps
 */
function build_report(array $rows, array $ownIps): string
{
    $external = array_values(array_filter($rows, fn (array $r): bool => ! in_array($r['ip_address'], $ownIps, true)));
    $own = array_values(array_filter($rows, fn (array $r): bool => in_array($r['ip_address'], $ownIps, true)));

    $byDay = [];

    foreach ($rows as $row) {
        $day = local_date($row['started_at']);
        $byDay[$day]['sessions'] = ($byDay[$day]['sessions'] ?? 0) + 1;
        $byDay[$day][in_array($row['ip_address'], $ownIps, true) ? 'own' : 'ext'][] = $row['ip_address'];
    }

    ksort($byDay);

    // An external IP seen on more than one day — a real returning visitor,
    // not a one-off, called out rather than buried among one-off rows.
    $externalDaysByIp = [];

    foreach ($external as $row) {
        $externalDaysByIp[$row['ip_address']][local_date($row['started_at'])] = true;
    }

    $returning = array_keys(array_filter($externalDaysByIp, fn (array $days): bool => count($days) > 1));

    $maxDaySessions = max(1, ...array_map(fn (array $d): int => $d['sessions'], array_values($byDay) ?: [['sessions' => 1]]));

    // Pixel widths, not percentages: a percentage on a span inside a <td>
    // resolves against that cell's own (auto, content-dependent) width,
    // which is circular — browsers handle it inconsistently, rendering the
    // bars far too large. A fixed pixel scale sidesteps that entirely.
    $barScale = 160;

    $dayRows = '';
    foreach ($byDay as $day => $d) {
        $ownCount = count($d['own'] ?? []);
        $extCount = count($d['ext'] ?? []);
        $extIps = count(array_unique($d['ext'] ?? []));
        $ownPx = (int) round(($ownCount / $maxDaySessions) * $barScale);
        $extPx = (int) round(($extCount / $maxDaySessions) * $barScale);
        $dayRows .= '<tr><td>'.e($day).'</td>'
            .'<td class="num">'.$ownCount.'</td>'
            .'<td class="num">'.$extCount.'</td>'
            .'<td class="num">'.$extIps.'</td>'
            .'<td class="bar"><span class="bar-own" style="width:'.$ownPx.'px"></span><span class="bar-ext" style="width:'.$extPx.'px"></span></td>'
            .'</tr>'."\n";
    }

    $externalRowsHtml = '';
    foreach (array_reverse($external) as $row) {
        $badge = in_array($row['ip_address'], $returning, true) ? ' <span class="badge">returning</span>' : '';
        $externalRowsHtml .= '<tr><td>'.e(local_datetime($row['started_at'])).'</td>'
            .'<td>'.e($row['ip_address']).$badge.'</td>'
            .'<td>'.e($row['host_name']).'</td>'
            .'<td>'.e($row['device']).'</td>'
            .'<td>'.e($row['login']).'</td></tr>';
    }

    $ownRowsHtml = '';
    foreach (array_reverse($own) as $row) {
        $ownRowsHtml .= '<tr><td>'.e(local_datetime($row['started_at'])).'</td>'
            .'<td>'.e($row['ip_address']).'</td>'
            .'<td>'.e($row['device']).'</td>'
            .'<td>'.e($row['login']).'</td></tr>';
    }

    $totalExternalIps = count(array_unique(array_column($external, 'ip_address')));
    $range = $rows === [] ? '—' : local_date($rows[0]['started_at']).' to '.local_date(end($rows)['started_at']);
    $totalSessions = count($rows);
    $externalSessions = count($external);
    $ownSessions = count($own);
    $returningCount = count($returning);
    $ownLabel = $ownSessions === 1 ? 'session' : 'sessions';
    $tz = DISPLAY_TIMEZONE;

    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Demo visitors</title>
<meta name="robots" content="noindex, nofollow">
<style>
  :root {
    --bg: #0d1117; --bg-alt: #161b22; --border: #30363d;
    --text: #c9d1d9; --muted: #8b949e; --accent: #238636; --link: #58a6ff;
  }
  * { box-sizing: border-box; }
  body { margin: 0; font-family: -apple-system, "Segoe UI", Roboto, sans-serif; background: var(--bg); color: var(--text); }
  header { padding: 26px 6%; background: var(--bg-alt); border-bottom: 1px solid var(--border); }
  header h1 { margin: 0; color: #fff; font-size: 1.5em; }
  header .range { color: var(--muted); font-size: 14px; margin-top: 4px; }
  main { max-width: 1100px; margin: 0 auto; padding: 30px 6% 80px; }
  .stats { display: flex; gap: 24px; flex-wrap: wrap; margin-bottom: 32px; }
  .stat { background: var(--bg-alt); border: 1px solid var(--border); border-radius: 8px; padding: 14px 20px; min-width: 140px; }
  .stat .n { font-size: 1.6em; font-weight: 700; color: #fff; }
  .stat .l { color: var(--muted); font-size: 13px; }
  h2 { color: #fff; font-size: 1.1em; border-bottom: 1px solid var(--border); padding-bottom: 6px; margin-top: 40px; }
  table { border-collapse: collapse; width: 100%; margin-top: 12px; }
  table.days { width: auto; min-width: 460px; }
  th, td { border-bottom: 1px solid var(--border); padding: 7px 10px; text-align: left; font-size: 14px; }
  th { color: var(--muted); font-weight: 500; text-transform: uppercase; font-size: 11px; letter-spacing: 0.5px; }
  th.num, td.num { text-align: right; font-variant-numeric: tabular-nums; }
  td.bar { white-space: nowrap; width: 1%; }
  .bar-own, .bar-ext { display: inline-block; height: 10px; vertical-align: middle; }
  .bar-own { background: var(--muted); }
  .bar-ext { background: var(--accent); }
  .badge { background: var(--accent); color: #fff; font-size: 10px; padding: 1px 6px; border-radius: 10px; }
  details summary { cursor: pointer; color: var(--muted); margin-top: 10px; }
  .legend { color: var(--muted); font-size: 13px; margin-top: 6px; }
  .legend .sw { display: inline-block; width: 10px; height: 10px; margin-right: 4px; vertical-align: middle; }
</style>
</head>
<body>
<header>
  <h1>Who used the demo</h1>
  <div class="range">{$range}</div>
</header>
<main>
  <div class="stats">
    <div class="stat"><div class="n">{$totalSessions}</div><div class="l">total sessions</div></div>
    <div class="stat"><div class="n">{$externalSessions}</div><div class="l">external sessions</div></div>
    <div class="stat"><div class="n">{$totalExternalIps}</div><div class="l">external IP addresses</div></div>
    <div class="stat"><div class="n">{$returningCount}</div><div class="l">returning visitor IPs</div></div>
  </div>

  <h2>Per day</h2>
  <div class="legend"><span class="sw" style="background:var(--muted)"></span>own testing &nbsp; <span class="sw" style="background:var(--accent)"></span>external</div>
  <table class="days">
    <tr><th>Day</th><th class="num">Own</th><th class="num">External</th><th class="num">External IPs</th><th></th></tr>
    {$dayRows}
  </table>

  <h2>External sessions</h2>
  <table>
    <tr><th>Started ({$tz})</th><th>IP</th><th>Host</th><th>Device</th><th>Login</th></tr>
    {$externalRowsHtml}
  </table>

  <details>
    <summary>Your own testing ({$ownSessions} {$ownLabel})</summary>
    <table>
      <tr><th>Started ({$tz})</th><th>IP</th><th>Device</th><th>Login</th></tr>
      {$ownRowsHtml}
    </table>
  </details>
</main>
</body>
</html>
HTML;
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES);
}

if ($argc < 2) {
    fail('Usage: php build-visitor-report.php <demo-visitors-dir>');
}

$dir = rtrim($argv[1], '/\\');

if (! is_dir($dir)) {
    fail("No such directory: $dir");
}

detect_and_record_own_ip($dir);
$rows = merge_raw_csvs($dir);
$ownIps = load_own_ips($dir);
file_put_contents($dir.'/login_audit_report.html', build_report($rows, $ownIps));

$externalCount = count(array_filter($rows, fn (array $r): bool => ! in_array($r['ip_address'], $ownIps, true)));
echo count($rows)." sessions total, $externalCount external. Report: $dir/login_audit_report.html".PHP_EOL;
