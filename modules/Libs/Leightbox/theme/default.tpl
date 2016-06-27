{php}
	load_js($this->get_template_vars('theme_dir').'/Libs/Leightbox/default.js');
{/php}

<div class="panel panel-default" style="margin-bottom: 0px; height: 100%">
	<div class="panel-heading">
		<span class="panel-title">{$header}</span>
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
	<div class="panel-body">
		{$content}
	</div>
</div>