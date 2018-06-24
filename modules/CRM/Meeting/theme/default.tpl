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
	if($this->_tpl_vars['action']=='view') $this->_tpl_vars['grid_cols']-=1;
	$this->_tpl_vars['fdow'] = Utils_PopupCalendarCommon::get_first_day_of_week();
	$this->_tpl_vars['fdow']--;
	if ($this->_tpl_vars['fdow']<0) $this->_tpl_vars['fdow']+=7;
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

<div id="CRM_Meeting">
	<div class="row">
                {if $action == 'view'}
                <!-- NEW HEADER -->
                <div class="col-md-{$cols}">
                    <table border="0" class="header-new">
                        <tbody>
                            <tr>
                                <td class="weekday green">{$day_details.start.weekday}</td>
								{if $event_info.start_date != $event_info.end_date}
									{assign var=colspan value=3}
									<td class="weekday green">&nbsp;-&nbsp;</td>
									<td class="weekday green">{$day_details.end.weekday}</td>
								{else}
									{assign var=colspan value=1}
								{/if}
                            </tr>
                            <tr>
                                <td class="day black">{$day_details.start.day}</td>
								{if $event_info.start_date != $event_info.end_date}
									<td class="day black">&nbsp;-&nbsp;</td>
									<td class="day black">{$day_details.end.day}</td>
								{/if}
                            </tr>
                            <tr>
                                <td class="month blue">{$day_details.start.month}&nbsp;{$day_details.start.year}</td>
								{if $event_info.start_date != $event_info.end_date}
									<td></td>
									<td class="month blue">{$day_details.end.month}&nbsp;{$day_details.start.year}</td>
								{/if}
                            </tr>
                            <tr>
                                <td colspan="{$colspan}" class="time black">
                                    {if isset($event_info)}
                                        {$event_info.start_time}&nbsp;-&nbsp;{$event_info.end_time}
                                    {/if}
                                </td>
                            </tr>
                            <tr>
                                <td colspan="{$colspan}" class="duration dark-gray">
                                    {if isset($event_info)}
                                        {$event_info.duration}
                                    {/if}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                {/if}

								{assign var=x value=1}
								{assign var=y value=1}
								<div class="col col-md-{$grid_cols}">

								{$fields.title.full_field}
								
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

								{$fields.permission.full_field}
								
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

								{$fields.priority.full_field}
								
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

								{$fields.status.full_field}
								
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

								{if $action!='view'}

								<div class="form-group clearfix" id="_{$form_data.date.name}__container">
									<div class="row">
										<label class="control-label{if $form_data.date.type != 'long text'} col-md-4 col-sm-3{/if} col-xs-12">{$form_data.date.label}{if $form_data.date.required}*{/if}{$form_data.date.advanced}</label>
										<span class="data {if $form_data.date.type != 'long text'} col-md-8 col-sm-9{/if} col-xs-12" style="{$form_data.date.style}" id="_{$form_data.date.name}__data">
											{if $form_data.date.error}{$form_data.date.error}{/if}
											{if $form_data.time.error}{$form_data.time.error}{/if}
											{if $form_data.date.help}
												<div class="help"><img src="{$form_data.date.help.icon}" alt="help" {$form_data.date.help.text}></div>
											{/if}
											<div class="row">
												<div class="col-md-6 col-xs-7" id="_date__data">
													{$form_data.date.html}
												</div>
												<div class="col-md-6 col-xs-5" id="_time__data">
													{$form_data.time.html}
												</div>
											</div>
										</span>
									</div>
								</div>

								{/if}

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

								<div class="form-group clearfix" id="_{$form_data.timeless.name}__container">
									<div class="row">
										<label class="control-label col-md-4 col-sm-3 col-xs-12">{$form_data.timeless.label}{if $form_data.timeless.required}*{/if}{$form_data.timeless.advanced}</label>
										<span class="data col-md-8 col-sm-9 col-xs-12" style="{$form_data.timeless.style}" id="_{$form_data.timeless.name}__data">
											{if $form_data.timeless.error}{$form_data.timeless.error}{/if}
											{if $form_data.timeless.help}
												<div class="help"><img src="{$form_data.timeless.help.icon}" alt="help" {$form_data.timeless.help.text}></div>
											{/if}
											<div>
											{$form_data.timeless.html}{if $action == 'view'}&nbsp;{/if}
											</div>
										</span>
									</div>
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
								
								{if $action!='view'}
								<div class="form-group clearfix" id="_{$form_data.duration.name}__container">
									<div class="row">
										<label class="control-label{if $form_data.duration.type != 'long text'} col-md-4 col-sm-3{/if} col-xs-12">{$form_data.duration.label} / {$form_data.end_time.label}{if $form_data.duration.required}*{/if}{$form_data.duration.advanced} {$form_data.end_time.advanced}</label>
										<span class="data {if $form_data.duration.type != 'long text'} col-md-8 col-sm-9{/if} col-xs-12" style="{$form_data.duration.style}" id="_{$form_data.duration.name}__data">
											{if $form_data.duration.help}
												<div class="help"><img src="{$form_data.duration.help.icon}" alt="help" {$form_data.duration.help.text}></div>
											{/if}
											<div class="row">
												<div class="col-xs-4">
													{$form_data.toggle.html}
												</div>
												<div class="col-xs-8" id="crm_calendar_duration_block">
													{if $form_data.duration.error}{$form_data.duration.error}{/if}
													<span id="duration">{$form_data.duration.html}</span>
												</div>
												<div class="col-xs-8" id="crm_calendar_event_end_block">
													{if $form_data.end_time.error}{$form_data.end_time.error}{/if}
													<span id="time_e">{$form_data.end_time.html}</span>
												</div>
											</div>
										</span>
									</div>
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

								{$fields.recurrence_type.full_field}

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

								{if $fields.recurrence_start_date.full_field}
									{$fields.recurrence_start_date.full_field}
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

								<div class="form-group clearfix" id="_{$form_data.recurrence_end.name}__container">
									<div class="row">
										<label class="control-label{if $form_data.recurrence_end.type != 'long text'} col-md-4 col-sm-3{/if} col-xs-12">{$form_data.recurrence_end.label}{if $form_data.recurrence_end.required}*{/if}{$form_data.recurrence_end.advanced}</label>
										<span class="data {if $form_data.recurrence_end.type != 'long text'} col-md-8 col-sm-9{/if} col-xs-12" style="{$form_data.recurrence_end.style}" id="_{$form_data.recurrence_end.name}__data">
											{if $form_data.recurrence_end.help}
												<div class="help"><img src="{$form_data.recurrence_end.help.icon}" alt="help" {$form_data.recurrence_end.help.text}></div>
											{/if}
											<div class="row">
												{if isset($form_data.recurrence_end_checkbox)}
													<div class="col-xs-2" id="_recurrence_end_checkbox__data">
														{if $form_data.recurrence_end_checkbox.error}{$form_data.recurrence_end_checkbox.error}{/if}
														{$form_data.recurrence_end_checkbox.html}
													</div>
												{/if}
												<div class="col-xs-10" id="_recurrence_end__data">
													{if $form_data.recurrence_end.error}{$form_data.recurrence_end.error}{/if}
													<span id="recurrence_end_date_span">{$form_data.recurrence_end.html}</span>
												</div>
											</div>
										</span>
									</div>
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

								<div class="form-group clearfix" id="recurrence_hash_row">
									<div class="row">
								    <label class="control-label{if $form_data.recurrence_hash.type != 'long text'} col-sm-2{/if} col-xs-12">{$form_data.recurrence_hash.label}{if $form_data.recurrence_hash.required}*{/if}{$form_data.recurrence_hash.advanced}</label>
								    <span class="data {if $form_data.recurrence_hash.type != 'long text'} col-md-8 col-sm-9{/if} col-xs-12" style="{$form_data.recurrence_hash.style}" id="_{$form_data.recurrence_hash.name}__data">
								        {if $form_data.recurrence_hash.error}{$form_data.recurrence_hash.error}{/if}
								        {if $form_data.recurrence_hash.help}
								            <div class="help"><img src="{$form_data.recurrence_hash.help.icon}" alt="help" {$form_data.recurrence_hash.help.text}></div>
								        {/if}
								        <div style="position:relative;">
										<table class="recurrence-table">
											<tr>
												{if $fdow<=0}<td>{$form_data.recurrence_hash_0.label}</td>{/if}
												{if $fdow<=1}<td>{$form_data.recurrence_hash_1.label}</td>{/if}
												{if $fdow<=2}<td>{$form_data.recurrence_hash_2.label}</td>{/if}
												{if $fdow<=3}<td>{$form_data.recurrence_hash_3.label}</td>{/if}
												{if $fdow<=4}<td>{$form_data.recurrence_hash_4.label}</td>{/if}
												{if $fdow<=5}<td>{$form_data.recurrence_hash_5.label}</td>{/if}
												<td>{$form_data.recurrence_hash_6.label}</td>
												{if $fdow>0}<td>{$form_data.recurrence_hash_0.label}</td>{/if}
												{if $fdow>1}<td>{$form_data.recurrence_hash_1.label}</td>{/if}
												{if $fdow>2}<td>{$form_data.recurrence_hash_2.label}</td>{/if}
												{if $fdow>3}<td>{$form_data.recurrence_hash_3.label}</td>{/if}
												{if $fdow>4}<td>{$form_data.recurrence_hash_4.label}</td>{/if}
												{if $fdow>5}<td>{$form_data.recurrence_hash_5.label}</td>{/if}
											</tr>
											<tr>
												{if $fdow<=0}<td>{$form_data.recurrence_hash_0.html}</td>{/if}
												{if $fdow<=1}<td>{$form_data.recurrence_hash_1.html}</td>{/if}
												{if $fdow<=2}<td>{$form_data.recurrence_hash_2.html}</td>{/if}
												{if $fdow<=3}<td>{$form_data.recurrence_hash_3.html}</td>{/if}
												{if $fdow<=4}<td>{$form_data.recurrence_hash_4.html}</td>{/if}
												{if $fdow<=5}<td>{$form_data.recurrence_hash_5.html}</td>{/if}
												<td>{$form_data.recurrence_hash_6.html}</td>
												{if $fdow>0}<td>{$form_data.recurrence_hash_0.html}</td>{/if}
												{if $fdow>1}<td>{$form_data.recurrence_hash_1.html}</td>{/if}
												{if $fdow>2}<td>{$form_data.recurrence_hash_2.html}</td>{/if}
												{if $fdow>3}<td>{$form_data.recurrence_hash_3.html}</td>{/if}
												{if $fdow>4}<td>{$form_data.recurrence_hash_4.html}</td>{/if}
												{if $fdow>5}<td>{$form_data.recurrence_hash_5.html}</td>{/if}
											</tr>
										</table>
								        </div>
								    </span>
									</div>
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


								{foreach key=k item=f from=$fields name=fields}
									{if (	$k!='title' &&
									$k!='permission' &&
									$k!='status' &&
									$k!='priority' &&
									$k!='customers' &&
									$k!='duration' &&
									$k!='employees' &&
									$k!='recurrence_type' &&
									$k!='recurrence_hash' &&
									$k!='recurrence_end' &&
									$k!='date' &&
									$k!='time' &&
									$k!='end_time' &&
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
	                {if $action=='add'}
        		<div class="row">
							<div class="form-group clearfix" id="_{$form_data.messenger_on.name}__container">
								<label class="control-label{if $form_data.messenger_on.type != 'long text'} col-md-4 col-sm-3{/if} col-xs-12">{$form_data.messenger_on.label}{if $form_data.messenger_on.required}*{/if}{$form_data.messenger_on.advanced}</label>
								<span class="data {if $form_data.messenger_on.type != 'long text'} col-md-8 col-sm-9{/if} col-xs-12" style="{$form_data.messenger_on.style}" id="_{$form_data.messenger_on.name}__data">
							        {if $form_data.messenger_on.error}{$form_data.messenger_on.error}{/if}
							        {if $form_data.messenger_on.help}
							            <div class="help"><img src="{$form_data.messenger_on.help.icon}" alt="help" {$form_data.messenger_on.help.text}></div>
							        {/if}
							        {$form_data.messenger_on.html}
							    </span>
							</div>

			                	        <div id="messenger_block">
								<div class="form-group clearfix" id="_{$form_data.messenger_before.name}__container">
								    <label class="control-label{if $form_data.messenger_before.type != 'long text'} col-md-4 col-sm-3{/if} col-xs-12">{$form_data.messenger_before.label}{if $form_data.messenger_before.required}*{/if}{$form_data.messenger_before.advanced}</label>
								    <span class="data {if $form_data.messenger_before.type != 'long text'} col-md-8 col-sm-9{/if} col-xs-12" style="{$form_data.messenger_before.style}" id="_{$form_data.messenger_before.name}__data">
								        {if $form_data.messenger_before.error}{$form_data.messenger_before.error}{/if}
								        {if $form_data.messenger_before.help}
								            <div class="help"><img src="{$form_data.messenger_before.help.icon}" alt="help" {$form_data.messenger_before.help.text}></div>
								        {/if}
								        {$form_data.messenger_before.html}
								    </span>
								</div>

								<div class="form-group clearfix" id="_{$form_data.messenger_message.name}__container">
								    <label class="control-label{if $form_data.messenger_message.type != 'long text'} col-md-4 col-sm-3{/if} col-xs-12">{$form_data.messenger_message.label}{if $form_data.messenger_message.required}*{/if}{$form_data.messenger_message.advanced}</label>
								    <span class="data {if $form_data.messenger_message.type != 'long text'} col-md-8 col-sm-9{/if} col-xs-12" style="{$form_data.messenger_message.style}" id="_{$form_data.messenger_message.name}__data">
								        {if $form_data.messenger_message.error}{$form_data.messenger_message.error}{/if}
								        {if $form_data.messenger_message.help}
								            <div class="help"><img src="{$form_data.messenger_message.help.icon}" alt="help" {$form_data.messenger_message.help.text}></div>
								        {/if}
								        {$form_data.messenger_message.html}
								    </span>
								</div>
				                        </div>
				</div>
			{/if}
	</div>



{php}
	eval_js('focus_by_id(\'event_title\');');
{/php}

</div>

		{if $main_page}
	</div>
</div>
{/if}