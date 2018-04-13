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
    public static $fileFields = [];

    public $maxFiles = null;
    public $acceptedFiles = null;

    public function get_div($identifier = '')
    {
        $this->check_clear();
        $identifier = 'dropzone_' . $identifier;
        $content = "<div id=\"{$identifier}\" class=\"dropzone\"></div>";
        $dir = 'modules/Utils/FileUpload/';
        load_css($dir . 'theme/dropzone.css');
        load_css(EPESI_LOCAL_DIR . '/vendor/enyo/dropzone/dist/min/basic.min.css');
        load_css(EPESI_LOCAL_DIR . '/vendor/enyo/dropzone/dist/min/dropzone.min.css');
        load_js(EPESI_LOCAL_DIR . '/vendor/enyo/dropzone/dist/min/dropzone.min.js');
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
        if (isset($files['existing'])) {
            foreach ($files['existing'] as $file) {
                if (isset($files['delete'][$file['file_id']])) continue;
                $js_file = json_encode(array('name' => $file['name'], 'size' => $file['size']));
                $thumbnail = isset($file['file']) && strpos($file['type'], 'image/') === 0 ? 'dz.createThumbnailFromUrl(mockFile, ' . json_encode(strval($file['file'])) . ');' : '';
                $files_js .= '(function(dz) {
                    var mockFile = ' . $js_file . ';
                    dz.emit("addedfile", mockFile);
                    ' . $thumbnail . '
                    dz.emit("complete", mockFile);
                })(dz);';
            }
        }
        $options = [
            'url' => $dir . 'dropzoneupload.php?' . $query,
            'uploadMultiple' => true,
            'addRemoveLinks' => true,
            'maxFiles' => $this->maxFiles,
        	'acceptedFiles' => $this->acceptedFiles,
            'dictDefaultMessage' => __('Drop files here or click to upload')
        ];
        eval_js('jq(".dz-hidden-input").remove(); if (document.querySelector("#' . $identifier . '") && !document.querySelector("#' . $identifier . '").dropzone) {
            var dz = new Dropzone("#' . $identifier . '", '.json_encode($options).');
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

    public function set_defaults($files)
    {
        $uploaded = $this->get_uploaded_files();
        foreach ($files as $file_id => $file) {
            $arr = [
                'name' => $file['filename'],
                'type' => $file['type'],
                'size' => $file['size'],
                'file_id' => $file_id
            ];
            if (isset($file['file'])) {
                $arr['file'] = $file['file'];
            }
            $uploaded['existing'][$file_id] = $arr;
        }
        $this->set_uploaded_files($uploaded);
    }

    public function set_max_files($maxFiles)
    {
        $this->maxFiles = $maxFiles;
    }
    
    public function set_accepted_files($acceptedFiles)
    {
    	$this->acceptedFiles = $acceptedFiles;
    }

    public function add_to_form(Libs_QuickForm $form, $identifier, $label)
    {
        $content = $this->get_div($identifier);
        $form->addElement('static', $identifier, $label, $content)->freeze();
        $form->setDefaults(array($identifier => $content));
        $this->register_file_fields($form, $identifier);
    }

    public function get_uploaded_files()
    {
        $var = $this->get_module_variable('files');

        if (!is_array($var)) {
            $var = [];
        }
        foreach (['add', 'delete', 'existing'] as $key) {
            if (!array_key_exists($key, $var)) {
                $var[$key] = [];
            }
        }
        return $var;
    }

    protected function set_uploaded_files($files)
    {
        $this->set_module_variable('files', $files);
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
    
    public function register_file_fields(Libs_QuickForm $form, $identifier)
    {
        self::$fileFields[$form->get_name()][$identifier] = $this;
    }

    public static function get_registered_file_fields(Libs_QuickForm $form)
    {
        $form_name = $form->get_name();
        if (isset(self::$fileFields[$form_name]) && is_array(self::$fileFields[$form_name])) {
            return self::$fileFields[$form_name];
        }
        return [];
    }

    protected $disable_check_clear = false;

    public function enable_persistent_fileupload()
    {
        $this->disable_check_clear = true;
    }

    protected function check_clear()
    {
        if ($this->disable_check_clear) {
            return;
        }
        $last_hist = $this->get_module_variable('hist', 0);
        $curr_hist = History::get_id();
        if ($curr_hist - $last_hist > 1) {
            $files = $this->get_uploaded_files();
            $files['add'] = [];
            $files['delete'] = [];
            $this->set_uploaded_files($files);
        }
        $this->set_module_variable('hist', $curr_hist);
    }
    
    public static function export_values($form, $clear = true) {
    	$ret = [];
    	foreach (self::get_registered_file_fields($form) as $file_field => $file_module) {
    		$files = [];
    		$uploaded_files = $file_module->get_uploaded_files();
    		foreach ($uploaded_files['existing'] as $file) {
    			if (isset($uploaded_files['delete'][$file['file_id']])) continue;
    			$files[] = $file['file_id'];
    		}
    		foreach ($uploaded_files['add'] as $file) {
    			$files[] = [
    					'filename' => $file['name'],
    					'file' => $file['file']
    			];
    		}
    		$ret[$file_field] = $files;
    		if ($clear) $file_module->clear_uploaded_files();
    	}
    	return $ret;
    }
}