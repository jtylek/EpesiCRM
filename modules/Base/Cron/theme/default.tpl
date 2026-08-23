<div class="important_notice">
    {'Cron is used to periodically execute some job. Every module can define several methods with different intervals. All you need to do is to set up a system to run cron.php file every 1 minute.'|t}
    <br>
    {'You can read more on our wiki'|t}: <a href="{$wiki_url}" target="_blank">{$wiki_url}</a>
    <br>
    <br>
    {'EPESI uses token to verify cron url. Only link with valid token can execute cron. Do not reveal Cron URL. If you suspect that someone knows your unique token, then make a new one.'|t}
    <br>
    <br>
    {'Cron URL'|t}: <a href="{$cron_url}" target="_blank">{$cron_url}</a>
    <br>
    <br>
    <a {$new_token_href} class="button">{'New Token'|t}</a>
</div>

<div>{$history}</div>