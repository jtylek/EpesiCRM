{$form_open}

<div id="recordbrowser_filters_{$id}" class="Utils_RecordBrowser__Filter form-inline " style="margin-bottom: 20px; {if !isset($dont_hide)}display: none;{/if}">
			{foreach item=f from=$filters}
                <div class="input-group">
                    <span class="input-group-addon">{$form_data.$f.label}</span>
                    <div class="form-control">{$form_data.$f.html}</div>
                </div>
			{/foreach}
				{$form_data.submit.html}
{$form_close}

</div>