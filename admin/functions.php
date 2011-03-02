<?php
//*********** Header **********************
function pageheader(){
?>

            <!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1" />
<title>Epesi Admin Tools</title>
<link href="./images/admintools.css" rel="stylesheet" type="text/css" />

</head>

<body>
<table id="banner" border="0" cellpadding="0" cellspacing="0">
            <tr>
                <td class="image">&nbsp;</td>
                <td class="header">&nbsp;&nbsp;Administrator's Tools&nbsp;</td>
            </tr>
</table>
<BR />
<?php
}

function starttable(){
?>
	<center>
        <table id="main-1" border="0" cellpadding="0" cellspacing="0">
            <tr>
              <td>
              <div class="content">

<?php
}

function closetable(){
?>
</div></td>
            </tr>
        </table>
<?php
}

function pagefooter(){
//*********** Footer **********************
		
        print('<BR><center><div class="header"><a href="index.php">Main Menu</a></div>');

?>
		<HR>
        <span class="footer">Copyright &copy; 2010 &bull; <a href="http://www.epesi.org/">epesi framework</a> &bull; Application developed by <a href="http://www.telaxus.com">Telaxus LLC</a></span>
        <br>
        <p><a href="http://www.epesi.org"><img src="./images/epesi-powered.png" border="0"></a></p>
        </center>

</body>

</html>
</body>
</html>
<!-- End of Footer -->
<?php
}
// Print 2 columns
function printTD($left='&nbsp;',$right='&nbsp;',$color='green'){
	if ($right=="NO"){
		$color='red';
	}
	print('<div class="left">'.$left.'</div><div class="right"><strong class='.$color.'>'.$right.'</strong></div><br>');
}
?>