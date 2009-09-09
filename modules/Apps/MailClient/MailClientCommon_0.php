<?php
/**
 * Simple mail client
 * @author pbukowski@telaxus.com
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-apps
 * @subpackage mailclient
 */

defined("_VALID_ACCESS") || die('Direct access forbidden');

class Apps_MailClientCommon extends ModuleCommon {

	public static function filter_match_method($v) {
	    static $arr,$rev;
	    if(!isset($arr)) {
		$arr = array('allrules', 'anyrule', 'allmessages');
		$rev = array_flip($arr);
	    }
	    if(is_numeric($v))
		    return $arr[$v];
	    return $rev[$v];
	}

	public static function filter_rules_match($v) {
	    static $arr,$rev;
	    if(!isset($arr)) {
		$arr = array('contains','notcontains','is','notis','begins','ends');
		$rev = array_flip($arr);
	    }
	    if(is_numeric($v))
		    return $arr[$v];
	    return $rev[$v];
	}
	
	public static function filter_actions($v) {
	    static $arr,$rev;
	    if(!isset($arr)) {
		$arr = array('move','copy','forward','read','delete','forward_delete');
		$rev = array_flip($arr);
	    }
	    if(is_numeric($v))
		    return $arr[$v];
	    return $rev[$v];
	}

	public static function user_settings() {
		if(Acl::is_user()) {
			$opts = array('both'=>'Private message and contact mail', 'pm'=>'Private message only');
			$contact_exists = false;
			if(ModuleManager::is_installed('CRM/Contacts')>=0) {
				$my = CRM_ContactsCommon::get_my_record();
				if($my['id']>=0 && isset($my['email']) && $my['email']!=='')
					$contact_exists = true;
			}	
			if($contact_exists)
				$opts['mail']='Mail only';
			return array('Mail accounts'=>'account_manager',
				    'Mail filters'=>'manage_filters',
				    'Mail settings'=>array(
					array('name'=>'default_dest_mailbox','label'=>'Messages from epesi users deliver to', 'type'=>'select', 'values'=>$opts, 'default'=>'both'),
					)
			);
		}
		return array();
	}

	public static function user_settings_icon() {
		if(Acl::is_user()) 
			return array('Mail accounts'=>Base_ThemeCommon::get_template_file(self::Instance()->get_type(),'icon.png'),
				'Mail settings'=>Base_ThemeCommon::get_template_file(self::Instance()->get_type(),'settings.png')
				);
		return array();	
	}


	public static function account_manager_access() {
		return Acl::is_user();
	}

	public static function applet_caption() {
		return "Mail indicator";
	}

	public static function applet_info() {
		return "Checks if there is new mail";
	}

	public static function applet_settings() {
		$ret = Apps_MailClientCommon::get_mailbox_data();
		$conf = array(array('type'=>'header','label'=>'Choose accounts'));
		if(empty($ret))
			$ret[] = array('id'=>Apps_MailClientCommon::create_internal_mailbox(), 'mail'=>'#internal');

		foreach($ret as $row)
			if($row['mail']==='#internal')
				$conf[] = array('name'=>'account_'.$row['id'], 'label'=>Base_LangCommon::ts('Apps_MailClient','Private messages'), 'type'=>'checkbox', 'default'=>1);
			else
				$conf[] = array('name'=>'account_'.$row['id'], 'label'=>$row['mail'], 'type'=>'checkbox', 'default'=>0);
		if(count($conf)==1)
			return array(array('type'=>'static','label'=>'No accounts configured, go Home->My settings->Mail accounts'));
		return $conf;
	}

	public static function menu() {
		return array('Mail client'=>array());
	}

	public static function admin_caption() {
		return 'Mail client settings';
	}
	
	public static function admin_icon() {
		return Base_ThemeCommon::get_template_file(self::Instance()->get_type(),'settings.png');
	}

	////////////////////////////////////////////////////
	// scan mail dir, etc

	public static function create_mailbox_dir($id,$imap_create = true) {
		$acc_dir = self::Instance()->get_data_dir().$id.'/';
		if (!is_dir($acc_dir)) mkdir($acc_dir);
		Apps_MailClientCommon::get_mailbox_dir($id,false);
		$dirs = array('Inbox','Sent','Trash','Drafts');
		foreach($dirs as $d) {
			self::create_mailbox_subdir($id,$d.'/',$imap_create);
		}
		return true;
	}
	
	public static function is_imap($id) {
		$v = self::get_mailbox_data($id);
		return $v['incoming_protocol']==1;
	}
	
	public static function create_mailbox_subdir($id,$new_name,$imap_create=true) {
		$mbox_dir = Apps_MailClientCommon::get_mailbox_dir($id);
		if($mbox_dir===false) return false;
		if(file_exists($mbox_dir.$new_name)) return true;

		if($imap_create && self::is_imap($id)) {
		    		$imap = self::imap_open($id);
				if(!$imap) {
					return false;
				}
				$iname = $imap['ref'].rtrim($new_name,'/');
				$iname = mb_convert_encoding( $iname, "UTF7-IMAP", "UTF-8" );
				$st = imap_status($imap['connection'],$iname,SA_UIDNEXT);
				if(!$st) {
				        imap_createmailbox($imap['connection'],$iname);
					imap_subscribe($imap['connection'],$iname);
					if(self::imap_errors('Unable to create directory on imap server.')) {
					        return false;
					}
				}
		}
		
		
		//local
		$name_arr = explode('/',$new_name);
		$all = '';
		$all_last = '';
		foreach($name_arr as $r) {
			$all .= $r.'/';
			if(!file_exists($mbox_dir.$all)) {
			    @mkdir($mbox_dir.$all);
			    Apps_MailClientCommon::build_index($id,$all);
			    $fs = @filesize($mbox_dir.$all_last.'.dirs');
			    $f = fopen($mbox_dir.$all_last.'.dirs','a');
			    fputs($f,($fs?',':'').$r);
			    fclose($f);
			}
			$all_last = $all;
		}
		return true;
	}
	
	public static function remove_mailbox_subdir($id,$dir,$imap_remove=true) {
		if($imap_remove && self::is_imap($id)) {
		    		$imap = self::imap_open($id);
				if(!$imap) {
					return false;
				}
				$iname = $imap['ref'].rtrim($dir,'/');
				$iname = mb_convert_encoding( $iname, "UTF7-IMAP", "UTF-8" );
				$st = imap_status($imap['connection'],$iname,SA_UIDNEXT);
				if(!$st) {
					return false;
				}
				imap_unsubscribe($imap['connection'],$iname);
				imap_deletemailbox($imap['connection'],$iname);
				if(self::imap_errors('Unable to remove directory on imap server')) {
					return false;
				}
		}

		$mbox_dir = Apps_MailClientCommon::get_mailbox_dir($id);
		recursive_rmdir($mbox_dir.$dir);
		$pos = strrpos(rtrim($dir,'/'),'/');
		if($pos)
			$parent_dir = substr($dir,0,$pos+1);
		else
			$parent_dir = '';
		$ret = explode(',',file_get_contents($mbox_dir.$parent_dir.'.dirs'));
		$removed = substr($dir,strlen($parent_dir),-1);
		$ret = array_filter($ret,create_function('$o','return $o!="'.$removed.'";'));
		file_put_contents($mbox_dir.$parent_dir.'.dirs',implode(',',$ret));
		return true;
	}
	
