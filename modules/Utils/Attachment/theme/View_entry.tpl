{* Get total number of fields to display *}
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
                        <div class="data long_data {$longfields.note.style}" id="_{$longfields.note.element}__data">
                            {if $longfields.note.error}{$longfields.note.error}{/if}
                            {if $longfields.note.help}
                                <div class="help"><img src="{$longfields.note.help.icon}" alt="help" {$longfields.note.help.text}></div>
                            {/if}
                            <div>
                                {$longfields.note.html}{if $action == 'view'}&nbsp;{/if}
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
                        {if $f.type!="multiselect"}
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
                        {/if}
                    {/foreach}
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
                        {if $k!='note'}
                            {$f.full_field}
                        {/if}
                    {/foreach}
                </div>
            </div>

            {if $main_page}
                {php}
                    if (isset($this->_tpl_vars['focus'])) eval_js('focus_by_id(\''.$this->_tpl_vars['focus'].'\');');
                {/php}
            {/if}

        </div>

    </div>
</div>
