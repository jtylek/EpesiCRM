{* #Base_StatusBar (root), #statusbar_text and #dismiss are all targeted
   directly by id from modules/Base/StatusBar/js/main.js - display toggling,
   a CSS-transition fade (statusbar_fade_out()), and Epesi.updateIndicator's
   own hook into this markup (see updateEpesiIndicatorFunction()) - kept
   exactly as in the default theme, only the box/colours are reskinned.

   Base_StatusBarCommon::message() builds a full
   "<div class='message TYPE'>TEXT</div>" string server-side, and js/main.js
   swaps it into #statusbar_text's innerHTML - but #statusbar_text's own
   class ("message loading") is never itself changed by JS, so that swap
   always nests the new message div INSIDE this permanently-"loading"-classed
   outer one. default.css's ":has(.message)" rule handles that nesting
   instead of the default theme's negative-margin trick. *}
<div id="{$statusbar_id}" class="Base_StatusBar epesi-statusbar">
	<div class="epesi-statusbar-content" id="statusbar_content">
		<div id="{$text_id}" class="message loading">
			Loading...
			<div class="epesi-statusbar-progress" role="status" aria-hidden="true">
				<div class="epesi-statusbar-progress-bar"></div>
			</div>
		</div>
		<div id="dismiss" class="epesi-statusbar-dismiss">{$close_text}</div>
	</div>
</div>