	public static function rename_mailbox_subdir($id,$old,$new,$imap_rename=true) {
		if($imap_rename) {
			if(self::is_imap($id)) {
		    		$imap = self::imap_open($id);
				if(!$imap) {
					return false;
				}
				$iname = $imap['ref'].rtrim($old,'/');
				$iname = mb_convert_encoding( $iname, "UTF7-IMAP", "UTF-8" );
				
				$oname = $imap['ref'].rtrim($new,'/');
				$oname = mb_convert_encoding( $oname, "UTF7-IMAP", "UTF-8" );

				imap_unsubscribe($imap['connection'],$iname);
				imap_renamemailbox($imap['connection'],$iname,$oname);
				imap_subscribe($imap['connection'],$oname);
				if(self::imap_errors('Unable to rename directory on imap server.')) {
					return false;
				}
			}
		}
		
		//local
		$mbox_dir = Apps_MailClientCommon::get_mailbox_dir($id);
		rename($mbox_dir.$old,$mbox_dir.$new);
		$dir = dirname(rtrim($old,'/')).'/';
		$old_name = basename(rtrim($old,'/'));
		$new_name = basename(rtrim($new,'/'));
		$ret = explode(',',file_get_contents($mbox_dir.$dir.'.dirs'));
		$ret = array_filter($ret,create_function('$o','return $o!="'.$old_name.'";'));
		$ret[] = $new_name;
		file_put_contents($mbox_dir.$dir.'.dirs',implode(',',$ret));
		return true;
	}

	public static function create_internal_mailbox($user=null) {
		if($user===null) $user = Acl::get_user();
		DB::Execute('INSERT INTO apps_mailclient_accounts(user_login_id,mail,login,password,incoming_server,incoming_protocol) VALUES(%d,\'#internal\',\'\',\'\',\'\',2)',array($user));
		$id = DB::Insert_ID('apps_mailclient_accounts','id');
		Apps_MailClientCommon::create_mailbox_dir($id,false);
		return $id;
	}
	
	public static function get_mailbox_data($id=null,$use_cache=true) {
		static $cache;
		if(!Acl::is_user()) return false;
		if(!isset($cache) || !$use_cache) {
			$ret = DB::Execute('SELECT * FROM apps_mailclient_accounts WHERE user_login_id=%d ORDER BY mail',array(Acl::get_user()));
			$cache = array();
			while($row = $ret->FetchRow())
				$cache[$row['id']] = $row;
		}
		if($id===null)
			return $cache;
		if(!isset($cache[$id]))
			return DB::GetRow('SELECT * FROM apps_mailclient_accounts WHERE id=%d',array($id));
		return $cache[$id];
	}

	//gets mailbox dir
	public static function get_mailbox_dir($id,$use_cache=true) {
		$ret = self::get_mailbox_data($id,$use_cache);
		if(!$ret) return false;
		$acc_dir = self::Instance()->get_data_dir().$id.'/';
		if(!file_exists($acc_dir))
			return false;
		return $acc_dir;
	}

	//gets array of folders in mailbox
	public static function get_mailbox_structure($id,$mdir='') {
		$st = array();
		$mbox_dir = self::get_mailbox_dir($id);
		if($mbox_dir===false) return array();
		$cont = @file_get_contents($mbox_dir.$mdir.'.dirs');
		if($cont===false) return array();
		$cont = array_filter(explode(",",$cont));
		foreach($cont as $f) {
			$path = $mdir.$f;
			if(is_dir($mbox_dir.$path) && is_readable($mbox_dir.$path) && is_writable($mbox_dir.$path))
				$st[$f] = self::get_mailbox_structure($id,$path.'/');
		}
		return $st;
	}
	
	public static function get_number_of_messages($box,$p) {
		$box_dir = Apps_MailClientCommon::get_mailbox_dir($box);
		if($box_dir===false) {
			Epesi::alert($this->ht('Invalid mailbox'));
			return false;
		}
		$num = @file_get_contents($box_dir.$p.'.num');
		if($num!==false) {
			$num = explode(',',$num);
			if(count($num)==2) {
				return array('unread'=>$num[1],'all'=>$num[0]);
			}
		}
		return false;
	}

	public static function get_msg_id($id,$dir) {
		$mbox_dir = self::get_mailbox_dir($id);
		if($mbox_dir === false)
			return false;
		$mailbox = $mbox_dir.$dir;
		$nid = @file_get_contents($mailbox.'.mid');
		if($nid===false || !is_numeric($nid)) {
			$nid = -1;
			$files = scandir($mailbox);
			foreach($files as $f) {
				if(is_numeric($f) && $nid<$f)
					$nid=$f;
			}
		}
		return $nid;
	}

	public static function set_msg_id($id,$dir,$nid) {
		$mbox_dir = self::get_mailbox_dir($id);
		if($mbox_dir === false)
			return false;
		$mailbox = $mbox_dir.$dir;
		file_put_contents($mailbox.'.mid',$nid);
		return $nid;
	}
	
	//gets next message id
	public static function get_next_msg_id($id,$dir) {
		$nid = self::get_msg_id($id,$dir);
		if($nid===false) return false;
		$nid++;
		self::set_msg_id($id,$dir,$nid);
		return $nid;
	}
	
	public static function include_path() {
		static $ok;
		if(!isset($ok)) {
			$ok=true;
			ini_set('include_path','modules/Apps/MailClient/PEAR'.PATH_SEPARATOR.ini_get('include_path'));
		}
	}
	
	//drops message to specified mailbox
	public static function drop_message($mailbox_id,$dir,$subject,$from,$to,$date,$body,$read=false) {
		self::include_path();
		require_once('Mail/mime.php');

		$mbox_dir = self::get_mailbox_dir($mailbox_id);
		if($mbox_dir===false) return false;
		
		if($subject=='') $subject = Base_LangCommon::ts('Apps/MailClient','no subject');

		$mime = new Mail_Mime();
		$headers = array();
	        $headers['From'] = $from;
		$headers['To'] = $to;
	 	$headers['Subject'] = $subject;
		$headers['Date'] = $date;
		$mime->headers($headers);
		$mime->setHTMLBody($body);
		$mbody = $mime->getMessage(null,array('html_charset'=>'utf-8'));
		
		if(self::is_imap($mailbox_id)) {
			$imap = self::imap_open($mailbox_id);
			if(!$imap) return false;
			$mailbox_name = mb_convert_encoding( $imap['ref'].rtrim($dir,'/'), "UTF7-IMAP", "UTF-8" );
			$st = imap_status($imap['connection'],$mailbox_name,SA_UIDNEXT);
			if(self::imap_errors('Unable to save message on imap server')) return false;
			$msg_id = $st->uidnext;
    			imap_append($imap['connection'], $mailbox_name, $mbody);
		} else {
			$msg_id = self::get_next_msg_id($mailbox_id,$dir);
			if($msg_id===false) return false;
		}

		$mailbox = $mbox_dir.$dir;
		Apps_MailClientCommon::append_msg_to_index($mailbox_id,$dir,$msg_id,$subject,$from,$to,$date,strlen($mbody),$read);
		file_put_contents($mailbox.$msg_id,$mbody);

		return $msg_id;
	}

