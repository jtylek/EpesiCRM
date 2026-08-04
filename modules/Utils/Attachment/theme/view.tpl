<div class="Utils_Attachment__table">
	<div class="icon"><img src="{$theme_dir}/Utils/Attachment/icon.png" width="32" height="32" border="0"></div>
	<div class="name">View note</div>
	<div class="required_fav_info">&nbsp;{if isset($fav_tooltip)}{$fav_tooltip}{/if}&nbsp;&nbsp;&nbsp;{if isset($info_tooltip)}{$info_tooltip}{/if}</div>
</div>


<div class="css3_content_shadow_view">
    {* Was a <table> (header row, a note row spanning 2 of 3 columns, then a
       3-column file row) - CSS Grid instead. The note row's unused third
       column gets an explicit empty cell: without it, Grid's own
       auto-placement would flow the next row's first cell into that gap
       instead of starting a new row, unlike a real <tr> boundary (see
       AI-shared/adminlte-theme.md). *}
    <div id="Utils_Attachment__view" role="table" style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 5px;">
    		<div role="row" style="display: contents;">
    			<div class="header" role="cell" style="grid-column: span 3;">{$header}</div>
        	</div>
            <div role="row" style="display: contents;">
                <!--<div class="notepad-left">&nbsp;</div>-->
                <div class="note" role="cell" style="grid-column: span 2;">{$note}</div>
                <div role="presentation"></div>
            </div>
			{if $file!=''}
			<div role="row" style="display: contents;">
				<div class="file file_icon" role="cell">
                    {$__link.file.open}
                        <img src="{$theme_dir}/Utils/Attachment/attach.png" alt="" align="left" border="0" width="32" height="32">
                    {$__link.file.close}
                </div>
				<div class="file file_name" role="cell">
                    {$__link.file.open}
                        <span>{$__link.file.text}</span>
                    {$__link.file.close}
                </div>
                <div class="file_desc" role="cell">File size: {$file_size}<br>Created by: {$upload_by}<br>Created on: {$upload_on}</div>
			</div>
            {else}
			<div role="row" style="display: contents;">
				<div class="file file_icon" role="cell">&nbsp;</div>
				<div class="file file_name" role="cell">&nbsp;</div>
                <div class="file_desc" role="cell">&nbsp;</div>
			</div>
			{/if}
    </div>
 </div>
