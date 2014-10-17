<?php

class Admin_UpdateManager extends SteppedAdminModule
{
    const info_url = 'https://ess.epe.si/update.json';

    public function menu_entry()
    {
        return "Update EPESI";
    }

    public function header()
    {
        return "EPESI update utility";
    }

    public function start_text()
    {
        $requirements = $this->requirements();
        if ($requirements) {
            $this->set_next_step(null);
            return $requirements;
        } else {
            $body = $this->list_update_files();
            $body .= 'Check for update will connect to ESS server to retrieve update info.';
            $this->set_next_step('check');
            $this->set_button_text('Check for update');
            return $body;
        }
    }

    public function action()
    {
        switch ($this->get_step()) {
            case 'check':
                return $this->action_check();
                break;
            case 'download':
                return $this->action_download();
                break;
        }
    }

    private function action_check()
    {
        $update_info = $this->get_update_info();
        if (!$update_info) {
            return false;
        }
        $_SESSION['update_info'] = $update_info;
        if ($this->file_exists($update_info)) {
            print "Current update package has been already downloaded!<br><br>";
            $this->go_to_update_button();
            return true;
        }
        $this->set_next_step('download');
        if (version_compare($update_info['version'], EPESI_VERSION, '>')) {
            print "Update to version: <strong>$update_info[version]</strong><br>";
            print "Download file: <strong>$update_info[file]</strong><br>";
            $this->set_button_text('Download');
            return true;
        } else {
            print "You're using the most recent version of EPESI. However you can download again if you wish to restore custom changes.<br>";
            print "Version: <strong>$update_info[version]</strong><br>";
            print "File: <strong>$update_info[file]</strong><br>";
            $this->set_button_text('Re-Download');
            return false;
        }
    }

    private function file_exists($update_info)
    {
        $file = self::ei_file($update_info);
        return file_exists($file) && sha1_file($file) == $update_info['checksum'];
    }

    private function action_download()
    {
        $update_info = &$_SESSION['update_info'];
        unset($_SESSION['update_info']);
        $this->set_next_step(null);
        $success = $this->download_and_check($update_info);
        if ($success) {
            print "File downloaded!<br><br>";
            $this->go_to_update_button();
        }
    }

    private function go_to_update_button()
    {
        $url = rtrim(get_epesi_url(), '/') . '/update.php';
        print "Navigate to <a href=\"$url\">update.php</a>";
    }

    public function success_text()
    {
        return '';
    }

    public function failure_text()
    {
        return '';
    }

    public function list_update_files()
    {
        $k = 'delete_file';

        $ret = '';
        $delete_file = isset($_GET[$k]) ? $_GET[$k] : null;
        $files = glob('epesi-*.ei.zip');
        if ($delete_file && in_array($delete_file, $files) && file_exists($delete_file)) {
            if (unlink($delete_file)) {
                unset($_GET[$k]);
                header('Location: ?' . http_build_query($_GET));
                die();
            } else {
                $ret .= "Can't remove file: $delete_file.<br/>";
            }
        }
        foreach ($files as $f) {
            $str = "$f - <a href=\"" . $this->href(array($k => $f)) . "\">delete</a>";
            $ret .= "$str<br>";
        }
        if ($ret) {
            $ret = "<h3>Update files:</h3><p>$ret</p><hr>";
        }
        return $ret;
    }

    private function href($args)
    {
        return '?' . http_build_query(array_merge($_GET, $args));
    }

    public function requirements()
    {
        $issues = array();
        if (!class_exists('ZipArchive')) {
            $issues[] = "Zip extension missing!";
        }
        if (!extension_loaded('openssl')) {
            $issues[] = "OpenSSL extension missing!";
        }
        if ($issues) {
            return implode('<br>', $issues);
        }
        return false;
    }

    public function get_update_info()
    {
        $info_raw = file_get_contents(self::info_url);
        if ($info_raw === false) {
            print "Can't download update info file.";
            return null;
        }
        $info = json_decode($info_raw, true);
        if ($this->verify_update_info($info)) {
            return $info;
        }
        return null;
    }

    private function verify_update_info($info, $print = true)
    {
        $ret = true;
        $issues = array();
        if (!isset($info['version'])) {
            $issues[] = "Missing 'version' in update info";
            $ret = false;
        }
        if (!isset($info['revision'])) {
            $issues[] = "Missing 'revision' in update info";
            $ret = false;
        }
        if (!isset($info['file'])) {
            $issues[] = "Missing 'file' in update info";
            $ret = false;
        }
        if (!isset($info['checksum'])) {
            $issues[] = "Missing 'checksum' in update info";
            $ret = false;
        }
        if (!isset($info['signature'])) {
            $issues[] = "Missing 'signature' in update info";
            $ret = false;
        }
        if ($print) {
            print implode('<br>', $issues);
        }
        return $ret;
    }

    public function download_and_check($update_info)
    {
        if (!$this->verify_update_info($update_info)) {
            return false;
        }
        $data = file_get_contents($update_info['file']);
        if (!$data) {
            print "File download error";
            return false;
        } else {
            if (sha1($data) != $update_info['checksum']) {
                print "Checksum mismatch";
                return false;
            }
        }
        $verify_status = openssl_verify($data, base64_decode($update_info['signature']), self::public_key(), OPENSSL_ALGO_SHA256);
        if ($verify_status === 1) {
            $filename = self::ei_file($update_info);
            file_put_contents($filename, $data);
            return true;
        } elseif ($verify_status === 0) {
            print "Signature verification failed!";
        } else {
            print "Signature check error";
        }
        return false;
    }

    private static function ei_file($update_info)
    {
        return "epesi-" . $update_info['version'] . "-" . $update_info['revision'] . ".ei.zip";
    }

    public static function public_key()
    {
        $file = dirname(__FILE__) . '/public_key.pem';
        $key = file_get_contents($file);
        return $key;
    }
}

?>