<?php
/**
 * Use this module if you want to add attachments to some page.
 * Owner of note has always 3x(private,protected,public) write&read.
 * Permission for group is set by methods allow_{private,protected,public}.
 *
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-utils
 * @subpackage attachment
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_Attachment extends Module {
	private $group;
	private $persistent_deletion = false;

	private $private_read = false;
	private $private_write = false;
	private $protected_read = false;
	private $protected_write = true;
	private $public_read = true;
	private $public_write = true;
	private $author = true;

	private $inline = false;
	private $add_header = '';

	private $caption = '';
	
	private $watchdog_category;
	private $watchdog_id;
	
	private $func = null;
	private $args = array();

	private $add_func = null;
	private $add_args = array();

	public function construct($group,$pd=null,$in=null,$priv_r=null,$priv_w=null,$prot_r=null,$prot_w=null,$pub_r=null,$pub_w=null,$header=null,$watchdog_cat=null,$watchdog_id=null,$func=null,$args=null,$add_func=null,$add_args=null) {
		if(!isset($group)) trigger_error('Key not given to attachment module',E_USER_ERROR);
		$this->group = $group;

		if(isset($pd)) $this->persistent_deletion = $pd;
		if(isset($in)) $this->inline = $in;
		if(isset($priv_r)) $this->private_read = $priv_r;
		if(isset($priv_w)) $this->private_write = $priv_w;
		if(isset($prot_r)) $this->protected_read = $prot_r;
		if(isset($prot_w)) $this->protected_write = $prot_w;
		if(isset($pub_r)) $this->public_read = $pub_r;
		if(isset($pub_w)) $this->public_write = $pub_w;
		if(isset($header)) $this->add_header = $header;
		if(isset($watchdog_cat)) $this->watchdog_category = $watchdog_cat;
		if(isset($watchdog_id)) $this->watchdog_id = $watchdog_id;
		if(isset($func)) $this->func = $func;
		if(isset($args)) $this->args = $args;
		if(isset($add_func)) $this->add_func = $add_func;
		if(isset($add_args)) $this->add_args = $add_args;
	}

	public function additional_header($x) {
		$this->add_header = $x;
	}

	public function set_view_func($x, array $y=array()) {
		$this->func = $x;
		$this->args = $y;
	}

	public function set_add_func($x, array $y=array()) {
		$this->add_func = $x;
		$this->add_args = $y;
	}

	public function inline_attach_file($x=true) {
		$this->inline = $x;
	}

	public function set_persistent_delete($x=true) {
		$this->persistent_deletion = $x;
	}

	public function allow_private($read,$write=null) {
		$this->private_read = $read;
		if(!isset($write)) $write=$read;
		$this->private_write = $write;
	}

	public function allow_protected($read,$write=null) {
		$this->protected_read = $read;
		if(!isset($write)) $write=$read;
		$this->protected_write = $write;
	}

	public function allow_public($read,$write=null) {
		$this->public_read = $read;
		if(!isset($write)) $write=$read;
		$this->public_write = $write;
	}

	public function display_author_column($x=true) {
		$this->author = $x;
	}

	public function body() {
		$vd = null;
		if(!$this->persistent_deletion)
			$vd = isset($_SESSION['view_deleted_attachments']) && $_SESSION['view_deleted_attachments'];

		$gb = $this->init_module('Utils/GenericBrowser',null,md5($this->group));
		$cols = array();
		if($vd)
			$cols[] = array('name'=>$this->t('Deleted'),'order'=>'ual.deleted','width'=>5);
		if($this->author)
			$cols[] = array('name'=>$this->t('User'), 'order'=>'note_by','width'=>5);
		$cols[] = array('name'=>$this->t('Date'), 'order'=>'note_on','width'=>1,'wrapmode'=>'nowrap');
		$expand_id = 'expand_collapse_'.md5($this->get_path());
		$cols[] = array('name'=>$this->t('Note'), 'preppend'=>'<span id="'.$expand_id.'"></span>', 'width'=>70);
		$cols[] = array('name'=>$this->t('Attachment'), 'order'=>'uaf.original','width'=>5);
		$gb->set_table_columns($cols);

		if($vd) {
			$query = 'SELECT ual.sticky,uaf.id as file_id,(SELECT count(*) FROM utils_attachment_download uad INNER JOIN utils_attachment_file uaf ON uaf.id=uad.attach_file_id WHERE uaf.attach_id=ual.id) as downloads,(SELECT l.login FROM user_login l WHERE ual.permission_by=l.id) as permission_owner,ual.permission,ual.permission_by,ual.deleted,ual.local,uac.revision as note_revision,uaf.revision as file_revision,ual.id,uac.created_on as note_on,(SELECT l.login FROM user_login l WHERE uac.created_by=l.id) as note_by,uac.text,uaf.original,uaf.created_on as upload_on,(SELECT l2.login FROM user_login l2 WHERE uaf.created_by=l2.id) as upload_by FROM (utils_attachment_link ual INNER JOIN utils_attachment_note uac ON uac.attach_id=ual.id) INNER JOIN utils_attachment_file uaf ON uaf.attach_id=ual.id WHERE ual.local='.DB::qstr($this->group).' AND uac.revision=(SELECT max(x.revision) FROM utils_attachment_note x WHERE x.attach_id=uac.attach_id) AND uaf.revision=(SELECT max(x.revision) FROM utils_attachment_file x WHERE x.attach_id=uaf.attach_id)';
			$query_lim = 'SELECT count(ual.id) FROM utils_attachment_link ual WHERE ual.local='.DB::qstr($this->group);
		} else {
			$query = 'SELECT ual.sticky,uaf.id as file_id,(SELECT count(*) FROM utils_attachment_download uad INNER JOIN utils_attachment_file uaf ON uaf.id=uad.attach_file_id WHERE uaf.attach_id=ual.id) as downloads,(SELECT l.login FROM user_login l WHERE ual.permission_by=l.id) as permission_owner,ual.permission,ual.permission_by,ual.local,uac.revision as note_revision,uaf.revision as file_revision,ual.id,uac.created_on as note_on,(SELECT l.login FROM user_login l WHERE uac.created_by=l.id) as note_by,uac.text,uaf.original,uaf.created_on as upload_on,(SELECT l2.login FROM user_login l2 WHERE uaf.created_by=l2.id) as upload_by FROM (utils_attachment_link ual INNER JOIN utils_attachment_note uac ON uac.attach_id=ual.id) INNER JOIN utils_attachment_file uaf ON ual.id=uaf.attach_id WHERE ual.local='.DB::qstr($this->group).' AND uac.revision=(SELECT max(x.revision) FROM utils_attachment_note x WHERE x.attach_id=uac.attach_id) AND uaf.revision=(SELECT max(x.revision) FROM utils_attachment_file x WHERE x.attach_id=uaf.attach_id) AND ual.deleted=0';
			$query_lim = 'SELECT count(ual.id) FROM utils_attachment_link ual WHERE ual.local='.DB::qstr($this->group).'  AND ual.deleted=0';
		}
		$gb->set_default_order(array($this->t('Date')=>'DESC'));

		$query_order = $gb->get_query_order('ual.sticky DESC');
		$qty = DB::GetOne($query_lim);
		$query_limits = $gb->get_limit($qty);
		$ret = DB::SelectLimit($query.$query_order,$query_limits['numrows'],$query_limits['offset']);

		eval_js_once('utils_attachment_expand = function(id) {'.
			     '$(\'utils_attachment_more_\'+id).hide();'.
			     '$(\'utils_attachment_text_\'+id).show();'.
			     '};'.
			     'utils_attachment_expand_all = function(ids) {'.
			     'ids.each(function(k){utils_attachment_expand(k)})'.
			     '};'.
			     'utils_attachment_collapse = function(id) {'.
			     '$(\'utils_attachment_more_\'+id).show();'.
			     '$(\'utils_attachment_text_\'+id).hide();'.
			     '};'.
			     'utils_attachment_collapse_all = function(ids) {'.
			     'ids.each(function(k){utils_attachment_collapse(k)})'.
			     '};');
		$expandable = array();

		while($row = $ret->FetchRow()) {
			if(!Base_AclCommon::i_am_admin() && $row['permission_by']!=Acl::get_user()) {
				if($row['permission']==0 && !$this->public_read) continue;//protected
				elseif($row['permission']==1 && !$this->protected_read) continue;//protected
				elseif($row['permission']==2 && !$this->private_read) continue;//private
			}
			$r = $gb->get_new_row();


			$inline_img = '';
			if($row['original']!=='') {
				$f_filename = DATA_DIR.'/Utils_Attachment/'.$row['local'].'/'.$row['id'].'_'.$row['file_revision'];
				$filetooltip = $this->t('Filename: %s<br>File size: %s',array($row['original'],filesize_hr($f_filename))).'<hr>'.$this->t('Last uploaded by %s<br>on %s<br>Number of uploads: %d<br>Number of downloads: %d',array($row['upload_by'],Base_RegionalSettingsCommon::time2reg($row['upload_on']),$row['file_revision'],$row['downloads']));
				$view_link = '';
				$file = '<a '.$this->get_file($row,$view_link).' '.Utils_TooltipCommon::open_tag_attrs($filetooltip,false).'><img src="'.Base_ThemeCommon::get_template_file($this->get_type(),'attach.png').'" border=0></a>';
				if(preg_match('/\.(jpg|jpeg|gif|png|bmp)$/i',$row['original']) && $view_link)
					$inline_img = '<hr><img src="'.$view_link.'" style="max-width:700px" /><br>';
			} else {
				$file = '';
			}

			static $def_permissions = array('Public','Protected','Private');
			$perm = $this->t($def_permissions[$row['permission']]);
			$note_on = Base_RegionalSettingsCommon::time2reg($row['note_on'],0);
			$note_on_time = Base_RegionalSettingsCommon::time2reg($row['note_on'],1);
			$info = $this->t('Owner: %s',array($row['permission_owner'])).'<br>'.
				$this->t('Permission: %s',array($perm)).'<hr>'.
				$this->t('Last edited by %s<br>on %s<br>Number of edits: %d',array($row['note_by'],$note_on_time,$row['note_revision']));
			$r->add_info($info);
			if(Base_AclCommon::i_am_admin() ||
			 	$row['permission_by']==Acl::get_user() ||
			   ($row['permission']==0 && $this->public_write) ||
			   ($row['permission']==1 && $this->protected_write) ||
			   ($row['permission']==2 && $this->private_write)) {
				if($this->inline)
					$r->add_action($this->create_callback_href(array($this,'edit_note'),$row['id']),'edit');
				else
					$r->add_action($this->create_callback_href(array($this,'edit_note_queue'),$row['id']),'edit');
				$r->add_action($this->create_confirm_callback_href($this->ht('Delete this entry?'),array($this,'delete'),$row['id']),'delete');
			}
			if($this->inline) {
				$r->add_action($this->create_callback_href(array($this,'view'),array($row['id'])),'view');
				$r->add_action($this->create_callback_href(array($this,'edition_history'),$row['id']),'history');
			} else {
				$r->add_action($this->create_callback_href(array($this,'view_queue'),array($row['id'])),'view');
				$r->add_action($this->create_callback_href(array($this,'edition_history_queue'),$row['id']),'history');
			}
			$text = Utils_BBCodeCommon::parse(strip_tags(str_replace('</p>','<br>',preg_replace('/<\/p>\s*$/i','',$row['text'])),'<br><br/>'));
			$max_len = 120;
			if ($max_len>strlen(strip_tags($text))) $max_len = strlen(strip_tags($text));
			$br = strpos($text,'<br');
			if($br!==false && $br<$max_len) $max_len=$br;
			// ************* Strip without loosing html entities
			$i = 0;
			$continue=0;
			$in_middle=false;
			while ($continue>0 || $in_middle || $i<$max_len) {
				if ($text{$i}=='<') {
					if (isset($text{$i+1}) && $text{$i+1}!='/') $continue++;
					else $continue--;
					$in_middle=true;
				}
				if ($text{$i}=='>') {
					$in_middle=false;
					if (isset($text{$i-1}) && $text{$i-1}=='/') $continue--;
				}
				if (!isset($text{$i+1})) {
					$i++;
					break;
				}
				$i++;
			}
			$max_len=$i;
			// ************* Strip without loosing html entities
			if($max_len<strlen(strip_tags($text)) || $inline_img) {
				$text = array('value'=>substr($text,0,$max_len).'<a href="javascript:void(0)" onClick="utils_attachment_expand('.$row['id'].')" id="utils_attachment_more_'.$row['id'].'"> '.$this->t('[ + ]').'</a><span style="display:none" id="utils_attachment_text_'.$row['id'].'">'.substr($text,$max_len).$inline_img.' <a href="javascript:void(0)" onClick="utils_attachment_collapse('.$row['id'].')">'.$this->t('[ - ]').'</a></span>','hint'=>$this->t('Click on view icon to see full note'));
				$expandable[] = $row['id'];
				if($row['sticky']) $text['value'] = '<img src="'.Base_ThemeCommon::get_template_file($this->get_type(),'sticky.png').'" hspace=3 align="left"> '.$text['value'];
			} else {
				if($row['sticky']) $text = '<img src="'.Base_ThemeCommon::get_template_file($this->get_type(),'sticky.png').'" hspace=3 align="left"> '.$text;
			}

			$regional_note_on = $note_on;
			$arr = array();
			if($vd)
				$arr[] = ($row['deleted']?'yes':'no');
			if($this->author)
				$arr[] = $row['note_by'];
			$arr[] = $regional_note_on;
			$arr[] = $text;
			$arr[] = $file;
			$r->add_data_array($arr);
		}
		if($this->public_write) {
			if($this->inline) {
				print('<a '.$this->create_callback_href(array($this,'edit_note')).'>'.$this->t('Attach note').'</a>');
			} else {
				Base_ActionBarCommon::add('folder','Attach note',$this->create_callback_href(array($this,'edit_note_queue')));
			}
		}

		if(!empty($expandable))
			eval_js('$(\''.$expand_id.'\').innerHTML = \''.Epesi::escapeJS('<a style="display:inline;" href="javascript:void(0)" onClick=\'utils_attachment_expand_all('.Epesi::escapeJS(json_encode($expandable),false,true).')\' '.Utils_TooltipCommon::open_tag_attrs($this->t('Expand all')).'>[ + ]</a> <a style="display:inline;" href="javascript:void(0)" onClick=\'utils_attachment_collapse_all('.Epesi::escapeJS(json_encode($expandable),false,true).')\' '.Utils_TooltipCommon::open_tag_attrs($this->t('Collapse all')).'>[ - ]</a>').'\'');

		$this->display_module($gb);
	}

	public function view_queue($id) {
		$this->push_box0('view',array($id),array($this->group,$this->persistent_deletion,$this->inline,$this->private_read,$this->private_write,$this->protected_read,$this->protected_write,$this->public_read,$this->public_write,$this->add_header,$this->watchdog_category,$this->watchdog_id,$this->func,$this->args,$this->add_func,$this->add_args));
	}

	public function get_file($row, & $view_link = '') {
		static $th;
		if(!isset($th)) $th = $this->init_module('Base/Theme');

		if($row['original']==='') return '';

		//tag for get.php
		if(!$this->isset_module_variable('public')) {
			$this->set_module_variable('public',$this->public_read);
			$this->set_module_variable('protected',$this->protected_read);
			$this->set_module_variable('private',$this->private_read);
		}

		$lid = 'get_file_'.md5($this->get_path().serialize($row));

		$view_link = 'modules/Utils/Attachment/get.php?'.http_build_query(array('id'=>$row['file_id'],'path'=>$this->get_path(),'cid'=>CID,'view'=>1));
		$th->assign('view','<a href="'.$view_link.'" target="_blank" onClick="leightbox_deactivate(\''.$lid.'\')">'.$this->t('View').'</a><br>');
		$th->assign('download','<a href="modules/Utils/Attachment/get.php?'.http_build_query(array('id'=>$row['file_id'],'path'=>$this->get_path(),'cid'=>CID)).'" onClick="leightbox_deactivate(\''.$lid.'\')">'.$this->t('Download').'</a><br>');
		load_js('modules/Utils/Attachment/remote.js');
		$th->assign('link','<a href="javascript:void(0)" onClick="utils_attachment_get_link('.$row['file_id'].', '.CID.', \''.Epesi::escapeJS($this->get_path(),false).'\',\'get link\');leightbox_deactivate(\''.$lid.'\')">'.$this->t('Get link').'</a><br>');
		$th->assign('filename',$row['original']);
		$f_filename = DATA_DIR.'/Utils_Attachment/'.$row['local'].'/'.$row['id'].'_'.$row['file_revision'];
		$th->assign('file_size',$this->t('File size: %s',array(filesize_hr($f_filename))));

		$getters = ModuleManager::call_common_methods('attachment_getters');
		$custom_getters = array();
		foreach($getters as $mod=>$arr) {
			foreach($arr as $caption=>$func) {
				$custom_getters[] = array('open'=>'<a href="javascript:void(0)" onClick="'.Epesi::escapeJS($this->create_callback_href_js(array($mod.'Common',$func['func']),array($f_filename,$row['original'])),true,false).';leightbox_deactivate(\''.$lid.'\')">','close'=>'</a>','text'=>$caption,'icon'=>$func['icon']);
			}
		}
//		$custom_getters[] = array('open'=>'<a>','close'=>'</a>','text'=>'tekst','icon'=>'Utils/Attachment/download.png');
		$th->assign('custom_getters',$custom_getters);

		ob_start();
		$th->display('download');
		$c = ob_get_clean();

		Libs_LeightboxCommon::display($lid,$c,$this->t('Attachment'));
		return Libs_LeightboxCommon::get_open_href($lid);
	}

	public function view($id) {
		if($this->is_back()) {
			if($this->inline) return false;
			return $this->pop_box0();
		}

		$row = DB::GetRow('SELECT uaf.id as file_id,ual.permission_by,ual.permission,ual.deleted,ual.local,uac.revision as note_revision,uaf.revision as file_revision,ual.id,uac.created_on as note_on,(SELECT l.login FROM user_login l WHERE uac.created_by=l.id) as note_by,uac.text,uaf.original,uaf.created_on as upload_on,(SELECT l2.login FROM user_login l2 WHERE uaf.created_by=l2.id) as upload_by FROM (utils_attachment_link ual INNER JOIN utils_attachment_note uac ON uac.attach_id=ual.id) INNER JOIN utils_attachment_file uaf ON uaf.attach_id=ual.id WHERE ual.id=%d AND uac.revision=(SELECT max(x.revision) FROM utils_attachment_note x WHERE x.attach_id=uac.attach_id) AND uaf.revision=(SELECT max(x.revision) FROM utils_attachment_file x WHERE x.attach_id=uaf.attach_id)',array($id));

		if($this->inline) {
			if(Base_AclCommon::i_am_admin() ||
				$row['permission_by']==Acl::get_user() ||
			   ($row['permission']==0 && $this->public_write) ||
			   ($row['permission']==1 && $this->protected_write) ||
			   ($row['permission']==2 && $this->private_write)) {
				print('<a '.$this->create_callback_href(array($this,'edit_note'),$id).'>'.$this->t('Edit').'</a> :: ');
				print('<a '.$this->create_confirm_callback_href($this->ht('Delete this entry?'),array($this,'delete_back'),$id).'>'.$this->t('Delete').'</a> :: ');
			}
			print('<a '.$this->create_callback_href(array($this,'edition_history'),$id).'>'.$this->t('History').'</a> :: ');
			print('<a '.$this->create_back_href().'>'.$this->t('back').'</a><br>');
		} else {
			if(Base_AclCommon::i_am_admin() ||
				$row['permission_by']==Acl::get_user() ||
			   ($row['permission']==0 && $this->public_write) ||
			   ($row['permission']==1 && $this->protected_write) ||
			   ($row['permission']==2 && $this->private_write)) {
				Base_ActionBarCommon::add('edit','Edit',$this->create_callback_href(array($this,'edit_note_queue'),$id));
				Base_ActionBarCommon::add('delete','Delete',$this->create_confirm_callback_href($this->ht('Delete this entry?'),array($this,'delete_back'),$id));
			}
			Base_ActionBarCommon::add('history','Edition history',$this->create_callback_href(array($this,'edition_history_queue'),$id));
			Base_ActionBarCommon::add('back','Back',$this->create_back_href());
		}


		$th = $this->init_module('Base/Theme');
		$th->assign('header',$this->add_header);
		
		// display images inline

		$th->assign('upload_by',$row['upload_by']);
		$th->assign('upload_on',Base_RegionalSettingsCommon::time2reg($row['upload_on']));


		if($row['original']) {
			$inline_img = '';
			$view_link = '';

			$file = $this->get_file($row,$view_link);
			if(preg_match('/\.(jpg|jpeg|gif|png|bmp)$/i',$row['original']) && $view_link)
					$inline_img = '<hr><img src="'.$view_link.'" style="max-width:900px" /><br>';

			$f_filename = DATA_DIR.'/Utils_Attachment/'.$row['local'].'/'.$row['id'].'_'.$row['file_revision'];
			$th->assign('file_size',filesize_hr($f_filename));
			$th->assign('file','<a '.$file.'>'.$row['original'].'</a>');
			$th->assign('note',Utils_BBCodeCommon::parse($row['text']).$inline_img);
		} else {
			$th->assign('file','');
			$th->assign('note',Utils_BBCodeCommon::parse($row['text']));
		}

		$th->display('view');

		$this->caption = 'View note';

		return true;
	}

	public function delete_back($id) {
		$this->delete($id);
		$this->set_back_location();
		return false;
	}

	public function edition_history_queue($id) {
		$this->push_box0('edition_history',array($id),array($this->group,$this->persistent_deletion,$this->inline,$this->private_read,$this->private_write,$this->protected_read,$this->protected_write,$this->public_read,$this->public_write,$this->add_header,$this->watchdog_category,$this->watchdog_id,$this->func,$this->args,$this->add_func,$this->add_args));
	}

	public function edition_history($id) {
		if($this->is_back()) {
			if($this->inline) return false;
			return $this->pop_box0();
		}

		if($this->inline)
			print('<a '.$this->create_back_href().'>'.$this->t('back').'</a>');
		else
			Base_ActionBarCommon::add('back','Back',$this->create_back_href());

		$th = $this->init_module('Base/Theme');
		$th->assign('note_edition_header', $this->t('Note edit history'));

		$gb = $this->init_module('Utils/GenericBrowser',null,'hn'.md5($this->group));
		$gb->set_table_columns(array(
				array('name'=>$this->t('Revision'), 'order'=>'uac.revision','width'=>10),
				array('name'=>$this->t('Date'), 'order'=>'note_on','width'=>25),
				array('name'=>$this->t('Who'), 'order'=>'note_by','width'=>25),
				array('name'=>$this->t('Note'), 'order'=>'uac.text')
			));
		$gb->set_default_order(array($this->t('Date')=>'DESC'));

		$ret = $gb->query_order_limit('SELECT ual.permission_by,ual.permission,uac.revision,uac.created_on as note_on,(SELECT l.login FROM user_login l WHERE uac.created_by=l.id) as note_by,uac.text FROM utils_attachment_note uac INNER JOIN utils_attachment_link ual ON ual.id=uac.attach_id WHERE uac.attach_id='.$id, 'SELECT count(*) FROM utils_attachment_note uac WHERE uac.attach_id='.$id);
		while($row = $ret->FetchRow()) {
			$r = $gb->get_new_row();
			if(Base_AclCommon::i_am_admin() ||
				$row['permission_by']==Acl::get_user() ||
			   ($row['permission']==0 && $this->public_write) ||
			   ($row['permission']==1 && $this->protected_write) ||
			   ($row['permission']==2 && $this->private_write))
				$r->add_action($this->create_callback_href(array($this,'restore_note'),array($id,$row['revision'])),'restore');
			$r->add_data($row['revision'],Base_RegionalSettingsCommon::time2reg($row['note_on']),$row['note_by'],$row['text']);
		}
		$th->assign('note_edition',$this->get_html_of_module($gb));

		$th->assign('file_uploads_header',$this->t('File uploads history'));

		$gb = $this->init_module('Utils/GenericBrowser',null,'hua'.md5($this->group));
		$gb->set_table_columns(array(
				array('name'=>$this->t('Revision'), 'order'=>'file_revision','width'=>10),
				array('name'=>$this->t('Date'), 'order'=>'upload_on','width'=>25),
				array('name'=>$this->t('Who'), 'order'=>'upload_by','width'=>25),
				array('name'=>$this->t('Attachment'), 'order'=>'uaf.original')
			));
		$gb->set_default_order(array($this->t('Date')=>'DESC'));

		$ret = $gb->query_order_limit('SELECT uaf.id as file_id,ual.local,ual.permission_by,ual.permission,uaf.attach_id as id,uaf.revision as file_revision,uaf.created_on as upload_on,(SELECT l.login FROM user_login l WHERE uaf.created_by=l.id) as upload_by,uaf.original FROM utils_attachment_file uaf INNER JOIN utils_attachment_link ual ON ual.id=uaf.attach_id WHERE uaf.attach_id='.$id, 'SELECT count(*) FROM utils_attachment_file uaf WHERE uaf.attach_id='.$id);
		while($row = $ret->FetchRow()) {
			$r = $gb->get_new_row();
			if(Base_AclCommon::i_am_admin() ||
				$row['permission_by']==Acl::get_user() ||
			   ($row['permission']==0 && $this->public_write) ||
			   ($row['permission']==1 && $this->protected_write) ||
			   ($row['permission']==2 && $this->private_write))
				$r->add_action($this->create_callback_href(array($this,'restore_file'),array($id,$row['file_revision'])),'restore');
			$file = '<a '.$this->get_file($row).'>'.$row['original'].'</a>';
			$r->add_data($row['file_revision'],Base_RegionalSettingsCommon::time2reg($row['upload_on']),$row['upload_by'],$file);
		}
		$th->assign('file_uploads',$this->get_html_of_module($gb));

		$th->assign('file_access_header',$this->t('File access history'));

		$gb = $this->init_module('Utils/GenericBrowser',null,'hda'.md5($this->group));
		$gb->set_table_columns(array(
				array('name'=>$this->t('Create date'), 'order'=>'created_on','width'=>15),
				array('name'=>$this->t('Download date'), 'order'=>'download_on','width'=>15),
				array('name'=>$this->t('Who'), 'order'=>'created_by','width'=>15),
				array('name'=>$this->t('IP address'), 'order'=>'ip_address', 'width'=>15),
				array('name'=>$this->t('Host name'), 'order'=>'host_name', 'width'=>15),
				array('name'=>$this->t('Method description'), 'order'=>'description', 'width'=>20),
				array('name'=>$this->t('Revision'), 'order'=>'revision', 'width'=>10),
				array('name'=>$this->t('Remote'), 'order'=>'remote', 'width'=>10),
			));
		$gb->set_default_order(array($this->t('Create date')=>'DESC'));

		$query = 'SELECT uad.created_on,uad.download_on,(SELECT l.login FROM user_login l WHERE uad.created_by=l.id) as created_by,uad.remote,uad.ip_address,uad.host_name,uad.description,uaf.revision FROM utils_attachment_download uad INNER JOIN utils_attachment_file uaf ON uaf.id=uad.attach_file_id WHERE uaf.attach_id='.$id;
		$query_qty = 'SELECT count(*) FROM utils_attachment_download uad INNER JOIN utils_attachment_file uaf ON uaf.id=uad.attach_file_id WHERE uaf.attach_id='.$id;
		if($this->acl_check('view download history'))
			$ret = $gb->query_order_limit($query, $query_qty);
		else {
			print('You are allowed to see your own downloads only');
			$who = ' AND uad.created_by='.Acl::get_user();
			$ret = $gb->query_order_limit($query.$who, $query_qty.$who);
		}
		while($row = $ret->FetchRow()) {
			$r = $gb->get_new_row();
			$r->add_data(Base_RegionalSettingsCommon::time2reg($row['created_on']),($row['remote']!=1?Base_RegionalSettingsCommon::time2reg($row['download_on']):''),$row['created_by'], $row['ip_address'], $row['host_name'], $row['description'], $row['revision'], ($row['remote']==0?'no':'yes'));
		}
		$th->assign('file_access',$this->get_html_of_module($gb));

		$th->display('history');

		$this->caption = 'Note history';

		return true;
	}

	public function restore_note($id,$rev) {
		DB::StartTrans();
		$text = DB::GetOne('SELECT text FROM utils_attachment_note WHERE attach_id=%d AND revision=%d',array($id,$rev));
		$rev2 = DB::GetOne('SELECT max(x.revision) FROM utils_attachment_note x WHERE x.attach_id=%d',array($id));
		DB::Execute('INSERT INTO utils_attachment_note(text,attach_id,revision,created_by) VALUES (%s,%d,%d,%d)',array($text,$id,$rev2+1,Acl::get_user()));
		DB::CompleteTrans();
	}

	public function restore_file($id,$rev) {
		DB::StartTrans();
		$original = DB::GetOne('SELECT original FROM utils_attachment_file WHERE attach_id=%d AND revision=%d',array($id,$rev));
		$rev2 = DB::GetOne('SELECT max(x.revision) FROM utils_attachment_file x WHERE x.attach_id=%d',array($id));
		$rev2 = $rev2+1;
		DB::Execute('INSERT INTO utils_attachment_file(attach_id,original,created_by,revision) VALUES(%d,%s,%d,%d)',array($id,$original,Acl::get_user(),$rev2));
		DB::CompleteTrans();
		$local = $this->get_data_dir().$this->group.'/'.$id.'_';
		copy($local.$rev,$local.$rev2);
	}

	public function pop_box0() {
		$x = ModuleManager::get_instance('/Base_Box|0');
		if(!$x) trigger_error('There is no base box module instance',E_USER_ERROR);
		$x->pop_main();
	}

	public function push_box0($func,$args,$const_args) {
		$x = ModuleManager::get_instance('/Base_Box|0');
		if(!$x) trigger_error('There is no base box module instance',E_USER_ERROR);
		$x->push_main('Utils/Attachment',$func,$args,$const_args);
	}

	public function edit_note_queue($id=null) {
		$this->push_box0('edit_note',array($id),array($this->group,$this->persistent_deletion,$this->inline,$this->private_read,$this->private_write,$this->protected_read,$this->protected_write,$this->public_read,$this->public_write,$this->add_header,$this->watchdog_category,$this->watchdog_id,$this->func,$this->args,$this->add_func,$this->add_args));
	}

	public function edit_note($id=null) {
		if(!$this->is_back()) {
			$form = & $this->init_module('Utils/FileUpload',array(false));
			if(isset($id))
				$form->addElement('header', 'upload', $this->t('Edit note').': '.$this->add_header);
			else
				$form->addElement('header', 'upload', $this->t('Attach note').': '.$this->add_header);
			$fck = $form->addElement('fckeditor', 'note', $this->t('Note'));
			$fck->setFCKProps('800','300');
			
			$form->set_upload_button_caption('Save');
			if($form->getSubmitValue('note')=='' && $form->getSubmitValue('uploaded_file')=='')
				$form->addRule('note',$this->t('Please enter note or choose file'),'required');

			$form->addElement('select','permission',$this->t('Permission'),array($this->ht('Public'),$this->ht('Protected'),$this->ht('Private')));
			$form->addElement('checkbox','sticky',$this->t('Sticky'));

			if(isset($id))
				$form->addElement('header',null,$this->t('Replace attachment with file'));

			$form->add_upload_element();

			if(isset($id)) {
				$row = DB::GetRow('SELECT l.sticky,x.text,l.permission FROM utils_attachment_note x INNER JOIN utils_attachment_link l ON l.id=x.attach_id WHERE x.attach_id=%d AND x.revision=(SELECT max(z.revision) FROM utils_attachment_note z WHERE z.attach_id=%d)',array($id,$id));
				$form->setDefaults(array('note'=>$row['text'],'permission'=>$row['permission'],'sticky'=>$row['sticky']));
			}

			if(!$this->inline) {
				Base_ActionBarCommon::add('save','Save',$form->get_submit_form_href());
				Base_ActionBarCommon::add('back','Back',$this->create_back_href());
			} else {
				$s = HTML_QuickForm::createElement('button',null,$this->t('Save'),$form->get_submit_form_href());
				$c = HTML_QuickForm::createElement('button',null,$this->t('Cancel'),$this->create_back_href());
				$form->addGroup(array($s,$c));
			}

			$this->ret_attach = true;
			if(isset($id))
				$this->display_module($form, array( array($this,'submit_edit'),$id,$row['text']));
			else
				$this->display_module($form, array( array($this,'submit_attach') ));
		} else {
			$this->ret_attach = false;
		}

		$this->caption = 'Edit note';

		if($this->inline)
			return $this->ret_attach;
		elseif(!$this->ret_attach)
			return $this->pop_box0();
	}

	public function submit_attach($file,$oryg,$data) {		
		DB::Execute('INSERT INTO utils_attachment_link(local,permission,permission_by,sticky,func,args) VALUES(%s,%d,%d,%b,%s,%s)',array($this->group,$data['permission'],Acl::get_user(),isset($data['sticky']) && $data['sticky'],serialize($this->func),serialize($this->args)));
		$id = DB::Insert_ID('utils_attachment_link','id');
		if($file && trim($data['note'])=='')
			$data['note'] = $oryg;
		DB::Execute('INSERT INTO utils_attachment_file(attach_id,original,created_by,revision) VALUES(%d,%s,%d,0)',array($id,$oryg,Acl::get_user()));
		DB::Execute('INSERT INTO utils_attachment_note(attach_id,text,created_by,revision) VALUES(%d,%s,%d,0)',array($id,Utils_BBCodeCommon::optimize($data['note']),Acl::get_user()));
		if($file) {
			$local = $this->get_data_dir().$this->group;
			@mkdir($local,0777,true);
			$dest_file = $local.'/'.$id.'_0';
			rename($file,$dest_file);
			if ($this->add_func) call_user_func($this->add_func,$id,0,$dest_file,$oryg,$this->add_args);
		}
		$this->ret_attach = false;
		if (isset($this->watchdog_category)) Utils_WatchdogCommon::new_event($this->watchdog_category,$this->watchdog_id,'N_+_'.$id);
	}

	public function submit_edit($file,$oryg,$data,$id,$text) {
		DB::Execute('UPDATE utils_attachment_link SET sticky=%b,permission=%d,permission_by=%d WHERE id=%d',array(isset($data['sticky']) && $data['sticky'],$data['permission'],Acl::get_user(),$id));
		if($data['note']!=$text) {
			if($file && trim($data['note'])=='')
				$data['note'] = $oryg;
			DB::StartTrans();
			$rev = DB::GetOne('SELECT max(x.revision) FROM utils_attachment_note x WHERE x.attach_id=%d',array($id));
			DB::Execute('INSERT INTO utils_attachment_note(text,attach_id,revision,created_by) VALUES (%s,%d,%d,%d)',array(Utils_BBCodeCommon::optimize($data['note']),$id,$rev+1,Acl::get_user()));
			DB::CompleteTrans();
		}
		if($file) {
			DB::StartTrans();
			$rev = DB::GetOne('SELECT max(x.revision) FROM utils_attachment_file x WHERE x.attach_id=%d',array($id));
			$rev = $rev+1;
			DB::Execute('INSERT INTO utils_attachment_file(attach_id,original,created_by,revision) VALUES(%d,%s,%d,%d)',array($id,$oryg,Acl::get_user(),$rev));
			DB::CompleteTrans();
			$local = $this->get_data_dir().$this->group;
			@mkdir($local,0777,true);
			$dest_file = $local.'/'.$id.'_'.$rev;
			rename($file,$dest_file);
			if ($this->add_func) call_user_func($this->add_func,$id,$rev,$dest_file,$this->add_args);
		}
		$this->ret_attach = false;
		if (isset($this->watchdog_category)) Utils_WatchdogCommon::new_event($this->watchdog_category,$this->watchdog_id,'N_~_'.$id);
	}

	public function delete($id) {
		if($this->persistent_deletion) {
			DB::Execute('DELETE FROM utils_attachment_note WHERE attach_id=%d',array($id));
			$rev = DB::GetOne('SELECT count(*) FROM utils_attachment_file WHERE attach_id=%d',array($id));
			$file_base = $this->get_data_dir().$this->group.'/'.$id.'_';
			for($i=0; $i<$rev; $i++)
			    @unlink($file_base.$i);
			DB::Execute('DELETE FROM utils_attachment_file WHERE attach_id=%d',array($id));
			DB::Execute('DELETE FROM utils_attachment_link WHERE id=%d',array($id));
		} else {
			DB::Execute('UPDATE utils_attachment_link SET deleted=1 WHERE id=%d',array($id));
		}
		if (isset($this->watchdog_category)) Utils_WatchdogCommon::new_event($this->watchdog_category,$this->watchdog_id,'N_-_'.$id);
	}

	public function caption() {
		return $this->caption;
	}
	
	public function enable_watchdog($category, $id) {
		$this->watchdog_category = $category;
		$this->watchdog_id = $id;
	}
}

?>
