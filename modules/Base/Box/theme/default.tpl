{if !$logged}
<center>
<table id="Base_Box__login" cellspacing="0" cellpadding="0" border="0">
	<tbody>
	<tr><td class="status"><center>{$status}</center></td></tr>
	<tr><td class="entry">{$login}</td></tr>
	<tr><td class="starting">{$about}</td></tr>
	</tbody>
</table>
</center>
{else}
{php}
	load_js('data/Base_Theme/templates/default/Base_Box__default.js');
	eval_js_once('document.body.id=null');
{/php}

<center>

<table id="Base_Box__logged" cellspacing="0" cellpadding="0" border="0">
	<tbody>
	<tr>
		<td class="top-bar" colspan="3">
			<table cellspacing="0" cellpadding="0" border="0">
				<tbody>
				<tr>
					<td class="menu-bar">{$menu}</td>
					<td class="module_name">{if $moduleindicator}{$moduleindicator}{else}&nbsp;{/if}</td>
				</tr>
				</tbody>
			</table>
		</td>
	</tr>
	<tr>
		<td class="action-bar" colspan="3">
			<div id="menu">
			<table width="978" cellspacing="0" cellpadding="0" border="0">
				<tbody>
				<tr>
					<td class="logo"><a href="#"><img border="0" src="{$theme_dir}/images/logo-small.png" width="193" height="68"></a></td>
					<td>
						<table cellspacing="0" cellpadding="0" border="0">
							<tbody>
							<tr>
								<td class="icons" rowspan="2">{$actionbar}</td>
								<td class="login">{$login}</td>
							</tr>
							<tr>
								<td class="search"><center>{$search}</center></td>
							</tr>
							</tbody>
						</table>
					</td>
					</tbody>
				</tr>
			</table>
			</div>
		</td>
	</tr>
	<tr>
		<td colspan="3">
			<div id="content">
			<table style="width: 100%; height: 100%;" cellspacing="0" cellpadding="0" border="0">
				<tbody>
				<tr>
					<td class="main">
						<center>{$main}</center>
					</td>
				</tr>
            	<tr>
                    <td class="footer">
                        <table id="footer" cellspacing="0" cellpadding="0" border="0">
							<tbody>
                            <tr>
                                <td class="left"><a href="http://sourceforge.net/project/showfiles.php?group_id=192918">version {$version_no}</a></td>
                                <td class="center">Copyright &copy; 2007 &bull; <a href="http://sourceforge.net/projects/epesi/">epesi framework</a> &bull; Application developed by <a href="http://www.telaxus.com">Telaxus LLC</a></td>
                                <td class="right">{$about}</td>
                            </tr>
							</tbody>
                        </table>
                    </td>
                </tr>
				</tbody>
            </table>
			</div>
		</td>
	</tr>
	</tbody>
</table>

</center>
{/if}

{$status}
