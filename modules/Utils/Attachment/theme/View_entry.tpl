{* Get total number of fields to display *}
{assign var=count value=0}
{foreach key=k item=f from=$fields name=fields}
    {assign var=count value=$count+1}
{/foreach}
{php}
    $this->_tpl_vars['rows'] = ceil($this->_tpl_vars['count']/$this->_tpl_vars['cols']);
    $this->_tpl_vars['no_empty'] = $this->_tpl_vars['count']-floor($this->_tpl_vars['count']/$this->_tpl_vars['cols'])*$this->_tpl_vars['cols'];
    if ($this->_tpl_vars['no_empty']==0) $this->_tpl_vars['no_empty'] = $this->_tpl_vars['cols']+1;
    $this->_tpl_vars['cols_percent'] = 100 / $this->_tpl_vars['cols'];
{/php}

{if $main_page}
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

{/if}

<div class="layer" style="padding: 9px; width: 98%;">
    <div class="css3_content_shadow">

        <div class="Utils_RecordBrowser__container">

            {* Outside table *}
            <div class="Utils_RecordBrowser__View_entry">
                <div class="epesi-rv-columns">
                    <div class="column">
                        <div class="{if $action == 'view'}view{else}edit{/if}">
                            {$fields.title.full_field}
                        </div>
                    </div>
                    <div class="column">
                        <div class="{if $action == 'view'}view{else}edit{/if}">
                            {$fields.edited_on.full_field}
                        </div>
                    </div>
                    <div class="column">
                        <div class="{if $action == 'view'}view{else}edit{/if}">
                            {$fields.permission.full_field}
                        </div>
                    </div>
                </div>
                <div class="epesi-rv-columns">
                    <div class="column" style="flex: 1 1 0; min-width: 0;">
                        <div class="{if $action == 'view'}view{else}edit{/if}">
                        <div class="epesi-rv-row">
                        <div class="data long_data {$secondary_fields.note.style}" id="_{$secondary_fields.note.element}__data">
                            {if $secondary_fields.note.error}{$secondary_fields.note.error}{/if}
                            {if $secondary_fields.note.help}
                                <div class="help"><img src="{$secondary_fields.note.help.icon}" alt="help" {$secondary_fields.note.help.text}></div>
                            {/if}
                            <div>
                                {$secondary_fields.note.html}{if $action == 'view'}&nbsp;{/if}
                            </div>
                        </div>
                        </div>
                        </div>
                    </div>
                </div>
                <div class="epesi-rv-columns">
                    <div class="column">
                        <div class="{if $action == 'view'}view{else}edit{/if}">
                            {$fields.sticky.full_field}
                        </div>
                    </div>
                    <div class="column" style="flex: 1 1 0; min-width: 0;">
                        <div class="{if $action == 'view'}view{else}edit{/if}">
                            {$fields.crypted.full_field}
                        </div>
                    </div>
                </div>
            </div>

            <div class="Utils_RecordBrowser__View_entry">
                <div class="epesi-rv-columns">
                    {assign var=x value=1}
                    {assign var=y value=1}
                    {foreach key=k item=f from=$fields name=fields}
                        {if $k!='title' && $k!='permission' && $k!='edited_on' && $k!='sticky' && $k!='crypted'}
                            {if !isset($focus) && $f.type=="text"}
                                {assign var=focus value=$f.element}
                            {/if}

                            {if $y==1}
                                <div class="column" style="width: {$cols_percent}%;">
                                <div class="{if $action == 'view'}view{else}edit{/if}">
                            {/if}
                            {$f.full_field}
                            {if $y==$rows or ($y==$rows-1 and $x>$no_empty)}
                                {assign var=y value=1}
                                {assign var=x value=$x+1}
                                </div>
                                </div>
                            {else}
                                {assign var=y value=$y+1}
                            {/if}
                        {/if}
                    {/foreach}
                </div>
                {* Multiselect and long text fields (e.g. Attached to, Note, or
                   any admin-added custom field), in their shared RecordBrowser
                   field order (RecordBrowser_0.php's $secondary_blocks) - Note
                   is skipped here since it already rendered, fixed-position,
                   above. *}
                {foreach key=bk item=block from=$secondary_blocks name=secondary_blocks}
                    {if $block.type=='long'}
                        {if $block.item.element!="note"}
                            <div class="longfields {if $action == 'view'}view{else}edit{/if}">
                                {$block.item.full_field}
                            </div>
                        {/if}
                    {else}
                        {php}
                            $items = $this->_tpl_vars['block']['items'];
                            $this->_tpl_vars['mss_block_rows'] = ceil(count($items)/$this->_tpl_vars['cols']);
                            $this->_tpl_vars['mss_block_no_empty'] = count($items)-floor(count($items)/$this->_tpl_vars['cols'])*$this->_tpl_vars['cols'];
                            if ($this->_tpl_vars['mss_block_no_empty']==0) $this->_tpl_vars['mss_block_no_empty'] = $this->_tpl_vars['cols']+1;
                        {/php}
                        <div class="epesi-rv-columns">
                            {assign var=x value=1}
                            {assign var=y value=1}
                            {foreach key=k item=f from=$block.items name=secondary_block_items}
                                {if $y==1}
                                    <div class="column" style="width: {$cols_percent}%;">
                                    <div class="multiselects {if $action == 'view'}view{else}edit{/if}">
                                {/if}
                                {$f.full_field}
                                {if $y==$mss_block_rows or ($y==$mss_block_rows-1 and $x>$mss_block_no_empty)}
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
                {/foreach}
            </div>

            {if $main_page}
                {php}
                    if (isset($this->_tpl_vars['focus'])) eval_js('focus_by_id(\''.$this->_tpl_vars['focus'].'\');');
                {/php}
            {/if}

        </div>

    </div>
</div>
