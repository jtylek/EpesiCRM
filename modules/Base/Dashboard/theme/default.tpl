<div class="card">
	<div class="handle card-header">


		{if !empty($actions)}
			<div class="card-options card-options-left">
				{foreach item=action from=$actions}
					{$action}
				{/foreach}
			</div>
		{/if}

		<h3 class="card-title">{$caption}</h3>

		<div class="card-options">
			{if isset($href)}
				{$__link.href.open}
				<span class="card-options-fullscreen"><i class="fa fa-arrows-alt"></i></span>
				{$__link.href.close}
			{/if}
			{if isset($toggle)}
				{$__link.toggle.open}
				<span class="card-options-collapse"><i class="fa fa-caret-square-o-down"></i></span>
				{$__link.toggle.close}
			{/if}
			{if isset($configure)}
				{$__link.configure.open}
				<span class="card-options-collapse"><i class="fa fa-cog"></i></span>
				{$__link.configure.close}
			{/if}
			{if isset($remove)}
				{$__link.remove.open}
				<span class="card-options-remove"><i class="fa fa-times"></i></span>
				{$__link.remove.close}
			{/if}
		</div>

	</div>
	<div class="card-body">
		{$content}
	</div>
</div>
