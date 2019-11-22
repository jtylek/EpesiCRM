<div style="text-align:left;padding-left:6px;">
{$form_open}

{foreach from=$form_data.header item=h}
	<div class="epesi_label header" style="width:700px;">
		{$h}
	</div>
{/foreach}
<div style="margin:5px; line-height: 200%">
		{foreach from=$form_data item=f}
		{if is_array($f) && isset($f.type) && isset($f.html) && isset($f.label) && $f.type!='hidden' && $f.type!='button' && $f.type!='submit'}
            <div style="display: inline-block; white-space: nowrap;">
			<span style="display: table-cell;  width:10px" class="epesi_label">
				{$f.label}{if $f.required}*{/if}
			</span>
			<span style="display: table-cell; width: auto;" class="epesi_data{if $f.frozen} static_field{/if}">
					{$f.error}
					{$f.html}
			</span>
            </div>
		{/if}
		{/foreach}
			{foreach from=$form_data item=f}
				{if is_array($f) && isset($f.type) && ($f.type=='button' || $f.type=='submit')}
                    <div style="display: inline-block; white-space: nowrap">
                        <div class="child_button" style="display: table-cell">
					{$f.html}
                        </div>
                    </div>
				{/if}
			{/foreach}
</div>


{$form_close}
</div>