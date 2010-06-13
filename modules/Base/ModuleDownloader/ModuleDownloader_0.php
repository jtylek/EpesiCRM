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
        if (!$x) trigger_error('There is no base box module instance',E_USER_ERROR);
        $x->push_main('Base_ModuleDownloader', $func, $params);
        return false;
    }

    public function pop_main() {
        $x = ModuleManager::get_instance('/Base_Box|0');
        if (!$x) trigger_error('There is no base box module instance',E_USER_ERROR);
        $x->pop_main();
    }


	public function admin() {
        $form = $this->init_module('Libs/QuickForm');
        $form->addElement('text', 'mid', 'Download Code');
        $form->addElement('submit', 'submit', 'Download');
        if ($form->validate()) {
            $this->navigate('processQuery', array($form->exportValue('mid')));
        }
        $form->display();
	}

    protected function processQuery($mid /* Module ID */) {
        if ($this->is_back()) {
            $this->pop_main();
            return;
        }
        Base_ActionBarCommon::add('back', 'Back', $this->create_back_href());

        /* cache last module list to decrease server requests */
        $modules_id = $this->get_module_variable('mid', null);
        $modules = $this->get_module_variable('modules', array());
        /* request modules list */
        if ( $modules_id != $mid ) {
            $modules = unserialize($this->request(array('action'=>'list', 'id'=>$mid)));
            $this->set_module_variable('modules', $modules);
            $this->set_module_variable('mid', $mid);
        }

        /* check code */
        if ($modules === false) {
            print($this->t('This code is not valid!'));
        } elseif ($modules === true) {
            print($this->t('This code is no longer valid! Please request appropriate person for valid code!').'<br/>');
        } else {
            print($this->t('Click "Download now!" to download listed modules.<br/>If you have those modules in "modules" directory, they will be <b>overwritten</b>!'));
            print("<ul>");
            foreach($modules as $m) {
                print('<li>'. $m .'</li>');
            }
            print("</ul>");

            $form = $this->init_module('Libs/QuickForm');

            /* get result of downloading - display button to download if no result */
            $download_info = $this->get_module_variable('download_info', '');
            if ($download_info) {
                echo $download_info . '<br/>';
                /* check if downloading succeded */
                if($this->get_module_variable('download_success', false)) {
                    /* refresh available modules */
                    if ( $this->get_module_variable('modules_refreshed', false) === false) {
                        Base_SetupCommon::refresh_available_modules();
                        $this->set_module_variable('modules_refreshed', true);
                    }

                    /* display installation process results */
                    $install_info = $this->get_module_variable('install_info', '');
                    echo $install_info. '<br/><br/>';

                    /* check every module if it's installed already
                     * count not installed modules and display for every install
                     * button and if count > 1 display "Install all"
                     */
                    $install_cnt = 0;
                    $modules_to_install = array();
                    $html = '';
                    foreach ($modules as $mo) {
                        /* obtain module name */
                        $m = rtrim($mo, DIRECTORY_SEPARATOR);
                        $m = str_replace(DIRECTORY_SEPARATOR, '_', $m);
                        $inst = ModuleManager::is_installed($m) === -1;
                        $html .= '<tr><td>'.$mo.'</td><td class="element_button" style="text-align: center">';
                        /* if installed print button to form */
                        if($inst) {
                            $el = $form->createElement('button', 'install', 'Install', $this->create_callback_href(array($this, 'install'), array(array($m))));
                            $html .= $el->toHtml();
                            $install_cnt ++;
                            $modules_to_install[] = $m;
                        }
                        else $html .= '<b>already installed</b>';
                        $html .= '</td></tr>';

                    }
                    /* add prepared list of modules with buttons */
                    $form->addElement('html', $html);

                    /* add "Install All" and "Restart" button */
                    if($install_cnt > 1) $form->addElement('button', 'install', 'Install All', $this->create_callback_href(array($this, 'install'), array($modules)));
                    $form->addElement('button', 'restart', 'Restart Epesi', 'onclick="document.location=\'\'"');
                }
            } else {
                $form->addElement('button', 'download', 'Download Now!', $this->create_callback_href(array($this, 'processDownload'), array($mid)));
            }

            $form->display();
            return false;
        }
    }

    protected function processDownload($mid /* Module ID */) {
        $success = true;
        $text = '';
        // make temp destination filename
        $destfile = $this->get_data_dir() . escapeshellcmd($mid);
        while(file_exists($destfile.'.zip')) $destfile .= '0';
        $destfile .= '.zip';
        // download file
        if( ($ret = $this->request(array('action'=>'file', 'id'=>$mid), $destfile)) === true ) {
            $text .= $this->t('File download succeded!').'<br/>';
        } else {
            $text .= $this->t('Error downloading file!').'<br/>';
            $success = false;
        }
        // request md5 sum
        if($success) {
            $md5 = unserialize($this->request(array('action'=>'md5', 'id'=>$mid)));
            if( $md5 == md5_file($destfile) ) {
                $text .= $this->t('File consistency  OK').'<br/>';
            } else {
                $text .= $this->t('File consistency  <b>Error</b>').'<br/>';
                $success = false;
            }
        }
        // extract file contents
        if($success) {
            if(class_exists('ZipArchive')) {
                $zip = new ZipArchive();
                if( filesize($destfile) == 0 || $zip->open($destfile) != true || $zip->extractTo('./modules') == false ) {
                    $text .= $this->t("Archive error!").'<br/>';
                    $success = false;
                } else {
                    $zip->close();
                }
            } else {
                $text .= $this->t("Please enable zip extension in server configuration!").'<br/>';
                $success = false;
            }
        }
        // remove file
        @unlink($destfile);
        $this->set_module_variable('download_success', $success);
        $this->set_module_variable('download_info', $text);
    }

    public function install($modules) {
        $text = '<b>Installation process:</b><br/>';

        foreach ($modules as $mo) {
            $m = rtrim($mo, DIRECTORY_SEPARATOR);
            $m = str_replace(DIRECTORY_SEPARATOR, '_', $m);
            $success = ModuleManager::install($m);
            $text .= $mo . ' - <b>' . ($success ? 'Success!' : 'Failure!') . '</b><br/>';
        }

        $processed = ModuleManager::get_processed_modules();
        if(is_array($processed['install']) && sizeof($processed['install'])) {
            $this->navigate('post_install', array($processed['install']));
        }

        $this->set_module_variable('install_info', $text);
    }

    function post_install($modules) {
        if (! $this->isset_module_variable('post_install')) $this->set_module_variable('post_install', $modules);

        $post_install = & $this->get_module_variable('post_install', array());

        foreach($post_install as $i=>$v) {
            ModuleManager::include_install($i);
            $f = array($i.'Install','post_install');
            $fs = array($i.'Install','post_install_process');
            if(!is_callable($f) || !is_callable($fs)) {
                unset($post_install[$i]);
                continue;
            }
            $ret = call_user_func($f);
            $form = $this->init_module('Libs/QuickForm',null,$i);
            $form->addElement('header',null,'Post installation of '.str_replace('_','/',$i));
            $form->add_array($ret);
            $form->addElement('submit',null,'OK');
            if($form->validate()) {
                $form->process($fs);
                unset($post_install[$i]);
            } else {
                $form->display();
                break;
            }
        }
        if( ! sizeof($post_install) ) {
            $this->pop_main();
            return;
        }
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
