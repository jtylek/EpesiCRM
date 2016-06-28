<div id="Base_Admin">
{foreach from=$sections key=sk item=s}
	<div class="panel panel-default">
		<div class="panel-heading" style="clear:both;">{$s.header}</div>
		<div class="panel-body">
			{foreach key=key item=button from=$s.buttons}
				{$__link.sections.$sk.buttons.$key.link.open}
				<button class="btn btn-default">
					{if isset($button.icon)}
						<img src="{$button.icon}" border="0" width="32" height="32" align="middle">
					{/if}
					<div>
						{$__link.sections.$sk.buttons.$key.link.text}
					</div>
				</button>
				{$__link.sections.$sk.buttons.$key.link.close}
			{/foreach}
		</div>
	</div>
{/foreach}
</div>