{php}
	load_js('data/Base_Theme/templates/default/Utils_Tasks__default.js');
{/php}

<table class="Utils_Tasks__table" border="0" cellpadding="0" cellspacing="0">
	<tbody>
		<tr>
			<td class="icon"><img src="{$theme_dir}/Utils_Tasks__icon.png" width="32" height="32" border="0"></td>
			<td class="name">Tasks - {if $action == 'view'}view{else}edit{/if}</td>
			<td class="required_fav_info">
			</td>
		</tr>
	</tbody>
</table>

{$form_open}

<div id="Utils_Tasks">

<!-- SHADIW BEGIN-->
	<div class="layer" style="padding: 9px; width: 750px;">
		<div class="content_shadow">
<!-- -->

<div style="background-color: white; padding: 5px;">
    <table name="UtilsTasks" class="form {if $action == 'view'}view{else}edit{/if}" cellspacing="0" cellpadding="0" border="0">
    	<tbody>
			<tr>
				<td class="group_bottom label title" align="left" style="width: 10%;">{$form_data.title.label}</td>
				<td class="group_bottom data title" align="left"><span class="error">{$form_data.title.error}</span>
                {$form_data.title.html}
                </td>
			</tr>
        </tbody>
    </table>
    {* description *}
    <table name="UtilsTasks" class="form {if $action == 'view'}view{else}edit{/if} no-border" cellspacing="0" cellpadding="0" border="0">
    	<tbody>
            {if $action != 'view'}
        	<tr>
                <td class="label">{$form_data.description.label}</td>
            </tr>
            {/if}
        	<tr>
                <td class="data" colspan="4" style="vertical-align: top; padding-top: 2px;">
                    {if $action == 'view'}<div style="height: 50px; white-space: normal; overflow: auto;">{/if}
                        {$form_data.description.html}
                    {if $action == 'view'}</div>{/if}
                </td>
            </tr>
        </tbody>
    </table>
    {* employees customers *}
    <table name="UtilsTasks" class="form {if $action == 'view'}view{else}edit{/if} no-border" cellspacing="0" cellpadding="0" border="0">
    	<tbody>
            <tr>
	        	<td class="label" style="width: 50%; border-right: 1px solid #b3b3b3;" colspan="4">{$form_data.emp_id.label}</td>
        	    <td class="label" style="width: 50%; padding-right: 0px;" colspan="4"><div style="float: left; padding-top: 3px;">{$form_data.cus_id.label}</div><div style="float: right; border-left: 1px solid #b3b3b3;">{$cus_click}</div></td>
			</tr>
            <tr>
				<td class="data arrows" style="border-right: 1px solid #b3b3b3; vertical-align: top;" colspan="4"><span class="error">{$form_data.emp_id.error}</span>{$form_data.emp_id.html}</td>
				<td class="data arrows" style="vertical-align: top;" colspan="4"><span class="error">{$form_data.cus_id.error}</span>{$form_data.cus_id.html}</td>
			</tr>
            {* *}
			<tr>
			  	<td class="label" align="left" style="width: 10%;">{$form_data.status.label}</td>
				<td class="data status" align="left" style="width: 15%;">{$form_data.status.html}</td>

                <td class="label" align="left">{$form_data.is_deadline.label}</td>
				<td class="data" align="left">{$form_data.is_deadline.html}</td>

                <td class="label" align="left">Deadline date</td>
				<td class="data" align="{if $action == 'view'}left{else}right{/if}" style="padding-right: 0px;" colspan=3>{$form_data.deadline.html}</td>
            </tr>
            <tr>
				<td class="label" align="left">{$form_data.longterm.label}</td>
				<td class="data" align="left">{$form_data.longterm.html}</td>

                <td class="label" align="left" style="width: 10%;">{$form_data.permission.label}</td>
				<td class="data permission" align="left" style="width: 15%;">
                    {if $action=='view'}
                        <div class="icon"></div>
                    {/if}
                    {$form_data.permission.html}
                </td>

                <td class="label" align="left" style="width: 20%;">{$form_data.priority.label}</td>
				<td class="data priority" align="left" style="width: 30%;" {if $action!='edit'}colspan=3{/if}>
                    {if $action=='view'}
                        <div class="icon"></div>
                    {/if}
                    {$form_data.priority.html}
                </td>
		{if $action=='edit'}
                <td class="label" align="left" style="width: 20%;">{$form_data.notify.label}</td>
				<td class="data" align="left">{$form_data.notify.html}</td>
		{/if}
        	</tr>
        </tbody>
    </table>
    {if $action == 'view'}
        <div class="AdditionalInfoButton" onclick="additional_info_roll('{$theme_dir}')">
            Additional Info&nbsp;&nbsp;&nbsp;<img id="AdditionalInfoImg" src="{$theme_dir}/Utils_Tasks__roll-down.png" width="14" height="14" alt="=" border="0">
        </div>
    {/if}
    <div id="AdditionalInfo" style="display: none;">
        <table name="UtilsTasks" class="form {if $action == 'view'}view{else}edit{/if}" cellspacing="0" cellpadding="0" border="0">
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
    </div>
    {$attachments}
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
