{$form_open}

{foreach from=$form_data.header item=h}
	{foreach from=$form_data.header item=h}
		<h2 class="text-center">
			{$h}
		</h2>
	{/foreach}
{/foreach}
	{foreach from=$form_data item=f}
		{if is_array($f) && isset($f.type) && $f.type!='hidden' && $f.type!='button' && $f.type!='submit'}
			<div class="input-group" style="margin-bottom: 5px">
				<span class="input-group-addon">
					{$f.label}{if $f.required}*{/if}
				</span>
				<span class="form-control{if $f.frozen} form-control-static{/if}">
					{$f.error}
					{$f.html}
				</span>
			</div>
		{/if}
	{/foreach}
			{foreach from=$form_data item=f}
				{if is_array($f) && isset($f.type) && ($f.type=='button' || $f.type=='submit')}
					{$f.html}
				{/if}
			{/foreach}


{$form_close}