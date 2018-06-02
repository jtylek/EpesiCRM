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
<div class="card ">
    <div class="card-header clearfix">
        <div class="pull-left">
            <img alt="&nbsp;" class="icon" src="{$icon}" width="32" height="32" border="0"> <span class="form-inline">{$caption}</span>
        </div>
        <div class="pull-right">
            {$required_note}
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
    <div class="card-body">

    {if isset($click2fill)}
        {$click2fill}
    {/if}

{/if}

<div class="container-fluid" style="padding: 9px; width: 100%;">
    <div class="css3_content_shadow">
        {* first line *}
        <div class="Utils_RecordBrowser__container container-fluid" style="padding-top: 20px">
            <div class="row-fluid" style="padding: 5px 10px">
                <div>
                    {$fields.title.full_field}
                </div>
                <div>
                    {$fields.edited_on.full_field}
                </div>
            </div>
        </div>
        {* note box *}
        <div class="row">
            <td class="data long_data {$longfields.note.style}" id="_{$longfields.note.element}__data">
                {if $longfields.note.error}{$longfields.note.error}{/if}

                {if $longfields.note.help}
                    <div class="help"><img src="{$longfields.note.help.icon}" alt="help" {$longfields.note.help.text}></div>
                {/if}
                <div class="container-fluid">
                    {$longfields.note.html}
                </div>
            </td>
        </div>
        {* options *}
        <div class="row" style="padding: 20px 40px">
            <div class="container">
                {$fields.permission.full_field}
            </div>
            <div class="container">
                {$fields.sticky.full_field}
            </div>
            <div class="container">
                {$fields.crypted.full_field}
            </div>
            <div class="container" style="text-align: center">
                {if $action != 'view'}
                    <br>
                    <div id="multiple_attachments"><div id="filelist"></div></div>
                    {'Click here and press CTRL+V to paste your clipboard'|t}
                    <button type="button" class="btn" href="javascript:void(0)" id="pickfiles">{'Select files'|t}</button>
                {/if}
            </div>
        </div>

                    {*{$fields|@var_dump}*}

                    {*{foreach key=k item=f from=$fields name=fields}*}
                        {*{if $k!='title' && $k!='permission' && $k!='edited_on' && $k!='sticky' && $k!='crypted'}*}
                            {*{if $f.type!="multiselect"}*}
                                {*{if !isset($focus) && $f.type=="text"}*}
                                    {*{assign var=focus value=$f.element}*}
                                    {*{$focus|@var_dump}*}
                                {*{/if}*}
                                {*{$f.full_field}*}
                            {*{/if}*}
                        {*{/if}*}
                    {*{/foreach}*}

                    {*{if !empty($multiselects)}*}
                        {*{foreach key=k item=f from=$multiselects name=fields}*}
                            {*{$f.full_field}*}
                        {*{/foreach}*}
                    {*{/if}*}



                    {*{foreach key=k item=f from=$longfields name=fields}*}
                       {*{if $k!='note'}*}
                           {*{$f.full_field}*}
                       {*{/if}*}
                    {*{/foreach}*}


            {if $main_page}
                {php}
                    if (isset($this->_tpl_vars['focus'])) eval_js('focus_by_id(\''.$this->_tpl_vars['focus'].'\');');
                {/php}
            {/if}

        </div>

    </div>
</div>
        {if $main_page}
    </div>
</div>
{/if}