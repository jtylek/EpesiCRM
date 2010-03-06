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
    const server = "http://localhost/epesi/tools/PMServer.php";
	
	public function body() {
	}

	public function admin() {
        $form = $this->init_module('Libs/QuickForm');
        $form->addElement('text', 'mid', 'Download Code');
        $form->addElement('submit', 'submit', 'Download');
        if($form->validate()) {
            $this->process($form->exportValue('mid'));
        }
        $form->display();
	}

    private function process($mid /* Module ID */) {
        // make temp destination filename
        $destfile = $this->get_data_dir() . escapeshellcmd($mid);
        while(file_exists($destfile.'.zip')) $destfile .= '0';
        $destfile .= '.zip';
        // download file
        $url = self::server . '?' . http_build_query(array('action'=>'file', 'id'=>$mid));
        if( ($ret = $this->download_remote_file($url, $destfile)) === true ) {
            print($this->t('File download succeded!').'<br/>');
        } else {
            print($this->t('Error downloading file!').'<br/>');
            if( $ret == '403' ) {
                print($this->t('This code is no longer valid! Please request appropriate person for valid code!').'<br/>');
            }
            return;
        }
        // request md5 sum
        $url = self::server . '?' . http_build_query(array('action'=>'md5', 'id'=>$mid));
        $md5 = $this->download_remote_file($url);
        if( $md5 == md5_file($destfile) ) {
            print('MD5: '.$md5.'  OK');
        } else {
            print('MD5: '.$md5.'  Error');
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
    private function download_remote_file($fileurl, $filename = null) {
        $err_msg = '';
        $ch = curl_init($fileurl);

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

        if($filename) file_put_contents($filename,$output);
        else return $output;

        return true;
    }
// *********** End of Function download_remote_file **************
	
}
?>
