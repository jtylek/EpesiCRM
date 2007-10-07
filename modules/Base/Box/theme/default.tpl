{if !$logged}
<center>
<table id="Base_Box__login" cellspacing="0" cellpadding="0" border="0">
	<tr><td class="status"><center>{$status}</center></td></tr>
	<tr><td class="title">Managing Business Your Way <span>TM</span></td></tr>
	<tr><td class="entry">{$login}</td></tr>
	<tr><td class="starting">{$about}</td></tr>
</table>
</center>
{else}
{php}
	load_js('data/Base_Theme/templates/default/Base_Box__default.js');
	eval_js_once('document.body.id=null');
{/php}

<center>

<!-- shadow begin
<table id="shadow" cellpadding="0" cellspacing="0" border="0">
    <tr>
        <td class="td-5x5 top-left"></td>
        <td class="td-5x5 top-left-right"></td>
        <td class="top-center">&nbsp;</td>
        <td class="td-5x5 top-right-left"></td>
        <td class="td-5x5 top-right"></td>
    </tr>
    <tr>
        <td class="td-5x5 top-left-left"></td>
        <td colspan="3" rowspan="3" class="center-center">
 -->

<table id="Base_Box__logged" cellspacing="0" cellpadding="0" border="0">
	<tr>
		<td colspan="3" style="height: 20px; border-bottom: 4px solid #B3B3B3;">
			<table cellspacing="0" cellpadding="0" border="0">
				<tr>
					<td class="menu-bar">{$menu}</td>
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
			<div id="content">
			<table width="95%" height="100%" cellspacing="0" cellpadding="0" border="0">
				<tr>
					<td style="padding: 4px 4px 4px 4px; vertical-align: top;">
						<center>{$main}</center>
					</td>
				</tr>
			</table>
			</div>
		</td>
	</tr>
	<tr>
		<td class="footer" style="width: 100px; vertical-align: center; text-align: left; padding-left: 4px;"><a href="http://sourceforge.net/project/showfiles.php?group_id=192918">version {$version_no}</a></td>
		<td class="footer" style="width: 778px;">Copyright &copy; 2007 &bull; <a href="http://sourceforge.net/projects/epesi/">epesi framework</a> &bull; Application developed by <a href="http://www.telaxus.com">Telaxus LLC</a><a href="http://www.epesi.org"></td>
		<td class="footer" style="width: 100px; vertical-align: center; text-align: right; padding-right: 2px; padding-top: 2px;">{$about}</td>
	</tr>
</table>

<!--
        </td>
        <td class="td-5x5 top-right-right"></td>
    </tr>
    <tr>
        <td class="center-left">&nbsp;</td>
        <td class="center-right">&nbsp;</td>
    </tr>
    <tr>
        <td class="td-5x5 bottom-left-right"></td>
        <td class="td-5x5 bottom-right-left"></td>
    </tr>
    <tr>
        <td class="td-5x5 bottom-left"></td>
        <td class="td-5x5 bottom-left-left"></td>
        <td class="bottom-center">&nbsp;</td>
        <td class="td-5x5 bottom-right-right"></td>
        <td class="td-5x5 bottom-right"></td>
    </tr>
</table>
 -->

</center>
{/if}

{$status}
