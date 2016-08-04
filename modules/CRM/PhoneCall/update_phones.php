<?php
/**
 * CRM Phone Call Class
 *
 * @author     Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright  Copyright &copy; 2008, Telaxus LLC
 * @license    MIT
 * @version    1.0
 * @package    epesi-crm
 * @subpackage phonecall
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

$ret = '';
$values = $_POST['values'];
foreach ($values as $v) {
    if ($ret != '') {
        $ret = $v;
        break;
    }
    $ret = $v;
}

$res = array();
if ($ret !== '') {
    list ($r, $id) = CRM_ContactsCommon::decode_record_token($ret);
    if ($id) {
        if ($r == 'contact') {
            $contact = CRM_ContactsCommon::get_contact($id);
            $i = 1;
            foreach (array('mobile_phone' => __('Mobile Phone'), 'work_phone' => __('Work Phone'), 'home_phone' => __('Home Phone')) as $field => $label) {
                if (isset($contact[$field]) && $contact[$field]) {
                    $res[$i] = $label . ': ' . $contact[$field];
                }
                $i++;
            }
        } else {
            $company = CRM_ContactsCommon::get_company($id);
            if (isset($company['phone'])) {
                $res[4] = __('Phone') . ': ' . $company['phone'];
            }
        }
    }
}

print(json_encode($res));
