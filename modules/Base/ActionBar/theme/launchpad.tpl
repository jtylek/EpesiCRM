<center>

{* Was a <table> hand-wrapping every 5 icons into a new <tr> - flex-wrap
   replaces that, same recipe used app-wide for this pattern (see
   AI-shared/adminlte-theme.md): the browser decides how many icons fit per
   line, no more hardcoded wrap count or x-counter bookkeeping. *}
<div id="Base_ActionBar__launchpad" style="display: flex; flex-wrap: wrap; justify-content: center; margin: 10px;">
    {foreach item=i from=$icons}
	    {$i.open}
		<div class="epesi_big_button">
            <img src="{$i.icon}" alt="" align="middle" border="0" width="32" height="32">
            <span>{$i.label}</span>
        </div>
	    {$i.close}
	{/foreach}
</div>

</center>
