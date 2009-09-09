<?php
/**
 * @author Kuba Slawinski <kslawinski@telaxus.com> and Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-apps
 * @subpackage gallery
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Apps_Gallery extends Module {
	private $user;
	private $user_name;
	private $root;
	private $_id = null; // TODO: cleanup
	private $_sub = null; // TODO: cleanup
	
	public function construct() {
		$this->root = $this->get_data_dir();
		//print Acl::get_user()." - id<br>";
		//print Base_AclCommon::i_am_user()." - am i user<br>";
		//print Base_UserCommon::get_user_login(Acl::get_user())." - login<br>";
		if(Base_AclCommon::i_am_user()) {
			$this->user = Acl::get_user();
			$this->user_name = Base_UserCommon::get_user_login(Acl::get_user());
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
	
	private function create_structure_for_tree($root_user, $dir, &$form) {
		$dir_listing = $this->getDirsRecursive($root_user);
		
		$ret = DB::Execute('SELECT user_id, media FROM gallery_shared_media where user_id = %s', array($this->user));
		$shared = array();
		while($row = $ret->FetchRow() ) {
			$shared[$row['media']] = $row['media'];
		}
		
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
						if( !isset($c[$d]) || !is_array($c[$d]) ) {
							$title = $d;
							if(array_key_exists(str_replace(" ", "_", $up), $shared)) {
								$title .= ' (shared)';
							}
							$tmp = & $form->createElement('radio', 'target', $up, $title, $up.'/');
							$opened = 0;
							if($up == $dir) {
								$opened = 1;
								$tmp->setChecked(1);
							}
							$c[$d] = array(
								'name' => $tmp->toHtml(),
								'selected' => $opened,
								'visible' => $opened,
								'opened' => $opened,
								'sub' => array()
							);
						}
						$c = & $c[$d]['sub'];
					}
				}
			}
			
		}
		$title = 'My Gallery';
		if(array_key_exists(str_replace(" ", "_", $up), $shared)) {
			$title .= ' (shared)';
		}
		$tmp_t = & $form->createElement('radio', 'target', '/', $title, '/');
		$opened = 0;
		if($dir == '') {
			$tmp_t->setChecked(1);
			$opened = 1;
		}
		$structure = array(
			'My Gallery' => array(
				'name'=> $tmp_t->toHtml(),
				'selected' => $opened,
				'visible' => $opened,
				'opened' => $opened,
				'sub'=>$structure
			));
		return $structure;
	}
	
	public function submit_mk_folder($data) {
		//print "Created folder: ".$data['target'] . $data['new'];
		mkdir($this->root.$this->user.$data['target'] . $data['new']);
		$this->set_module_variable('dir', $data['target'] . $data['new']);
		unset($data);
		return true;
	}

	public function mk_folder() {
		$dir = $this->get_module_variable_or_unique_href_variable('dir', "");
		$user = $this->get_module_variable_or_unique_href_variable('user', $this->user);
		$dirs = $this->getDirsRecursive($this->root.$this->user, "/^[^\.].*$/");
		ksort($dirs);
		$form = & $this->init_module('Libs/QuickForm');
		
		$form->addElement('header', 'mk_folder', $this->t('Add Folder to Your Gallery'));
		
		$structure = $this->create_structure_for_tree($this->root.$this->user, $dir, $form);
		$tree = & $this->init_module('Utils/Tree');
		$tree->set_structure( 
			$structure
		);
		$tree->sort();
		
		
		$form->addElement('text', 'new', 'New Folder:', array('value'=>''));
		$form->addElement('submit', 'submit_button', $this->ht('Create'));
		$form->addRule('new', $this->t('Field required'),'required');
		
		if($form->getSubmitValue('submited') && $form->validate()) {
			if($form->process(array(&$this, 'submit_mk_folder')))
				location(array());
		} else {	
			$theme =  & $this->pack_module('Base/Theme');
			$theme->assign('type', 'mk_folder');
			$form->assign_theme('form', $theme);
			$theme->assign('tree', $this->get_html_of_module($tree));
			$theme->display();
		}
	}
	
	////////////////////////////////////////////////////////////////////////
	
	public function submit_rm_folder($data) {
		print "Removed folder: ".$data['target'];
		$this->delete($this->root.$this->user.$data['target']);
		//print '<br>'.$data['target'].'<br>';
		$tmp = explode('/', $data['target']);
		array_pop($tmp);
		array_pop($tmp);
		$tmp = join('/', $tmp);
		$this->set_module_variable('dir', $tmp);
		//print $tmp;
		unset($data);
		return true;
	}
	
	public function rm_folder() {
		$dir = $this->get_module_variable_or_unique_href_variable('dir', "");
		$user = $this->get_module_variable_or_unique_href_variable('user', $this->user);
		$dirs = $this->getDirsRecursive($this->root.$this->user, "/^[^\.].*$/");
		ksort($dirs);
		$form = & $this->init_module('Libs/QuickForm');
		$form->addElement('header', 'rm_folder', $this->t('Remove Folder from Your Gallery'));
		
		$structure = $this->create_structure_for_tree($this->root.$this->user, $dir, $form);
		$tree = & $this->init_module('Utils/Tree');
		$tree->set_structure( 
			$structure
		);
		$tree->sort();
		
		$form->addElement('submit', 'submit_button', $this->ht('Remove'));
		
		if($form->getSubmitValue('submited') && $form->validate()) {
			if($form->process(array(&$this, 'submit_rm_folder'))) {
				location(array());
			}
		} else {
			$theme =  & $this->pack_module('Base/Theme');
			$theme->assign('type', 'rm_folder');
			$form->assign_theme('form', $theme);
			$theme->assign('tree', $this->get_html_of_module($tree));
			$theme->display();
		}
	}
	
	///////////////////////////////////////////////////////////////////////////
	public function submit_share_folders($data) {
		//print "<span align=left>Sharing folders:<br>";
		DB::Execute('delete from gallery_shared_media where user_id = %s', array($this->user));
		unset($data['submited']);
		unset($data['submit_button']);
		//print Base_UserCommon::get_user_login(Acl::get_user()) ." <br>";
		foreach( $data as $dir => $sel) {
			//print $this->user_name.": ". $dir ." <br>";
			//print $dir . " <br>";
			if( is_dir($this->root.$this->user.'/'.$dir) )
				DB::Execute('insert into gallery_shared_media values(%s, %s)', array($this->user, $dir));
		}
		//print "</span>";
		return true;
	}
	public function share_folders() {
		$dir = $this->get_module_variable_or_unique_href_variable('dir', "");
		$user = $this->get_module_variable_or_unique_href_variable('user', $this->user);
		$form = & $this->init_module('Libs/QuickForm');
		
		$form->addElement('header', 'share', $this->t('Select folders You want to share with others.'));
		
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
						if( !isset($c[$d]) || !is_array($c[$d]) ) {
							$tmp = & $form->createElement('checkbox', $up, $up, $d);
							$opened = 0;
							if(array_key_exists(str_replace(" ", "_", $up), $shared)) {
								$tmp->setChecked(1);
								$opened = 1;
							}
								//print $up." -- ".$shared[$up]."<br>";
							$c[$d] = array(
								'name' => $tmp->toHtml(),
								'selected' => $opened,
								'visible' => $opened,
								'sub' => array()
							);
						}
						$c = & $c[$d]['sub'];
					}
				}
			}
			
		}
		
		$tree = & $this->init_module('Utils/Tree');
		$tmp_t = & $form->createElement('checkbox', '/', 'My Gallery', 'My Gallery');
		$opened = 0;
		if(array_key_exists('/', $shared)) {
			$tmp_t->setChecked(1);
			$opened = 1;
		}
		$tree->set_structure( array(
			'My Gallery' => array(
				'name'=> $tmp_t->toHtml(),
				'selected' => $opened,
				'visible' => $opened,
				'sub'=>$structure
			))
		);
		$tree->sort();
		
		
		$form->addElement('submit', 'submit_button', $this->ht('Share selected'));
		if($form->getSubmitValue('submited') && $form->validate()) {
			if($form->process(array(&$this, 'submit_share_folders'))) {
				location(array());
			}
		} else {
			$theme =  & $this->pack_module('Base/Theme');
			$theme->assign('type', 'share');
			$form->assign_theme('form', $theme);
			$theme->assign('tree', $this->get_html_of_module($tree));
			$theme->display();
		}
	}
	
	////////////////////////////////////////////////////////////////////////
	public function submit_upload($file, $ory, $data) {
		$ext = strrchr($ory,'.');
		if($ext==='' || !preg_match('/\.(jpg|jpeg|gif|png)$/i', $ext)) {
			$GLOBALS['base']->alert($this->t('Invalid extension'));
		} else {
			$dest = $this->root.$this->user.$_REQUEST['target'].$ory;
			copy($file, $dest);
			$this->set_module_variable('last_uploaded_img',$dest);
		}
		return true;
	}
	
	public function upload() {
		if($this->is_back()) return false;
		Base_ActionBarCommon::add('back',$this->ht('Back to Gallery'),$this->create_back_href());


		$last = $this->get_module_variable('last_uploaded_img');
		if($last) {
			print 'Last succesfully uploaded image<br>';
	
			Utils_ImageCommon::display_thumb($last,120);
		}

		$dirs = $this->getDirsRecursive($this->root.$this->user, "/^[^\.].*$/");
		$dir = $this->get_module_variable_or_unique_href_variable('dir', "");
		$user = $this->get_module_variable_or_unique_href_variable('user', $this->user);
		
		$form = & $this->init_module('Utils/FileUpload');
		
		if($this->isset_module_variable('data'))
			return $this->process_data();
		
		//$form = & $this->init_module('Libs/QuickForm', array($this->ht('Uploading file...'),'modules/Apps/Gallery/upload.php','upload_iframe',''),'file_chooser');
		$form->addElement('header', 'upload', $this->t('Import an image to your gallery'));
		
//		$form->addElement('hidden', 'root', $this->root.$this->user);
		
		
		// TREE
		$structure = $this->create_structure_for_tree($this->root.$this->user, $dir, $form);
		$tree = & $this->init_module('Utils/Tree');
		$tree->set_structure( 
			$structure
		);
		$tree->sort();
		$tree->set_inline_display();
		$form->addElement('static', null, $this->get_html_of_module($tree));
		
		$this->display_module($form, array( array($this,'submit_upload') ));
		
		
		return true;
	}

	
	public function manage() {
		if($this->is_back()) return false;
		Base_ActionBarCommon::add('back',$this->ht('Back to Gallery'),$this->create_back_href());
		

		$tb = & $this->init_module('Utils/TabbedBrowser');
		$tb->set_tab($this->t('Add folder'),array($this, 'mk_folder'));
		$tb->set_tab($this->t('Remove folder'),array($this, 'rm_folder'));
		$tb->set_tab($this->t('Share folders'),array($this, 'share_folders'));
		$tb->body();
		$tb->tag();
		return true;
	}
	
	public function body() {
		
		if( Base_AclCommon::i_am_user() ) {
			Base_ActionBarCommon::add('add',$this->ht('Upload'),$this->create_callback_href(array($this,'upload')));
			Base_ActionBarCommon::add('settings',$this->ht('Manage Folders'),$this->create_callback_href(array($this,'manage')));
		}
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
		$ret = DB::Execute('SELECT user_id, media FROM gallery_shared_media where user_id = %s', array($this->user));
		$shared = array();
		while($row = $ret->FetchRow() ) {
			$shared[$row['media']] = $row['media'];
		}
		
		foreach( $dir_listing as $k => $v ) {
			$c = & $structure;
			$pt = explode("/", $v);
			$up = '';
			foreach($pt as $d) {
				if( $d != "" ) {
					$up .= '/'.$d;
					if($d != "" ) {
						if( !isset($c[$d]) || !is_array($c[$d]) ) {
							$title = $d;
							if(array_key_exists(str_replace(" ", "_", $up), $shared)) {
								$title .= ' (shared)';
							}
							$c[$d] = array(
								'name' => '<a '.$this->create_unique_href(array('dir'=>$up, 'parent_dir'=>$dir, 'user'=>$this->user)).'>'.$title.'</a>',
								'selected' => 0,
								'sub' => array()
							);
							if($up == $dir) {
								$c[$d]['selected'] = 1;
								$c[$d]['visible'] = 1;
							}
						}
						$c = & $c[$d]['sub'];
							
					}
				}
			}
		}
		
		$tree = & $this->init_module('Utils/Tree');
		$tmp = ($dir == '' ? 1 : 0);
		$title = 'My Gallery';
		if(array_key_exists(str_replace(" ", "_", $up), $shared)) {
			$title .= ' (shared)';
		}
		$tree->set_structure(array('My Gallery'=>array(
			'selected' => $tmp, 
			'name' => '<a '.$this->create_unique_href(array('dir'=>"", 'user'=>$this->user)).' >'.$title.'</a>', 
			'sub' => $structure
		)));
		$tree->sort();
		//$tree->open_all();
		
		$other = & $this->init_module('Utils/Tree');
		$structure = array();
		$ret = DB::Execute('SELECT user_id, media FROM gallery_shared_media where not (user_id = %s)', array($this->user));
		while($row = $ret->FetchRow() ) {
			$c = & $structure;
			$pt = explode("/", $row['media']);
			$up = '';
			if(!isset($structure[$row['user_id']]) || !is_array($structure[$row['user_id']])) {
				$structure[$row['user_id']] = array();
				//$structure[$row['user_id']]['name'] = "<a ".$this->create_unique_href(array('dir'=>"", 'user'=>$row['user_id'])).">".Base_UserCommon::get_user_login($row['user_id'])."'s gallery</a>";
				$structure[$row['user_id']]['name'] = Base_UserCommon::get_user_login($row['user_id'])."'s gallery";
				$structure[$row['user_id']]['sub'] = array();
			}
			$tmp_struct = array( 
				'sub'=>array(), 
				'name'=>
				'<a '.$this->create_unique_href(array('dir'=>$row['media'] , 'parent_dir'=>'/', 'user'=>$row['user_id'] )).'>'.$row['media'] .'</a>',
				);
			if($row['media'] == $dir) {
				$tmp_struct['selected'] = 1;
				$tmp_struct['visible'] = 1;
			}
			$structure[$row['user_id']]['sub'][] = $tmp_struct;
		}
		$other->set_structure($structure);
		
		
		// IMAGES
		$images = & $this->init_module('Utils/Gallery', $this->root.$user."/".$dir);
	
		
		$theme = & $this->init_module('Base/Theme');
		$theme->assign('type', 'images');
		$theme->assign('path', $path->toHtml());
		$theme->assign('dirs', $dirs);
		if(Base_AclCommon::i_am_user() > 0)
			$theme->assign('tree', $this->get_html_of_module($tree));
		else
			$theme->assign('tree', '');
		if(count($structure) > 0)
			$theme->assign('other', $this->get_html_of_module($other));
		else
			$theme->assign('other', '');
		
		$theme->assign('images', $images->toHtml($this->root.$user."/".$dir));
		$theme->display();
	}
	
	public function applet($vars,$opts) {  //available applet options: toggle,href,title,go,go_function,go_arguments,go_contruct_arguments
		$opts['go'] = true;
		if(!isset($vars['image']))
			print(Base_LangCommon::ts($this->get_type(),'No image selected'));
		elseif(!file_exists($vars['image']))
			print(Base_LangCommon::ts($this->get_type(),'Selected image doesn\'t exists'));
		else
			Utils_ImageCommon::display_thumb($vars['image'],$vars['size']);
	}

	public function caption() {
		return "Gallery";
	}
}
?>
