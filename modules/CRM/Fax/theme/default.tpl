{$form_open}

<div id="CRM_Fax__filters" style="display: flex; flex-wrap: wrap; align-items: center; width: 100%;">
		{if isset($form_data.status)}
		<div class="label">
			{$form_data.status.label}
		</div>
		<div class="data" style="width:30px;">
			{$form_data.status.error}
			{$form_data.status.html}
		</div>
		{/if}
		{if isset($form_data.start)}
		<div class="label">
			{$form_data.start.label}
		</div>
		<div class="data" style="width:30px;">
			{$form_data.start.error}
			{$form_data.start.html}
		</div>
		<div class="label">
			{$form_data.end.label}
		</div>
		<div class="data" style="width:30px;">
			{$form_data.end.error}
			{$form_data.end.html}
		</div>
		{/if}
		<div class="data" style="width:30px;">
			{$form_data.submit_button.html}
		</div>
</div>


{$form_close}

<br><br>
{$table_data}
