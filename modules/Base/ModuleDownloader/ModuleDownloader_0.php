<?php
/**
 * 
 * @author Adam Bukowski <abukowski@telaxus.com>
 * @copyright Copyright &copy; 2010, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-base
 * @subpackage ModuleDownloader
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_ModuleDownloader extends Module {
    const server = "http://pm.epesicrm.com/";
	
	public function body() {
	}

    public function navigate($func, $params = array()) {
        $x = ModuleManager::get_instance('/Base_Box|0');
        if(!$x) trigger_error('There is no base box module instance',E_USER_ERROR);
        $x->push_main('Base_ModuleDownloader', $func, $params);
        return false;
    }

    public function pop_main() {
        $x = ModuleManager::get_instance('/Base_Box|0');
        if(!$x) trigger_error('There is no base box module instance',E_USER_ERROR);
        $x->pop_main();
    }


	public function admin() {
        $form = $this->init_module('Libs/QuickForm');
        $form->addElement('text', 'mid', 'Download Code');
        $form->addElement('submit', 'submit', 'Download');
        if($form->validate()) {
            $this->navigate('processQuery', array($form->exportValue('mid')));
        }
        $form->display();
	}

    protected function processQuery($mid /* Module ID */) {
        if($this->is_back()) {
            $this->pop_main();
        }
        Base_ActionBarCommon::add('back', 'Back', $this->create_back_href());

        /* request modules list */
        $modules = unserialize($this->request(array('action'=>'list', 'id'=>$mid)));

        if($modules === false) {
            print($this->t('This code is not valid!'));
        } elseif($modules === true) {
            print($this->t('This code is no longer valid! Please request appropriate person for valid code!').'<br/>');
        } else {
            print($this->t('Click "Download now!" to download listed modules.<br/>If you have those modules, in "modules" directory they will be <b>overwritten</b>!'));
            print("<ul>");
            foreach($modules as $m) {
                print('<li>'. $m .'</li>');
            }
            print("</ul>");
            $form = $this->init_module('Libs/QuickForm');
            if($form->validate()) {
                $this->processDownload($mid);
            } else $form->addElement('submit', 'submit', 'Download Now!');
            $form->display();
            return false;
        }
    }

    protected function processDownload($mid /* Module ID */) {
        // make temp destination filename
        $destfile = $this->get_data_dir() . escapeshellcmd($mid);
        while(file_exists($destfile.'.zip')) $destfile .= '0';
        $destfile .= '.zip';
        // download file
        if( ($ret = $this->request(array('action'=>'file', 'id'=>$mid), $destfile)) === true ) {
            print($this->t('File download succeded!').'<br/>');
        } else {
            print($this->t('Error downloading file!').'<br/>');
            return;
        }
        // request md5 sum
        $md5 = unserialize($this->request(array('action'=>'md5', 'id'=>$mid)));
        if( $md5 == md5_file($destfile) ) {
            print('File consistency  OK');
        } else {
            print('File consistency  <b>Error</b>');
            return;
        }
        // extract file contents
        if(class_exists('ZipArchive')) {
            $zip = new ZipArchive();
            if( filesize($destfile) == 0 || $zip->open($destfile) != true || $zip->extractTo('./modules') == false ) {
                print($this->t("Archive error!").'<br/>');
            } else {
                $zip->close();
            }
        } else {
            print($this->t("Please enable zip extension in server configuration!").'<br/>');
        }
        // remove file
        unlink($destfile);
    }

    // *********** Function download_remote_file **************
    protected function request($get = '', $filename = null) {
        $err_msg = '';
        if(is_array($get)) $get = http_build_query($get);
        if(strlen($get) && $get[0] != '?') $get = '?'.$get;
        $ch = curl_init(self::server . $get);

        curl_setopt($ch, CURLOPT_VERBOSE, 1);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 300);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $output = curl_exec($ch);
        $errno = curl_error($ch);
        $av_speed = curl_getinfo($ch,CURLINFO_SPEED_DOWNLOAD);
        $response_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);
        if($errno!='') {
            print($filename.' download returned cURL error: '.$errno);
        }

        if ($response_code == '404' || $response_code == '403') {
            return $response_code;
        }

        if($filename) return file_put_contents($filename,$output) !== false;
        else return $output;

        return true;
    }
// *********** End of Function download_remote_file **************
	
}
?>
