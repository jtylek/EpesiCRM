<ul>
{foreach key=mail item=indicator from=$accounts}
<li><i>{$mail}</i> - <b>{$indicator}</b></li>
{/foreach}
</ul>
