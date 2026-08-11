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
<div class="Utils_RecordBrowser__table">
	<div class="Utils_RecordBrowser__table_row">
		<div class="Utils_RecordBrowser__table_icon">
			<div class="name">
				<img alt="&nbsp;" class="icon" src="{$icon}" width="32" height="32" border="0">
				<div class="label">{$caption}</div>
			</div>
		</div>
		<div class="required_fav_info">
			{if $required_note}&nbsp;*&nbsp;{$required_note}{/if}
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
</div>
{if isset($click2fill)}
    {$click2fill}
{/if}
<div class="CRM_Calendar_Event_Personal">

<!-- SHADIW BEGIN-->
	<div class="layer" style="padding: 9px; width: 98%;">
		<div class="css3_content_shadow">
<!-- -->

<div class="Utils_RecordBrowser__container">
<div class="Utils_RecordBrowser__View_entry">
<div class="epesi-rv-columns" style="align-items: flex-start;">
                {if $action == 'view' && isset($day_details)}
                <!-- NEW HEADER -->
                <div class="column" style="flex: 0 0 143px;">
                    {* Was a <table> with a <td rowspan="3"> sitting beside
                       the title/fields column below - CSS grid instead,
                       with the column count (1, or 3 when the event spans
                       multiple days) set inline per-record via $colspan; the
                       time/duration rows always span every column. $colspan
                       must be computed before the grid container's own
                       style attribute is emitted, not inside it. *}
                    {if isset($event_info) && $event_info.start_date != $event_info.end_date}
                    	{assign var=colspan value=3}
                    {else}
                    	{assign var=colspan value=1}
                    {/if}
                    <div class="header-new" style="grid-template-columns: repeat({$colspan}, auto);">
                        <div class="weekday green">{$day_details.start.weekday}</div>
						{if isset($event_info) && $event_info.start_date != $event_info.end_date}
							<div class="weekday green">&nbsp;-&nbsp;</div>
							<div class="weekday green">{$day_details.end.weekday}</div>
						{/if}
                        <div class="day black">{$day_details.start.day}</div>
						{if isset($event_info) && $event_info.start_date != $event_info.end_date}
							<div class="day black">&nbsp;-&nbsp;</div>
							<div class="day black">{$day_details.end.day}</div>
						{/if}
                        <div class="month blue">{$day_details.start.month}&nbsp;{$day_details.start.year}</div>
						{if isset($event_info) && $event_info.start_date != $event_info.end_date}
							<div></div>
							<div class="month blue">{$day_details.end.month}&nbsp;{$day_details.start.year}</div>
						{/if}
                        <div class="time black" style="grid-column: 1 / -1;">
                            {if isset($event_info)}
                                {$event_info.start_time}&nbsp;-&nbsp;{$event_info.end_time}
                            {/if}
                        </div>
                        <div class="duration dark-gray" style="grid-column: 1 / -1;">
                            {if isset($event_info)}
                                {$event_info.duration}
                            {/if}
                        </div>
                    </div>
                </div>
                {/if}
                <div class="column" style="flex: 1 1 0; min-width: 0;">
                <div class="epesi-rv-columns">
                <!-- LEFT -->
                <div class="column" style="width: 50%;">
                    {* title *}
                    <div class="{if $action == 'view'}view{else}edit{/if}">
						{$fields.title.full_field}
						{$fields.permission.full_field}
						{$fields.priority.full_field}
						{$fields.status.full_field}
                    </div>
                </div>
                <!-- RIGHT -->
                <div class="column" style="width: 50%;">
                    <div class="{if $action == 'view'}view{else}edit{/if} no-border">
                    {* start - end *}
                    {if $action != 'view'}
                                <div class="epesi-rv-row">
                                    <div class="label">{$form_data.date.label}{if $form_data.date.required}*{/if}</div>
                                    <div class="data timestamp">
										<span class="error">{$form_data.date.error}</span>
										<div id="time_s" id="_time__data">{$form_data.time.html}</div>
										<div class="time_s" id="_date__data">{$form_data.date.html}</div>
									</div>
                                </div>
                    {/if}
                            <div class="epesi-rv-row">
                                <div class="label">{$form_data.timeless.label}{if $form_data.timeless.required}*{/if}</div>
                                <div class="data" id="_timeless__data">{$form_data.timeless.html}</div>
                            </div>
                    {if $action != 'view'}
                                <div class="epesi-rv-row" id="duration_end_date__data_">
                                    <div class="label">
										{$form_data.duration.label} / {$form_data.end_time.label}
									</div>
                                    <div class="data">
										<div class="toggle_button">{$form_data.toggle.html}</div>
										<div id="crm_calendar_duration_block">
												<span class="error">{$form_data.duration.error}</span><div id="_duration__data"><span id="duration">{$form_data.duration.html}</span></div>
										</div>
										<div id="crm_calendar_event_end_block" id="_end_time__data"><span class="error">{$form_data.end_time.error}</span><span id="time_e">{$form_data.end_time.html}</span></div>
									</div>
                                </div>
                    {/if}
                            <div class="epesi-rv-row">
                                <div class="label">{$form_data.recurrence_type.label}</div>
                                <div class="data" id="_recurrence_type__data">
                                    {$form_data.recurrence_type.html}
                                </div>
                            </div>
			    {if isset($form_data.recurrence_start_date)}
				    <div class="epesi-rv-row" id="recurrence_start_date_row">
					<div class="label">{$form_data.recurrence_start_date.label}</div>
					<div class="data" id="_recurrence_start_date__data">
						<span id="recurrence_start_date_span">
							{$form_data.recurrence_start_date.html}
						</span>
					</div>
				    </div>
			    {/if}
                            <div class="epesi-rv-row" id="recurrence_end_date_row">
                                <div class="label" style="flex-basis: 25%;">{$form_data.recurrence_end.label}</div>
                                {if isset($form_data.recurrence_end_checkbox)}
									<div style="flex: 1 1 auto; min-width: 0; display: flex;">
										<div id="_recurrence_end_checkbox__data">
											{$form_data.recurrence_end_checkbox.html}
										</div>
										<div class="data" style="flex: 1 1 auto; min-width: 0;" id="_recurrence_end__data">
								{else}
                                <div class="data" id="_recurrence_end__data">
								{/if}
									<span id="recurrence_end_date_span">
										{$form_data.recurrence_end.html}
									</span>
                                </div>
                                {if isset($form_data.recurrence_end_checkbox)}
									</div>
								{/if}
                            </div>
                            <div class="epesi-rv-row" id="recurrence_hash_row">
                                <div class="label">{$form_data.recurrence_hash.label}</div>
                                <div class="data" id="_recurrence_hash__data">
									<span class="error">{$form_data.recurrence_hash.error}</span>
									{* Was a 2-row/7-column <table> (label row, checkbox row). CSS
									   grid with a fixed 7-column template auto-places the flat
									   sequence of 14 divs into 2 rows of 7, same as the table did,
									   with no explicit row wrapper needed. *}
									<div class="recurrence-hash-grid">
										{if $fdow<=0}<div>{$form_data.recurrence_hash_0.label}</div>{/if}
										{if $fdow<=1}<div>{$form_data.recurrence_hash_1.label}</div>{/if}
										{if $fdow<=2}<div>{$form_data.recurrence_hash_2.label}</div>{/if}
										{if $fdow<=3}<div>{$form_data.recurrence_hash_3.label}</div>{/if}
										{if $fdow<=4}<div>{$form_data.recurrence_hash_4.label}</div>{/if}
										{if $fdow<=5}<div>{$form_data.recurrence_hash_5.label}</div>{/if}
										<div>{$form_data.recurrence_hash_6.label}</div>
										{if $fdow>0}<div>{$form_data.recurrence_hash_0.label}</div>{/if}
										{if $fdow>1}<div>{$form_data.recurrence_hash_1.label}</div>{/if}
										{if $fdow>2}<div>{$form_data.recurrence_hash_2.label}</div>{/if}
										{if $fdow>3}<div>{$form_data.recurrence_hash_3.label}</div>{/if}
										{if $fdow>4}<div>{$form_data.recurrence_hash_4.label}</div>{/if}
										{if $fdow>5}<div>{$form_data.recurrence_hash_5.label}</div>{/if}
										{if $fdow<=0}<div>{$form_data.recurrence_hash_0.html}</div>{/if}
										{if $fdow<=1}<div>{$form_data.recurrence_hash_1.html}</div>{/if}
										{if $fdow<=2}<div>{$form_data.recurrence_hash_2.html}</div>{/if}
										{if $fdow<=3}<div>{$form_data.recurrence_hash_3.html}</div>{/if}
										{if $fdow<=4}<div>{$form_data.recurrence_hash_4.html}</div>{/if}
										{if $fdow<=5}<div>{$form_data.recurrence_hash_5.html}</div>{/if}
										<div>{$form_data.recurrence_hash_6.html}</div>
										{if $fdow>0}<div>{$form_data.recurrence_hash_0.html}</div>{/if}
										{if $fdow>1}<div>{$form_data.recurrence_hash_1.html}</div>{/if}
										{if $fdow>2}<div>{$form_data.recurrence_hash_2.html}</div>{/if}
										{if $fdow>3}<div>{$form_data.recurrence_hash_3.html}</div>{/if}
										{if $fdow>4}<div>{$form_data.recurrence_hash_4.html}</div>{/if}
										{if $fdow>5}<div>{$form_data.recurrence_hash_5.html}</div>{/if}
									</div>
                                </div>
                            </div>
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
							$k!='permission' &&
                            $f.type != 'multiselect'
                    )}
						{$f.full_field}
					{/if}
				{/foreach}
                    </div>
                </div>
                </div>
			{* $colspan="3" rowspan (see the header-new comment above) meant the
			   date badge spans down through 3 stacked rows: the title/fields
			   row above, this multiselects row (if present), and the
			   longfields row below - all 3 need to stay inside the same
			   flex:1 1 0 wrapper column the date badge sits beside, not
			   become its siblings (see AI-shared/adminlte-theme.md - this was
			   originally mis-nested one level too shallow, starving that
			   column down to 0 width). *}
			{if !empty($multiselects)}
				<div class="epesi-rv-columns">
					{assign var=x value=1}
					{assign var=y value=1}
					{foreach key=k item=f from=$multiselects name=fields}
						{if $y==1}
						<div class="column" style="width: {$cols_percent}%;">
							<div class="multiselects {if $action == 'view'}view{else}edit{/if}">
						{/if}
						{$f.full_field}
						{if $y==$mss_rows or ($y==$mss_rows-1 and $x>$mss_no_empty)}
							{assign var=y value=1}
							{assign var=x value=$x+1}
							</div>
						</div>
						{else}
							{assign var=y value=$y+1}
						{/if}
					{/foreach}
				</div>
			{/if}
			<div class="longfields {if $action == 'view'}view{else}edit{/if}">
				{foreach key=k item=f from=$longfields name=fields}
					{$f.full_field}
				{/foreach}
			</div>
            {if $action=='add'}
                <div id="alert" style="padding-top: 5px;">
                    <div class="{if $action == 'view'}view{else}edit{/if}" style="border-left: none;">
                        <div class="epesi-rv-row">
                            <div class="label" style="flex-basis: 30%;">{$form_data.messenger_on.label}*</div>
                            <div class="data" style="flex-basis: 70%;">
								<span class="error">{$form_data.messenger_on.error}</span>{$form_data.messenger_on.html}
							</div>
                        </div>
                    </div>
                    <div id="messenger_block">
                    <div class="{if $action == 'view'}view{else}edit{/if}" style="border-left: none;">
                        <div class="epesi-rv-row">
                            <div class="label" style="flex-basis: 30%;">{$form_data.messenger_before.label}*</div>
                            <div class="data" style="flex-basis: 70%;">
								<span class="error">{$form_data.messenger_before.error}</span>{$form_data.messenger_before.html}
							</div>
                        </div>
                        <div class="epesi-rv-row">
                            <div class="label">{$form_data.messenger_message.label}*</div>
                            <div class="data smalltext">
								<span class="error">{$form_data.messenger_message.error}</span>{$form_data.messenger_message.html}
							</div>
                        </div>
                    </div>
                    </div>
                </div>
			{/if}
                </div>
</div>


{php}
	eval_js('focus_by_id(\'event_title\');');
{/php}

<!-- SHADOW END-->
 		</div>
	</div>
<!-- -->

</div>
