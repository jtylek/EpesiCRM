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

class Utils_AttachmentCommon extends ModuleCommon {
	public static function admin_caption() {
		return array('label'=>__('Google Docs integration'), 'section'=>__('Server Configuration'));
	}

	public static function new_addon($table) {
		Utils_RecordBrowserCommon::new_addon($table, 'Utils/Attachment', 'body', 'Notes');
	}

	public static function delete_addon($table) {
		Utils_RecordBrowserCommon::delete_addon($table, 'Utils/Attachment', 'body');
	}

	public static function user_settings() {
		if(Acl::is_user()) {
			return array(
				__('Misc')=>array(
					array('name'=>'editor','label'=>__('Notes editor'), 'type'=>'select', 'default'=>0, 'values'=>array(__('Simple'),__('Advanced')))
				)
			);
		}
		return array();
	}

	public static function get_where($group,$group_starts_with=true) {
		if($group_starts_with)
			return DB::GetCol('SELECT attachment FROM utils_attachment_local WHERE local '.DB::like().' \''.DB::addq($group).'%\'');
		else
            return DB::GetCol('SELECT attachment FROM utils_attachment_local WHERE local='.DB::qstr($group));
	}
	/**
	 * Example usage:
	 * Utils_AttachmentCommon::persistent_mass_delete('CRM/Contact'); // deletes all entries located in CRM/Contact*** group
	 */
	public static function persistent_mass_delete($group,$group_starts_with=true,array $selective=null) {
        $ids = self::get_where($group,$group_starts_with);
        if(isset($selective) && !empty($selective))
            $ids = array_intersect($ids,$selective);
        foreach($ids as $id) {
            $mids = DB::GetCol('SELECT id FROM utils_attachment_file WHERE attach_id=%d',array($id));
            $file_base = self::Instance()->get_data_dir().$id.'/';
            foreach($mids as $mid) {
                @unlink($file_base.$mid);
                DB::Execute('DELETE FROM utils_attachment_download WHERE attach_file_id=%d',array($mid));
            }
            DB::Execute('DELETE FROM utils_attachment_file WHERE attach_id=%d',array($id));
            DB::Execute('DELETE FROM utils_attachment_local WHERE attachment=%d',array($id));
            Utils_RecordBrowserCommon::delete_record('utils_attachment',$id,true);
        }
	}
	
