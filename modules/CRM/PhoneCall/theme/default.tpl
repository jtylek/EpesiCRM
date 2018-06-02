{assign var=count value=0}
{php}
	$this->_tpl_vars['multiselects'] = array();
{/php}
{foreach key=k item=f from=$fields name=fields}
	{if $f.type!="multiselect"}
		{assign var=count value=$count+1}
	{else}
		{php}
			$this->_tpl_vars['multiselects'][] = $this->_tpl_vars['f'];
		{/php}
	{/if}
{/foreach}
{php}
	$this->_tpl_vars['rows'] = ceil($this->_tpl_vars['count']/$this->_tpl_vars['cols']);
	$this->_tpl_vars['mss_rows'] = ceil(count($this->_tpl_vars['multiselects'])/$this->_tpl_vars['cols']);
	$this->_tpl_vars['no_empty'] = $this->_tpl_vars['count']-floor($this->_tpl_vars['count']/$this->_tpl_vars['cols'])*$this->_tpl_vars['cols'];
	if ($this->_tpl_vars['no_empty']==0) $this->_tpl_vars['no_empty'] = $this->_tpl_vars['cols']+1;
	$this->_tpl_vars['mss_no_empty'] = count($this->_tpl_vars['multiselects'])-floor(count($this->_tpl_vars['multiselects'])/$this->_tpl_vars['cols'])*$this->_tpl_vars['cols'];
	if ($this->_tpl_vars['mss_no_empty']==0) $this->_tpl_vars['mss_no_empty'] = $this->_tpl_vars['cols']+1;
	$this->_tpl_vars['cols_percent'] = 100 / $this->_tpl_vars['cols'];
	$this->_tpl_vars['grid_cols'] = 12 / $this->_tpl_vars['cols'];
{/php}

{if $main_page}

	<div class="card ">
		<div class="card-header clearfix">
			<div class="pull-left">
				<i class="pull-left fa fa-{$icon} fa-2x" style="color: #73879c"></i>
				<span class="form-inline">{$caption}</span>
			</div>
			<div class="pull-right">
					&nbsp;*&nbsp;{$required_note}
					{if isset($subscription_tooltip)}
						&nbsp;&nbsp;&nbsp;{$subscription_tooltip}
					{/if}
					{if isset($fav_tooltip)}
						&nbsp;&nbsp;&nbsp;{$fav_tooltip}
					{/if}
					{if isset($info_tooltip)}
						&nbsp;&nbsp;&nbsp;{$info_tooltip}
					{/if}
					{if isset($clipboard_tooltip)}
						&nbsp;&nbsp;&nbsp;{$clipboard_tooltip}
					{/if}
					{if isset($history_tooltip)}
						&nbsp;&nbsp;&nbsp;{$history_tooltip}
					{/if}
					{if isset($new)}
						{foreach item=n from=$new}
							&nbsp;&nbsp;&nbsp;{$n}
						{/foreach}
					{/if}
			</div>
		</div>
		<div class="card-body">

			{if isset($click2fill)}
				{$click2fill}
			{/if}

			{/if}

<div id="CRM_PhoneCall">

							<div class="row">
								{assign var=x value=1}
								{assign var=y value=1}
								<div class="col col-md-{$grid_cols}">

								{$fields.subject.full_field}
								
								{if $y==$rows or ($y==$rows-1 and $x>$no_empty)}
									{assign var=y value=1}
									{assign var=x value=$x+1}
									</div>
								{else}
									{assign var=y value=$y+1}
								{/if}
								{if $y==1}
									<div class="col col-md-{$grid_cols}">
								{/if}

								{if $action=='view'}

<dl class="dl-horizontal">
    <dt>{$form_data.customer.label}{if $form_data.customer.required}*{/if}{$form_data.customer.advanced}</dt>
    <dd>
        {if $form_data.customer.error}{$form_data.customer.error}{/if}
        {if $form_data.customer.help}
            <div class="help"><img src="{$form_data.customer.help.icon}" alt="help" {$form_data.customer.help.text}></div>
        {/if}
        {if $raw_data.other_customer}{$form_data.other_customer_name.html}{else}{$form_data.customer.html}{/if}&nbsp;
    </dd>
</dl>

								{if $y==$rows or ($y==$rows-1 and $x>$no_empty)}
									{assign var=y value=1}
									{assign var=x value=$x+1}
									</div>
								{else}
									{assign var=y value=$y+1}
								{/if}
								{if $y==1}
									<div class="col col-md-{$grid_cols}">
								{/if}


<dl class="dl-horizontal">
    <dt>{$form_data.phone.label}{if $form_data.phone.required}*{/if}{$form_data.phone.advanced}</dt>
    <dd>
        {if $form_data.phone.error}{$form_data.phone.error}{/if}
        {if $form_data.phone.help}
            <div class="help"><img src="{$form_data.phone.help.icon}" alt="help" {$form_data.phone.help.text}></div>
        {/if}
        {if $raw_data.other_phone}{$form_data.other_phone_number.html}{else}{$form_data.phone.html}{/if}&nbsp;
    </dd>
