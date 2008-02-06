{$form_open}

    <div id="Utils_Tasks">

<!-- SHADIW BEGIN-->
	<div class="layer" style="padding: 9px; width: 740px;">
		<div class="content_shadow">
<!-- -->

<div style="background-color: white; padding: 5px;">
    <table name="UtilsTasks" class="form {if $action == 'view'}view{else}edit{/if}" cellspacing="0" cellpadding="0" border="0">
    	<tbody>
			<tr>
				<td class="group_bottom label title" align="left">{$form_data.title.label}</td>
				<td class="group_bottom data title" align="left" colspan="5"><span class="error">{$form_data.title.error}</span>
                {$form_data.title.html}
                </td>
			</tr>
			<tr>
			  	<td class="label" align="left">{$form_data.status.label}</td>
				<td class="data" align="left">{$form_data.status.html}</td>
				<td class="label" align="left">{$form_data.permission.label}</td>
				<td class="data access" align="left">
                    {if $action=='view'}
                        <div class="icon"></div>
                    {/if}
                    {$form_data.permission.html}
                </td>
			  	<td class="label" align="left">{$form_data.priority.label}</td>
				<td class="data priority" align="left">
                    {if $action=='view'}
                        <div class="icon"></div>
                    {/if}
                    {$form_data.priority.html}
                </td>
		</tr><tr>
				<td class="label" align="left">{$form_data.longterm.label}</td>
				<td class="data" align="left">{$form_data.longterm.html}</td>
			  	<td class="label" align="left">{$form_data.is_deadline.label}</td>
				<td class="data" align="left">{$form_data.is_deadline.html}</td>
				<td class="data" align="left" colspan=2>{$form_data.deadline.html}</td>
			</tr>
        </tbody>
    </table>
    <table name="UtilsTasks" class="form {if $action == 'view'}view{else}edit{/if} no-border" cellspacing="0" cellpadding="0" border="0">
        <tbody>
            <tr>
	        	<td class="label" style="border-right: 1px solid #b3b3b3;">{$form_data.emp_id.label}</td>
        	    <td class="label" style="padding-right: 0px;"><div style="float: left; padding-top: 3px;">{$form_data.cus_id.label}</div><div style="float: right; border-left: 1px solid #b3b3b3;">{$cus_click}</div></td>
			</tr>
            <tr>
				<td class="data arrows" style="border-right: 1px solid #b3b3b3; vertical-align: top;"><span class="error">{$form_data.emp_id.error}</span>{$form_data.emp_id.html}</td>
				<td class="data arrows" style="vertical-align: top;"><span class="error">{$form_data.cus_id.error}</span>{$form_data.cus_id.html}</td>
			</tr>
        </tbody>
    </table>
    <table name="UtilsTasks" class="form {if $action == 'view'}view{else}edit{/if} no-border" cellspacing="0" cellpadding="0" border="0">
        <tbody>
            {* description *}
        	<tr>
                <td class="label">{$form_data.description.label}</td>
            </tr>
        	<tr>
                <td class="data" colspan="4" style="height: 100px; vertical-align: top; padding-top: 2px;">{$form_data.description.html}</td>
            </tr>
        </tbody>
    </table>
    <table name="UtilsTasks" class="form {if $action == 'view'}view{else}edit{/if} no-border" cellspacing="0" cellpadding="0" border="0">
        <tbody>
        	{* created by *}
            {if isset($form_data.created_by)}
        	<tr>
        		<td class="label" style="width: 25%;">{$form_data.created_by.label}</td>
        		<td class="data" style="width: 25%;">{$form_data.created_by.html}</td>
        		<td class="label" style="width: 25%;">{$form_data.edited_by.label}</td>
        		<td class="data" style="width: 25%;">{$form_data.edited_by.html}</td>
        	</tr>
        	<tr>
        		<td class="label">{$form_data.created_on.label}</td>
        		<td class="data">{$form_data.created_on.html}</td>
        		<td class="label">{$form_data.edited_on.label}</td>
        		<td class="data">{$form_data.edited_on.html}</td>
        	</tr>
        	{/if}
        </tbody>
	</table>

<br><br>
</div>


{php}
	eval_js('focus_by_id(\'task_title\');');
{/php}

<!-- SHADOW END-->
 		</div>
		<div class="shadow-top">
			<div class="left"></div>
			<div class="center"></div>
			<div class="right"></div>
		</div>
		<div class="shadow-middle">
			<div class="left"></div>
			<div class="right"></div>
		</div>
		<div class="shadow-bottom">
			<div class="left"></div>
			<div class="center"></div>
			<div class="right"></div>
		</div>
	</div>
<!-- -->
</div>
