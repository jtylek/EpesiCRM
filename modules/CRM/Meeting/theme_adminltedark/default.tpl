{* CRM_Meeting registers this as the per-table template for the
   'crm_meeting' record type (MeetingInstall.php calls
   Utils_RecordBrowserCommon::set_tpl('crm_meeting', ...)) - same bypass-of-
   View_entry.tpl mechanism as CRM_Contacts's Contact.tpl / CRM_PhoneCall's
   default.tpl (see ../../Contacts/theme_adminlte/Contact.tpl and
   [[adminlte-theme-incomplete]] memory). Already has its own {if $main_page}
   guard around the header - kept. Icon+caption dropped from the header,
   tooltips kept. Layout below mirrors View_entry.tpl/Contact.tpl/
   PhoneCall's default.tpl's own conversion to flex (.epesi-rv-columns/
   .column/.view/.edit/.epesi-rv-row/.label/.data instead of a <table> of
   <table>s) - real per-field content, not decoration, unchanged beyond the
   wrapper markup. Two genuinely tabular bits are left as real <table>s
   rather than forced into the flex grid: the "header-new" day/month/time/
   duration date box (a small fixed display, not a layout device - it used
   a <td rowspan="3"> to sit beside the title/fields column AND the
   multiselects/longfields rows below it; flexbox has no rowspan equivalent,
   so this is reproduced by making the day box one flex sibling of a second
   flex item that itself stacks title/fields, multiselects and longfields -
   same visual result, no rowspan needed) and the recurrence-hash weekday
   grid (a genuine 2-row, up-to-7-column data table - which weekday
   checkboxes go with which label). Kept the "CRM_Calendar_Event_Personal"
   wrapper class - modules/CRM/Meeting/theme/default.css scopes status/
   priority/access select-option icons and the header-new day-box styling
   off this class name, and has no theme_adminlte override, so it still
   resolves via the normal fallback-to-default mechanism and needs the class
   present to keep matching. View_entry.css (loaded alongside any custom
   $tpl by RecordBrowser_0.php) covers .label/.data/.column/etc generically. *}
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
{if $main_page}
<div class="epesi-rv-header">
	<div class="epesi-rv-tools">
		{if isset($subscription_tooltip)}
			{$subscription_tooltip}
		{/if}
		{if isset($fav_tooltip)}
			{$fav_tooltip}
		{/if}
		{if isset($info_tooltip)}
			{$info_tooltip}
		{/if}
		{if isset($clipboard_tooltip)}
			{$clipboard_tooltip}
		{/if}
		{if isset($history_tooltip)}
			{$history_tooltip}
		{/if}
		{if isset($new)}
			{foreach item=n from=$new}
				{$n}
			{/foreach}
		{/if}
	</div>
</div>
{if isset($click2fill)}
    {$click2fill}
{/if}
{/if}
<div class="CRM_Calendar_Event_Personal">

<div class="epesi-rv-card{if $main_page} card{/if}">
	<div class="card-body p-0">

<div class="Utils_RecordBrowser__container">
<div class="Utils_RecordBrowser__View_entry">
<div class="epesi-rv-columns" style="align-items: flex-start;">
                {if $action == 'view' && isset($day_details)}
                {* Plain Bootstrap .card instead of the <table class="header-new">
                   the light theme still uses (modules/CRM/Meeting/theme/default.css)
                   - that table's styling is a hardcoded #F9F9F9 box with an inset
                   shadow that doesn't adapt to dark mode; a .card picks up the
                   theme's own card background/border for free. Own class names
                   (epesi-meeting-*) rather than the shared weekday/day/month/time/
                   duration + green/black/blue/dark-gray utility classes, styled in
                   this module's own theme_adminltedark/default.css - the grid
                   layout/colspan logic (multi-day events span 3 columns instead of
                   1, time/duration always span every column) mirrors the light
                   theme's own div/grid conversion of this same table. *}
                {if isset($event_info) && $event_info.start_date != $event_info.end_date}
                	{assign var=colspan value=3}
                {else}
                	{assign var=colspan value=1}
                {/if}
                <div class="column" style="flex: 0 0 143px;">
                    <div class="card epesi-meeting-datebox">
                        <div class="card-body text-center p-2">
                            <div class="epesi-meeting-grid" style="grid-template-columns: repeat({$colspan}, auto);">
                                <div class="epesi-meeting-weekday">{$day_details.start.weekday}</div>
								{if isset($event_info) && $event_info.start_date != $event_info.end_date}
									<div class="epesi-meeting-weekday">&nbsp;-&nbsp;</div>
									<div class="epesi-meeting-weekday">{$day_details.end.weekday}</div>
								{/if}
                                <div class="epesi-meeting-day">{$day_details.start.day}</div>
								{if isset($event_info) && $event_info.start_date != $event_info.end_date}
									<div class="epesi-meeting-day">&nbsp;-&nbsp;</div>
									<div class="epesi-meeting-day">{$day_details.end.day}</div>
								{/if}
                                <div class="epesi-meeting-month">{$day_details.start.month}&nbsp;{$day_details.start.year}</div>
								{if isset($event_info) && $event_info.start_date != $event_info.end_date}
									<div></div>
									<div class="epesi-meeting-month">{$day_details.end.month}&nbsp;{$day_details.start.year}</div>
								{/if}
                                <div class="epesi-meeting-time">
                                    {if isset($event_info)}
                                        {$event_info.start_time}&nbsp;-&nbsp;{$event_info.end_time}
                                    {/if}
                                </div>
                                <div class="epesi-meeting-duration">
                                    {if isset($event_info)}
                                        {$event_info.duration}
                                    {/if}
                                </div>
                            </div>
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
										<div class="time_s" id="_date__data">{$form_data.date.html}</div>
										<div id="time_s" id="_time__data">{$form_data.time.html}</div>
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
										<div id="crm_calendar_duration_block">
												<span class="error">{$form_data.duration.error}</span><div id="_duration__data"><span id="duration">{$form_data.duration.html}</span></div>
										</div>
										<div id="crm_calendar_event_end_block" id="_end_time__data"><span class="error">{$form_data.end_time.error}</span><span id="time_e">{$form_data.end_time.html}</span></div>
										<div class="toggle_button">{$form_data.toggle.html}</div>
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
</div>


{php}
	eval_js('focus_by_id(\'event_title\');');
{/php}

	</div>
</div>

</div>
