{math assign="col" equation="x" x=3}

<!-- SHADIW BEGIN -->
<div class="layer" style="padding: 9px; width: 740px;">
<div class="content_shadow">
<!-- -->

<table border="0" cellpadding="0" cellspacing="5" style="background-color: #FFFFFF;">
{section name=m loop=$months}
	
	{if $col % 3 == 0}<tr>{/if} {* begin of new row *}
		<td style="vertical-align: top">
			{$months[m]}
		</td>
	{if $col % 3 == 3}</tr>{/if} {* end of row *}
	
	{math assign="col" equation="x+1" x=$col}
{/section}
</table>

<!-- SHADOW END -->
	</div>
<div class="shadow-top">
<div class="left"></div>
<div class="center"></div>
<div class="right"></div>
</div>
<div class="shadow-middle">
<div class="left"></div>
<div class="right"></div>
</div>
<div class="shadow-bottom">
<div class="left"></div>
<div class="center"></div>
<div class="right"></div>
</div>
</div>
<!-- -->