{if !$logged}
<center>
<table id="Base_Box__login" cellspacing="0" cellpadding="0" border="0">
	<tr><td class="status"><center>{$status}</center></td></tr>
	<tr><td class="entry">{$login}</td></tr>
	<tr><td class="starting"><a href="http://www.epesi.org"><img src="{$theme_dir}/images/epesi-powered.png" border="0"></a></td></tr>
</table>
</center>
{else}
{php}
	load_js_inline('data/Base/Theme/templates/default/Base_Box__default.js');
	eval_js_once('setInterval(\'base_box__set_content_height(\\\'content\\\')\',200)');
	eval_js('correctPNG()');
{/php}

<center>

<table id="Base_Box__logged" cellspacing="0" cellpadding="0" border="0">
	<tr>
		<td colspan="3" style="height: 20px; border-bottom: 4px solid #CCCCCC;">
			<table cellspacing="0" cellpadding="0" border="0">
				<tr>
					<td style="width: 691px; height: 20px; text-align: left;">{$menu}</td>
					<td class="module_name">{if $moduleindicator}{$moduleindicator}{else}&nbsp;{/if}</td>
				</tr>
			</table>
		</td>
	</tr>
	<tr>
		<td class="top-bar" colspan="3">
			<div id="menu">
			<table width="978" cellspacing="0" cellpadding="0" border="0">
				<tr>
					<td class="logo"><a href="#"><img border="0" src="{$theme_dir}/images/logo-small.png" width="193" height="68"></a></td>
					<td>
						<table cellspacing="0" cellpadding="0" border="0">
							<tr>
								<td class="icons" rowspan="2">
									{$actionbar}
								</td>
								<td class="login">{$login}</td>
							</tr>
							<tr>
								<td class="search"><center>{$search}</center></td>
								<!-- <td class="entry"><center>{$homepage}</center></td> -->
							</tr>
						</table>
					</td>	
				</tr>
			</table>
			</div>
		</td>
	</tr>
	<tr>
		<td colspan="3">
			{if $main neq ""}
			<div id="content">
			<table width="90%" height="100%" cellspacing="0" cellpadding="0" border="0">
				<tr>
					<td style="padding: 4px 4px 4px 4px; vertical-align: top;">
						<center>{$main}</center>
					</td>
				</tr>
			</table>
			</div>
			{/if}
		</td>
	</tr>
	<tr>
		<td class="footer" style="width: 100px; vertical-align: center; text-align: left; padding-left: 4px;"><a href="http://sourceforge.net/project/showfiles.php?group_id=192918">version {$version_no}</a></td>
		<td class="footer" style="width: 778px;">Copyright &copy; 2007 &bull; <a href="http://sourceforge.net/projects/epesi/">epesi framework</a> &bull; Application developed by <a href="http://www.telaxus.com">Telaxus LLC</a><a href="http://www.epesi.org"></td>
		<td class="footer" style="width: 100px; vertical-align: center; text-align: right; padding-right: 2px; padding-top: 2px;"><a href="http://www.epesi.org"><img src="{$theme_dir}/images/epesi-powered.png" border="0"></a></td>
	</tr>	
</table>
</center>
{/if}

{$status}
