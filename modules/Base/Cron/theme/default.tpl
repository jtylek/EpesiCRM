<div class="important_notice">
    {'Cron periodically executes scheduled jobs for every module. It needs to run at least once every minute, using one of the two methods below.'|t}
    <ol>
        <li>{'Recommended: have your server run cron.php from the command line every minute, e.g. via a system cron job.'|t}</li>
        <li>{"If you can't run PHP from the command line, use the Cron URL below instead. Loading it - in a browser, or via an external service that fetches URLs on a schedule - triggers the same cron run over HTTP."|t}</li>
    </ol>
    {'You can read more on our wiki'|t}: <a href="{$wiki_url}" target="_blank">{$wiki_url}</a>
    <br>
    <br>
    {'Cron URL'|t}: <a href="{$cron_url}" target="_blank">{$cron_url}</a>
    <br>
    {'This URL contains a secret token, so anyone with it can trigger cron. Keep it private, and generate a new one if you suspect it has leaked.'|t}
    <br>
    <br>
    <a {$new_token_href} class="button">{'New Token'|t}</a>
</div>

<div>{$history}</div>