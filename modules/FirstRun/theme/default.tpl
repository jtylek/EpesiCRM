<table id="banner" border="0" cellpadding="0" cellspacing="0">
    <tr>
        <td class="image">&nbsp;</td>
        <td class="back">&nbsp;</td>
    </tr>
</table>

<br>

<center>
<table id="main" border="0" cellpadding="0" cellspacing="0">
    <tr>
        <td>
            <!-- -->{$wizard}<!-- -->
        </td>
    </tr>
</table>
</center>

<br>

<center>
<span class="footer">Copyright &copy; {php}echo date("Y"){/php} &bull; <a href="http://www.telaxus.com">Telaxus LLC</a></span>
<br>
<p><a href="http://www.epesi.org"><img src="images/epesi-powered.png" border="0"></a></p>
</center>
{php}
eval_js_once('document.body.id=\'FirstRun\'');
{/php}
