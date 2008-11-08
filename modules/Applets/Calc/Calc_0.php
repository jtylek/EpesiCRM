<?php

/**
 *
 * @author msteczkiewicz@telaxus.com
 * @copyright msteczkiewicz@telaxus.com
 * @license EPL
 * @version 0.9
 * @package applets-calc
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
                        <input type="button" class="button" name="one"   value="  1  " OnClick="Calc.Input.value += \'1\'">
                        <input type="button" class="button" name="two"   value="  2  " OnCLick="Calc.Input.value += \'2\'">
                        <input type="button" class="button" name="three" value="  3  " OnClick="Calc.Input.value += \'3\'">
                        <input type="button" class="button" name="plus"  value="  +  " OnClick="Calc.Input.value += \' + \'"><br>
                        <input type="button" class="button" name="four"  value="  4  " OnClick="Calc.Input.value += \'4\'">
                        <input type="button" class="button" name="five"  value="  5  " OnCLick="Calc.Input.value += \'5\'">
                        <input type="button" class="button" name="six"   value="  6  " OnClick="Calc.Input.value += \'6\'">
                        <input type="button" class="button" name="minus" value="  -  " OnClick="Calc.Input.value += \' - \'"><br>
                        <input type="button" class="button" name="seven" value="  7  " OnClick="Calc.Input.value += \'7\'">
                        <input type="button" class="button" name="eight" value="  8  " OnCLick="Calc.Input.value += \'8\'">
                        <input type="button" class="button" name="nine"  value="  9  " OnClick="Calc.Input.value += \'9\'">
                        <input type="button" class="button" name="times" value="  x  " OnClick="Calc.Input.value += \' * \'"><br>
                        <input type="button" class="button" name="clear" value="  c  " OnClick="Calc.Input.value = \'\'">
                        <input type="button" class="button" name="zero"  value="  0  " OnClick="Calc.Input.value += \'0\'">
                        <input type="button" class="button" name="DoIt"  value="  =  " OnClick="Calc.Input.value = eval(Calc.Input.value)">
                        <input type="button" class="button" name="div"   value="  /  " OnClick="Calc.Input.value += \' / \'">
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
