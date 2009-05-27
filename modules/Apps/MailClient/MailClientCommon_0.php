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
		$arr = array('move','copy','forward','read','delete');
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
		$ret = DB::GetAll('SELECT id,mail FROM apps_mailclient_accounts WHERE user_login_id=%d',array(Acl::get_user()));
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

	public static function create_mailbox_dir($id) {
		//TODO: check protocol, on imap get lsub and create dirs
		$acc_dir = self::Instance()->get_data_dir().$id.'/';
		if (!is_dir($acc_dir)) mkdir($acc_dir,0777,true);
		$dirs = array('Inbox','Sent','Trash','Drafts');
		file_put_contents($acc_dir.'.dirs',implode(",",$dirs));
		foreach($dirs as $d)
			mkdir($acc_dir.$d);
	}

	public static function create_internal_mailbox($user=null) {
		if($user===null) $user = Acl::get_user();
		DB::Execute('INSERT INTO apps_mailclient_accounts(user_login_id,mail,login,password,incoming_server,incoming_protocol) VALUES(%d,\'#internal\',\'\',\'\',\'\',2)',array($user));
		$id = DB::Insert_ID('apps_mailclient_accounts','id');
		Apps_MailClientCommon::create_mailbox_dir($id);
		return $id;
	}

	//gets mailbox dir
	public static function get_mailbox_dir($id) {
		if(!Acl::is_user()) return false;
		$ret = DB::GetOne('SELECT 1 FROM apps_mailclient_accounts WHERE user_login_id=%d AND id=%d',array(Acl::get_user(),$id));
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

	//gets next message id
	public static function get_next_msg_id($id,$dir) {
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
		$nid++;
		file_put_contents($mailbox.'.mid',$nid);
		return $nid;
	}

	//drops message to specified mailbox
	public static function drop_message($mailbox_id,$dir,$subject,$from,$to,$date,$body,$read=false) {
		ini_set('include_path','modules/Apps/MailClient/PEAR'.PATH_SEPARATOR.ini_get('include_path'));
		require_once('Mail/mime.php');

		$msg_id = self::get_next_msg_id($mailbox_id,$dir);
		if($msg_id===false) return false;

		$mime = new Mail_Mime();
		$headers = array();
        $headers['From'] = $from;
        $headers['To'] = $to;
	 	$headers['Subject'] = $subject;
		$headers['Date'] = $date;
		$mime->headers($headers);
		$mime->setHTMLBody($body);
		$mbody = $mime->getMessage();
		$mbox_dir = self::get_mailbox_dir($mailbox_id);
		if($mbox_dir===false) return false;
		$mailbox = $mbox_dir.$dir;
		Apps_MailClientCommon::append_msg_to_index($mailbox_id,$dir,$msg_id,$subject,$from,$to,$date,strlen($mbody),$read);
		file_put_contents($mailbox.$msg_id,$mbody);

		return $msg_id;
	}

/*
	public static function imap_open($v) {
		if(!is_array($v))
			$v = DB::GetRow('SELECT * FROM apps_mailclient_accounts WHERE id=%d',array($v));

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

		$imap_ref = '{'.$host.':'.$port.'/imap'.($ssl?'/ssl/novalidate-cert':'').'}';
		$imap = @imap_open($imap_ref, $user,$pass, OP_HALFOPEN);
		return array(& $imap, $imap_ref, $v['mail']);
	}

	public static function imap_refresh_folders($id,$ref=null,$account=null) {
		if($ref===null) {
			list($imap,$ref,$account) = self::imap_open($id);
		} else {
			$imap = $id;
		}
		$sub = false;
		if ($imap!==false) {
			if(is_array($list = imap_lsub($imap, $ref, "*"))) {
				$imap_ref_len = strlen($ref);
				$sub = array();
				$dir = self::Instance()->get_data_dir().Acl::get_user().'/'; //mail data dir
				if(!file_exists($dir)) mkdir($dir);
				$acc_dir = $dir.self::mailname2dirname($account).'/';
				if(!file_exists($acc_dir))
					mkdir($acc_dir);
		    	foreach ($list as $val) {
					$box = imap_utf7_decode($val);
					$box = substr($box,$imap_ref_len);
					if(!file_exists($acc_dir.$box))
						mkdir($acc_dir.$box,0777,true);

					$x = explode('/',$box);
					$y = & $sub;
					foreach($x as $v) {
						if(!isset($y[$v])) $y[$v] = array('label'=>$v, 'name'=>$box, 'sub'=>array());
							$y = & $y[$v]['sub'];
			    	}
				}
			}
			imap_close($imap);
		}
		return $sub;
	}

 */
	public static function build_index($id,$dir) {
		ini_set('include_path','modules/Apps/MailClient/PEAR'.PATH_SEPARATOR.ini_get('include_path'));
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
			fputcsv($out, array($f,isset($structure->headers['subject'])?substr($structure->headers['subject'],0,256):'no subject',substr($structure->headers['from'],0,256),substr($structure->headers['to'],0,256),substr($structure->headers['date'],0,64),substr(strlen($message),0,64),'0'));
			$c++;
			if($f>$max) $max=$f;
		}
		fclose($out);
		file_put_contents($boxpath.'.num',$c.','.$c);
		file_put_contents($boxpath.'.mid',$max);
		return true;
	}

	public static function mark_all_as_read($id,$dir) {
		$mbox_dir = self::get_mailbox_dir($id);
		if($mbox_dir===false) return false;
		$box = $mbox_dir.$dir;
		$in = @fopen($box.'.idx','r');
		if($in==false) return false;
		$ret = array();
		while (($data = fgetcsv($in, 700)) !== false) { //teoretically max is 640+integer and commas
			$num = count($data);
			if($num!=7) continue;
			$data[6]=1;
			$ret[] = $data;
		}
		fclose($in);

		$out = @fopen($box.'.idx','w');
		if($out==false) return false;
		$c = 0;
		foreach($ret as $d) {
			fputcsv($out, $d);
			$c++;
		}
		fclose($out);
		file_put_contents($box.'.num',$c.',0');
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

	public static function remove_msg($mailbox_id, $dir, $id) {
		if(!self::remove_msg_from_index($mailbox_id,$dir,$id)) return false;

		$mbox_dir = self::get_mailbox_dir($mailbox_id);
		if($mbox_dir===false) return false;
		$box = $mbox_dir.$dir;
		@unlink($box.$id);

		return true;
	}

	public static function remove_msg_from_index($mailbox_id,$dir,$id) {
		$idx = self::get_index($mailbox_id,$dir);

		if($idx===false || !isset($idx[$id])) return false;
		unset($idx[$id]);

		$mbox_dir = self::get_mailbox_dir($mailbox_id);
		if($mbox_dir===false) return false;
		$box = $mbox_dir.$dir;
		$out = @fopen($box.'.idx','w');
		if($out==false) return false;

		$c = 0;
		$ur = 0;
		foreach($idx as $id=>$d) {
			fputcsv($out, array($id,substr($d['subject'],0,256),substr($d['from'],0,256),substr($d['to'],0,256),substr($d['date'],0,64),substr($d['size'],0,64),$d['read']));
			if(!$d['read']) $ur++;
			$c++;
		}
		fclose($out);
		file_put_contents($box.'.num',$c.','.$ur);
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

		$id2 = self::get_next_msg_id($box2,$dir2);
		if($id2===false) return false;

		file_put_contents($boxpath2.$id2,$msg);
		$idx = self::get_index($box,$dir);
		$idx = $idx[$id];
		if(!self::append_msg_to_index($box2,$dir2,$id2,$idx['subject'],$idx['from'],$idx['to'],$idx['date'],$idx['size'],$idx['read']))
			return false;

		if(!self::remove_msg($box,$dir,$id)) return false;

		if($dir=='Trash/') {		
			$trashpath = $boxpath.'.del';
			$in = @fopen($trashpath,'r');
			if($in!==false) {
				$ret = array();
				while (($data = fgetcsv($in, 700)) !== false) {
					$num = count($data);
					if($num!=2 || $data[0]==$id) continue;
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
		fputcsv($out,array($id, substr($subject,0,256), substr($from,0,256), substr($to,0,256), substr($date,0,64), substr($size,0,64),$read?'1':'0'));
		fclose($out);
		file_put_contents($mailbox.'.num',($num[0]+1).','.($num[1]+($read?0:1)));
		return true;
	}

	public static function read_msg($box,$dir, $id) {
		$idx = self::get_index($box,$dir);

		if($idx===false || !isset($idx[$id])) return false;
		if($idx[$id]['read']) return true;
		$idx[$id]['read'] = '1';

		$mbox_dir = self::get_mailbox_dir($box);
		if($mbox_dir===false) return false;
		$boxpath = $mbox_dir.$dir;

		$num = @file_get_contents($boxpath.'.num');
		if($num===false) return false;
		$num = explode(',',$num);
		if(count($num)!=2) return false;

		$out = @fopen($boxpath.'.idx','w');
		if($out==false) return false;

		foreach($idx as $id=>$d)
			fputcsv($out, array($id,substr($d['subject'],0,256),substr($d['from'],0,256),substr($d['to'],0,256),substr($d['date'],0,64),substr($d['size'],0,64),$d['read']));
		fclose($out);
		file_put_contents($boxpath.'.num',$num[0].','.($num[1]-1));
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

	public static function get_message_structure($box,$dir,$id) {
		$box_dir = Apps_MailClientCommon::get_mailbox_dir($box);
		if($box_dir===false)
			return false;
		$box = $box_dir.$dir;
		$message = @file_get_contents($box.'/'.$id);
		if($message===false)
			return false;

		ini_set('include_path','modules/Apps/MailClient/PEAR'.PATH_SEPARATOR.ini_get('include_path'));
		require_once('Mail/mimeDecode.php');
		$decode = new Mail_mimeDecode($message, "\r\n");
		$structure = $decode->decode(array('decode_bodies'=>true,'include_bodies'=>true));
		if(!isset($structure->headers['from']))
			$structure->headers['from'] = '';
		if(!isset($structure->headers['to']))
			$structure->headers['to'] = '';
		if(!isset($structure->headers['date']))
			$structure->headers['date'] = '';
		return $structure;
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
						$attachments[$part->ctype_parameters['name']] = array('type'=>isset($part->headers['content-type'])?$part->headers['content-type']:false,'body'=>$part->body,'id'=>$coid,'disposition'=>$disposition);
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
		$subject = isset($structure->headers['subject'])?Apps_MailClientCommon::mime_header_decode($structure->headers['subject']):'no subject';
		return array('body'=>$body, 'subject'=>$subject,'type'=>$body_type, 'ctype'=>$body_ctype, 'attachments'=>$attachments);
	}
	
	public static function parse_message($box,$dir,$id,$structure=true) {
		$str = self::get_message_structure($box,$dir,$id);
		if($str===false) return false;
		return array_merge(self::parse_message_structure($str),array('headers'=>$str->headers));
	}

	public static function addressbook_rp_mail($e){
		return CRM_ContactsCommon::contact_format_default($e,true);
	}
}

?>
