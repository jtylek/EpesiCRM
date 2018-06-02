{php}
	load_js($this->get_template_vars('theme_dir').'/Libs/Leightbox/default.js');
{/php}

<div class="card " style="margin-bottom: 0px;">
	<div class="card-header">
		<span class="card-title">{if $header}{$header}{else}&nbsp;{/if}</span>
		<div class="pull-right action-buttons">
			<a onClick="libs_leightbox_resize(this.parentNode.parentNode.parentNode.parentNode)" title="{$resize_label}">
				<button class="btn btn-success btn-xs">
					<i class="fa fa-arrows-alt"></i>
				</button>
			</a>
			<a {$close_href} title="{$close_label}">
				<button class="btn btn-danger btn-xs">
					<i class="fa fa-times"></i>
				</button>
			</a>
		</div>
	</div>
	<div class="card-body">
		{$content}
	</div>
</div>