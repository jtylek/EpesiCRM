<?php
/**
 * @author Kuba Slawinski <kslawinski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 0.9
 * @package apps-gallery
 * @licence SPL
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Apps_Gallery extends Module {
	private $user;
	private $user_name;
	private $root;
	private $lang;
	
	private function init() {
		$this->lang = $this->pack_module('Base/Lang');
		$this->root = $this->get_data_dir();
		//print Base_UserCommon::get_my_user_id()." - id<br>";
		//print Base_AclCommon::i_am_user()." - am i user<br>";
		//print Base_UserCommon::get_user_login(Base_UserCommon::get_my_user_id())." - login<br>";
		if(Base_AclCommon::i_am_user()) {
			$this->user = Base_UserCommon::get_my_user_id();
			$this->user_name = Base_UserCommon::get_user_login(Base_UserCommon::get_my_user_id());
			if(!is_dir( $this->root.$this->user )) {
				mkdir( $this->root.$this->user );
			}
		} else {
			$this->user = -1;
			$this->user_name = $this->user;
		}
	}
	
	function getDirs($p_dirpath, $pattern = '0') { 
		$r_ret = array(); 
		if ( is_dir($p_dirpath) ) {
			if ($handle = opendir($p_dirpath)) { 
				while(false !== ($file = readdir($handle))) { 
					if($file != "." && $file != "..") { 
						if ( is_dir($p_dirpath."/".$file) ){
							if($pattern != '0') {
								if(preg_match($pattern, $file)) 
									array_push($r_ret, $file); 
							} else 
								array_push($r_ret, $file); 
						} 
					} 
				} 
				closedir($handle);
			} else 	{
				return "Unable to open directory: ./" . $p_dirpath; 
			} 
			return $r_ret; 
		}
	}
	
	public function delete($path) {
		if( !file_exists($path) ) return false;
	
		if(is_file($path) || is_link($path)) 
			return unlink($path);

		$files = scandir($path);
		foreach($files as $filename) {
			if($filename == '.' || $filename == '..') 
				continue;
			$file = str_replace('//','/',$path.'/'.$filename);
			$this->delete($file);
		}
		if( !rmdir($path) ) 
			return false;
		return true;
	}//end function unlink
	
	function getDirsRecursive($p_dirpath, $pattern = '0') { 
		$r_ret = array("/"=>"/");
		$stack = array();
		array_push($stack, "");
		while(count($stack) > 0) {
			//print_r($stack); print "<br>";
			$curr = array_pop($stack);
			//print "curr: ".$p_dirpath.$curr."<br>";
			if( $handle = opendir($p_dirpath."/".$curr) ) { 
				while(false !== ($file = readdir($handle))) { 
					if($file != "." && $file != "..") { 
						if ( is_dir($p_dirpath."/".$curr."/".$file) ){
							if($pattern != '0') {
								if(preg_match($pattern, $file)) {
									//array_push($r_ret, $curr."/".$file."/"); 
									$r_ret[$curr."/".$file."/"] = $curr."/".$file."/";
									array_push($stack, $curr."/".$file); 
								}
							} else  {
								//array_push($r_ret, $curr."/".$file."/"); 
								$r_ret[$curr."/".$file."/"] = $curr."/".$file."/";
								array_push($stack, $curr."/".$file); 
							}
						} 
					} 
				} 
				closedir($handle);
			}
		}
		return $r_ret;
	}
	
	public function submit_mk_folder($data) {
		print "Created folder: ".$data['target'] . $data['new'];
		mkdir($this->root.$this->user.$data['target'] . $data['new']);
		unset($data);
		return true;
	}

	public function mk_folder( $last_submited = 0 ) {
		$dirs = $this->getDirsRecursive($this->root.$this->user, "/^[^\.].*$/");
		ksort($dirs);
		$form = & $this->init_module('Libs/QuickForm');
		$lang = $this->pack_module('Base/Lang');
		
		$form->addElement('header', 'mk_folder', $lang->t('Add Folder to Your Gallery'));
		
		$dir_listing = $this->getDirsRecursive($this->root.$this->user);
		$structure = array();
		$media = array();
		foreach( $dir_listing as $k => $v ) {
			$c = & $structure;
			$pt = explode("/", $v);
			$up = '';
			foreach($pt as $d) {
				if( $d != "" ) {
					$up .= '/'.$d;
					if($d != "" ) {
						if( !is_array($c[$d]) ) {
							$tmp = & $form->createElement('radio', 'target', $up, $d, $up.'/');
							$c[$d] = array(
								'name' => $tmp->toHtml(),
								'selected' => 0,
								'sub' => array()
							);
						}
						$c = & $c[$d]['sub'];
					}
				}
			}
			
		}
		
		$tree = & $this->init_module('Utils/Tree', $this->root.$this->user);
		$tmp_t = & $form->createElement('radio', 'target', '/', 'My Gallery', '/');
		$tmp_t->setChecked(1);
		$tree->set_structure( array(
			'My Gallery' => array(
				'name'=> $tmp_t->toHtml(),
				'selected'=>0,
				'sub'=>$structure
			))
		);
		$tree->sort();
		
		if($last_submited != 0)
			$tree->setClosed(false);
		
		$form->addElement('text', 'new', 'New Folder:');
		$form->addElement('submit', 'submit_button', $lang->t('Create',true));
		
		if($form->getSubmitValue('submited') && $last_submited == 0) {
			if($form->validate()) {
				if($form->process(array(&$this, 'submit_mk_folder'))) {
					$this->mk_folder(12);
				}
			} else {
				$renderer = & new HTML_QuickForm_Renderer_TCMSArraySmarty();
				$form->accept($renderer);
				$theme =  & $this->pack_module('Base/Theme');
				$theme->assign('type', 'mk_folder');
				$theme->assign('form_name', $form->getAttribute('name'));
				$theme->assign('form_data', $renderer->toArray());
				$theme->assign('tree', $tree->toHtml());
				$theme->display();
			}
		} else {	
			$renderer = & new HTML_QuickForm_Renderer_TCMSArraySmarty();
			$form->accept($renderer);
			$theme =  & $this->pack_module('Base/Theme');
			$theme->assign('type', 'mk_folder');
			$theme->assign('form_name', $form->getAttribute('name'));
			$theme->assign('form_data', $renderer->toArray());
			$theme->assign('tree', $tree->toHtml());
			$theme->display();
		}
	}
	
	////////////////////////////////////////////////////////////////////////
	
	public function submit_rm_folder($data) {
		print "Removed folder: ".$data['target'];
		$this->delete($this->root.$this->user.$data['target']);
		unset($data);
		return true;
	}
	
	public function rm_folder($last_submited = 0) {
		$dirs = $this->getDirsRecursive($this->root.$this->user, "/^[^\.].*$/");
		ksort($dirs);
		$form = & $this->init_module('Libs/QuickForm');
		$lang = $this->pack_module('Base/Lang');
		$form->addElement('header', 'rm_folder', $lang->t('Remove Folder from Your Gallery'));
		
		$dir_listing = $this->getDirsRecursive($this->root.$this->user);
		$structure = array();
		$media = array();
		foreach( $dir_listing as $k => $v ) {
			$c = & $structure;
			$pt = explode("/", $v);
			$up = '';
			foreach($pt as $d) {
				if( $d != "" ) {
					$up .= '/'.$d;
					if($d != "" ) {
						if( !is_array($c[$d]) ) {
							$tmp = & $form->createElement('radio', 'target', $up, $d, $up.'/');
							$c[$d] = array(
								'name' => $tmp->toHtml(),
								'selected' => 0,
								'sub' => array()
							);
						}
						$c = & $c[$d]['sub'];
					}
				}
			}
			
		}
		
		$tree = & $this->init_module('Utils/Tree', $this->root.$this->user);
		$tmp_t = & $form->createElement('radio', 'target', '', 'My Gallery', '/');
		$tmp_t->setChecked(1);
		$tree->set_structure( array(
			'My Gallery' => array(
				'name'=> $tmp_t->toHtml(),
				'selected'=>0,
				'sub'=>$structure
			))
		);
		$form->addRule('target', $lang->t('Select a folder'), 'required');
		$tree->sort();
		
		$form->addElement('submit', 'submit_button', $lang->t('Remove',true));
		
		if($form->getSubmitValue('submited') && $last_submited == 0) {
			if($form->validate()) {
				$this->set_module_variable('action', 'show');
				if($form->process(array(&$this, 'submit_rm_folder'))) {
					$this->rm_folder(12);
				}
			} else {
				$renderer = & new HTML_QuickForm_Renderer_TCMSArraySmarty();
				$form->accept($renderer);
				$theme =  & $this->pack_module('Base/Theme');
				$theme->assign('type', 'rm_folder');
				$theme->assign('form_name', $form->getAttribute('name'));
				$theme->assign('form_data', $renderer->toArray());
				$theme->assign('tree', $tree->toHtml());
				$theme->display();
			}
		} else {	
			$renderer = & new HTML_QuickForm_Renderer_TCMSArraySmarty();
			$form->accept($renderer);
			$theme =  & $this->pack_module('Base/Theme');
			$theme->assign('type', 'rm_folder');
			$theme->assign('form_name', $form->getAttribute('name'));
			$theme->assign('form_data', $renderer->toArray());
			$theme->assign('tree', $tree->toHtml());
			$theme->display();
		}
	}
	
	///////////////////////////////////////////////////////////////////////////
	public function submit_share_folders($data) {
		print "<span align=left>Sharing folders:<br>";
		DB::Execute('delete from gallery_shared_media where user_id = %s', array($this->user));
		unset($data['submited']);
		unset($data['submit_button']);
		//print Base_UserCommon::get_user_login(Base_UserCommon::get_my_user_id()) ." <br>";
		foreach( $data as $dir => $sel) {
			//print $this->user_name.": ". $dir ." <br>";
			print $dir . " <br>";
			DB::Execute('insert into gallery_shared_media values(%s, %s)', array($this->user, $dir));
		}
		print "</span>";
		return true;
	}
	public function share_folders($last_submited = 0) {
		$form = & $this->init_module('Libs/QuickForm');
		$lang = $this->pack_module('Base/Lang');
		
		$form->addElement('header', 'share', $lang->t('Select folders You want to share with others.'));
		
		$ret = DB::Execute('SELECT user_id, media FROM gallery_shared_media where user_id = %s', array($this->user));
		$shared = array();
		while($row = $ret->FetchRow() ) {
			$shared[$row['media']] = $row['media'];
		}
		$dir_listing = $this->getDirsRecursive($this->root.$this->user);
		$structure = array();
		$media = array();
		//print_r($shared);
		foreach( $dir_listing as $k => $v ) {
			$c = & $structure;
			$pt = explode("/", $v);
			$up = '';
			foreach($pt as $d) {
				if( $d != "" ) {
					$up .= '/'.$d;
					if($d != "" ) {
						if( !is_array($c[$d]) ) {
							$tmp = & $form->createElement('checkbox', $up, $up, $d);
							if(array_key_exists(str_replace(" ", "_", $up), $shared))
								$tmp->setChecked(1);
								//print $up." -- ".$shared[$up]."<br>";
							$c[$d] = array(
								'name' => $tmp->toHtml(),
								'selected' => 0,
								'sub' => array()
							);
						}
						$c = & $c[$d]['sub'];
					}
				}
			}
			
		}
		
		$tree = & $this->init_module('Utils/Tree', $this->root.$this->user);
		$tmp_t = & $form->createElement('checkbox', '/', 'My Gallery', 'My Gallery');
		if(array_key_exists('/', $shared))
			$tmp_t->setChecked(1);
		$tree->set_structure( array(
			'My Gallery' => array(
				'name'=> $tmp_t->toHtml(),
				'selected'=>0,
				'sub'=>$structure
			))
		);
		$tree->sort();
		
		
		$form->addElement('submit', 'submit_button', $lang->t('Share selected',true));
		if($form->getSubmitValue('submited') && $last_submited == 0) {
			if($form->validate()) {
				$this->set_module_variable('action', 'show');
				if($form->process(array(&$this, 'submit_share_folders'))) {
					$this->share_folders(13);
				}
			} else {
				$renderer = & new HTML_QuickForm_Renderer_TCMSArraySmarty();
				$form->accept($renderer);
				$theme =  & $this->pack_module('Base/Theme');
				$theme->assign('type', 'share');
				$theme->assign('form_name', $form->getAttribute('name'));
				$theme->assign('form_data', $renderer->toArray());
				$theme->assign('tree', $tree->toHtml());
				$theme->display();
			}
		} else {	
				$renderer = & new HTML_QuickForm_Renderer_TCMSArraySmarty();
				$form->accept($renderer);
				$theme =  & $this->pack_module('Base/Theme');
				$theme->assign('type', 'share');
				$theme->assign('form_name', $form->getAttribute('name'));
				$theme->assign('form_data', $renderer->toArray());
				$theme->assign('tree', $tree->toHtml());
				$theme->display();
		}
	}
	
	////////////////////////////////////////////////////////////////////////
	public function submit_all($data) {
		//print_r($data);
		//print "File uploaded succesfully!";
		//print $data['root'].$data['target'].$data['uploaded_file'];
		//print $data['root'].$data['target'].$data['uploaded_file'];
		print 'Successfully uploaded "' . $data['uploaded_file'] . '" to "' . $data['target'] . '".<br>';
		$image = & $this->init_module('Utils/Image');
		$image->load($data['root'].$data['target'].$data['uploaded_file']);
		$image->create_thumb(600);
		$image->display_thumb(120);
		unset($data);
		return true;
	}
	
	public function upload_image($last_submited = 0) {
		$dirs = $this->getDirsRecursive($this->root.$this->user, "/^[^\.].*$/");
		$this->lang = $this->pack_module('Base/Lang');

		if($_REQUEST['menu_click']) {
			$this->unset_module_variable('data');
			$this->unset_module_variable('create_leads');
		}
		
		if($this->isset_module_variable('data'))
			return $this->process_data();
		
		$form = & $this->init_module('Libs/QuickForm', array($this->lang->ht('Uploading file...'),'modules/Apps/Gallery/upload.php','upload_iframe',''),'file_chooser');
		$form->addElement('header', 'upload', 'Import an image to your gallery');
		
		$form->addElement('hidden', 'root', $this->root.$this->user);
		
		
		// TREE
		$dir_listing = $this->getDirsRecursive($this->root.$this->user);
		$structure = array();
		$media = array();
		foreach( $dir_listing as $k => $v ) {
			$c = & $structure;
			$pt = explode("/", $v);
			$up = '';
			foreach($pt as $d) {
				if( $d != "" ) {
					$up .= '/'.$d;
					if($d != "" ) {
						if( !is_array($c[$d]) ) {
							$tmp = & $form->createElement('radio', 'target', $up, $d, $up.'/');
							$c[$d] = array(
								'name' => $tmp->toHtml(),
								'selected' => 0,
								'sub' => array()
							);
						}
						$c = & $c[$d]['sub'];
							
					}
				}
			}
			
		}
		
		$tree = & $this->init_module('Utils/Tree');
		$tmp_t = & $form->createElement('radio', 'target', '', 'My Gallery', '/');
		$tmp_t->setChecked(1);
		$tree->set_structure( array(
			'My Gallery' => array(
				'name'=> $tmp_t->toHtml(),
				'selected'=>0,
				'sub'=>$structure
			))
		);
		$tree->sort();
		
		
		
		$form->addElement('html', $tree->toHtml());
		
		
		$form->addElement('static',null,null,'<iframe frameborder="0" id="upload_iframe", name="upload_iframe" src="" scrolling="No" height="0" width="0"></iframe>');
		$form->addElement('hidden','uploaded_file');
		$form->addElement('hidden','form_name', $form->getAttribute('name'));

		$s = $form->get_submit_form_js(false,$this->lang->t('Processing file...',true));
		$s = str_replace("saja.","parent.saja.",$s);
		$s = str_replace("serialize_form","parent.serialize_form",$s);

		$form->addElement('hidden','submit_js',$s);
		$form->addElement('file', 'xls', $this->lang->t('Specify file',true), array('id'=>'import_filename'));
		eval_js('focus_by_id(\'import_filename\');');
		$form->addElement('static',null,$this->lang->t('Upload status'),'<div id="upload_status"></div>');
		$form->addElement('submit', 'button', $this->lang->t('Upload',true), "onClick=\"document.getElementById('upload_status').innerHTML='uploading...'; submit(); disabled=true;\"");
		
		if($form->validate() && $last_submited == 0) {
			if($form->process(array($this,'submit_all'))) {
				$this->upload_image(13);
			}
		} else {
			
			$form->display();
		}
	}

	public function show() {
			$dir = $this->get_module_variable_or_unique_href_variable('dir', "");
			$user = $this->get_module_variable_or_unique_href_variable('user', $this->user);
			
			$uname = ($user == $this->user ? 'My Gallery' : Base_UserCommon::get_user_login($user)."'s Gallery") ;
			
			$dir_listing = $this->getDirs($this->root.$user."/".$dir, "/^[^\.].*$/");
			$parent_dir = explode("/", $dir);
			
			// PATH
			$path = & $this->init_module('Utils/Path');
			//$main_ch = array('<a class=gallery_path_child_link "'.$this->create_unique_href(array('dir'=>"", 'user'=>$this->user)).'" class=path_link  id=\'gallery_path_link_'.$this->_id.'_'.$this->_sub.'\')">My Gallery</a>');
			//$ret = DB::Execute('SELECT user_id, media FROM gallery_shared_media where not (user_id = %s) group by user_id', array($this->user));
			//while($row = $ret->FetchRow() ) {
			//	$main_ch[] ="<a class=gallery_path_child_link ".$this->create_unique_href(array('dir'=>"", 'user'=>$row['user_id'])).">".Base_UserCommon::get_user_login($row['user_id'])."'s Gallery</a>";
			//}
			//$path->set_title( '<a class=gallery_path_link "'.$this->create_unique_href(array('dir'=>"", 'user'=>$user)).'" class=path_link  id=\'gallery_path_link_'.$this->_id.'_'.$this->_sub.'\')">'.$uname.'</a>', $main_ch );
			$c = '';
			if($user == $this->user) {
				$path->set_title( '<a class=gallery_path_link "'.$this->create_unique_href(array('dir'=>"", 'user'=>$user)).'" class=path_link  id=\'gallery_path_link_'.$this->_id.'_'.$this->_sub.'\')">'.$uname.'</a>' );
			
				foreach($parent_dir as $k => $v) {
					if($v != "") {
						$children_t = $this->getDirs($this->root.$user."/".$c);
						if(count($children_t) > 1) {
							$children = array();
							foreach($children_t as $kk => $vv) {
								$children[] = "<a class=gallery_path_child_link ".$this->create_unique_href(array('dir'=>$c."/".$vv, 'parent_dir'=>$c, 'user'=>$this->user)).">".$vv."</a>";
							}
							$c .= "/".$v;
							$path->add_node('<a class=gallery_path_link "'.$this->create_unique_href(array('dir'=>$c, 'parent_dir'=>$dir, 'user'=>$this->user)).'" class=path_link  id=\'gallery_path_link_'.$this->_id.'_'.$this->_sub.'\')">'.$v.'</a>', $children);
						} else {
							$c .= "/".$v;
							$path->add_node('<a class=gallery_path_link "'.$this->create_unique_href(array('dir'=>$c, 'parent_dir'=>$dir, 'user'=>$this->user)).'" class=path_link  id=\'gallery_path_link_'.$this->_id.'_'.$this->_sub.'\')">'.$v.'</a>');
							
						}
					}
				}
			} else {
				$path->set_title( $uname );
			
				$ret = DB::Execute('SELECT user_id, media FROM gallery_shared_media where (user_id = %s)', array($user));
				$children = array();
				while($row = $ret->FetchRow() ) {
					$children[] = "<a class=gallery_path_child_link ".$this->create_unique_href(array('dir'=>$row['media'], 'parent_dir'=>'/', 'user'=>$user)).">".$row['media']."</a>";
				}
				$path->add_node('<a class=gallery_path_link "'.$this->create_unique_href(array('dir'=>$dir, 'parent_dir'=>'/', 'user'=>$user)).'" class=path_link  id=\'gallery_path_link_'.$this->_id.'_'.$this->_sub.'\')">'.$dir.'</a>', $children);
				
			}
			array_pop( $parent_dir );
			$parent_dir = join("/", $parent_dir);
			$dirs = array();
			if($user == $this->user) {
				foreach( $dir_listing as $v ) {
					$dirs[] = "<a " . $this->create_unique_href(array('dir'=>$dir."/".$v, 'parent_dir'=>$dir, 'user'=>$this->user)) . ">". $v . "</a>";
					
				}
			} else {
				$ret = DB::Execute('SELECT user_id, media FROM gallery_shared_media where (user_id = %s)', array($user));
				while($row = $ret->FetchRow() ) {
					$dirs[] = "<a ".$this->create_unique_href(array('dir'=>$row['media'], 'parent_dir'=>'/', 'user'=>$user)).">".$row['media']."</a>";
				}
			}
			
			// TREE
			$dir_listing = $this->getDirsRecursive($this->root.$this->user);
			$structure = array();
			foreach( $dir_listing as $k => $v ) {
				$c = & $structure;
				$pt = explode("/", $v);
				$up = '';
				foreach($pt as $d) {
					if( $d != "" ) {
						$up .= '/'.$d;
						if($d != "" ) {
							if( !is_array($c[$d]) ) {
								$c[$d] = array(
									'name' => '<a '.$this->create_unique_href(array('dir'=>$up, 'parent_dir'=>$dir, 'user'=>$this->user)).'>'.$d.'</a>',
									'selected' => 0,
									'sub' => array()
								);
								if($up == $dir) {
									$c[$d]['selected'] = 1;
								}
							}
							$c = & $c[$d]['sub'];
								
						}
					}
				}
			}
			
			$tree = & $this->init_module('Utils/Tree');
			$tmp = ($dir == '' ? 1 : 0);
			$tree->set_structure(array('My Gallery'=>array(
				'selected' => $tmp, 
				'name' => '<a '.$this->create_unique_href(array('dir'=>"", 'user'=>$this->user)).' >My Gallery</a>', 
				'sub' => $structure
			)));
			$tree->sort();
			
			$other = & $this->init_module('Utils/Tree');
			$structure = array();
			$ret = DB::Execute('SELECT user_id, media FROM gallery_shared_media where not (user_id = %s)', array($this->user));
			while($row = $ret->FetchRow() ) {
				$c = & $structure;
				$pt = explode("/", $row['media']);
				$up = '';
				if(!is_array($structure[$row['user_id']])) {
					$structure[$row['user_id']] = array();
					//$structure[$row['user_id']]['name'] = "<a ".$this->create_unique_href(array('dir'=>"", 'user'=>$row['user_id'])).">".Base_UserCommon::get_user_login($row['user_id'])."'s gallery</a>";
					$structure[$row['user_id']]['name'] = Base_UserCommon::get_user_login($row['user_id'])."'s gallery";
					$structure[$row['user_id']]['sub'] = array();
				}
				$structure[$row['user_id']]['sub'][] = array( 
					'sub'=>array(), 
					'name'=>
					'<a '.$this->create_unique_href(array('dir'=>$row['media'] , 'parent_dir'=>'/', 'user'=>$row['user_id'] )).'>'.$row['media'] .'</a>',
					);
			}
			$other->set_structure($structure);
			
			
			// IMAGES
			$images = & $this->init_module('Utils/Gallery', $this->root.$user."/".$dir);
		
			
		$theme = & $this->init_module('Base/Theme');
		$theme->assign('type', 'images');
		$theme->assign('path', $path->toHtml());
		$theme->assign('dirs', $dirs);
		if(Base_AclCommon::i_am_user() > 0)
			$theme->assign('tree', $tree->toHtml());
		if(count($structure) > 0)
			$theme->assign('other', $other->toHtml());
		else
			$theme->assign('other', '');
		
		$theme->assign('images', $images->toHtml($this->root.$user."/".$dir));
		
		$theme->display();
	}
	
	public function menu_main($action) {
		$tb = & $this->init_module('Utils/TabbedBrowser');
		$tb->set_tab($this->lang->t('View'), array($this, 'show'));
		if($this->user > 0) {
			$tb->set_tab($this->lang->t('Upload'), array($this, 'upload_image'));
			$tb->set_tab($this->lang->t('Manage Folders'), array($this, 'menu_manage'));
		}
		$this->display_module($tb);
		$tb->tag();
	}
	
	public function menu_manage($manage) {
		
		$tb = & $this->init_module('Utils/TabbedBrowser');
		$tb->set_tab($this->lang->t('Add folder'),array($this, 'mk_folder'));
		$tb->set_tab($this->lang->t('Remove folder'),array($this, 'rm_folder'));
		$tb->set_tab($this->lang->t('Share folders'),array($this, 'share_folders'));
		$this->display_module($tb);
		$tb->tag();
	}
	
	public function body( $arg ) {
		$this->init();
		$this->menu_main();
	}
}
?>