	public static function call_user_func_on_file($group,$func,$group_starts_with=false, $add_args=array()) {
	        $where = self::get_where($group,$group_starts_with);
	        if(!$where) return;
		$ret = DB::Execute('SELECT f.id, f.original, f.created_on, f.attach_id as aid
				    FROM utils_attachment_data_1 ual INNER JOIN utils_attachment_file f ON (f.attach_id=ual.id)
				    WHERE ual.active=1 AND f.deleted=0 AND ual.id IN ('.implode(',',$where).')');
		while($row = $ret->FetchRow()) {
			$id = $row['id'];
			$local = $row['aid'];
			$file = self::Instance()->get_data_dir().$local.'/'.$id;
			if(file_exists($file))
    				call_user_func($func,$id,$file,$row['original'],$add_args,$row['created_on']);
		}
	}

    public static function watchdog_label($rid = null, $events = array(), $details = true) {
        return Utils_RecordBrowserCommon::watchdog_label(
            'utils_attachment',
            __('Note'),
            $rid,
            $events,
            array('Utils_AttachmentCommon','note_title_with_attached_to'),
            $details
        );
    }

	public static function add($group,$permission,$user,$note=null,$oryg=null,$file=null,$func=null,$args=null,$sticky=false,$note_title='',$crypted=false) {
		if(($oryg && !$file) || ($file && !$oryg))
		    trigger_error('Invalid add attachment call: missing original filename or temporary filepath',E_USER_ERROR);

        $old_user = Acl::get_user();
        if($old_user!=$user) Acl::set_user($user);
        $id = Utils_RecordBrowserCommon::new_record('utils_attachment',array('local'=>$group,'note'=>$note,'permission'=>$permission,'func'=>serialize($func),'args'=>serialize($args),'sticky'=>$sticky?1:0,'title'=>$note_title,'crypted'=>$crypted?1:0));
        if($old_user!=$user) Acl::set_user($old_user);

		if($file)
			self::add_file($id, $user, $oryg, $file);
		return $id;
	}
	
	public static function add_file($note, $user, $oryg, $file) {
		if($oryg===null) $oryg='';
		$local = self::Instance()->get_data_dir().$note;
		if(!file_exists($local))
			mkdir($local,0777,true);
		DB::Execute('INSERT INTO utils_attachment_file(attach_id,original,created_by) VALUES(%d,%s,%d)',array($note,$oryg,$user));
		$id = DB::Insert_ID('utils_attachment_file','id');
		$dest_file = $local.'/'.$id;
		rename($file,$dest_file);
	}

	public static function count($group=null,$group_starts_with=false) {
		return Utils_RecordBrowserCommon::get_records_count('utils_attachment',array('id'=>self::get_where($group,$group_starts_with)));
	}

	public static function get($group=null,$group_starts_with=false) {
        $attachments = Utils_RecordBrowserCommon::get_records('utils_attachment',array('id'=>self::get_where($group,$group_starts_with)));
        foreach($attachments as &$a) {
            $a['permission_owner'] = $a['permission_by'] = $a['note_by'] = $a['created_by'];
            $a['local'] = '';//deprecated here
            $a['note_revision'] = 0; //deprecated
            $a['note_on'] = $a['created_on'];
            $a['text'] = $a['note'];
        }
		return $attachments;
	}

	public static function get_files($group=null,$group_starts_with=false) {
	        $where = self::get_where($group,$group_starts_with);
	        if(!$where) return array();
        $sql = 'SELECT uaf.attach_id as note_id,' .
               ' uaf.id as file_id,' .
               ' uaf.created_by as upload_by,' .
               ' uaf.created_on as upload_on,' .
               ' uaf.original,' .
               ' (SELECT count(*) FROM utils_attachment_download uad WHERE uaf.id=uad.attach_file_id) as downloads ' .
               'FROM utils_attachment_file uaf INNER JOIN utils_attachment_data_1 note' .
               ' ON uaf.attach_id=note.id ' .
               'WHERE note.id IN (' . implode(',', $where) . ') AND note.active=1 AND uaf.deleted=0';
        return DB::GetAll($sql);
	}

	public static function search($word, $types) {
	        if(!$types) return;
	        
	        $r = null;
                $limit = Base_SearchCommon::get_recordset_limit_records();
	        $ret = array();
                
                foreach($types as $type) {
                    if($type=='files') {
		        $r = DB::SelectLimit('SELECT ua.id,uaf.original,ual.func,ual.args,ual.local,ua.f_title FROM utils_attachment_data_1 ua INNER JOIN utils_attachment_local AS ual ON ual.attachment=ua.id INNER JOIN utils_attachment_file AS uaf ON uaf.attach_id=ua.id WHERE ua.active=1 AND '.
				' uaf.original '.DB::like().' '.DB::Concat(DB::qstr('%'),'%s',DB::qstr('%')).' AND uaf.deleted=0', $limit, -1, array($word));
                    } elseif($type=='downloads') {
                        $query = parse_url($word,PHP_URL_QUERY);
                        if($query) {
                            $vars = array();
                            parse_str($query,$vars);
                            if($vars && isset($vars['id']) && isset($vars['token'])) {
                                $query = 'SELECT ua.id,uaf.original,ual.func,ual.args,ual.local,ua.f_title FROM utils_attachment_file uaf INNER JOIN utils_attachment_download uad ON uad.attach_file_id=uaf.id INNER JOIN utils_attachment_data_1 ua ON uaf.attach_id=ua.id INNER JOIN utils_attachment_local AS ual ON ual.attachment=ua.id WHERE uad.id='.DB::qstr($vars['id']).' AND uad.token='.DB::qstr($vars['token']);
                                $r = DB::Execute($query);
                            }
                        }
                    }
                
                    if($r) {
		        while($row = $r->FetchRow()) {
		            if(!self::get_access($row['id'])) continue;
			    $func = unserialize($row['func']);
                            $record = $func ? call_user_func_array($func, unserialize($row['args'])) : '';
                            if(!$record) continue;
                            $title = $row['original'].' - '.self::description_callback(Utils_RecordBrowserCommon::get_record('utils_attachment',$row['id']));
                            $title = Utils_RecordBrowserCommon::record_link_open_tag('utils_attachment', $row['id'])
                                 . __('Files').': ' . $title
                                 . Utils_RecordBrowserCommon::record_link_close_tag();
                            $ret[$row['id'].'#'.$row['local']] = $title . " ($record)";
		        }
                    }
                }
                return $ret;
	}

	public static function search_categories() {
	        return array('files'=>__('Files'),'downloads'=>Utils_TooltipCommon::create(_('Downloads'),_('Paste file download remote URL as "Keyword"')));
	}

	public static function move_notes($to_group, $from_group) {
		DB::Execute('UPDATE utils_attachment_local SET local=%s WHERE local=%s', array($to_group, $from_group));
	}

	public static function copy_notes($from_group, $to_group) {
		$notes = self::get_files($from_group);
		$mapping = array();
		foreach ($notes as $n) {
			$file = self::Instance()->get_data_dir().$n['note_id'].'/'.$n['file_id'];
			if(file_exists($file)) {
				$file2 = $file.'_tmp';
				copy($file,$file2);
			} else {
				$file2 = null;
			}
			$mapping[$n['id']] = @Utils_AttachmentCommon::add($to_group,$n['permission'],Acl::get_user(),$n['text'],$n['original'],$file2);
		}
		return $mapping;
	}
	
	public static function is_image($note) {
		if (!is_string($note)) $note = $note['original'];
		return preg_match('/\.(jpg|jpeg|gif|png|bmp)$/i',$note);
	}

	public static function create_remote($file_id, $description, $expires_on) {
		$r = DB::GetRow('SELECT id, token FROM utils_attachment_download WHERE remote=1 AND attach_file_id=%d AND created_on>'.DB::DBTimeStamp(time()-3600).' AND created_by=%d',array($file_id,Acl::get_user()));
		if (!empty($r)) {
			$id = $r['id'];
			$token = $r['token'];
		} else {
			$token = md5($file_id.$expires_on);
			DB::Execute('INSERT INTO utils_attachment_download(remote,attach_file_id,created_by,created_on,expires_on,description,token) VALUES (1,%d,%d,%T,%T,%s,%s)',array($file_id,Acl::get_user(),time(),$expires_on,$description,$token));
			$id = DB::Insert_ID('utils_attachment_download','id');
		}
		return get_epesi_url().'/modules/Utils/Attachment/get_remote.php?'.http_build_query(array('id'=>$id,'token'=>$token));
	}
	
	public static function get_google_auth($user=null, $pass=null, $service="writely") {
		if ($user===null) {
			$user = Variable::get('utils_attachments_google_user', false);
			$pass = Variable::get('utils_attachments_google_pass', false);
			if (!$user) return false;
		}
		$company = CRM_ContactsCommon::get_company(CRM_ContactsCommon::get_main_company());

		$clientlogin_url = "https://www.google.com/accounts/ClientLogin";
		$clientlogin_post = array(
			"accountType" => "HOSTED_OR_GOOGLE",
			"Email" => $user,
			"Passwd" => $pass,
			"service" => $service,
			"source" => $company['company_name'].'-EPESI-'.'1.0'
		);

		$curl = curl_init($clientlogin_url);
		curl_setopt($curl, CURLOPT_POST, true);
		curl_setopt($curl, CURLOPT_POSTFIELDS, $clientlogin_post);
		curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
		$response = curl_exec($curl);
		curl_close($curl);

		preg_match("/Auth=([a-z0-9_-]+)/i", $response, $matches);
		$g_auth = @$matches[1];
		return $g_auth;
	}
	
	public static function get_temp_dir() {
	        $targetDir = DATA_DIR.'/Utils_Attachment/temp/'.Acl::get_user();
                if(!file_exists($targetDir))
                	mkdir($targetDir,0777,true);
		return $targetDir;
	}
	
	public static function cleanup_paste_temp() {
		DB::StartTrans();
		$ret = DB::Execute('SELECT * FROM utils_attachment_clipboard WHERE created_on<=%T', array(date('Y-m-d H:i:s', strtotime('-1 day'))));
		while ($row = $ret->FetchRow()) {
			DB::Execute('DELETE FROM utils_attachment_clipboard WHERE id=%d', array($row['id']));
			if ($row['filename']) @unlink($row['filename']);
		}
		DB::CompleteTrans();
	}

    public static function encrypt($input,$password) {
        $iv = '';
        $input .= md5($input);
        $encrypted = base64_encode(self::crypt($input,$password,self::ENCRYPT,$iv));
        return $encrypted."\n".base64_encode($iv);
    }

    public static function decrypt($input,$password) {
        list($note,$iv) = explode("\n",$input);
        $ret = rtrim(self::crypt(base64_decode($note),$password,self::DECRYPT,base64_decode($iv)),"\0"); //we can trim, because on the end there is md5 sum (100% text character is last char in file)
        $md5 = substr($ret,-32);
        $ret = substr($ret,0,-32);
        return md5($ret)==$md5?$ret:false;
    }

    const ENCRYPT = 1;
    const DECRYPT = 2;

    public static function crypt($input,$password,$mode,& $iv=null) {
        $td = mcrypt_module_open('rijndael-256', '', 'cbc', '');
        if(!$iv && $mode===self::ENCRYPT) $iv = mcrypt_create_iv(mcrypt_enc_get_iv_size($td));
        $iv2 = $iv;
        $ks = mcrypt_enc_get_key_size($td);
        $key = substr(sha1($password), 0, $ks);
        mcrypt_generic_init($td, $key, $iv2);
        if($mode==self::ENCRYPT)
            $ret = mcrypt_generic($td, $input);
        else
            $ret = mdecrypt_generic($td, $input);
        mcrypt_generic_deinit($td);
        mcrypt_module_close($td);
        return $ret;
    }

    public static function display_date($row, $nolink = false, $a=null,$view=false) {
        $date = Base_RegionalSettingsCommon::time2reg($row['edited_on'], false);
        $time = Base_RegionalSettingsCommon::time2reg($row['edited_on'], true, false);
        $separator = $nolink ? ' ' : '<br><br>';
        return "$date{$separator}$time";
    }
    
    public static function display_note($row, $nolink = false, $a=null,$view=false) {
        $inline_img = '';
        $link_href = '';
        $link_img = '';
        $icon = '';
        $crypted = Utils_RecordBrowserCommon::get_value('utils_attachment',$row['id'],'crypted');
        if(!$crypted || isset($_SESSION['client']['cp'.$row['id']])) {
            $files = DB::GetAll('SELECT id, created_by, created_on, original, (SELECT count(*) FROM utils_attachment_download uad WHERE uaf.id=uad.attach_file_id) as downloads FROM utils_attachment_file uaf WHERE uaf.attach_id=%d AND uaf.deleted=0', array($row['id']));
            foreach ($files as $f) {
                $f_filename = DATA_DIR.'/Utils_Attachment/'.$row['id'].'/'.$f['id'];
                if(file_exists($f_filename)) {
                    $filename = $f['original'];
                    $filetooltip = __('Filename: %s',array($filename)).'<br>'.__('File size: %s',array(filesize_hr($f_filename))).'<hr>'.
                        __('Last uploaded by %s', array(Base_UserCommon::get_user_label($f['created_by'], true))).'<br/>'.
                        __('On: %s',array(Base_RegionalSettingsCommon::time2reg($f['created_on']))).'<br/>'.
                        __('Number of downloads: %d',array($f['downloads']));
                    $view_link = '';
                    $lb = array();
                    $lb['aid'] = $row['id'];
                    $lb['crypted'] = $crypted;
                    $lb['original'] = $f['original'];
                    $lb['id'] = $f['id'];
                    $link_href = Utils_TooltipCommon::open_tag_attrs($filetooltip).' '.self::get_file_leightbox($lb,$view_link);
                    $link_img = Base_ThemeCommon::get_template_file('Utils_Attachment','z-attach.png');
                    if(Utils_AttachmentCommon::is_image($filename) && $view_link)
                        $inline_img .= '<hr><a href="'.$view_link.'" target="_blank"><img src="'.$view_link.'" style="max-width:700px" /></a><br>';
                } else {
                    $filename = __('Missing file: %s',array($f_filename));
                    $link_href = Utils_TooltipCommon::open_tag_attrs($filename);
                    $link_img = Base_ThemeCommon::get_template_file('Utils_Attachment','z-attach-off.png');
                }
                if ($link_href)
                    $icon .= '<div class="file_link"><a '.$link_href.'><img src="'.$link_img.'"><span class="file_name">'.$filename.'</span></a></div>';
            }
        }

        if($crypted) {
            $text = false;
            if(isset($_SESSION['client']['cp'.$row['id']])) {
                $note_pass = $_SESSION['client']['cp'.$row['id']];
                $decoded = Utils_AttachmentCommon::decrypt($row['note'],$note_pass);
                if($decoded!==false) $text = $decoded;
            }
            if($text===false) {
                $text = '<div id="note_value_'.$row['id'].'"><a href="javascript:void(0);" onclick="utils_attachment_password(\''.Epesi::escapeJS(__('Password').':').'\','.$row['id'].')" style="color:red">'.__('Note encrypted').'</a></div>';
                $icon = '';
                $files = array();
            }
        } else {
            $text = $row['note'];
        }

        $text = (!$view?'<b style="float:left;margin-right:30px;">'.$row['title'].'</b> ':'').$text.$icon.$inline_img;
        if($row['sticky']) $text = '<img src="'.Base_ThemeCommon::get_template_file('Utils_Attachment','sticky.png').'" hspace=3 align="left"> '.$text;

        return $text;
    }

    public static function display_attached_to($row, $nolink = false, $a=null,$view=false) {
        $locals = DB::GetCol('SELECT local FROM utils_attachment_local WHERE attachment=%d',array($row['id']));
        $ret = array();
        foreach ($locals as $local) {
            $param = explode('/', $local);
            if (count($param) == 2 && preg_match('/^[1-9][0-9]*$/', $param[1])) {
                $ret[] = Utils_RecordBrowserCommon::create_default_linked_label($param[0],$param[1],$nolink);
            }
        }
        return implode(', ',$ret);
    }
    
    public static function description_callback($row,$nolink=false) {
        if($row['title']) $ret = $row['title'];
        elseif($row['crypted']) $ret = $row['id'].' ('.__('encrypted note').')';
        else $ret = substr(strip_tags($row['note']),0,50);
        if(!$ret) $ret = $row['id'];
        return __('Note').': '.$ret;
    }

    public static function note_title_with_attached_to($row, $nolink = false) {
        $note = self::description_callback($row, $nolink);
        $of = Utils_RecordBrowserCommon::get_val('utils_attachment', 'attached_to', $row, $nolink);
        $of = " [ $of ]";
        return $note . $of;
    }

    public static function QFfield_note(&$form, $field, $label, $mode, $default, $desc, $rb_obj) {
        if($rb_obj->record['crypted']) {
            if(!(isset($rb_obj->record['id']) && isset($_SESSION['client']['cp'.$rb_obj->record['id']])) && !(isset($rb_obj->record['clone_id']) && isset($_SESSION['client']['cp'.$rb_obj->record['clone_id']]))) {
                Epesi::alert(__('Note encrypted.'));
                $x = ModuleManager::get_instance('/Base_Box|0');
                if(!$x) trigger_error('There is no base box module instance',E_USER_ERROR);
                return $x->pop_main();
            } else {
                if(isset($rb_obj->record['id']) && isset($_SESSION['client']['cp'.$rb_obj->record['id']]))
                    $note_pass = $_SESSION['client']['cp'.$rb_obj->record['id']];
                else
                    $note_pass = $_SESSION['client']['cp'.$rb_obj->record['clone_id']];
                $decoded = Utils_AttachmentCommon::decrypt($default,$note_pass);
                if($decoded!==false) $default = $decoded;
                else {
                    Epesi::alert(__('Note encrypted.'));
                    $x = ModuleManager::get_instance('/Base_Box|0');
                    if(!$x) trigger_error('There is no base box module instance',E_USER_ERROR);
                    return $x->pop_main();
                }
            }
        }
        if ($mode=='add' || $mode=='edit') {

            $fck = $form->addElement('ckeditor', $field, $label);
            $fck->setFCKProps('99%','300',Base_User_SettingsCommon::get(self::Instance()->get_type(),'editor'));

            load_js('modules/Utils/Attachment/js/lib/plupload.js');
            load_js('modules/Utils/Attachment/js/lib/plupload.flash.js');
            load_js('modules/Utils/Attachment/js/lib/plupload.browserplus.js');
            load_js('modules/Utils/Attachment/js/lib/plupload.html4.js');
            load_js('modules/Utils/Attachment/js/lib/plupload.html5.js');
            load_js('modules/Utils/Attachment/attachments.js');
            if (!isset($_SESSION['client']['utils_attachment'][CID])) $_SESSION['client']['utils_attachment'][CID] = array('files'=>array());
            eval_js('Utils_Attachment__init_uploader("'.floor(self::max_upload_size()/1024/1024).'mb")');
//            eval_js('alert("'.self::max_upload_size().'")');
            eval_js_once('var Utils_Attachment__delete_button = "'.Base_ThemeCommon::get_template_file('Utils_Attachment', 'delete.png').'";');
            eval_js_once('var Utils_Attachment__restore_button = "'.Base_ThemeCommon::get_template_file('Utils_Attachment', 'restore.png').'";');
            eval_js('Utils_Attachment__submit_note = function() {'.$form->get_submit_form_js().'}');

            $del = $form->addElement('hidden', 'delete_files', null, array('id'=>'delete_files'));
            $add = $form->addElement('hidden', 'clipboard_files', null, array('id'=>'clipboard_files'));

            Libs_QuickFormCommon::add_on_submit_action('if(uploader.files.length){uploader.start();return;}');

            if(isset($rb_obj->record['id']))
                $files = DB::GetAssoc('SELECT id, original FROM utils_attachment_file uaf WHERE uaf.attach_id=%d AND uaf.deleted=0', array($rb_obj->record['id']));
            elseif(isset($rb_obj->record['clone_id']))
                $files = DB::GetAssoc('SELECT id, original FROM utils_attachment_file uaf WHERE uaf.attach_id=%d AND uaf.deleted=0', array($rb_obj->record['clone_id']));
            else $files = array();
            foreach($files as $id=>$name) {
                eval_js('Utils_Attachment__add_file_to_list("'.Epesi::escapeJS($name,true,false).'", null, '.$id.');');
            }

            $form->setDefaults(array($field=>$default));
        } else {
            $form->addElement('static', $field, $label);
            $form->setDefaults(array($field=>self::display_note($rb_obj->record,false,null,true)));
            if(class_exists('ZipArchive')) {
                $files = DB::GetOne('SELECT 1 FROM utils_attachment_file uaf WHERE uaf.attach_id=%d AND uaf.deleted=0', array($rb_obj->record['id']));
                if($files) Base_ActionBarCommon::add('download','Download all attachments','href="'.self::Instance()->get_module_dir().'get_all.php?id='.$rb_obj->record['id'].'&cid='.CID.'" target="_blank"');
            }
        }
    }

    public static function QFfield_crypted(&$form, $field, $label, $mode, $default, $desc, $rb_obj) {
        if ($mode=='view') {
            $elem = $form->addElement('checkbox', $field, $label,'', array('id'=>$field));
            $form->setDefaults(array($field=>$default));
            $elem->freeze(1);
        } else {
            $elems = array();
            $elems[] = $form->createElement('checkbox', $field, '','', array('id'=>$field,'onChange'=>'this.form.elements["note_password"].disabled=this.form.elements["note_password2"].disabled=!this.checked;','style'=>'margin-right:40px;'));
            $elems[] = $form->createElement('static','note_password_label','',__('Password').':');
            $elems[] = $form->createElement('password','note_password',__('Password'), array('id'=>'note_password','style'=>'width:200px;margin-right:20px;'));
            $elems[] = $form->createElement('static','note_password2_label','',__('Confirm Password').':');
            $elems[] = $form->createElement('password','note_password2',__('Confirm Password'), array('id'=>'note_password2','style'=>'width:200px'));
            $form->addGroup($elems,$field,__('Encryption'));

            if($default) {
                $form->setDefaults(array('crypted'=>array('crypted'=>$default,'note_password'=>'*@#old@#*','note_password2'=>'*@#old@#*')));
            }
            $crypted = $form->exportValue($field);
            if(!$crypted) eval_js('$("note_password").disabled=1;$("note_password2").disabled=1;');

            $form->addFormRule(array('Utils_AttachmentCommon','crypted_rules'));
        }
    }

    public static function QFfield_date(&$form, $field, $label, $mode, $default, $desc, $rb_obj) {
        $form->addElement('static', $field, $label)->freeze();
        $form->setDefaults(array($field=>Base_RegionalSettingsCommon::time2reg($default,false,true,false)));
    }

    public static function crypted_rules($a) {
        if(isset($a['crypted']['crypted']) && $a['crypted']['crypted']) {
            if(empty($a['crypted']['note_password']))
                return array('crypted'=>__('Please provide password'));
            if($a['crypted']['note_password']!=$a['crypted']['note_password2'])
                return array('crypted'=>__('Password mismatch'));
        }
        return true;
    }

    public static function submit_attachment($values, $mode) {
        static $new_values, $old_password;
        switch ($mode) {
            case 'index':
                if($values['crypted']) unset($values['note']);
                return $values;
            case 'adding':
                $values['edited_on'] = time();
                return $values;
            case 'add':
            case 'edit':
                if(isset($values['__date'])) $values['edited_on'] = $values['__date'];
                else $values['edited_on'] = time();

                $crypted = 0;
                $old_pass = ($mode=='edit' && isset($_SESSION['client']['cp'.$values['id']]))?$_SESSION['client']['cp'.$values['id']]:($mode=='add' && isset($values['clone_id']) && isset($_SESSION['client']['cp'.$values['clone_id']])?$_SESSION['client']['cp'.$values['clone_id']]:'');
                if((is_array($values['crypted']) && isset($values['crypted']['crypted']) && $values['crypted']['crypted']) || (!is_array($values['crypted']) && $values['crypted'])) {
                    if(is_array($values['crypted']) && isset($values['crypted']['note_password'])) {
                        if($values['crypted']['note_password']=='*@#old@#*')
                            $values['crypted']['note_password'] = $old_pass;
                    }
                    $crypted = 1;
                }

                if(is_array($values['crypted']) && isset($values['crypted']['note_password']) && $mode=='edit' && $old_pass!=$values['crypted']['note_password']) {
                    //reencrypt old revisions
                    $old_notes = DB::GetAssoc('SELECT hd.edit_id,hd.old_value FROM utils_attachment_edit_history h INNER JOIN utils_attachment_edit_history_data hd ON h.id=hd.edit_id WHERE h.utils_attachment_id=%d AND hd.field="note"', array($values['id']));
                    foreach($old_notes as $old_id=>$old_note) {
                        if($old_pass!=='') $old_note = Utils_AttachmentCommon::decrypt($old_note,$old_pass);
                        if($old_note===false) continue;
                        if($crypted && $values['crypted']['note_password']) $old_note = Utils_AttachmentCommon::encrypt($old_note,$values['crypted']['note_password']);
                        if($old_note===false) continue;
                        DB::Execute('UPDATE utils_attachment_edit_history_data SET old_value=%s WHERE edit_id=%d AND field="note"',array($old_note,$old_id));
                    }
                    //file reencryption
                    $old_files = DB::GetCol('SELECT uaf.id as id FROM utils_attachment_file uaf WHERE uaf.attach_id=%d',array($values['id']));
                    foreach($old_files as $old_id) {
                        $filename = DATA_DIR.'/Utils_Attachment/'.$values['id'].'/'.$old_id;
                        $content = @file_get_contents($filename);
                        if($content===false) continue;
                        if($old_pass!=='') $content = Utils_AttachmentCommon::decrypt($content,$old_pass);
                        if($content===false) continue;
                        if($crypted && $values['crypted']['note_password']) $content = Utils_AttachmentCommon::encrypt($content,$values['crypted']['note_password']);
                        if($content===false) continue;
                        file_put_contents($filename,$content);
                    }
                }

                if($crypted) {
                    if(is_array($values['crypted']) && isset($values['crypted']['note_password'])) {
                        $values['note'] = Utils_AttachmentCommon::encrypt($values['note'],$values['crypted']['note_password']);
                        $values['note_password']=$values['crypted']['note_password'];
                    }
                    $values['crypted'] = 1;
                } else {
                    $values['crypted'] = 0;
                }
                $new_values = $values;

                break;
            case 'cloning':
                $values['clone_id']=$values['id'];
                break;
            case 'added':
                if(isset($values['local']))
                    DB::Execute('INSERT INTO utils_attachment_local(attachment,local,func,args) VALUES(%d,%s,%s,%s)',array($values['id'],$values['local'],$values['func'],$values['args']));
                $new_values = $values;
                break;
            case 'edit_changes':
                if(isset($values['note']) && isset($values['crypted']) && $new_values['crypted']!=$values['crypted']) {
                    if($new_values['crypted'] && isset($new_values['note_password'])) {
                        $values['note'] = Utils_AttachmentCommon::encrypt($values['note'],$new_values['note_password']);
                    } elseif(!$new_values['crypted'] && isset($_SESSION['client']['cp'.$new_values['id']])) {
                        $values['note'] = Utils_AttachmentCommon::decrypt($values['note'],$_SESSION['client']['cp'.$new_values['id']]);
                        unset($_SESSION['client']['cp'.$new_values['id']]);
                    }
                } elseif(isset($new_values['note_password']) && isset($old_password) && $new_values['note_password']!=$old_password) {
                    $values['note'] = Utils_AttachmentCommon::decrypt($values['note'],$old_password);
                    $values['note'] = Utils_AttachmentCommon::encrypt($values['note'],$new_values['note_password']);
                }
                break;
            case 'view':
                $ret = self::get_access($values['id']);
                if(!$ret) print(__('Access denied'));
                return $ret;
            case 'display':
                if(DB::GetOne('SELECT 1 FROM utils_attachment_file WHERE attach_id=%d',array($values['id']))) {
                    $ret = array();
                    $ret['new'] = array();
                    $ret['new']['crm_filter'] = '<a '.Utils_TooltipCommon::open_tag_attrs(__('File history')).' '.Module::create_href(array('file_history'=>1)).'>F</a>';
                    if (isset($_REQUEST['file_history']) && (!$values['crypted'] || isset($_SESSION['client']['cp'.$values['id']])))
                        Base_BoxCommon::push_module('Utils_Attachment','file_history',array($values));
                    //    CRM_FiltersCommon::set_profile('c'.$values['id']);
                    return $ret;
                }
                break;
            case 'delete':
                if($values['crypted'] && !isset($_SESSION['client']['cp'.$values['id']])) {
                    Epesi::alert(__('Cannot delete encrypted note'));
                    return false;
                }
                $count_locals = DB::GetOne('SELECT count(DISTINCT local) FROM utils_attachment_local WHERE attachment=%d',array($values['id']));
                if($count_locals>1) {
                    $is_local = false;
                    if(isset($_SESSION['client']['utils_attachment_group']))
                        $is_local = DB::GetOne('SELECT 1 FROM utils_attachment_local WHERE attachment=%d AND local=%s',array($values['id'],$_SESSION['client']['utils_attachment_group']));
                    if($is_local) {
                        DB::Execute('DELETE FROM utils_attachment_local WHERE attachment=%d AND local=%s',array($values['id'],$_SESSION['client']['utils_attachment_group']));
                        self::new_watchdog_event($_SESSION['client']['utils_attachment_group'], '-', $values['id']);
                    } else
                        Epesi::alert(__('This note is attached to multiple records - please go to record and delete note there.'));
                    location(array());
                    return false;
                } 
                location(array());
                return true;
        }
        switch($mode) {
            case 'edit':
            case 'added':
                if(isset($values['note_password'])) {
                    $old_password = isset($_SESSION['client']['cp' . $values['id']])
                        ? $_SESSION['client']['cp' . $values['id']] : '';
                    $_SESSION['client']['cp'.$values['id']] = $values['note_password'];
                }

                $note_id = $values['id'];
                $files_dir = self::Instance()->get_data_dir().$note_id;
                
                if(isset($values['delete_files']))
                    $deleted_files = array_filter(explode(';',$values['delete_files']));
                else
                    $deleted_files = array();
                foreach ($deleted_files as $k=>$v)
                    $deleted_files[$k] = intVal($v);
                $deleted_files = array_combine($deleted_files,$deleted_files);
                
                if($mode=='added' && isset($values['clone_id'])) { //on cloning
                    $locals = DB::Execute('SELECT local,func,args FROM utils_attachment_local WHERE attachment=%d',array($values['clone_id']));
                    while($local = $locals->FetchRow())
                        DB::Execute('INSERT INTO utils_attachment_local(attachment,local,func,args) VALUES(%d,%s,%s,%s)',array($note_id,$local['local'],$local['func'],$local['args']));
                    
                    $clone_files = DB::GetAll('SELECT id,original,created_by,created_on FROM utils_attachment_file uaf WHERE uaf.attach_id=%d AND uaf.deleted=0', array($values['clone_id']));
                    foreach($clone_files as $file) {
                        $cf = self::Instance()->get_data_dir().$values['clone_id'].'/'.$file['id'];
                        if(!file_exists($cf)) continue;
                        if(!file_exists($files_dir))
                            mkdir($files_dir,0777,true);

                        DB::Execute('INSERT INTO utils_attachment_file (attach_id,deleted,original,created_by,created_on) VALUES(%d,0,%s,%d,%T)',array($note_id,$file['original'],$file['created_by'],$file['created_on']));
                        $new_file_id = DB::Insert_ID('utils_attachment_file','id');
                        if(isset($deleted_files[$file['id']])) $deleted_files[$file['id']] = $new_file_id;

                        $cf2 = $files_dir.'/'.$new_file_id;
                        copy($cf,$cf2);
                        if(isset($_SESSION['client']['cp'.$values['clone_id']]) && $_SESSION['client']['cp'.$values['clone_id']])
                            file_put_contents($cf2,Utils_AttachmentCommon::decrypt(file_get_contents($cf2),$_SESSION['client']['cp'.$values['clone_id']]));
                        if($values['crypted'])
                            file_put_contents($cf2,Utils_AttachmentCommon::encrypt(file_get_contents($cf2),$values['note_password']));
                    }
                }

                $current_files = DB::GetAssoc('SELECT id, id FROM utils_attachment_file uaf WHERE uaf.attach_id=%d AND uaf.deleted=0', array($note_id));
                $remaining_files = $current_files;
                foreach ($deleted_files as $k=>$v) {
                    if (!isset($remaining_files[$v])) unset($deleted_files[$k]);
                    else unset($remaining_files[$v]);
                }
                foreach ($deleted_files as $v)
                    DB::Execute('UPDATE utils_attachment_file SET deleted=1 WHERE id=%d', array($v));

                if(isset($values['clipboard_files'])) {
                    $clipboard_files = array_filter(explode(';',$values['clipboard_files']));
                    foreach ($clipboard_files as $cf_id) {
                        $cf = DB::GetOne('SELECT filename FROM utils_attachment_clipboard WHERE id=%d', array($cf_id));
                        if($values['crypted'])
                            file_put_contents($cf,Utils_AttachmentCommon::encrypt(file_get_contents($cf),$values['note_password']));
                        Utils_AttachmentCommon::add_file($note_id, Acl::get_user(), __('clipboard').'.png', $cf);
                    }
                }

                $files = isset($_SESSION['client']['utils_attachment'][CID]['files'])?$_SESSION['client']['utils_attachment'][CID]['files']:array();
                $_SESSION['client']['utils_attachment'][CID]['files'] = array();
                foreach ($files as $f) {
                    if($values['crypted'])
                        file_put_contents($f,Utils_AttachmentCommon::encrypt(file_get_contents($f),$values['note_password']));
                    Utils_AttachmentCommon::add_file($note_id, Acl::get_user(), basename($f), $f);
                }

                $locals = DB::GetCol('SELECT local FROM utils_attachment_local WHERE attachment=%d',array($note_id));
                foreach ($locals as $local) {
                    $param = explode('/', $local);
                    if (count($param) == 2 && preg_match('/^[1-9][0-9]*$/', $param[1])) {
                        $subscribers = Utils_WatchdogCommon::get_subscribers($param[0], $param[1]);
                        foreach ($subscribers as $user_id) {
                            Utils_WatchdogCommon::user_subscribe($user_id, 'utils_attachment', $note_id);
                        }
                    }
                }

                break;
        }
        return $values;
    }
    
    public static function get_access($id) {
        $locals = DB::GetCol('SELECT local FROM utils_attachment_local WHERE attachment=%d',array($id));
        $ret = false;
        foreach($locals as $local) {
            list($recordset,$key) = explode('/',$local,2);
            if(!Utils_RecordBrowserCommon::check_table_name($recordset, false, false)
               || !is_numeric($key)
               || Utils_RecordBrowserCommon::get_access($recordset,'view',$key)) {
                $ret = true;
                break;
            }
        }
        return $ret;
    }

    public static function get_file_leightbox($row, & $view_link = '') {
        static $th;
        if(!isset($th)) $th = Base_ThemeCommon::init_smarty();

        if($row['original']==='') return '';

        $links = array();

        $lid = 'get_file_'.md5(serialize($row));
        if(isset($_GET['save_google_docs']) && $_GET['save_google_docs']==$lid) {
            self::save_google_docs($row['id']);
        }
        if(isset($_GET['discard_google_docs']) && $_GET['discard_google_docs']==$lid) {
            self::discard_google_docs($row['id']);
        }

        $close_leightbox_js = 'leightbox_deactivate(\''.$lid.'\');';
        if (Variable::get('utils_attachments_google_user',false) && preg_match('/\.(xlsx?|docx?|txt|odt|ods|csv)$/i',$row['original'])) {
            $label = __('Open with Google Docs');
            $label = explode(' ', $label);
            $mid = floor(count($label) / 2);
            $label = implode('&nbsp;', array_slice($label, 0, $mid)).' '.implode('&nbsp;', array_slice($label, $mid));
            $script = 'get_google_docs';
            $onclick = '$(\'attachment_save_options_'.$row['id'].'\').style.display=\'\';$(\'attachment_download_options_'.$row['id'].'\').hide();';
            $th->assign('save_options_id','attachment_save_options_'.$row['id']);
            $links['save'] = '<a href="javascript:void(0);" onclick="'.$close_leightbox_js.Module::create_href_js(array('save_google_docs'=>$lid)).'">'.__('Save Changes').'</a><br>';
            $links['discard'] ='<a href="javascript:void(0);" onclick="'.$close_leightbox_js.Module::create_href_js(array('discard_google_docs'=>$lid)).'">'.__('Discard Changes').'</a><br>';
        } else {
            $label = __('View');
            $th->assign('save_options_id','');
            $script = 'get';
            $onclick = $close_leightbox_js;
        }
        $th->assign('download_options_id','attachment_download_options_'.$row['id']);

        $view_link = 'modules/Utils/Attachment/'.$script.'.php?'.http_build_query(array('id'=>$row['id'],'cid'=>CID,'view'=>1));
        $links['view'] = '<a href="'.$view_link.'" target="_blank" onClick="'.$onclick.'">'.$label.'</a><br>';
        $links['download'] = '<a href="modules/Utils/Attachment/get.php?'.http_build_query(array('id'=>$row['id'],'cid'=>CID)).'" onClick="leightbox_deactivate(\''.$lid.'\')">'.__('Download').'</a><br>';

        load_js('modules/Utils/Attachment/remote.js');
        if(!$row['crypted']) {
            $links['link'] = '<a href="javascript:void(0)" onClick="utils_attachment_get_link('.$row['id'].', '.CID.',\'get link\');leightbox_deactivate(\''.$lid.'\')">'.__('Get link').'</a><br>';
        }
        $th->assign('filename',$row['original']);
        $f_filename = DATA_DIR.'/Utils_Attachment/'.$row['aid'].'/'.$row['id'];
        if(!file_exists($f_filename)) return 'missing file: '.$f_filename;
        $th->assign('file_size',__('File size: %s',array(filesize_hr($f_filename))));

        $th->assign('labels',array(
            'filename'=>__('Filename'),
            'file_size'=>__('File size')
        ));

        foreach($links as $key=>&$l) {
            $th->assign($key,$l);
            $l = Base_ThemeCommon::parse_links($key, $l);
        }
        $th->assign('__link',$links);

        $custom_getters = array();
        if(!$row['crypted']) {
            $getters = ModuleManager::call_common_methods('attachment_getters');
            foreach($getters as $mod=>$arr) {
                if (is_array($arr))
                    foreach($arr as $caption=>$func) {
                        $cus_id = md5($mod.$caption.serialize($func));
                        if(isset($_GET['utils_attachment_custom_getter']) && $_GET['utils_attachment_custom_getter']==$cus_id)
                            call_user_func_array(array($mod.'Common',$func['func']),array($f_filename,$row['original'],$row['id']));
                        $custom_getters[] = array('open'=>'<a href="javascript:void(0)" onClick="'.Epesi::escapeJS(Module::create_href_js(array('utils_attachment_custom_getter'=>$cus_id)),true,false).';leightbox_deactivate(\''.$lid.'\')">','close'=>'</a>','text'=>$caption,'icon'=>$func['icon']);
                    }
            }
        }
        $th->assign('custom_getters',$custom_getters);

        ob_start();
        Base_ThemeCommon::display_smarty($th,'Utils_Attachment','download');
        $c = ob_get_clean();

        Libs_LeightboxCommon::display($lid,$c,__('Attachment'));
        return Libs_LeightboxCommon::get_open_href($lid);
    }

    public static function save_google_docs($note_id) {
        $edit_url = DB::GetOne('SELECT doc_id FROM utils_attachment_googledocs WHERE note_id = %d', array($note_id));
        if (!$edit_url) {
            Base_StatusBarCommon::message(__('Document not found'), 'warning');
            return false;
        }
        if(!preg_match('/(spreadsheet|document)%3A(.+)$/i',$edit_url,$matches)) {
            Base_StatusBarCommon::message(__('Document not found'), 'warning');
            return false;
        }
        $edit_url = $matches[2];
        $doc = $matches[1]=='document';
        if ($doc)
            $export_url = 'https://docs.google.com/feeds/download/documents/Export?id='.$edit_url.'&exportFormat=doc';
        else
            $export_url = 'https://spreadsheets.google.com/feeds/download/spreadsheets/Export?key='.$edit_url.'&exportFormat=xls';

        DB::Execute('DELETE FROM utils_attachment_googledocs WHERE note_id = %d', array($note_id));
        $g_auth = Utils_AttachmentCommon::get_google_auth(null, null, $doc?'writely':'wise');
        $curl = curl_init();

        curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);

        $headers = array(
            "Authorization: GoogleLogin auth=" . $g_auth,
            "If-Match: *",
            "GData-Version: 3.0",
        );
        curl_setopt($curl, CURLOPT_URL, $export_url);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'GET');
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_POST, false);
        $response = curl_exec_follow($curl);

        $row = DB::GetRow('SELECT f.*,l.f_crypted as crypted FROM utils_attachment_file f INNER JOIN utils_attachment_data_1 l ON l.id=f.attach_id WHERE f.id=%d',array($note_id));

        $local = DATA_DIR.'/Utils_Attachment/temp/'.Acl::get_user().'/gdocs';
        @mkdir($local,0777,true);
        $dest_file = $local.'/'.$row['id'];

        if($row['crypted']) {
            $password = $_SESSION['client']['cp'.$row['attach_id']];
            $response = Utils_AttachmentCommon::encrypt($response,$password);
        }
        file_put_contents($dest_file, $response);
        if($doc) {
            $ext = 'docx';
        } else $ext = 'xlsx';

        $row['original'] = substr($row['original'],0,strrpos($row['original'],'.')).'.'.$ext;

        Utils_AttachmentCommon::add_file($row['attach_id'], Acl::get_user(), $row['original'], $dest_file);
        DB::Execute('UPDATE utils_attachment_file SET deleted=1 WHERE id=%d',array($row['id']));

        $headers = array(
            "Authorization: GoogleLogin auth=" . $g_auth,
            "If-Match: *",
            "GData-Version: 3.0",
        );
        curl_setopt($curl, CURLOPT_URL, $edit_url);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'DELETE');
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_POST, false);
        $response = curl_exec($curl);

        Base_StatusBarCommon::message(__('Changes saved'));
    }

    public static function discard_google_docs($note_id) {
        $edit_url = DB::GetOne('SELECT doc_id FROM utils_attachment_googledocs WHERE note_id = %d', array($note_id));
        DB::Execute('DELETE FROM utils_attachment_googledocs WHERE note_id = %d', array($note_id));
        $g_auth = Utils_AttachmentCommon::get_google_auth();
        $curl = curl_init();

        curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);

        $headers = array(
            "Authorization: GoogleLogin auth=" . $g_auth,
            "If-Match: *",
            "GData-Version: 3.0",
        );
        curl_setopt($curl, CURLOPT_URL, $edit_url);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'DELETE');
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_POST, false);
        $response = curl_exec($curl);
        Base_StatusBarCommon::message(__('Changes discarded'));
    }
    
    //got from: http://www.kavoir.com/2010/02/php-get-the-file-uploading-limit-max-file-size-allowed-to-upload.html
    private static function max_upload_size() {
        $normalize = function($size) {
            if (preg_match('/^([\d\.]+)([KMG])$/i', $size, $match)) {
                $pos = array_search($match[2], array('K', 'M', 'G'));
                if ($pos !== false) {
                    $size = $match[1] * pow(1024, $pos + 1);
                }
            }
            return $size;
        };
        $max_upload = $normalize(ini_get('upload_max_filesize'));
        $max_post = (ini_get('post_max_size') == 0) ?2*1024*1024: $normalize(ini_get('post_max_size'));
        $memory_limit = (ini_get('memory_limit') == -1) ?$max_post : $normalize(ini_get('memory_limit'));
        if($memory_limit < $max_post || $memory_limit < $max_upload) return $memory_limit;
        if($max_post < $max_upload) return $max_post;
        $maxFileSize = min($max_upload, $max_post, $memory_limit);
        return $maxFileSize;
    }

    /**
     * Create new watchdog event for record if $group denotes record.
     *
     * @param string $group   <Recordset>/<Id>
     * @param string $action  Action string
     * @param int    $note_id Note id
     *
     * @return bool True if events has been created, false otherwise
     */
    public static function new_watchdog_event($group, $action, $note_id)
    {
        $param = explode('/', $group);
        if (count($param)==2 && preg_match('/^[1-9][0-9]*$/', $param[1])) {
            Utils_WatchdogCommon::new_event($param[0], $param[1], implode('_', array('N', $action, $note_id, time(), Base_AclCommon::get_user())));
            return true;
        }
        return false;
    }
}

?>