	public static function build_index($id,$dir) {
		self::include_path();
		require_once('Mail/mimeDecode.php');
		$mbox_dir = self::get_mailbox_dir($id);
		if($mbox_dir===false) return false;
		$boxpath = $mbox_dir.$dir;
		$out = @fopen($boxpath.'.idx','w');
		if($out==false) return false;
		$files = scandir($boxpath);
		$c = 0;
		$max = 0;
		foreach($files as $f) {
			if(!is_numeric($f)) continue;
			$message = @file_get_contents($boxpath.$f);
			if($message===false) continue;
			$decode = new Mail_mimeDecode($message, "\r\n");
			$structure = $decode->decode();
			if(!isset($structure->headers['from']) || !isset($structure->headers['to']) || !isset($structure->headers['date']))
				continue;
			fputcsv($out, array($f,isset($structure->headers['subject'])?substr($structure->headers['subject'],0,256):Base_LangCommon::ts('Apps/MailClient','no subject'),substr($structure->headers['from'],0,256),substr($structure->headers['to'],0,256),substr($structure->headers['date'],0,64),substr(strlen($message),0,64),'0'));
			$c++;
			if($f>$max) $max=$f;
		}
		fclose($out);
		file_put_contents($boxpath.'.num',$c.','.$c);
		file_put_contents($boxpath.'.mid',$max);
		return true;
	}

	public static function mark_all_as_read($id,$dir) {
		$is_imap = self::is_imap($id);
	
		$mbox_dir = self::get_mailbox_dir($id);
		if($mbox_dir===false) return false;
		$box = $mbox_dir.$dir;
		$in = @fopen($box.'.idx','r');
		if($in==false) return false;

		if(!self::lock_mailbox_dir($id,$dir,'idx')) return false;

		$ret = array();
		if($is_imap)
			$uidls = array();
		while (($data = fgetcsv($in, 700)) !== false) { //teoretically max is 640+integer and commas
			$num = count($data);
			if($num!=7) continue;
			$data[6]=1;
			$ret[] = $data;
			if($is_imap)
				$uidls[] = $data[0];
		}
		fclose($in);

		if($is_imap) {
			$imap = self::imap_open($id);
			if(!$imap) {
				self::unlock_mailbox_dir($id,$dir,'idx');
				return false;
			}
			imap_reopen($imap['connection'],$imap['ref'].rtrim($dir,'/'));
			imap_setflag_full($imap['connection'], implode(',',$uidls), "\\Seen", ST_UID);
			imap_reopen($imap['connection'],$imap['ref']);
		}

		$out = @fopen($box.'.idx','w');
		if($out==false) {
			self::unlock_mailbox_dir($id,$dir,'idx');
			return false;
		}
		$c = 0;
		foreach($ret as $d) {
			fputcsv($out, $d);
			$c++;
		}
		fclose($out);
		file_put_contents($box.'.num',$c.',0');
		self::unlock_mailbox_dir($id,$dir,'idx');
		return true;
	}

	public static function get_index($id,$dir) {
		$mbox_dir = self::get_mailbox_dir($id);
		if($mbox_dir===false) return false;
		$box = $mbox_dir.$dir;
		if(!file_exists($box.'.idx')) self::build_index($id,$dir);
		$in = @fopen($box.'.idx','r');
		if($in===false) return false;
		$ret = array();
		while (($data = fgetcsv($in, 700)) !== false) { //teoretically max is 640+integer and commas
			$num = count($data);
			if($num!=7) continue;
			$ret[$data[0]] = array('from'=>$data[2], 'to'=>$data[3], 'date'=>$data[4], 'subject'=>$data[1], 'size'=>$data[5],'read'=>$data[6]);
		}
		fclose($in);
		return $ret;
	}
	
	public static function remove_msg($mailbox_id, $dir, $id, $imap_remove = true) {
		if(!self::lock_mailbox_dir($mailbox_id,$dir,'trash')) return false;
		
		if($imap_remove && self::is_imap($mailbox_id)) {
			$imap = self::imap_open($mailbox_id);
			if(!$imap) return false;
			$mailbox_name = mb_convert_encoding( $imap['ref'].rtrim($dir,'/'), "UTF7-IMAP", "UTF-8" );
			imap_reopen($imap['connection'],$mailbox_name);
    			imap_delete($imap['connection'], $id, FT_UID);
			if(self::imap_errors('Unable to remove message from imap server')) return false;
			imap_reopen($imap['connection'],$imap['ref']);
		}
	
		if(!self::remove_msg_from_index($mailbox_id,$dir,$id)) {
			self::unlock_mailbox_dir($mailbox_id,$dir,'trash');
			return false;
		}

		$mbox_dir = self::get_mailbox_dir($mailbox_id);
		if($mbox_dir===false) {
			self::unlock_mailbox_dir($mailbox_id,$dir,'trash');
			return false;
		}
		$box = $mbox_dir.$dir;
		@unlink($box.$id);
		
		if($dir!=='Trash/') {
			self::unlock_mailbox_dir($mailbox_id,$dir,'trash');
			return true;
		}
		
		//trash? delete entry from .del file		
		$trashpath = $mbox_dir.'Trash/.del';
		$in = @fopen($trashpath,'r');
		if($in!==false) {
			$ret = array();
			while (($data = fgetcsv($in, 700)) !== false) {
				$num = count($data);
				if($num!=3 || $data[0]==$id) continue;
				$ret[] = $data;
			}
			fclose($in);
			$out = @fopen($trashpath,'w');
			if($out!==false) {
				foreach($ret as $v)
					fputcsv($out,$v);
				fclose($out);
			}
		}

		self::unlock_mailbox_dir($mailbox_id,$dir,'trash');

		return true;
	}

	public static function remove_msg_from_index($mailbox_id,$dir,$id) {
		if(!self::lock_mailbox_dir($mailbox_id,$dir,'idx')) return false;
		$idx = self::get_index($mailbox_id,$dir);

		if($idx===false || !isset($idx[$id])) {
			self::unlock_mailbox_dir($id,$dir,'idx');
			return false;
		}
		unset($idx[$id]);

		$mbox_dir = self::get_mailbox_dir($mailbox_id);
		if($mbox_dir===false) {
			self::unlock_mailbox_dir($mailbox_id,$dir,'idx');
			return false;
		}
		$box = $mbox_dir.$dir;
		$out = @fopen($box.'.idx','w');
		if($out==false) {
			self::unlock_mailbox_dir($mailbox_id,$dir,'idx');
			return false;
		}

		$c = 0;
		$ur = 0;
		foreach($idx as $id=>$d) {
			fputcsv($out, array($id,substr($d['subject'],0,256),substr($d['from'],0,256),substr($d['to'],0,256),substr($d['date'],0,64),substr($d['size'],0,64),$d['read']));
			if(!$d['read']) $ur++;
			$c++;
		}
		fclose($out);
		file_put_contents($box.'.num',$c.','.$ur);
		self::unlock_mailbox_dir($mailbox_id,$dir,'idx');
		return true;
	}

