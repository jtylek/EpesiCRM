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
{/php}
{php}
	$this->_tpl_vars['fdow'] = Utils_PopupCalendarCommon::get_first_day_of_week();
	$this->_tpl_vars['fdow']--;
	if ($this->_tpl_vars['fdow']<0) $this->_tpl_vars['fdow']+=7;
{/php}
<table class="Utils_RecordBrowser__table" border="0" cellpadding="0" cellspacing="0">
	<tbody>
		<tr>
			<td style="width:100px;">
				<div class="name">
					<img alt="&nbsp;" class="icon" src="{$icon}" width="32" height="32" border="0">
					<div class="label">{$caption}</div>
				</div>
			</td>
			<td class="required_fav_info">
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
				{foreach item=n from=$new}
					&nbsp;&nbsp;&nbsp;{$n}
				{/foreach}
			</td>
		</tr>
	</tbody>
</table>
{if isset($click2fill)}
    {$click2fill}
{/if}
<div class="CRM_Calendar_Event_Personal">

<!-- SHADIW BEGIN-->
	<div class="layer" style="padding: 9px; width: 98%;">
		<div class="css3_content_shadow">
<!-- -->

<div class="Utils_RecordBrowser__container">
    <table class="Utils_RecordBrowser__View_entry" cellspacing="0" cellpadding="0" border="0" style="width: 100%;">
        <tbody>
            <tr>
                {if $action == 'view'}
                <!-- NEW HEADER -->
                <td rowspan="3" style="width:143px; vertical-align:top;">
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
                </td>
                {/if}
                <!-- LEFT -->
                <td style="width: 50%; height: 101px; vertical-align: top;">
                    {* title *}
                    <table name="CRMCalendar" class="form {if $action == 'view'}view{else}edit{/if}" cellspacing="0" cellpadding="0" border="0">
                        <tbody>
                            <tr>
                                <td class="group_bottom label title" align="left">{$form_data.title.label}{if $form_data.title.required}*{/if}</td>
                                <td class="group_bottom data title" align="left" id="_title__data">
									<div style="position:relative;">
										<span class="error">{$form_data.title.error}</span>
										{$form_data.title.html}
									</div>
                                </td>
                            </tr>
                            <tr>
                                <td class="label" align="left">{$form_data.permission.label}{if $form_data.permission.required}*{/if}</td>
                                <td class="data permission" align="left" id="_permission__data">
									<div style="position:relative;">
										<span class="error">{$form_data.permission.error}</span>
										{if $action=='view'}
											<div class="icon permission_{$raw_data.permission}"></div>
										{/if}
										{$form_data.permission.html}
									</div>
                                </td>
                            </tr>
                            <tr>
                                <td class="label" align="left">{$form_data.priority.label}{if $form_data.priority.required}*{/if}</td>
                                <td class="data priority" align="left" id="_priority__data">
									<div style="position:relative;">
										<span class="error">{$form_data.priority.error}</span>
										{if $action=='view'}
											<div class="icon priority_{$raw_data.priority}"></div>
										{/if}
										{$form_data.priority.html}
									</div>
                                </td>
                            </tr>
                            <tr>
                                <td class="label" align="left">{$form_data.status.label}{if $form_data.status.required}*{/if}</td>
                                <td class="data status" align="left" id="_status__data">
									<div style="position:relative;">
										<span class="error">{$form_data.status.error}</span>
										{if $action=='view'}
											<div class="icon status_{$raw_data.status}"></div>
										{/if}
										{$form_data.status.html}
									</div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </td>
                <!-- -->
                <!-- RIGHT -->
                <td style="width: 50%; height: 101px; vertical-align: top;">
                    <table style="table-layout: auto;" name="CRMCalendar" class="form {if $action == 'view'}view{else}edit{/if} no-border" cellspacing="0" cellpadding="0" border="0">
                        <tbody>
                    {* start - end *}
                    {if $action != 'view'}
                                <tr>
                                    <td class="label">{$form_data.date.label}{if $form_data.date.required}*{/if}</td>
                                    <td colspan="2" class="data timestamp">
										<div style="position:relative;">
											<span class="error">{$form_data.date.error}</span>
											<div id="time_s" id="_time__data">{$form_data.time.html}</div>
											<div class="time_s" id="_date__data">{$form_data.date.html}</div>
										</div>
									</td>
                                </tr>
                    {/if}
                            <tr>
                                <td class="label" align="left">{$form_data.timeless.label}{if $form_data.timeless.required}*{/if}</td>
                                <td class="data" align="left" colspan="2" id="_timeless__data">{$form_data.timeless.html}</td>
                            </tr>
                    {if $action != 'view'}
                                <tr id="duration_end_date__data_">
                                    <td class="label">
										{$form_data.duration.label} / {$form_data.end_time.label}
									</td>
                                    <td colspan="2" class="data" style="height: 20px;">
										<div style="position:relative;">
											<div class="toggle_button">{$form_data.toggle.html}</div>
											<div id="crm_calendar_duration_block">
													<span class="error">{$form_data.duration.error}</span><div style="margin-right: 105px;" id="_duration__data"><span id="duration">{$form_data.duration.html}</span></div>
											</div>
											<div id="crm_calendar_event_end_block" id="_end_time__data"><span class="error">{$form_data.end_time.error}</span><span id="time_e">{$form_data.end_time.html}</span></div>
										</div>
                                    </td>
                                </tr>
                    {/if}
                            <tr>
                                <td class="label" align="left">{$form_data.recurrence_type.label}</td>
                                <td class="data" align="left" colspan="2" id="_recurrence_type__data">
                                    {$form_data.recurrence_type.html}
                                </td>
                            </tr>
			    {if isset($form_data.recurrence_start_date)}
				    <tr id="recurrence_start_date_row">
					<td class="label" align="left">{$form_data.recurrence_start_date.label}</td>
					<td class="data" align="left" colspan="2" id="_recurrence_start_date__data">
						<span id="recurrence_start_date_span">
							{$form_data.recurrence_start_date.html}
						</span>
					</td>
				    </tr>
			    {/if}
                            <tr id="recurrence_end_date_row">
                                <td class="label" align="left" style="width:25%">{$form_data.recurrence_end.label}</td>
                                {if isset($form_data.recurrence_end_checkbox)}
									<td align="left" style="width:1px;" id="_recurrence_end_checkbox__data">
										{$form_data.recurrence_end_checkbox.html}
									</td>
								<td class="data" align="left" id="_recurrence_end__data" style="width:99%;">
								{else}
                                <td class="data" align="left" id="_recurrence_end__data" colspan="2">
								{/if}
									<span id="recurrence_end_date_span">
										{$form_data.recurrence_end.html}
									</span>
                                </td>
                            </tr>
                            <tr id="recurrence_hash_row">
                                <td class="label" align="left">{$form_data.recurrence_hash.label}</td>
                                <td class="data" align="left" colspan="2" id="_recurrence_hash__data">
									<div style="position:relative;">
										<span class="error">{$form_data.recurrence_hash.error}</span>
										<table>
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
								</td>
                            </tr>
				{foreach key=k item=f from=$fields name=fields}
					{if (	$k!='title' &&
							$k!='customers' &&
							$k!='duration' &&
							$k!='employees' &&
							$k!='recurrence_type' &&
							$k!='recurrence_hash' &&
							$k!='recurrence_end' &&
							$k!='date' &&
							$k!='time' &&
							$k!='end_time' &&
							$k!='priority' &&
							$k!='status' &&
							$k!='permission')}
					<tr>
						<td class="label" align="left">{$f.label}{if $f.required}*{/if}</td>
						<td class="data" align="left" colspan="2"  id="_{$f.element}__data">
							<div style="position:relative;">
								<span class="error">{$f.error}</span>
								{$f.html}
							</div>
						</td>
					</tr>
					{/if}
				{/foreach}
                        </tbody>
                    </table>
                </td>
            </tr>
			{if !empty($multiselects)}
				<tr>
					{assign var=x value=1}
					{assign var=y value=1}
					{foreach key=k item=f from=$multiselects name=fields}
						{if $y==1}
						<td class="column" style="width: {$cols_percent}%;">
							<table cellpadding="0" cellspacing="0" border="0" class="multiselects {if $action == 'view'}view{else}edit{/if}" style="border-top: none;">
						{/if}
								<tr>
									<td class="label">{$f.label}{if $f.required}*{/if}{$f.advanced}</td>
									<td class="data {$f.style}" id="_{$f.element}__data">
										<div style="position:relative;">
											{if isset($f.error)}{$f.error}{/if}{$f.html}{if $action == 'view'}&nbsp;{/if}
										</div>
									</td>
								</tr>
						{if $y==$mss_rows or ($y==$mss_rows-1 and $x>$mss_no_empty)}
							{if $x>$mss_no_empty}
								<tr style="display:none;">
									<td class="label">&nbsp;</td>
									<td class="data">&nbsp;</td>
								</tr>
							{/if}
							{assign var=y value=1}
							{assign var=x value=$x+1}
							</table>
						</td>
						{else}
							{assign var=y value=$y+1}
						{/if}
					{/foreach}
				</tr>
			{/if}
			<tr>
				<td colspan="2">
				<table cellpadding="0" cellspacing="0" border="0" class="longfields {if $action == 'view'}view{else}edit{/if}" style="border-top: none;">
					{foreach key=k item=f from=$longfields name=fields}
						<tr>
							<td class="label long_label">{$f.label}{if $f.required}*{/if}</td>
							<td class="data long_data {if $f.type == 'currency'}currency{/if}" id="_{$f.element}__data">
								<div style="position:relative;">
									{if $f.error}{$f.error}{/if}{$f.html}{if $action == 'view'}&nbsp;{/if}
								</div>
							</td>
						</tr>
					{/foreach}
				</table>
				</td>
			</tr>
            {if $action=='add'}
            <tr>
                <td style="width: 50%; vertical-align: top;" colspan=2>
                        <div id="alert" style="padding-top: 5px;">
                            <table name="CRMCalendar" class="form {if $action == 'view'}view{else}edit{/if}" style="border-left: none;" cellspacing="0" cellpadding="0" border="0">
                                <tbody>
                                    <tr>
                                        <td class="label" align="left" style="width: 30%;">{$form_data.messenger_on.label}*</td>
                                        <td class="data" align="left" style="width: 70%;">
											<div style="position:relative;">
												<span class="error">{$form_data.messenger_on.error}</span>{$form_data.messenger_on.html}
											</div>
										</td>
	                                </tr>
    	                        </tbody>
        	                </table>
            	            <div id="messenger_block">
                            <table name="CRMCalendar" class="form {if $action == 'view'}view{else}edit{/if}" style="border-left: none;" cellspacing="0" cellpadding="0" border="0">
                                <tbody>
                                    <tr>
                                        <td class="label" align="left" style="width: 30%;">{$form_data.messenger_before.label}*</td>
                                        <td class="data" align="left" style="width: 70%;">
											<div style="position:relative;">
												<span class="error">{$form_data.messenger_before.error}</span>{$form_data.messenger_before.html}
											</div>
										</td>
                                    </tr>
                                    <tr>
                                        <td class="label" align="left">{$form_data.messenger_message.label}*</td>
                                        <td class="data smalltext" align="left">
											<div style="position:relative;">
												<span class="error">{$form_data.messenger_message.error}</span>{$form_data.messenger_message.html}
											</div>
										</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                </td>
			</tr>
			{/if}
        </tbody>
    </table>
</div>


{php}
	eval_js('focus_by_id(\'event_title\');');
{/php}

<!-- SHADOW END-->
 		</div>
	</div>
<!-- -->

</div>
