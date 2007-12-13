<?php

/**
 *
 * @author msteczkiewicz@telaxus.com
 * @copyright msteczkiewicz@telaxus.com
 * @license SPL
 * @version 1.1
 * @package applets-google
 */

defined("_VALID_ACCESS") || die('Direct access forbidden');

class Applets_Google extends Module {

	public function body() {

	}

	public function applet() {
        Base_ThemeCommon::load_css($this -> get_type());
        print ('<div id="google">');
 		print ('
            <form method="get" action="http://www.google.com/custom" target="_blank">
                <fieldset style="border: 0px;">
                    <a href="http://www.google.com/search"><img src="http://www.google.com/logos/Logo_40wht.gif" alt="Google" style="border: 0px; width: 128px; height: 53px;" /></a>
                    <center>
                    <table>
                        <tr>
                            <td><input name="q" size="30" maxlength="255" value="" type="text" /></td>
                            <td><input class="button" name="sa" value="Search" type="submit" /></td>
                        </tr>
                    </table>
                    </center>
                </fieldset>
            </form>               
        ');
		print ('</div>');
	}

}

?>