	public static function move_msg($box, $dir, $box2, $dir2, $id) {
		$mbox_dir = self::get_mailbox_dir($box);
		if($mbox_dir===false) return false;
		$boxpath = $mbox_dir.$dir;
		$mbox2_dir = self::get_mailbox_dir($box2);
		if($mbox2_dir===false) return false;
		$boxpath2 = $mbox2_dir.$dir2;
		$msg = @file_get_contents($boxpath.$id);
		if($msg===false) return false;

		if(!self::lock_mailbox_dir($box,$dir,'idx') || !self::lock_mailbox_dir($box2,$dir2,'idx')) return false;
		
		if(self::is_imap($box2)) {
			$imap2 = self::imap_open($box2);
			if(!$imap2) return false;
			$mailbox_name = mb_convert_encoding( $imap2['ref'].rtrim($dir2,'/'), "UTF7-IMAP", "UTF-8" );
			$st = imap_status($imap2['connection'],$mailbox_name,SA_UIDNEXT);
			if(self::imap_errors('Unable to save message on imap server')) {
				self::unlock_mailbox_dir($box,$dir,'idx');
				self::unlock_mailbox_dir($box2,$dir2,'idx');
				return false;
			}
			$id2 = $st->uidnext;
    			imap_append($imap2['connection'], $mailbox_name, $msg);
			self::set_msg_id($box2,$dir2,$id2);
		} else {
			$id2 = self::get_next_msg_id($box2,$dir2);
			if($id2===false) {
				self::unlock_mailbox_dir($box,$dir,'idx');
				self::unlock_mailbox_dir($box2,$dir2,'idx');
				return false;
			}
		}

		file_put_contents($boxpath2.$id2,$msg);
		$idx = self::get_index($box,$dir);
		$idx = $idx[$id];
		if(!self::append_msg_to_index($box2,$dir2,$id2,$idx['subject'],$idx['from'],$idx['to'],$idx['date'],$idx['size'],$idx['read'])) {
			self::unlock_mailbox_dir($box,$dir,'idx');
			self::unlock_mailbox_dir($box2,$dir2,'idx');
			return false;
		}

		if(!self::remove_msg($box,$dir,$id)) {
			self::unlock_mailbox_dir($box,$dir,'idx');
			self::unlock_mailbox_dir($box2,$dir2,'idx');
			return false;
		}
		self::unlock_mailbox_dir($box,$dir,'idx');
		self::unlock_mailbox_dir($box2,$dir2,'idx');

		if($dir=='Trash/' && self::lock_mailbox_dir($box,$dir,'trash')) {		
			$trashpath = $boxpath.'.del';
			$in = @fopen($trashpath,'r');
			if($in!==false) {
				$ret = array();
				while (($data = fgetcsv($in, 700)) !== false) {
					$num = count($data);
					if($num!=3 || $data[0]==$id) continue;
					$ret[] = $data;
				}
				fclose($in);
				$out = @fopen($trashpath,'w');
				if($out!==false) {
					foreach($ret as $v)
						fputcsv($out,$v);
					fclose($out);
				}
			}
			self::unlock_mailbox_dir($box,$dir,'trash');
		}
		if($dir2=='Trash/') {
			$trashpath = $boxpath2.'.del';
			$out = @fopen($trashpath,'a');
			if($out!==false) {
				fputcsv($out,array($id2,$dir,$id));
				fclose($out);
			}
		}
		return $id2;
	}

	public static function copy_msg($box, $dir, $box2, $dir2, $id) {
		$mbox_dir = self::get_mailbox_dir($box);
		if($mbox_dir===false) return false;
		$boxpath = $mbox_dir.$dir;
		$mbox2_dir = self::get_mailbox_dir($box2);
		if($mbox2_dir===false) return false;
		$boxpath2 = $mbox2_dir.$dir2;
		$msg = @file_get_contents($boxpath.$id);
		if($msg===false) return false;

		if(!self::lock_mailbox_dir($box2,$dir2,'idx')) return false;
		
		if(self::is_imap($box2)) {
			$imap2 = self::imap_open($box2);
			if(!$imap2) return false;
			$mailbox_name = mb_convert_encoding( $imap2['ref'].rtrim($dir2,'/'), "UTF7-IMAP", "UTF-8" );
			$st = imap_status($imap2['connection'],$mailbox_name,SA_UIDNEXT);
			if(self::imap_errors('Unable to save message on imap server')) {
				self::unlock_mailbox_dir($box2,$dir2,'idx');
				return false;
			}
			$id2 = $st->uidnext;
    			imap_append($imap2['connection'], $mailbox_name, $msg);
			self::set_msg_id($box2,$dir2,$id2);
		} else {
			$id2 = self::get_next_msg_id($box2,$dir2);
			if($id2===false) {
				self::unlock_mailbox_dir($box2,$dir2,'idx');
				return false;
			}
		}

		file_put_contents($boxpath2.$id2,$msg);
		$idx = self::get_index($box,$dir);
		$idx = $idx[$id];
		if(!self::append_msg_to_index($box2,$dir2,$id2,$idx['subject'],$idx['from'],$idx['to'],$idx['date'],$idx['size'],$idx['read'])) {
			self::unlock_mailbox_dir($box2,$dir2,'idx');
			return false;
		}

		self::unlock_mailbox_dir($box2,$dir2,'idx');

		if($dir2=='Trash/') {
			$trashpath = $boxpath2.'.del';
			$out = @fopen($trashpath,'a');
			if($out!==false) {
				fputcsv($out,array($id2,$dir,$id));
				fclose($out);
			}
		}
		return $id2;
	}

	public static function append_msg_to_index($box,$dir, $id, $subject, $from, $to, $date, $size,$read=false) {
		$mbox_dir = self::get_mailbox_dir($box);
		if($mbox_dir===false) return false;
		$mailbox = $mbox_dir.$dir;
		$num = @file_get_contents($mailbox.'.num');
		if($num===false) {
			self::build_index($box,$dir);
			$num = @file_get_contents($mailbox.'.num');
			if($num===false) return false;
		}
		$num = explode(',',$num);
		if(count($num)!=2) return false;
		$out = @fopen($mailbox.'.idx','a');
		if($out==false) return false;

		if($subject=='') $subject = Base_LangCommon::ts('Apps/MailClient','no subject');

		fputcsv($out,array($id, substr($subject,0,256), substr($from,0,256), substr($to,0,256), substr($date,0,64), substr($size,0,64),$read?'1':'0'));
		fclose($out);
		file_put_contents($mailbox.'.num',($num[0]+1).','.($num[1]+($read?0:1)));
		return true;
	}

	public static function read_msg($box,$dir, $id, $value=1, $imap_seen = true) {
		if(!self::lock_mailbox_dir($box,$dir,'idx')) return false;

		if($imap_seen && self::is_imap($box)) {
			$imap = self::imap_open($box);
			if(!$imap) {
				self::unlock_mailbox_dir($box,$dir,'idx');
				return false;
			}
			imap_reopen($imap['connection'],$imap['ref'].rtrim($dir,'/'));
			if($value)
				imap_setflag_full($imap['connection'], $id, "\\Seen", ST_UID);
			else
				imap_clearflag_full($imap['connection'], $id, "\\Seen", ST_UID);
			imap_reopen($imap['connection'],$imap['ref']);
		}
	
		$idx = self::get_index($box,$dir);

		if($idx===false || !isset($idx[$id])) {
			self::unlock_mailbox_dir($box,$dir,'idx');
			return false;
		}
		if($idx[$id]['read']==$value) {
			self::unlock_mailbox_dir($box,$dir,'idx');
			return true;
		}
		$idx[$id]['read'] = $value;

		$mbox_dir = self::get_mailbox_dir($box);
		if($mbox_dir===false) {
			self::unlock_mailbox_dir($box,$dir,'idx');
			return false;
		}
		$boxpath = $mbox_dir.$dir;

		$out = @fopen($boxpath.'.idx','w');
		if($out==false) {
			self::unlock_mailbox_dir($box,$dir,'idx');
			return false;
		}
		
		$unread = 0;
		foreach($idx as $id=>$d) {
			if(!$d['read']) $unread++;
			fputcsv($out, array($id,substr($d['subject'],0,256),substr($d['from'],0,256),substr($d['to'],0,256),substr($d['date'],0,64),substr($d['size'],0,64),$d['read']));
		}
		fclose($out);
		file_put_contents($boxpath.'.num',count($idx).','.$unread);
		self::unlock_mailbox_dir($box,$dir,'idx');
		return true;
	}

