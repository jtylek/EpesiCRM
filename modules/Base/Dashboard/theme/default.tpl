<!-- SHADIW BEGIN -->
	<div class="layer" style="padding: 9px; width: 310px; min-height: 30px;">
		<div class="content_shadow">
<!-- -->

            <table class="container" cellpadding="0" cellspacing="0" border="0">
                <tbody>
                <tr>
                    <td class="header title {$handle_class}">&nbsp;<img src="{$theme_dir}/Base_Dashboard__grab.png" onMouseOver="this.src='{$theme_dir}/Base_Dashboard__grab-hover.png';" onMouseOut="this.src='{$theme_dir}/Base_Dashboard__grab.png';" width="14" height="14" border="0" alt="#" align="absmiddle">&nbsp;&nbsp;&nbsp;{$caption}</td>
                    <td class="header controls">{if isset($href)}{$__link.href.open}<img src="{$theme_dir}/Base_Dashboard__resize.png" onMouseOver="this.src='{$theme_dir}/Base_Dashboard__resize-hover.png';" onMouseOut="this.src='{$theme_dir}/Base_Dashboard__resize.png';" width="14" height="14" alt="G" border="0">&nbsp;{$__link.href.close}{/if}{if isset($toggle)}{$__link.toggle.open}<img src="{$theme_dir}/Base_Dashboard__roll-up.png" onClick="var x='{$theme_dir}/Base_Dashboard__roll-';if(this.src.indexOf(x+'down.png')>=0)this.src=x+'up.png';else this.src=x+'down.png';" width="14" height="14" alt="=" border="0">&nbsp;{$__link.toggle.close}{/if}{if isset($configure)}{$__link.configure.open}<img src="{$theme_dir}/Base_Dashboard__configure.png" onMouseOver="this.src='{$theme_dir}/Base_Dashboard__configure-hover.png';" onMouseOut="this.src='{$theme_dir}/Base_Dashboard__configure.png';" width="14" height="14" alt="c" border="0">&nbsp;{$__link.configure.close}{/if}{$__link.remove.open}<img src="{$theme_dir}/Base_Dashboard__close.png" onMouseOver="this.src='{$theme_dir}/Base_Dashboard__close-hover.png';" onMouseOut="this.src='{$theme_dir}/Base_Dashboard__close.png';" width="14" height="14" alt="x" border="0">&nbsp;{$__link.remove.close}</td>
                </tr>
                <tr>
                    <td colspan="2" class="content_td">{$content}</td>
                </tr>
                </tbody>
            </table>

<!-- SHADOW END -->
 		</div>
		<div class="shadow-top">
			<div class="left"></div>
			<div class="center"></div>
			<div class="right"></div>
		</div>
		<div class="shadow-middle">
			<div class="left"></div>
			<div class="right"></div>
		</div>
		<div class="shadow-bottom">
			<div class="left"></div>
			<div class="center"></div>
			<div class="right"></div>
		</div>
	</div>
<!-- -->
