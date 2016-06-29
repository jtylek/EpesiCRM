<div class="form-inline">
{$form_open}

{foreach from=$form_data.header item=h}
	<h2 class="text-center">
		{$h}
	</h2>
{/foreach}
<div style="margin:5px; line-height: 200%">
		{foreach from=$form_data item=f}
		{if is_array($f) && isset($f.type) && $f.type!='hidden' && $f.type!='button' && $f.type!='submit'}
            <div class="input-group">
			    <span class="input-group-addon"> {$f.label}{if $f.required}*{/if}</span>
			    <span  class="form-control{if $f.frozen} form-control-static{/if}">
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
</div>


{$form_close}
</div>