	public static function mime_header_decode($string) {
		if(!function_exists('imap_mime_header_decode')) return $string;
	    $array = imap_mime_header_decode($string);
	    $str = "";
	    foreach ($array as $key => $part) {
	        $str .= $part->text;
	    }
	    return $str;
	}

	public static function mime_decode($msg,$opts=null) {
		self::include_path();
		require_once('Mail/mimeDecode.php');
		$decode = new Mail_mimeDecode($msg, "\r\n");
		$structure = $decode->decode($opts);
		if(!isset($structure->headers['from']))
			$structure->headers['from'] = '';
		if(!isset($structure->headers['to']))
			$structure->headers['to'] = '';
		if(!isset($structure->headers['date']))
			$structure->headers['date'] = '';
		return $structure;
	}

	public static function get_message_structure($box,$dir,$id) {
		$box_dir = Apps_MailClientCommon::get_mailbox_dir($box);
		if($box_dir===false)
			return false;
		$box = $box_dir.$dir;
		$message = @file_get_contents($box.'/'.$id);
		if($message===false)
			return false;

		return self::mime_decode($message,array('decode_bodies'=>true,'include_bodies'=>true));
	}

	public static function parse_message_structure($structure,$full_attachments=true) {
		$body = false;
		$body_type = false;
		$body_ctype = false;
		$attachments = array();

		if($structure->ctype_primary=='multipart' && isset($structure->parts)) {
			$parts = $structure->parts;
			for($i=0; $i<count($parts); $i++) {
				$part = $parts[$i];
				if($part->ctype_primary=='multipart' && isset($part->parts))
					$parts = array_merge($parts,$part->parts);
				if($body===false && $part->ctype_primary=='text' && $part->ctype_secondary=='plain' && (!isset($part->disposition) || $part->disposition=='inline')) {
					$body = $part->body;
					$body_type = 'plain';
				} elseif($part->ctype_primary=='text' && $part->ctype_secondary=='html' && ($body===false || $body_type=='plain') && (!isset($part->disposition) || $part->disposition=='inline')) {
					$body = $part->body;
					$body_type = 'html';
				}
				$body_ctype = isset($part->headers['content-type'])?$part->headers['content-type']:'text/'.$body_type;
				//if(isset($part->disposition) && $part->disposition=='attachment')
				if(isset($part->ctype_parameters['name'])) {
					if(isset($part->headers['content-id']))
						$coid = trim($part->headers['content-id'],'><');
					else
						$coid = '';
					if(isset($part->headers['content-dispositon']))
						$disposition = $part->headers['content-disposition'];
					else
						$disposition = 'attachment';
					if($full_attachments) {
						$attachments[$part->ctype_parameters['name']] = array('type'=>isset($part->headers['content-type'])?$part->headers['content-type']:false,'body'=>isset($part->body)?$part->body:'','id'=>$coid,'disposition'=>$disposition);
					} else {
						$attachments[$part->ctype_parameters['name']] = $coid;
					}
				}
			}
		} elseif(isset($structure->body) && $structure->ctype_primary=='text') {
			$body = $structure->body;
			$body_type = $structure->ctype_secondary;
			$body_ctype = isset($structure->headers['content-type'])?$structure->headers['content-type']:'text/'.$body_type;
		}
		$subject = isset($structure->headers['subject'])?Apps_MailClientCommon::mime_header_decode($structure->headers['subject']):Base_LangCommon::ts('Apps/MailClient','no subject');
		return array('body'=>$body, 'subject'=>$subject,'type'=>$body_type, 'ctype'=>$body_ctype, 'attachments'=>$attachments);
	}
	
	public static function parse_message($box,$dir,$id) {
		$str = self::get_message_structure($box,$dir,$id);
		if($str===false) return false;
		return array_merge(self::parse_message_structure($str),array('headers'=>$str->headers));
	}

	public static function addressbook_rp_mail($e){
		return CRM_ContactsCommon::contact_format_default($e,true);
	}
	
	private static $lock_fp;
	public static function lock_mailbox_dir($id,$dir,$name) {
		$box_dir = self::get_mailbox_dir($id).$dir;
		if(!isset(self::$lock_fp))
			self::$lock_fp = array();
		if(!isset(self::$lock_fp[$id]))
			self::$lock_fp[$id] = array();
		if(!isset(self::$lock_fp[$id][$dir]))
			self::$lock_fp[$id][$dir] = array();
		if(isset(self::$lock_fp[$id][$dir][$name])) {
			self::$lock_fp[$id][$dir][$name]['count']++;
		} else {
			$fp = @fopen($box_dir.'.'.$name.'_lock','w');
			if($fp==false) return false;
			if (!flock($fp, LOCK_EX)) return false;
			self::$lock_fp[$id][$dir][$name] = array('count'=>1, 'fp'=>$fp);
		}
		return true;
	}

	public static function unlock_mailbox_dir($id,$dir,$name) {
		if(self::$lock_fp[$id][$dir][$name]['count']>1) {
			self::$lock_fp[$id][$dir][$name]['count']--;
		} else {
			flock(self::$lock_fp[$id][$dir][$name]['fp'], LOCK_UN);
			fclose(self::$lock_fp[$id][$dir][$name]['fp']);
			unset(self::$lock_fp[$id][$dir][$name]);
		}
	}
	
	private static $imap_connections;
	public static function imap_open($id) {
		if(!isset(self::$imap_connections)) {
			self::$imap_connections = array();
			on_exit(array('Apps_MailClientCommon','imap_close'),null,false);
		}
		if(isset(self::$imap_connections[$id]))
			return self::$imap_connections[$id];

		$v = Apps_MailClientCommon::get_mailbox_data($id);

		$ssl = $v['incoming_ssl'];
		$host = explode(':',$v['incoming_server']);
		if(isset($host[1])) $port=$host[1];
			else {
				if($ssl)
					$port = '993';
				else
					$port = '143';
			}
		$host = $host[0];
		$user = $v['login'];
		$pass = $v['password'];

		$imap_ref = '{'.$host.':'.$port.'/imap/novalidate-cert'.($ssl?'/ssl':'').'}';
		$imap = @imap_open($imap_ref, $user,$pass, OP_HALFOPEN);
		if($imap) {
			self::$imap_connections[$id] = array('connection'=>$imap, 'ref'=>$imap_ref, 'addr'=>$v['mail']);
			$online='1';
		} else {
			if(!defined('MAILCLIENT_CACHE'))
				Epesi::alert(Base_LangCommon::ts('Apps_MailClient','Unable to connect imap server: '.$host));
			$online='0';
			self::$imap_connections[$id] = false;
			imap_errors(); //flush imap errors
		}
		$mbox_dir = self::Instance()->get_data_dir().$id;
		if(!is_dir($mbox_dir))
			@mkdir($mbox_dir);
		$status_file = $mbox_dir.'/.imap_online';
		file_put_contents($status_file,$online);
		return self::$imap_connections[$id];
	}
	
	public static function is_online($id,$use_cache=true) {
		static $cache;
		if(!isset($cache)) $cache = array();
		if($use_cache && isset($cache[$id]))
			return $cache[$id];
		return $cache[$id] = !Apps_MailClientCommon::is_imap($id) || @file_get_contents(self::get_mailbox_dir($id).'.imap_online');
	}
	
	public static function imap_close() {
		if(isset(self::$imap_connections))
			foreach(self::$imap_connections as $c) 
				if($c) imap_close($c['connection']);
	}

