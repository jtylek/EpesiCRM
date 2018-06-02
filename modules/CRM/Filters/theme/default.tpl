<center>

<div id="CRM_Filters" style="margin-bottom: 10px">
        <!-- MY -->

	    {$__link.my.open}
		<button class="btn btn-default">
			<i class="fa fa-user fa-3x" aria-hidden="true"></i>
			<div>{$__link.my.text}</div>
        </button>
	    {$__link.my.close}


		{if isset($all)}
			<!-- ALL -->

			{$__link.all.open}
			<button class="btn btn-default">
				<i class="fa fa-globe fa-3x" aria-hidden="true"></i>
				<div>{$__link.all.text}</div>
			</button>
			{$__link.all.close}

		{/if}

        <!-- MANAGE FILTERS -->

	    {$__link.manage.open}
		<button class="btn btn-default">
			<i class="fa fa-cog fa-3x" aria-hidden="true"></i>
			<div>{$__link.manage.text}</div>
        </button>
	    {$__link.manage.close}

</div>
<div id="CRM_Filters" style="margin-bottom: 10px">
			{$contacts_open}
				{$contacts_data.crm_filter_contact.label}&nbsp;<span class="filters-autoselect">{$contacts_data.crm_filter_contact.html}</span>&nbsp;{$contacts_data.submit.html}
			{$contacts_close}
</div>

<div class="card ">
	<div class="card-header">
		{$saved_filters}
	</div>
	<div class="card-body">
		{foreach item=p key=k from=$filters}
			{$p.open}
			<div class="btn btn-default">
				{$p.title}
			</div>
			{$p.close}
		{/foreach}
	</div>
</div>