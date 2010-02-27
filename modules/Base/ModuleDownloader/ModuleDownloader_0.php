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
        $mid = urlencode($mid);
        // make temp destination filename
        $destfile = $this->get_data_dir().$mid;
        while(file_exists($destfile.'.zip')) $destfile .= '0';
        $destfile .= '.zip';
        // download file
        $url = self::server . '?' . http_build_query(array('id'=>$mid));
        if( $this->download_remote_file($url, $destfile) ) {
            print($this->t('File download succeded!').'<br/>');
        } else {
            print($this->t('Error downloading file!').'<br/>');
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
    private function download_remote_file($fileurl, $filename, $checkmd5= NULL) {
        $err_msg = '';
        $ch = curl_init($fileurl);

        curl_setopt($ch, CURLOPT_VERBOSE, 1);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 50);
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

        if ($response_code == '404') {
            return false;
        }

        file_put_contents($filename,$output);

        return true;
// Optionally check integrity of downloaded file
        print ('<br /><strong>Downloaded file:</strong> <strong class="blue">'.$filename.'</strong><br />');
        if (!is_null($checkmd5)) {
            if(file_exists($filename)) {
                print ('<br />Checking integrity:');
                print ('<br />md5  should  be: <b>' . $checkmd5 . '</b>');
                print ('<br />md5 of the file: <b>' . md5_file($filename) . '</b>');
                if(strcmp($checkmd5, md5_file($filename))) {
                    echo '<br /><strong class="red">NO</strong><br />';
                }
                else {
                    echo '<br /><strong class="green">OK</strong><br />';
                }
                if ($checkmd5==md5_file($filename)) {
                    print ('<br />File <b>'.$filename.'</b> was downloaded successfully and integrity verified.');
                } else {
                    print ('<br /><b>'.$filename.'</b> was downloaded with errors.<br />');
                    die('<br /><b>Setup can\'t continue. Proceed with manual installation.</b>');
                }
            } else {
                die('<br />File '.$filename.' can not be downloaded. Proceed with manual installation.');
            }
        } // End of $checkmd5
        print('<br />Average download speed: <b> '.$av_speed.' </b>bytes per second<br />');
    }
// *********** End of Function download_remote_file **************
	
}
?>