	public static function imap_sync_mailbox_dir($id) {
		$imap = self::imap_open($id);
		if (!$imap) {
			return false;
		}
		
		$changed = false;
		if(is_array($list = imap_getsubscribed($imap['connection'], $imap['ref'], "*"))) {
			$ref_len = strlen($imap['ref']);
			//$mbox_dir = self::get_mailbox_dir($id);
			$local_dirs = self::get_mailbox_structure($id);
			$remote_dirs = array();
			//go thru remote and create new dirs
			foreach($list as $d) {
				
				$remote_dir_orig = str_replace($d->delimiter,'/',mb_convert_encoding( substr($d->name,$ref_len), "UTF-8", "UTF7-IMAP" ));
				$remote_dir_arr = explode('/',$remote_dir_orig);
				$local_exists = true;
				$local_curr = & $local_dirs;
				$remote_curr = & $remote_dirs;
				$remote_dir = ''; //we will build here remote_dir with case changed dirs
				//check if local directory exists
				while(($x = array_shift($remote_dir_arr))!==null) {

					if(!empty($local_curr)) {
						$local_curr_keys = array_keys($local_curr);
						$local_curr_case_map = array_change_key_case(array_combine($local_curr_keys,$local_curr_keys));
					} else
						$local_curr_case_map = array();
					$lower_x = strtolower($x);
					
					//remote_curr is current remote dir
					if(!isset($remote_curr[$lower_x]))
						$remote_curr[$lower_x] = array();
					$remote_curr = & $remote_curr[$lower_x];

					if($local_exists && isset($local_curr_case_map[$lower_x])) {
						$local_curr = & $local_curr[$local_curr_case_map[$lower_x]];
						$remote_dir .= $local_curr_case_map[$lower_x].'/';
					} else {
						$local_exists = false;
						$remote_dir .= $x.'/';
					}
				}
				if(!$local_exists) {
					self::create_mailbox_subdir($id,$remote_dir,false);
					file_put_contents(self::get_mailbox_dir($id).$remote_dir.'.name',$remote_dir_orig);
					$local_dirs = self::get_mailbox_structure($id);
					$changed = true;
				} 
			}
			if(self::imap_sync_mailbox_dir_remove_old($id,$local_dirs,$remote_dirs))
				$changed = true;
		}
		return $changed;
	}
	
	private static function imap_sync_mailbox_dir_remove_old($id,$local_dirs,$remote_dirs,$curr_dir = '') {
		//go thru local and remove old dirs
		$changed = false;
		foreach($local_dirs as $name=>$sub) {
			$lower_name = strtolower($name);
			if(!isset($remote_dirs[$lower_name])) {
				self::remove_mailbox_subdir($id,$curr_dir.$name.'/',false);
				$changed = true;
				continue;
			}
			if(self::imap_sync_mailbox_dir_remove_old($id,$sub,$remote_dirs[$lower_name],$curr_dir.$name.'/'))
				$changed = true;
		}
		return $changed;
	}
	
	private static function imap_get_mailbox_name($id,$dir) {
		$ret = @file_get_contents(self::get_mailbox_dir($id).rtrim($dir,'/').'/.name');
		return $ret?$ret:$dir;
	}
	
	//get new messages in directory
	public static function imap_get_new_messages($id,$dir) {
		$mail_size_limit = Variable::get('max_mail_size');
		$box_root = Apps_MailClientCommon::get_mailbox_dir($id);
		if($box_root===false) return false;

		$imap = self::imap_open($id);
		if(!$imap) return false;
		$tdir = mb_convert_encoding( $imap['ref'].rtrim(self::imap_get_mailbox_name($id,$dir),'/'), "UTF7-IMAP", "UTF-8" );
		imap_reopen($imap['connection'],$tdir);
		$st = imap_status($imap['connection'],$tdir,SA_UIDNEXT);
		if(self::imap_errors('Unable to get status of directory: '.$dir.'.'))
			return false;
		$last_uid = $st->uidnext-1;
		$first_uid = self::get_msg_id($id,$dir)+1;
		if($first_uid>$last_uid) return 0;
		$l=imap_fetch_overview($imap['connection'],$first_uid.':'.$last_uid,FT_UID); //list of new messages
		if(self::imap_errors('Unable to get status of directory: '.$dir.'.'))
			return false;
		
		$downloaded = 0; //number of new messages

		foreach($l as $msgl) {
			if(!isset($msgl->size)) {
				continue;
			}
			$size = $msgl->size;
			if($size>$mail_size_limit) {
				continue;
			}
			if(!isset($msgl->uid)) {
				continue;
			}
			$msg = @imap_fetchheader($imap['connection'],$msgl->uid,FT_UID | FT_PREFETCHTEXT);
			if($msg===false) {
				continue;
			}
			$msg .= imap_body($imap['connection'],$msgl->uid,FT_UID | FT_PEEK);
			$structure = self::mime_decode($msg);
			if(!Apps_MailClientCommon::append_msg_to_index($id,$dir,$msgl->uid,isset($structure->headers['subject'])?$structure->headers['subject']:Base_LangCommon::ts('Apps/MailClient','no subject'),$structure->headers['from'],$structure->headers['to'],$structure->headers['date'],strlen($msg),$msgl->seen)) {
				continue;
			}
			file_put_contents($box_root.$dir.$msgl->uid,$msg);
			$downloaded++;
			if($msgl->deleted) 
				self::move_msg($id,$dir,$id,'Trash/',$msgl->uid);
			else {
				Apps_MailClientCommon::apply_filters($id,$dir,$msgl->uid);
			}
			unset($structure);
			unset($msg);
		}
		
		self::set_msg_id($id,$dir,$last_uid);
		
		imap_reopen($imap['connection'],$imap['ref']);
		
		return $downloaded;
	}
	
	public static function imap_sync_old_messages($id,$dir) {
		if($dir=='Trash/') return false;
		$ret = false;
		//old messages are synchronized from time to time, not always... some people have 10000 messages in inbox, 
		//but they don't modify them, we can check this messages once a day
		$sync_rate = array(180,600,600,600,600,600,600,3600,3600,3600,3600,3600,3600,3600,3600,7200,7200,7200,7200,7200,7200,7200,7200,18000,18000,18000,18000,18000,18000,18000,18000,18000,18000,18000,18000,18000,18000,18000,18000,86400); //3m, 10m x6, 1h x8, 1h, 2h x8 ,5h x16,24h
		$max_sync_rate = count($sync_rate)-1;
		$box = self::get_mailbox_dir($id);
		$box_dir = $box.$dir;
		$rate = @file_get_contents($box_dir.'.imap_rate');
		if($rate===false || !is_numeric($rate)) $rate=2;
		if($rate>$max_sync_rate) $rate = $max_sync_rate;
		
		$last_sync_file = $box_dir.'.imap_last_sync';
		$last_sync = @file_get_contents($last_sync_file);
		if($last_sync===false || !is_numeric($last_sync)) $last_sync=0;
		$time = time();
		if($time-$last_sync<$sync_rate[$rate]) return false;
		
		if(!self::lock_mailbox_dir($id,$dir,'sync')) return false;

		//sync messages
		$local = self::get_index($id,$dir);
		$trash = self::get_index($id,'Trash/');
		$trash_oryg = array();
		$trashpath = $box.'Trash/.del';
		$in = @fopen($trashpath,'r');
		if($in!==false) {
			while (($data = fgetcsv($in, 700)) !== false) {
				$num = count($data);
				if($num!=3 || $data[1]!=$dir) {
					unset($trash[$data[0]]);
					continue;
				}
				$trash_oryg[$data[2]] = $data[0];
			}
			fclose($in);
		}
		if(!empty($local)) {
			$imap = self::imap_open($id);
			if(!$imap) return false;
			$tdir = mb_convert_encoding( $imap['ref'].rtrim(self::imap_get_mailbox_name($id,$dir),'/'), "UTF7-IMAP", "UTF-8" );
			imap_reopen($imap['connection'],$tdir);
			$remote = imap_fetch_overview($imap['connection'],implode(',',array_merge(array_keys($local),array_keys($trash_oryg))),FT_UID);
			foreach($remote as $row) {
				$deleted_uid = isset($trash_oryg[$row->uid])?$trash_oryg[$row->uid]:false;
				if($deleted_uid!==false) {
					if($trash[$deleted_uid]['read']!=$row->seen) {
						self::read_msg($id,'Trash/',$row->uid,$row->seen,false);
						$ret = true;
					}
					if(!$row->deleted) {
						self::move_msg($id,'Trash/',$id,$dir,$deleted_uid,false);
						$ret = true;
					}
				} else {
					if($local[$row->uid]['read']!=$row->seen) {
						self::read_msg($id,$dir,$row->uid,$row->seen,false);
						$ret = true;
					}
					if($row->deleted) {
						self::move_msg($id,$dir,$id,'Trash/',$row->uid,false);
						$ret = true;
					}
				}
				unset($local[$row->uid]);
			}
			foreach($local as $k=>$v) { //remove remotly deleted messages
				self::remove_msg($id,$dir,$k,false);
				$ret = true;
			}
			imap_reopen($imap['connection'],$imap['ref']);
		}
		
		//save sync time and rate
		if($ret) { //increase sync rate
			if($rate>0)
				file_put_contents($box_dir.'.imap_rate',(($rate>3)?($rate-3):0));
		} else { //decrease sync rate
			if($rate<$max_sync_rate)
				file_put_contents($box_dir.'.imap_rate',$rate+1);
		}

		file_put_contents($last_sync_file,$time);

		self::unlock_mailbox_dir($id,$dir,'sync');

		return $ret;
	}
	
