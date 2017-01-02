<?php

/**
 * Submodule to upload files
 *
 * @author Adam Bukowski <abukowski@telaxus.com>
 * @copyright Copyright &copy; 2016, Telaxus LLC
 * @version 1.0
 * @license MIT
 * @package epesi-utils
 * @subpackage file-uploader
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_FileUpload_Dropzone extends Module
{

    public function get_div($identifier = 'dropzone')
    {
        $content = "<div id=\"{$identifier}\" class=\"dropzone\"></div>";
        $dir = 'modules/Utils/FileUpload/';
        load_css($dir . 'lib/dropzone.css');
        load_js($dir . 'lib/dropzone.js');
        $query = http_build_query(array('cid' => CID, 'path' => $this->get_path()));
        $files = $this->get_uploaded_files();
        $files_js = '';
        if (isset($files['add'])) {
            foreach ($files['add'] as $file) {
                $js_file = json_encode(array('name' => $file['name'], 'size' => $file['size']));
                $thumbnail = strpos($file['type'], 'image/') === 0 ? 'dz.emit("thumbnail", mockFile, ' . json_encode(strval($file['file'])) . ');' : '';
                $files_js .= '(function(dz) {
                    var mockFile = ' . $js_file . ';
                    dz.emit("addedfile", mockFile);
                    ' . $thumbnail . '
                    dz.emit("complete", mockFile);
                })(dz);';
            }
        }
        eval_js('jq(".dz-hidden-input").remove(); if (!document.querySelector("#' . $identifier . '").dropzone) {
            var dz = new Dropzone("#' . $identifier . '", {
            url:"' . get_epesi_url() . '/' . $dir . 'dropzoneupload.php?' . $query . '",
            uploadMultiple:true,
            addRemoveLinks:true});
            dz.on("removedfile", function(file) {
                   jq.ajax({
                    type:\'POST\',
                    url: this.options.url,
                    data: {
                      delete:file.name,
                    }
                  });
             });' . $files_js . '
             }');

        return $content;
    }

    public function add_to_form($form, $identifier, $label)
    {
        $content = $this->get_div($identifier);
        $form->addElement('static', $identifier, $label, $content)->freeze();
    }

    public function get_uploaded_files()
    {
        return $this->get_module_variable('files');
    }

    public function clear_uploaded_files()
    {
        $this->unset_module_variable('files');
    }

    public static function remove_old_temp_files($maxFileAge = 3600)
    {
        $targetDir = DATA_DIR . '/Utils_FileUpload/';
        if (is_dir($targetDir) && ($dir = opendir($targetDir))) {
            while (($file = readdir($dir)) !== false) {
                if ($file == '.htaccess' || $file == 'index.html') continue;

                $tmpfilePath = $targetDir . DIRECTORY_SEPARATOR . $file;

                // Remove temp file if it is older than the max age and is not the current file
                if (filemtime($tmpfilePath) < time() - $maxFileAge) {
                    @unlink($tmpfilePath);
                }
            }

            closedir($dir);
        }
    }
}