</dl>

								{if $y==$rows or ($y==$rows-1 and $x>$no_empty)}
									{assign var=y value=1}
									{assign var=x value=$x+1}
									</div>
								{else}
									{assign var=y value=$y+1}
								{/if}
								{if $y==1}
									<div class="col col-md-{$grid_cols}">
								{/if}

{else}

{$fields.customer.full_field}

								{if $y==$rows or ($y==$rows-1 and $x>$no_empty)}
									{assign var=y value=1}
									{assign var=x value=$x+1}
									</div>
								{else}
									{assign var=y value=$y+1}
								{/if}
								{if $y==1}
									<div class="col col-md-{$grid_cols}">
								{/if}

<div class="form-group clearfix" id="_{$form_data.other_customer.element}__container">
    <label class="control-label{if $form_data.other_customer.type != 'long text'} col-sm-2{/if}">{$form_data.other_customer.label}{if $form_data.other_customer.required}*{/if}{$form_data.other_customer.advanced}</label>
    <span class="data {if $form_data.other_customer.type != 'long text'} col-sm-10{/if}" style="{$form_data.other_customer.style}" id="_{$form_data.other_customer.element}__data">
        {if $form_data.other_customer.error}{$form_data.other_customer.error}{/if}
        {if $form_data.other_customer_name.error}{$form_data.other_customer_name.error}{/if}
        {if $form_data.other_customer.help}
            <div class="help"><img src="{$form_data.other_customer.help.icon}" alt="help" {$form_data.other_customer.help.text}></div>
        {/if}
        <div class="col-sm-2">
            {$form_data.other_customer.html}
        </div>
        <div class="col-sm-10">
            {$form_data.other_customer_name.html}
        </div>
    </span>
</div>

								{if $y==$rows or ($y==$rows-1 and $x>$no_empty)}
									{assign var=y value=1}
									{assign var=x value=$x+1}
									</div>
								{else}
									{assign var=y value=$y+1}
								{/if}
								{if $y==1}
									<div class="col col-md-{$grid_cols}">
								{/if}

{$fields.phone.full_field}

								{if $y==$rows or ($y==$rows-1 and $x>$no_empty)}
									{assign var=y value=1}
									{assign var=x value=$x+1}
									</div>
								{else}
									{assign var=y value=$y+1}
								{/if}
								{if $y==1}
									<div class="col col-md-{$grid_cols}">
								{/if}

<div class="form-group clearfix" id="_{$form_data.other_phone.element}__container">
    <label class="control-label{if $form_data.other_phone.type != 'long text'} col-sm-2{/if}">{$form_data.other_phone.label}{if $form_data.other_phone.required}*{/if}{$form_data.other_phone.advanced}</label>
    <span class="data {if $form_data.other_phone.type != 'long text'} col-sm-10{/if}" style="{$form_data.other_phone.style}" id="_{$form_data.other_phone.element}__data">
        {if $form_data.other_phone.error}{$form_data.other_phone.error}{/if}
        {if $form_data.other_phone_number.error}{$form_data.other_phone_number.error}{/if}
        {if $form_data.other_phone.help}
            <div class="help"><img src="{$form_data.other_phone.help.icon}" alt="help" {$form_data.other_phone.help.text}></div>
        {/if}
        <div class="col-sm-2">
	    {$form_data.other_phone.html}
        </div>
        <div class="col-sm-10">
            {$form_data.other_phone_number.html}
        </div>
    </span>
</div>

								{if $y==$rows or ($y==$rows-1 and $x>$no_empty)}
									{assign var=y value=1}
									{assign var=x value=$x+1}
									</div>
								{else}
									{assign var=y value=$y+1}
								{/if}
								{if $y==1}
									<div class="col col-md-{$grid_cols}">
								{/if}

{/if}
								{foreach key=k item=f from=$fields name=fields}
									{if (	$k!='subject' &&
									$k!='customer' &&
									$k!='other_customer' &&
									$k!='other_customer_name' &&
									$k!='phone' &&
									$k!='other_phone' &&
									$k!='other_phone_number' &&
									$f.type!="multiselect")}
										{if $y==1}
											<div class="col col-md-{$grid_cols}">
										{/if}
										{$f.full_field}
										{if $y==$rows or ($y==$rows-1 and $x>$no_empty)}
													{assign var=y value=1}
											{assign var=x value=$x+1}
											</div>
										{else}
											{assign var=y value=$y+1}
										{/if}
									{/if}
								{/foreach}
								</div>
							</div>
							{if !empty($multiselects)}
								<div class="row">
									{assign var=x value=1}
									{assign var=y value=1}
									{foreach key=k item=f from=$multiselects name=fields}
										{if $y==1}
											<div class="col col-md-{$grid_cols}">
										{/if}
										{$f.full_field}
										{if $y==$mss_rows or ($y==$mss_rows-1 and $x>$mss_no_empty)}
											{assign var=y value=1}
											{assign var=x value=$x+1}
											</div>
										{else}
											{assign var=y value=$y+1}
										{/if}
									{/foreach}
								</div>
							{/if}
							<div class="row">
										{foreach key=k item=f from=$longfields name=fields}
											<div class="col-md-12">{$f.full_field}</div>
										{/foreach}
							</div>



</div>


{php}
	eval_js('focus_by_id(\'subject\');');
{/php}

</div>

		{if $main_page}
	</div>
</div>
{/if}