	//full sync of messages
	public static function imap_sync_messages($id,$arr=null,$p='') {
		if($arr===null)
			$arr = Apps_MailClientCommon::get_mailbox_structure($id);
		$ret = false;
		foreach($arr as $k=>$a) {
			if($p.$k=='[Gmail]') continue;
			if(self::imap_sync_old_messages($id,$p.$k.'/')) //sync old messages
				$ret = true;
			if(self::imap_get_new_messages($id,$p.$k.'/')) //and get new ones
				$ret = true;
			if(self::imap_sync_messages($id,$a,$p.$k.'/')) //sync subdirs
				$ret = true;
		}
		return $ret;

	}
	
	public static function imap_errors($msg='') {
		$err = imap_errors();
		if($err) {
			Epesi::alert(Base_LangCommon::ts('Apps_MailClient',$msg)."\n".'IMAP errors: '.implode(', ',$err));
			return true;
		}
		return false;
	}
	
	private static function inbox_sum($id,$arr,$p) {
		$msgs = Apps_MailClientCommon::get_index($id,$p);
		$unread = 0;
		$list = array();
		foreach($msgs as $m)
			if(!$m['read']) {
				$unread++;
				$list[] = $m;
			}
		foreach($arr as $k=>$a) {
			$ret = self::inbox_sum($id,$a,$p.$k.'/');
			$unread += $ret[0];
			$list = array_merge($list,$ret[1]);
		}
		return array($unread,$list);
	}
	
	public static function get_number_of_new_pop3_messages($id) {
		$account = self::get_mailbox_data($id);
		if(!$account) return false;

		$box_dir = Apps_MailClientCommon::get_mailbox_dir($id);
		if($box_dir===false) {
			return false;
//				die('invalid mailbox');
		}

		$host = explode(':',$account['incoming_server']);
		$ssl = $account['incoming_ssl'];
		if(isset($host[1])) $port=$host[1];
		else {
			if($ssl) $port=995;
			else $port=110;
		}
		$host = $host[0];
		$user = $account['login'];
		$pass = $account['password'];
		$method = $account['incoming_method']!='auto'?$account['incoming_method']:null;
		$native_support = false;
		if(function_exists('imap_open')) {
			$native_support = true;
			$in = @imap_open('{'.$host.':'.$port.'/pop3/novalidate-cert'.($ssl?'/ssl':'').'}', $user,$pass);
			if(!$in) {
				return false;
	//					die('(connect error) '.implode(', ',imap_errors()));
			}

			if ($hdr = imap_check($in)) {
				$msgCount = $hdr->Nmsgs;
			} else {
				return false;
	//					die('(fetch error) '.implode(', ',imap_errors()));
			}
	
	
			$l=imap_fetch_overview($in,'1:'.$msgCount,0);
		} else {
			require_once('Net/POP3.php');
			$in = new Net_POP3();
	
	
			if(PEAR::isError( $ret= $in->connect(($ssl?'ssl://':'').$host , $port) )) {
				return false;
//					die('(connect error) '.$ret->getMessage());
			}
	
			if(PEAR::isError( $ret= $in->login($user , $pass, $method))) {
				return false;
	//					die('(login error) '.$ret->getMessage());
			}
			
			$l = $in->getListing();
		}
			
		if($l===false) {
			return false;
	//				die('unknown error');
		}
		$uidls_file = $box_dir.'.uidls';
		$list = array();
		if($account['pop3_leave_msgs_on_server']!=0 && file_exists($uidls_file)) {
			$uidls = array();
			if(($uidls_fp = @fopen($uidls_file,'r'))!==false) {
				while(($data = fgetcsv($uidls_fp,200))!==false) {
					$xxx = count($data);
					if($xxx!=2) continue;
					$uidls[$data[0]] = intval($data[1]);
				}
				fclose($uidls_fp);
			}
	
			if(!empty($uidls)) {
				$count = count($l);
				for($k=0; $k<$count; $k++) {
					if($native_support) {
						if(!isset($l[$k]->message_id)) {
							unset($l[$k]);
							continue;
						}
						$uidl = $l[$k]->message_id;
					} else {
						if(!isset($l[$k]['uidl'])) {
							unset($l[$k]);
							continue;
						}
						$uidl = $l[$k]['uidl'];
					}
					if(array_key_exists($uidl,$uidls)) {
						if($account['pop3_leave_msgs_on_server']>=0 && ($uidls[$uidl]+$account['pop3_leave_msgs_on_server']*86400)<=$now) {
							if($native_support)
								imap_delete($in,$l[$k]->uid);
							else
								$in->deleteMsg($l[$k]['msg_id']);
							unset($uidls[$uidl]);
						}
						unset($l[$k]);
					}
					if(isset($l[$k])) {
						if($native_support) //only in native we've got msgs list
							$list[] = array('subject'=>$l[$k]->subject,'from'=>$l[$k]->from,'to'=>$l[$k]->to,'date'=>$l[$k]->date,'size'=>$l[$k]->size);
					}
				}
			}
	
		}
		$num_msgs = count($l);
		
		if($native_support)
			imap_close($in);
		else
			$in->disconnect();

		return array($num_msgs,$list);
	}

