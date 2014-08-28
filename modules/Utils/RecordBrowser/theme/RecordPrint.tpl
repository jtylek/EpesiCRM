<h1>{$caption}</h1>
<h3>ID: {$record_id}</h3>
{if $no_access}<div style="color:red; font-weight: bold">{"Access forbidden"|t}</div>{/if}
{if count($data)}
<table border="1" cellpadding="3">
    {assign var=i value=0}
    {foreach from=$data item=field}
        {if $i % $cols == 0}<tr>{/if}
        <td style="width: {math equation='width / cols' width=40 cols=$cols}%; background-color:#DDD;text-align:right; font-weight: bold">{$field.label}</td>
        <td style="width: {math equation='width / cols' width=60 cols=$cols}%">{$field.value}</td>
        {assign var=i value=$i+1}
        {if $i % $cols == 0}</tr>{/if}
    {/foreach}
</table>
{/if}