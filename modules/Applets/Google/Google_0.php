<?php
/**
 * @author msteczkiewicz@telaxus.com
 * @copyright 2008 Telaxus LLC
 * @license MIT
 * @version 1.2
 * @package epesi-applets
 * @subpackage google
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
                    <a href="http://www.google.com/search"><img src="'.Base_ThemeCommon::get_template_file('Applets/Google','Logo_40wht.gif').'" alt="Google" style="border: 0px; width: 128px; height: 53px;" /></a>
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
