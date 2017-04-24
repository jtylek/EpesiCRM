{php}
	eval_js('var base_setup__last_filter;');
	eval_js('base_setup__preprocess_filter = base_setup__last_filter;');
	eval_js('base_setup__last_filter = "";');
	load_js($this->get_template_vars('theme_dir').'/Base/Setup/default.js');
	eval_js('if(base_setup__preprocess_filter!=null)base_setup__filter_by(base_setup__preprocess_filter);');
{/php}

<div class="Base_Setup">
	<div class="filters">
		{foreach key=label item=attr from=$filters}
			<button id="Base_Setup__filter_{$attr.arg}" {if !$attr.arg}class="btn selected" {else} class="btn"{/if} {if isset($attr.attrs)}{$attr.attrs}{/if} onclick="base_setup__filter_by('{$attr.arg}');"><strong>{$label}</strong>&nbsp;<span id="qty-badge" class="badge">{$attr.qty}</span></button>
		{/foreach}
	</div>
	
	<div id="Base_Setup" class="container">
		{foreach key=name item=package from=$packages}
			<div id="block" class="jumbotron" style="position:relative;"{foreach item=f from=$package.filter} {$f}="1"{/foreach}>
				<div id="inner-container" class="container-fluid">
					<div class="row-fluid" id="icon-row">
						{if $package.icon}
							<img class="package_icon" src="{$package.icon}">
						{else}
							<span class="package_icon glyphicon glyphicon-folder-close" style="font-size: 70px"></span>
						{/if}
					</div>

					<div class="row-fluid" id="title-row">
							{if $package.url}
								<a href="{$package.url}" target="_blank">
							{/if}
							<div id="module-name">
								{$package.name} {if $package.url}<span class="glyphicon glyphicon-info-sign"></span>{/if}
							</div>
							{if $package.url}
								</a>
							{/if}
					</div>

					<div class="row-fluid" id="version-row">
                        {if $package.version}
							<div class="version">
                                <strong>{$version_label} {$package.version}</strong>
							</div>
						{else}
							<div class="version"><strong>{$version_label}  ---</strong></div>
                        {/if}
					</div>


					<div class="actions container-fluid">
						<div class="row-fluid" id="actions-row">
							<div class="btn-group" style="float: left" id="btn-group">
								<button type="button" class="btn dropdown-toggle" action data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" {$package.style}" {$package.buttons_tooltip} {if !empty($package.options)} style="width: 128px" {else} style="width: 264px"{/if}>{$package.status} <span class="caret"></span></button>
								{if !empty($package.buttons)}
								<ul class="dropdown-menu" {if !empty($package.options)} style="width: 127px" {else} style="width: 263px"{/if}>
									{foreach from=$package.buttons item=button}
										<li style="height: 2.25rem"><a id="status-a" {$button.href} class="{$button.style}">{$button.label}</a></li>
									{/foreach}
								</ul>
								{/if}
							</div>

							<div style="float:right">
							{if !empty($package.options)}
								<div class="btn-group" id="btn-group">
									<button type="button" class="btn dropdown-toggle" style="width: 128px"  data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">{$labels.options} <span class="caret"></span></button>

									<ul class="dropdown-menu multi-level" role="menu" id="first-dropdown" aria-labelledby="dropdownMenu">
										{foreach from=$package.options key=option item=action name=packs}
											{if !empty($action.buttons)}
												<li id="option-li" style="display: block" class="dropdown-submenu">
													<div id="option-label">{$action.name}</div>
											{else}
												<li id="option-li" style="display: block">
													<div id="option-label">{$action.name}</div>
											{/if}
														{if !empty($action.buttons)}
															<div><span class="badge" id="qty-badge" style="float: right"><strong>{$action.status}</strong></span></div>
															<ul class="dropdown-menu">
																{foreach from=$action.buttons item=button name=wtf}
																	<li style="height: 2.25rem"><a class="action {$button.style}" style="height: 100%; font-size: 13px" {$button.href}>{$button.label}</a></li>
																	{if !$smarty.foreach.wtf.last}
																		<li role="separator" class="divider"></li>
																	{/if}
																{/foreach}
															</ul>
                                                        {/if}
												</li>
											{if !$smarty.foreach.packs.last}
												<li role="separator" class="divider" id="second-separator"></li>
											{/if}
										{/foreach}
									</ul>
								</div>
							{/if}
							</div>
						</div>
					</div>
				</div>
			</div>
		{/foreach}
	</div>
</div>

