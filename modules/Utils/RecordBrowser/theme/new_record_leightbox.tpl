<center>
<br>
<div style="display: flex; flex-wrap: wrap; justify-content: center;">
{foreach key=k item=cd from=$custom_defaults}
				{$cd.open}
				<div class="epesi_big_button">
                    {if $cd.icon}
					<img src="{$cd.icon}">
                    {/if}
					<span>{$cd.label}</span>
				</div>
				{$cd.close}
{/foreach}
</div>

</center>


