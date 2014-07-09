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
                {if isset($new)}
                    {foreach item=n from=$new}
                        &nbsp;&nbsp;&nbsp;{$n}
                    {/foreach}
                {/if}
            </td>
        </tr>
        </tbody>
    </table>

    {if isset($click2fill)}
        {$click2fill}
    {/if}

{/if}

<div class="layer" style="padding: 9px; width: 98%;">
    <div class="css3_content_shadow">

        <div class="Utils_RecordBrowser__container">

            {* Outside table *}
            <table class="Utils_RecordBrowser__View_entry" cellpadding="0" cellspacing="0" border="0">
                <tbody>
                <tr>
                    <td>
                        <table cellpadding="0" cellspacing="0" border="0" class="{if $action == 'view'}view{else}edit{/if}">
                        <tr>
                        <td class="label">{$fields.title.label}{if $fields.title.required}*{/if}</td>
                        <td class="data {$fields.title.style}" id="_{$fields.title.element}__data">
                            <div style="position:relative;">
                                {if $fields.title.error}{$fields.title.error}{/if}{$fields.title.html}{if $action == 'view'}&nbsp;{/if}
                            </div>
                        </td>
                        </tr>
                        </table>
                    </td>
                    <td>
                        <table cellpadding="0" cellspacing="0" border="0" class="{if $action == 'view'}view{else}edit{/if}">
                        <tr>
                        <td class="label">{$fields.edited_on.label}{if $fields.edited_on.required}*{/if}</td>
                        <td class="data {$fields.edited_on.style}" id="_{$fields.edited_on.element}__data">
                            <div style="position:relative;">
                                {if $fields.edited_on.error}{$fields.edited_on.error}{/if}{$fields.edited_on.html}{if $action == 'view'}&nbsp;{/if}
                            </div>
                        </td>
                        </tr>
                        </table>
                    </td>
                    <td>
                        <table cellpadding="0" cellspacing="0" border="0" class="{if $action == 'view'}view{else}edit{/if}">
                        <tr>
                        <td class="label">{$fields.permission.label}{if $fields.permission.required}*{/if}</td>
                        <td class="data {$fields.permission.style}" id="_{$fields.permission.element}__data">
                            <div style="position:relative;">
                                {if $fields.permission.error}{$fields.permission.error}{/if}{$fields.permission.html}{if $action == 'view'}&nbsp;{/if}
                            </div>
                        </td>
                        </tr>
                        </table>
                    </td>
                </tr>
                <tr>
                    <td colspan="3">
                        <table cellpadding="0" cellspacing="0" border="0" class="{if $action == 'view'}view{else}edit{/if}">
                        <tr>
                        <td class="data long_data {$longfields.note.style}" id="_{$longfields.note.element}__data">
                            <div style="position:relative;">
                                {if $longfields.note.error}{$longfields.note.error}{/if}{$longfields.note.html}{if $action == 'view'}&nbsp;{/if}
                            </div>
                        </td>
                        </tr>
                        </table>
                    </td>
                </tr>
                <tr>
                    <td>
                        <table cellpadding="0" cellspacing="0" border="0" class="{if $action == 'view'}view{else}edit{/if}">
                        <tr>
                        <td class="label">{$fields.sticky.label}{if $fields.sticky.required}*{/if}</td>
                        <td class="data {$fields.sticky.style}" id="_{$fields.sticky.element}__data">
                            <div style="position:relative;">
                                {if $fields.sticky.error}{$fields.sticky.error}{/if}{$fields.sticky.html}{if $action == 'view'}&nbsp;{/if}
                            </div>
                        </td>
                        </tr>
                        </table>
                    </td>
                    <td colspan="2">
                        <table cellpadding="0" cellspacing="0" border="0" class="{if $action == 'view'}view{else}edit{/if}">
                        <tr>
                        <td class="label">{$fields.crypted.label}{if $fields.crypted.required}*{/if}</td>
                        <td class="data {$fields.crypted.style}" id="_{$fields.crypted.element}__data">
                            <div style="position:relative;">
                                {if $fields.crypted.error}{$fields.crypted.error}{/if}{$fields.crypted.html}{if $action == 'view'}&nbsp;{/if}
                            </div>
                        </td>
                        </tr>
                        </table>
                    </td>
                </tr>
                {if $action != 'view'}
                <tr>
                    <td colspan="3">
                        <table cellpadding="0" cellspacing="0" border="0" class="{if $action == 'view'}view{else}edit{/if}">
                        <tr>
                        <td class="label">{'Files'|t}</td>
                       <td class="data frozen">
                           <div id="multiple_attachments"><div id="filelist"></div></div>
                           {'Click here and press CTRL+V to paste your clipboard'|t}
                           <a href="javascript:void(0)" id="pickfiles" class="button">{'Select files'|t}</a>
                       </td>
                        </tr>
                        </table>
                    </td>
                </tr>
                {/if}
                </tbody>
            </table>

            <table class="Utils_RecordBrowser__View_entry" cellpadding="0" cellspacing="0" border="0">
                <tbody>
                <tr>
                    {assign var=x value=1}
                    {assign var=y value=1}
                    {foreach key=k item=f from=$fields name=fields}
                        {if $k!='title' && $k!='permission' && $k!='edited_on' && $k!='sticky' && $k!='crypted'}
                        {if $f.type!="multiselect"}
                            {if !isset($focus) && $f.type=="text"}
                                {assign var=focus value=$f.element}
                            {/if}

                            {if $y==1}
                                <td class="column" style="width: {$cols_percent}%;">
                                <table cellpadding="0" cellspacing="0" border="0" class="{if $action == 'view'}view{else}edit{/if}">
                            {/if}
                            <tr>
                                <td class="label">{$f.label}{if $f.required}*{/if}</td>
                                <td class="data {$f.style}" id="_{$f.element}__data">
                                    <div style="position:relative;">
                                        {if $f.error}{$f.error}{/if}{$f.html}{if $action == 'view'}&nbsp;{/if}
                                    </div>
                                </td>
                            </tr>
                            {if $y==$rows or ($y==$rows-1 and $x>$no_empty)}
                                {if $x>$no_empty}
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
                        {/if}
                        {/if}
                    {/foreach}
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
                    <td colspan="{$cols}">
                        <table cellpadding="0" cellspacing="0" border="0" class="longfields {if $action == 'view'}view{else}edit{/if}" style="border-top: none;">
                            {foreach key=k item=f from=$longfields name=fields}
                                {if $k!='note'}
                                <tr>
                                    <td class="data long_data {if $f.type == 'currency'}currency{/if}" id="_{$f.element}__data">
                                        <div style="position:relative;">
                                            {if $f.error}{$f.error}{/if}{$f.html}{if $action == 'view'}&nbsp;{/if}
                                        </div>
                                    </td>
                                    <td class="data long_label" style="vertical-align: top;"></td>
                                </tr>
                                {/if}
                            {/foreach}
                        </table>
                    </td>
                </tr>
                </tbody>
            </table>

            {if $main_page}
                {php}
                    if (isset($this->_tpl_vars['focus'])) eval_js('focus_by_id(\''.$this->_tpl_vars['focus'].'\');');
                {/php}
            {/if}

        </div>

    </div>
</div>