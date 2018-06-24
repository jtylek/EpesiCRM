<div id="Base_Admin">
{foreach from=$sections key=sk item=s}
	<div class="card">
		<div class="card-header" style="clear:both;">{$s.header}</div>
		<div class="card-body btn-toolbar">
			{foreach key=key item=button from=$s.buttons}
				<div class="btn btn-default col-xs-4 col-sm-3 col-md-2 col-lg-1">
					{$__link.sections.$sk.buttons.$key.link.open}
					{if isset($button.icon)}
						<img src="{$button.icon}" border="0" width="32" height="32" align="middle">
					{/if}
					<div>
						{$__link.sections.$sk.buttons.$key.link.text}
					</div>
					{$__link.sections.$sk.buttons.$key.link.close}
				</div>
			{/foreach}
		</div>
	</div>
{/foreach}
</div>
