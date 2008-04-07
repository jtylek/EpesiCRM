{$form_open}

<table class="CRM_Tasks__table" border="0" cellpadding="0" cellspacing="0">
	<tbody>
		<tr>
			<td class="icon"><img src="{$theme_dir}/CRM_Tasks__icon.png" width="32" height="32" border="0"></td>
			<td class="name">Tasks</td>
			<td class="required_fav_info">
			</td>
		</tr>
	</tbody>
</table>
<br>

<div style="text-align: left; padding-left: 20px;">

<table id="CRM_Tasks" border="0" cellpadding="0" cellspacing="0">
    <tbody>
        <tr>
            <td class="label">{$form_data.term.label}</td><td class="data">{$form_data.term.html}&nbsp;</td><td class="empty"></td>
            <td class="label">{$form_data.closed.label}</td><td class="data">{$form_data.closed.html}&nbsp;</td><td class="empty"></td>
            <!--<td class="label">{$form_data.submit.label}</td>--><td class="submit">{$form_data.submit.html}</td>
        </tr>
    </tbody>
</table>

</div>

</form>

{$tasks}
