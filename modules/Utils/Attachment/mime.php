<?php
/**
 * Use this module if you want to add attachments to some page.
 *
 * @author     Paul Bukowski <pbukowski@telaxus.com>
 * @copyright  Copyright &copy; 2008, Telaxus LLC
 * @license    MIT
 * @version    1.0
 * @package    epesi-utils
 * @subpackage attachment
 */
function get_mime_type($file, $original, $buffer = null)
{
    $return = null;

    //new method, but not compiled in by default
    if (false && extension_loaded('fileinfo')) {
        $fff = new finfo(FILEINFO_MIME);
        if ($file) {
            $return = $fff->file($file);
        } elseif ($buffer) {
            $return = $fff->buffer($buffer);
        }
        unset($fff);
        if ($return) {
            return $return;
        }
    }

    $delete_file = false;
    if (!$file) {
        $file = tempnam(sys_get_temp_dir(), 'mime');
        if (file_put_contents($file, $buffer)) {
            $delete_file = true;
        } else {
            $file = null;
        }
    }

    if ($file) {

        // unix system
        $ret = 0;
        ob_start();
        @passthru("file -bi {$file}", $ret);
        $output = ob_get_clean();
        if ($ret == 0) {
            $return = trim($output);
        }

        // mime_content_type
        if (!$return) {
            if (function_exists('mime_content_type')) {
                $return = mime_content_type($file);
            }
        }
    }

    if ($delete_file) {
        @unlink($file);
    }
    if ($return) {
        return $return;
    }

    preg_match("/\.(.*?)$/", $original, $m);
    if (!isset($m[1])) {
        return "application/octet-stream";
    }
    switch (strtolower($m[1])) {
        // case "js": return "application/javascript";
        // case "json": return "application/json";
        case "jpg":
        case "jpeg":
        case "jpe":
            return "image/jpg";
        case "xlsx":
        case "xls":
            return "application/vnd.ms-excel";
        case "docx":
        case "txt":
            return "text/plain";
        case "doc":
            return "application/msword";
        case "pdf":
            return "application/pdf";
        case "png":
        case "gif":
        case "bmp":
            return "image/" . strtolower($m[1]);
        // case "css": return "text/css";
        // case "xml": return "application/xml";
        case "html":
        case "htm":
        case "php":
            return "text/html";
        default:
            return "application/octet-stream";
    }
}
