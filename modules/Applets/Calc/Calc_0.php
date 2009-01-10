<?php
/**
 * @author msteczkiewicz@telaxus.com
 * @copyright 2008 Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-applets
 * @subpackage calc
 */

defined("_VALID_ACCESS") || die('Direct access forbidden');

class Applets_Calc extends Module {

	public function body() {

	}

	public function applet() {
        Base_ThemeCommon::load_css($this->get_type());
        print ('<div id="calc">');
 		print ('
            <center>
            <form name="Calc">
            <table border="0">
                <tr>
                    <td>
                        <input class="text" type="text" name="Input" Size="16">
                        <br>
                    </td>
                </tr>
                <tr>
                    <td>
                        <input type="button" class="key" name="one"   value="  1  " OnClick="Calc.Input.value += \'1\'">
                        <input type="button" class="key" name="two"   value="  2  " OnCLick="Calc.Input.value += \'2\'">
                        <input type="button" class="key" name="three" value="  3  " OnClick="Calc.Input.value += \'3\'">
                        <input type="button" class="key" name="plus"  value="  +  " OnClick="Calc.Input.value += \' + \'"><br>
                        <input type="button" class="key" name="four"  value="  4  " OnClick="Calc.Input.value += \'4\'">
                        <input type="button" class="key" name="five"  value="  5  " OnCLick="Calc.Input.value += \'5\'">
                        <input type="button" class="key" name="six"   value="  6  " OnClick="Calc.Input.value += \'6\'">
                        <input type="button" class="key" name="minus" value="  -  " OnClick="Calc.Input.value += \' - \'"><br>
                        <input type="button" class="key" name="seven" value="  7  " OnClick="Calc.Input.value += \'7\'">
                        <input type="button" class="key" name="eight" value="  8  " OnCLick="Calc.Input.value += \'8\'">
                        <input type="button" class="key" name="nine"  value="  9  " OnClick="Calc.Input.value += \'9\'">
                        <input type="button" class="key" name="times" value="  x  " OnClick="Calc.Input.value += \' * \'"><br>
                        <input type="button" class="key" name="clear" value="  c  " OnClick="Calc.Input.value = \'\'">
                        <input type="button" class="key" name="zero"  value="  0  " OnClick="Calc.Input.value += \'0\'">
                        <input type="button" class="key" name="DoIt"  value="  =  " OnClick="Calc.Input.value = eval(Calc.Input.value)">
                        <input type="button" class="key" name="div"   value="  /  " OnClick="Calc.Input.value += \' / \'">
                    </td>
                </tr>
            </table>
            </form>
            </center>
        ');
		print ('</div>');
	}

}

?>