	public static function get_number_of_new_messages_in_inbox($id,$cache_pop3=false) {
		$account = self::get_mailbox_data($id);
		if(!$account) return false;
		$num_msgs = 0;
		$list = array();
		if($account['incoming_protocol']==0) {
			$box_dir = Apps_MailClientCommon::get_mailbox_dir($id);
			if($box_dir===false) {
				return false;
//				die('invalid mailbox');
			}
			$pop3_cache_file = $box_dir.'.pop3_new_msgs';
			if($cache_pop3) {
				$tmp = file_get_contents($pop3_cache_file);
				$tmp = explode("\n",$tmp);
				$num_msgs = $tmp[0];
				for($kk=1; $kk<count($tmp); $kk++)
					$list[] = unserialize($tmp[$kk]);
			} else {
				list($num_msgs,$list) = self::get_number_of_new_pop3_messages($id);
				$list2 = array();
				foreach($list as $ll)
					$list2[] = serialize($ll);
				file_put_contents($pop3_cache_file,$num_msgs."\n".
						implode("\n",$list2));
			}
		}
		$struct = Apps_MailClientCommon::get_mailbox_structure($id);
		if(isset($struct['Inbox'])) {
			$ret = self::inbox_sum($id,$struct['Inbox'],'Inbox/');
			$num_msgs += $ret[0];
			$list = array_merge($list,$ret[1]);
		}
		return array($num_msgs,$list);
	}

	public static function tray_notification($time) {
		$boxes = Apps_MailClientCommon::get_mailbox_data();
		if(!isset($_SESSION['mails'])) 
			$_SESSION['mails'] = array();
		$ret = array();
		foreach($boxes as $v) {
			$new_msgs = self::get_number_of_new_messages_in_inbox($v['id'],true);
			if(!$new_msgs) continue;
			list($num,$list) = $new_msgs;
			if(!isset($_SESSION['mails'][$v['id']]) || $_SESSION['mails'][$v['id']]!=$num) {
				$listing = '';
				if($num) {
					$listing .= '<br><small>';
					foreach($list as $l) {
						if(!$l) continue;
						$listing .= htmlspecialchars(Apps_MailClientCommon::mime_header_decode($l['from'])).': <i>'.Apps_MailClientCommon::mime_header_decode($l['subject']).'</i><br>';
					}
					$listing .= '</small>';
				}
				if($num) {
					$name = $v['mail']=='#internal'?Base_LangCommon::ts('Apps_MailClient','Private messages'):$v['mail'];
					$ret['mailclient_'.$v['id'].'_'.$time] = Base_LangCommon::ts('Apps_MailClient','<b>%s</b> new message in mailbox: <font color="gray">%s</font>',array($num,$name)).$listing;
				}
				$_SESSION['mails'][$v['id']] = $num;
			}
		}
		$ret = array('notifications'=>$ret);
		if(!isset($_SESSION['mailclient_tray_job']) || $time-300>$_SESSION['mailclient_tray_job'])
			$ret['jobs'] = array('mailclient'=>'modules/Apps/MailClient/tray_check.php');
		return $ret;
	}

	public static function init_imap() {
		if(!isset($_SESSION['client']['apps_mailclient_user']) || Acl::get_user()!=$_SESSION['client']['apps_mailclient_user']) {
			$_SESSION['client']['apps_mailclient_user'] = Acl::get_user();
			eval_js('Apps_MailClient.cache_mailboxes_start()',false);
		}
	}
	
	public static function new_mailer($box,$name='') {
		$mailer = Base_MailCommon::new_mailer();
		$from = Apps_MailClientCommon::get_mailbox_data($box);
		$mailer->SetFrom($from['mail'],$name);
		$mailer->IsSMTP();
		$mailer->Username = $from['smtp_login'];
		$mailer->Password = $from['smtp_password'];
		$mailer->SMTPAuth = $from['smtp_auth'];
		$h = explode(':', $from['smtp_server']);
		$mailer->Host = $h[0];
		if(isset($h[1]))
			$mailer->Port = $h[1];
		else {
			if($from['smtp_ssl'])
				$mailer->Port = 465;
		}
		if($from['smtp_ssl'])
			$mailer->SMTPSecure = "ssl";
		return $mailer;
	}
	
	public static function apply_filters($box,$dir,$msg_id) {
		static $filters;

		$msg = self::parse_message($box,$dir,$msg_id);
		if($msg==false) return;

		if(!isset($filters)) $filters = array();
		if(!isset($filters[$box])) {
			$filters[$box] = DB::GetAll('SELECT id,match_method FROM apps_mailclient_filters WHERE account_id=%d',array($box));
			foreach($filters[$box] as & $filter) {
				$filter['rules'] = DB::GetAll('SELECT header,rule,value FROM apps_mailclient_filter_rules WHERE filter_id=%d',array($filter['id']));
				$filter['actions'] = DB::GetAll('SELECT action,value FROM apps_mailclient_filter_actions WHERE filter_id=%d',array($filter['id']));
			}
		}

		foreach($filters[$box] as $filter) {
			$match_method = self::filter_match_method($filter['match_method']);
			$match = ($match_method=='allmessages');
			if(!$match)
				foreach($filter['rules'] as $rule) {
					$match_this = false;
					
					if(!isset($msg['headers'][$rule['header']])) //TODO: is it ok?
						$header_value = '';
					else
						$header_value = $msg['headers'][$rule['header']];
					$ru = self::filter_rules_match($rule['rule']);
					switch($ru) {
						case 'contains':
							if(strpos($header_value,$rule['value'])!==false)
								$match_this = true;
							break;
						case 'notcontains':
							if(strpos($header_value,$rule['value'])===false)
								$match_this = true;
							break;
						case 'is':
							if($header_value==$rule['value'])
								$match_this = true;
							break;
						case 'notis':
							if($header_value!=$rule['value'])
								$match_this = true;
							break;
						case 'begins':
							if(strpos($header_value,$rule['value'])===0)
								$match_this = true;
							break;
						case 'ends':
							if(strpos($header_value,$rule['value'])===(strlen($header_value)-strlen($rule['value'])))
								$match_this = true;
							break;
					}
					
					//if "any rule" and matched this rule break and go to actions
					if($match_this && $match_method=='anyrule') {
						$match=true;
						break;
					}
					//if "all rules" required and not matched this one, break and skip to another filter
					if(!$match_this && $match_method=='allrules') {
						break;
					}
				}
			if(!$match) continue;
			//matched, go to actions
			foreach($filter['actions'] as $action) {
				$act = self::filter_actions($action['action']);
				switch($act) {
					case 'copy':
						$out_box = explode('/',$action['value'],2);
						Apps_MailClientCommon::copy_msg($box,$dir,$out_box[0],$out_box[1],$msg_id);
						break;
					case 'move':
						$out_box = explode('/',$action['value'],2);
						Apps_MailClientCommon::move_msg($box,$dir,$out_box[0],$out_box[1],$msg_id);
						break;
					case 'forward':
					case 'forward_delete':
						$mailer = self::new_mailer($box);
						$mailer->AddAddress($action['value']);
						//TODO: attachments
						$mailer->Subject = $msg['subject'];
						if($msg['type']=='plain') {
							$mailer->IsHTML(false);
							if(preg_match("/charset=([a-z0-9\-]+)/i",$msg['ctype'],$reqs)) {
								$charset = $reqs[1];
								$mailer->CharSet = $charset;
							}

						} else {
							$mailer->IsHTML(true);
							$mailer->AltBody = strip_tags($msg['body']);
						}
						$mailer->Body = $msg['body'];
						$send_ok = $mailer->Send();
						
						if($act=='forward') break;
					case 'delete':
						Apps_MailClientCommon::remove_msg($box,$dir,$msg_id,$imap);
						break;
					case 'read':
						Apps_MailClientCommon::read_msg($box,$dir,$msg_id);
						break;
				}
			}
		}
	}
}
on_init(array('Apps_MailClientCommon','init_imap'));
load_js('modules/Apps/MailClient/utils.js');
?>
