{* AdminLTE/Bootstrap replacement for the classic theme's shadow/layer popup
   chrome (theme/default.tpl). {$calendar} is the placeholder table built in
   PopupCalendarCommon_0.php::create_href() - its #datepicker_..._header/_view
   cells get overwritten by theme_adminlte/main2.js's show()/show_month() etc.
   as soon as the popup opens. *}
<div class="card utils-popupcalendar-card">
	<div class="card-body">
{$calendar}
	</div>
</div>
