{if isset($custom_tooltip)}
<table id="Utils_Calendar__event_tip" border="0">
    <tbody>
	<tr>
            <td colspan="2">{$custom_tooltip}</td>
	</tr>
    <tr>
            <td class="info" colspan="2">{$show_hide_info}</td>
    </tr>
	</tbody>
</table>
{else}
<table id="Utils_Calendar__event_tip" border="0">
    <tbody>
	<tr>
            <td class="title" colspan="2">{$title}</td>
        </tr>
	<tr>
	   <td colspan="2">{$description}</td>
        </tr>
        <tr>
            <td class="label">Start</td><td class="data">{$start}</td>
        </tr>
        <tr>
            <td class="label">End</td><td class="data">{$end}</td>
        </tr>
{if $duration}
         <tr>
            <td class="label">Duration</td><td class="data">{$duration}</td>
        </tr>
{/if}
	<tr>
	   <td colspan="2">{$additional_info}</td>
        </tr>
        <tr>
            <td colspan="2">{$additional_info2}</td>
        </tr>
        <tr>
            <td class="info" colspan="2">{$show_hide_info}</td>
        </tr>
    </tbody>
</table>
{/if}