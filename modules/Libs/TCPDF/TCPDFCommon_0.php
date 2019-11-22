<?php
/**
 * TCPDF class.
 *
 * This module uses TCPDF PHP class released under
 * GNU LESSER GENERAL PUBLIC LICENSE Version 2.1
 * Author: Nicola Asuni
 * Copyright (c) 2001-2008: Nicola Asuni
 *
 * @author Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 1.0
 * @license MIT
 * @package epesi-libs
 * @subpackage tcpdf
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

define('TCPDF_DIR', 'modules/Libs/TCPDF/vendor/tecnickcom/tcpdf/');
require_once 'modules/Libs/TCPDF/vendor/autoload.php';

class Libs_TCPDFCommon extends ModuleCommon {
    public static $default_font = 'dejavusanscondensed';//'Helvetica';

    public static function user_settings()
    {
        $font_select = array(
            '' => __('System Default'),
            'cid0jp' => __('Japanese'),
            'cid0kr' => __('Korean'),
            'cid0cs' => __('Chinese Simplified'),
            'cid0ct' => __('Chinese Traditional')
        );
        $path = TCPDF_DIR . 'fonts/';
        foreach ($font_select as $file => $font) {
            if ($file && !file_exists($path . $file . '.php')) {
                unset($font_select[$file]);
            }
        }
        return array(__('Printing settings') => array(
            array('name'    => 'page_format',
                  'label'   => __('Page format'),
                  'type'    => 'select',
                  'values'  => array('A4' => __('A4'), 'LETTER' => __('LETTER'), 'LEGAL' => __('LEGAL')),
                  'default' => 'LETTER'),
            array('name'    => 'font',
                  'label'   => __('Font'),
                  'type'    => 'select',
                  'values'  => $font_select,
                  'default' => '')
        ));
    }

    public static function get_pdf_font()
    {
        $font = Base_User_SettingsCommon::get('Libs/TCPDF', 'font');
        if ($font && file_exists(TCPDF_DIR . "fonts/$font.php")) {
            return $font;
        }
        return 'dejavusanscondensed';
    }

    public static function new_pdf($orientation='P',$unit='mm',$format=null) {
        ini_set('memory_limit', '512M');

        define('K_TCPDF_EXTERNAL_CONFIG', true);
        
        require_once(self::Instance()->get_module_dir() . 'tcpdf_config.php');

        self::$default_font = self::get_pdf_font();
        if ($format===null) $format = Base_User_SettingsCommon::get(Libs_TCPDF::module_name(),'page_format');

        $tcpdf = new TCPDF($orientation, $unit, $format, true);

        $tcpdf->SetCreator(PDF_CREATOR);
        $tcpdf->SetAuthor("Powered by epesi");
        $tcpdf->SetKeywords("PDF");

        // set header and footer fonts
        $tcpdf->setHeaderFont(Array(self::$default_font, '', PDF_FONT_SIZE_MAIN));
        $tcpdf->setFooterFont(Array(self::$default_font, '', PDF_FONT_SIZE_DATA));

        //set margins
        $tcpdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
        $tcpdf->SetHeaderMargin(PDF_MARGIN_HEADER);
        $tcpdf->SetFooterMargin(PDF_MARGIN_FOOTER);

        //set auto page breaks
        $tcpdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);

        //set image scale factor
        $tcpdf->setImageScale(PDF_IMAGE_SCALE_RATIO);

        return $tcpdf;
    }

    public static function prepare_header(& $tcpdf, $title='', $subject='', $printed_by=true, $logo_filename=null, $l = array()) {
        if ($title!==null) {
            if ($logo_filename===null) $logo_filename = Libs_TCPDFCommon::get_logo_filename();
            if ($logo_filename!==false && !file_exists($logo_filename)) {
                $logo_filename = Base_ThemeCommon::get_template_file(Libs_TCPDF::module_name(),'logo-small.png');
            }
            $margins = $tcpdf->getMargins();
            if($logo_filename) {
                $logo_size = getimagesize($logo_filename);
                $logo_height = $logo_size[1] * PDF_HEADER_LOGO_WIDTH / $logo_size[0];
            } else {
                $logo_height = 0;
            }
            $tcpdf->SetHeaderMargin(10);
            $tcpdf->SetTopMargin($logo_height + $margins['top']);
            $tcpdf->SetHeaderData($logo_filename, $logo_filename?PDF_HEADER_LOGO_WIDTH:0, $title, $subject);
        } else {
            $tcpdf->setPrintHeader(false);
        }

        //set some language-dependent strings
        $l['a_meta_charset'] = "UTF-8";
        $l['a_meta_dir'] = "ltr";
        $l['a_meta_language'] = "pl";

        $who = null;
        if (CRM_ContactsInstall::is_installed()) {
            $who = CRM_ContactsCommon::get_contact_by_user_id(Acl::get_user());
        }
        if ($who!==null) $who = $who['last_name'].' '.$who['first_name'];
        else $who= Base_UserCommon::get_user_login(Acl::get_user());
        $when = date('Y-m-d H:i:s');
        if (!isset($l['w_page'])) {
			$l['w_page'] = '';
			if ($printed_by) {
                $product_name = EPESI;
                $url = EPESI == 'EPESI' ? ' (http://epe.si)' : '';
                $l['w_page'] .= __('Printed with %s by %s, on %s, ',array($product_name . $url, $who, $when));
            }
			$l['w_page'] .= __('Page');
		}
        $tcpdf->setLanguageArray($l);

        self::SetFont($tcpdf, self::$default_font, '', 9);
    }

    public static function add_page(& $tcpdf) {
        $tcpdf->AddPage();
    }

    public static function writeHTML(& $tcpdf, $html, $autobreak=true) {
        $html = Libs_TCPDFCommon::stripHTML($html);
        if ($autobreak) {
            $pages = $tcpdf->getNumPages();
            $tmppdf = clone($tcpdf);
            $tcpdf->WriteHTML($html,false,0,false);
            if ($pages!=$tcpdf->getNumPages()) {
                $tcpdf = $tmppdf;
                $tcpdf->AddPage();
                $tcpdf->WriteHTML($html,false,0,false);
            }
        } else
            $tcpdf->WriteHTML($html,false,0,false);
    }

    public static function SetFont(& $tcpdf, $family, $style='', $size=0) {
        $tcpdf->SetFont($family?$family:self::$default_font, $style, $size);
    }

    public static function output(& $tcpdf) {
        return $tcpdf->Output('','S');
    }

    public static function stripHTML($html) {
        $html = str_replace(array("\n", "\t", "\r"), '', $html);
        $html = preg_replace('/\<\/?[aA][^\>]*\>/', '', $html);
        return $html;
    }

    public static function admin_caption(){
		return array('label'=>__('Printing options'), 'section'=>__('Server Configuration'));
    }

    public static function get_logo_filename(){
        $i = self::Instance();
        return $i->get_data_dir().'company_logo.png';
    }
}
?>
