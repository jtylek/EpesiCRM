<div id="banner" style="display: flex;">
    <div class="image">&nbsp;</div>
    <div class="back" style="flex: 1 1 auto;">&nbsp;</div>
</div>

<br>

<center>
<div id="main">
    <!-- -->{$wizard}<!-- -->
</div>
</center>

<br>

<center>
<span class="footer">Copyright &copy;  2006-{php}echo date("Y"){/php} by Janusz Tylek</a></span>
<br>
<p><a href="http://www.epesi.org"><img src="images/epesi-powered.png" border="0"></a></p>
</center>
{php}
eval_js_once('document.body.id=\'FirstRun\'');
{/php}
