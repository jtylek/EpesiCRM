<?php
/**
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 0.9
 * @package epesi-base-extra
 * @subpackage ModuleManager
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_ModuleManager extends Module {
	const host = "localhost";
	const path = "tcms_repo";
	private $lang;
	
	public function body() {
	}

	public function admin() {
		if($this->is_back()) {
			$this->parent->reset();
			return;
		}
		$this->lang = & $this->init_module('Base/Lang');
		if(!$this->isset_module_variable('ok')) {
			$ret = self::unwritable_modules();
			if(!empty($ret)) {
				print('<div style="text-align:left;">'.$this->lang->t('Unwritable files/directories').':<br><ul>');
				foreach($ret as $r)
					print('<li>'.$r);
				print('</ul>'.$this->lang->t('Every file in modules directory should be accessable and writable by http server.').'</div>');
				return;
			}
			$this->set_module_variable('ok',1);
		}
		print('<a '.$this->create_callback_href(array($this,'update')).'>'.$this->lang->t('Update modules tree').'</a>');
		$new_modules = self::repo_only_modules();
		$local_modules = ModuleManager::list_modules();
		$modules = array_merge($new_modules,$local_modules);
		ksort($modules);
		
		//create default module form
		$form = & $this->init_module('Libs/QuickForm','Processing modules');

		//install module header
		$form->addElement('header', 'install_module_header', 'Module manager');

		$subgroups = array();
		foreach($modules as $entry=>$versions) {
				if(is_array($versions)) {
					$installed = ModuleManager::is_installed($entry);
					$versions[-1]='not installed';
					ksort($versions);
				}
				$tab = '';
				$path = explode('_',$entry);
				for($i=0;$i<count($path)-1;$i++){
					if ($subgroups[$i] == $path[$i]) {
						$tab .= '*&nbsp;&nbsp;';
						continue;
					}
					$subgroups[$i] = $path[$i];
					$form->addElement('static', 'group_header', '<div align=left>'.$tab.$path[$i].'</div>');
					$tab .= '*&nbsp;&nbsp;';
				}
				$subgroups[count($path)-1] = $path[count($path)-1];
				if(is_array($versions)) {
					$form->addElement('select', 'installed['.$entry.']', '<div align=left>'.$tab.$path[count($path)-1].'</div>', $versions);
					$form->setDefaults(array('installed['.$entry.']'=>$installed));
				} else {
					$form->addElement('checkbox', 'download['.$entry.']', '<div align=left>'.$tab.$path[count($path)-1].'</div>',$this->lang->t('Download'));
				}
		}
				
		//control buttons
		$ok_b = HTML_QuickForm::createElement('submit', 'submit_button', 'OK');
		$cancel_b = HTML_QuickForm::createElement('button', 'cancel_button', 'Cancel', $this->create_back_href());
		$form->addGroup(array($ok_b, $cancel_b));
		
		//validation or display
		if ($form->validate()) {
			if($form->process(array (
				& $this,
				'validate'
			))) {
				$this->parent->reset();
			}
		} else $form->display();
	}

	public function validate($data) {
		global $base;
		
		$default_module = false;
		$installed = array ();
		$download = array ();
		$install = array ();
		$uninstall = array();
		$anonymous_setup = false;
		$modified_modules_table = false;
		$return_code = true;
		
		foreach ($data as $k => $v)
			${ $k } = $v;

		foreach ($installed as $name => $new_version) {
			$old_version = ModuleManager::is_installed($name);
			if($old_version==$new_version) continue;
			if($old_version==-1 && $new_version>=0) {
				$install[$name]=$new_version;
				continue;
			}
			if($old_version>=0 && $new_version==-1) {
				$uninstall[$name]=1;
				continue;
			}
			if($old_version<$new_version) {
				if(!ModuleManager::upgrade($name, $new_version))
					return false;
				continue;
			}
			if($old_version>$new_version) {
				if(!ModuleManager::downgrade($name, $new_version))
					return false;
				continue;
			}
		}
		
		//install
		foreach($install as $i=>$v)
			if (!ModuleManager::install($i,$v))
				return false;
				
		
		//uninstall
		$modules_prio_rev = array();
		foreach (ModuleManager::$modules as $k => $v)
			$modules_prio_rev[] = $k; 
		$modules_prio_rev = array_reverse($modules_prio_rev);
		
		foreach ($modules_prio_rev as $k) 
			if(array_key_exists($k, $uninstall)) {
			  	if($k=='Setup') {
					if(count(ModuleManager::$modules)==1) {
						$ret = SetupInstall::uninstall();
						if ($ret) {
//							session_destroy();
							print('No modules installed. Go <a href="'.get_epesi_url().'">here</a> to install Setup module!');
							return false;
						} else
							print('Unable to remove Setup module!');
						return false;
					} else {
						print('You cannot delete setup if any other module is installed!');
						return false;
					}
				} else {
					if (!ModuleManager::uninstall($k)) {
						$return_code = false;
						break;
					}
			  	}
			}
		
		$ddd = array();
		foreach($download as $k=>$v)
			$ddd[]=$k;

//		print(self::http_get(array('user'=>$user,'pass'=>$pass,'action'=>'get','modules'=>$ddd)));
		eval('$downloaded_files='.self::http_get(array('user'=>$user,'pass'=>$pass,'action'=>'get','modules'=>$ddd)).';');
		foreach($downloaded_files as $name=>$c) {
			if(file_exists('modules/'.$name) && !is_writable('modules/'.$name)) {
				print(Base_LangCommon::ts('Base/ModuleManager','Unable to rewrite file: modules/'.$name));
			} else {
				$dirs = explode('/',$name);
				$curr = 'modules/';
				for($i=0; $i<sizeof($dirs)-1; $i++) {
					$curr .= $dirs[$i].'/';
					if(!is_dir($curr)) mkdir($curr);
				}
				
				$fd = fopen('modules/'.$name,'w');
				fwrite($fd, $c);
				fclose($fd);
			}
		}
		
		return $return_code;
	}
	
	private static function http_get($args) {
		$errno = "";
		$errstr = "";

		// user-agent name
		$ua = $_SERVER['SERVER_NAME'];

 		$filePointer = pfsockopen(self::host, 80, $errorNumber, $errorString);
       
		if (!$filePointer)
			return false;

        	$requestHeader = "GET http://".self::host.'/'.self::path."/?".http_build_query($args)."  HTTP/1.1\r\n";
		$requestHeader.= "Host: ".$_SERVER['HTTP_HOST']."\r\n";
		$requestHeader.= "User-Agent: ".$ua."\r\n";
		$requestHeader.= "Content-Type: application/x-www-form-urlencoded\r\n";
		$requestHeader.= "Connection: close\r\n\r\n";

		fwrite($filePointer, $requestHeader);

		$responseHeader = '';
		$responseContent = '';

		do {
			$responseHeader.= fread($filePointer, 1);
		} while (!preg_match('/\\r\\n\\r\\n$/', $responseHeader));

		if (!strstr($responseHeader, "Transfer-Encoding: chunked")) {
			while (!feof($filePointer))
				$responseContent.= fgets($filePointer, 128);
		} else {
			while ($chunk_length = hexdec(fgets($filePointer))) {
				$responseContentChunk = '';
				$read_length = 0;
               
				while ($read_length < $chunk_length) {
					$responseContentChunk .= fread($filePointer, $chunk_length - $read_length);
					$read_length = strlen($responseContentChunk);
				}

				$responseContent.= $responseContentChunk;
				fgets($filePointer);
               		}
		}

		return chop($responseContent);
	}

	public static function update($user,$pass) {
		eval('$files='.self::http_get(array('user'=>$user,'pass'=>$pass,'action'=>'list')).';');
		$dirs = dir_tree('modules');
		$files_to_download=array();
		foreach($dirs as $d) {
			$module = str_replace('/','_',substr($d,8,-1));
			$file = ModuleManager::get_module_file_name($module);

			if(!file_exists($d . $file . 'Install.php'))
				continue;
			
			$dir = trim(substr($d,8),'/');
			$tree = explode('/',$dir);
			$download = $files;
			foreach($tree as $x)
				$download = $download[$x];
			$files_to_download = array_merge($files_to_download,self::download_module($download, $dir.'/'));
		}
		eval('$downloaded_files='.self::http_get(array('user'=>$user,'pass'=>$pass,'action'=>'get','files'=>$files_to_download)).';');
		foreach($downloaded_files as $name=>$c) {
			if(file_exists('modules/'.$name) && !is_writable('modules/'.$name)) {
				print(Base_LangCommon::ts('Base/ModuleManager','Unable to rewrite file: modules/'.$name));
			} else {
				$fd = fopen('modules/'.$name,'w');
				fwrite($fd, $c);
				fclose($fd);
			}
		}
		Base_StatusBarCommon::message('Modules updated');
	}

	public static function repo_only_modules($user, $pass) {
		eval('$files='.self::http_get(array('user'=>$user,'pass'=>$pass,'action'=>'list')).';');
		$ret = self::repo_only_module_check($files,'');
		return $ret;
	}
	
	private static function repo_only_module_check($arr, $path) {
		$ret = array();
		foreach($arr as $name=>$a) {
			if(is_array($a)) {
				if(isset($a[ModuleManager::get_module_file_name(str_replace('/','_',$path.$name)).'Install.php']) && !is_dir('modules/'.$path.$name))
					$ret[str_replace('/','_',$path.$name)] = "download";
				$ret = array_merge($ret, self::repo_only_module_check($a,$path.$name.'/'));
			}
		}
		return $ret;
	}
	
	private static function download_module($array, $path) {
		$ret = array();
		foreach($array as $name=>$a) {
			if(is_array($a)) {
				if(isset($a[ModuleManager::get_module_file_name(str_replace('/','_',$path.$name)).'Install.php']))
					continue;
				if(file_exists('modules/'.$path.$name)) {
					if(!is_dir('modules/'.$path.$name))
						unlink('modules/'.$path.$name);
				} else
					mkdir('modules/'.$path.$name);
				$ret = array_merge($ret,self::download($a,$path.$name.'/'));
			} elseif(md5(@join("",@file('modules/'.$path.$name)))!=$a) {
				$ret[] = $path.$name;
			}
		}
		return $ret;
	}

	private static function unwritable_modules ( $path = "modules/") {
		$ret = array();
		if(!is_writable($path) || !is_readable($path))
			$ret[] = $path;
		if ( $handle = opendir ( $path ) ) {
			while ( false !== ( $file = readdir ( $handle ) ) && sizeof($ret)<=10) {
				if ( $file != '.' && $file != '..' ) {
					$file = $path . $file ;
					if (is_dir ( $file )) {
						$result = self::unwritable_modules ( $file . '/') ;
						$ret = array_merge ( $ret , $result ) ;
					} else {
						if(!is_writable($file) || !is_readable($file)) {
							$ret[] = $file;
							if(sizeof($ret)>10) {
								$ret[] = Base_LangCommon::ts('Base/ModuleManager','... more ...');
								break;
							}
						}
					}
				}
			}
			closedir ( $handle ) ;
		}
		return $ret ;
	}
}
?>
