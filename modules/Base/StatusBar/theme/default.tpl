{*<div id="{$statusbar_id}" class="Base_StatusBar">*}
{*<div class="layer">*}
{*<div class="shadow_15px" id="statusbar_content">*}
{*<div id="{$text_id}" class="message loading">*}
{*Loading...*}
{*</div>*}
{*<div id="dismiss">{$close_text}</div>*}
{*</div>*}
{*</div>*}
{*</div>*}

<div id="{$statusbar_id}" class="Base_StatusBar_background">
    <div class="Base_StatusBar">
        <p id="{$text_id}" class="lead">Loading...</p>
        <div class="spinner">
            <div class="bounce1"></div>
            <div class="bounce2"></div>
            <div class="bounce3"></div>
        </div>
        <div id="dismiss">{$close_text}</div>
    </div>
</div>