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
					array('name'=>'default_permission','label'=>__('Default notes permission'), 'type'=>'select', 'default'=>0, 'values'=>array(__('Public'),__('Protected'),__('Private')))
				)
			);
		}
		return array();
	}

	public static function get_where($group,$group_starts_with=true) {
		$ret = '';
		if(!Base_AclCommon::i_am_admin())
			$ret .= '(ual.permission<2 OR ual.permission_by='.Acl::get_user().') AND ';
		if($group_starts_with)
			return $ret.'ual.local '.DB::like().' \''.DB::addq($group).'%\'';
		else
			return $ret.'ual.local='.DB::qstr($group);
	}
	/**
	 * Example usage:
	 * Utils_AttachmentCommon::persistent_mass_delete('CRM/Contact'); // deletes all entries located in CRM/Contact*** group
	 */
	public static function persistent_mass_delete($group,$group_starts_with=true,array $selective=null) {
		if(isset($selective) && !empty($selective)) {
			$where = ' AND ual.id in ('.implode(',',$selective).')';
		} else
			$where = '';
		$ret = DB::Execute('SELECT ual.id,ual.local FROM utils_attachment_link ual WHERE '.self::get_where($group,$group_starts_with).$where);
		while($row = $ret->FetchRow()) {
			$id = $row['id'];
			$local = $row['local'];
			DB::Execute('DELETE FROM utils_attachment_note WHERE attach_id=%d',array($id));
			$mids = DB::GetCol('SELECT id FROM utils_attachment_file WHERE attach_id=%d',array($id));
			$file_base = self::Instance()->get_data_dir().$local.'/';
			foreach($mids as $mid) {
				@unlink($file_base.$mid);
				DB::Execute('DELETE FROM utils_attachment_download WHERE attach_file_id=%d',array($mid));
			}
			DB::Execute('DELETE FROM utils_attachment_file WHERE attach_id=%d',array($id));
			DB::Execute('DELETE FROM utils_attachment_link WHERE id=%d',array($id));
		}
	}
	
	public static function call_user_func_on_file($group,$func,$group_starts_with=false, $add_args=array()) {
		$ret = DB::Execute('SELECT f.id,ual.local, f.original, f.created_on
				    FROM utils_attachment_link ual INNER JOIN utils_attachment_file f ON (f.attach_id=ual.id)
				    WHERE ual.deleted=0 AND f.deleted=0 AND '.self::get_where($group,$group_starts_with));
		while($row = $ret->FetchRow()) {
			$id = $row['id'];
			$local = $row['local'];
			$file = self::Instance()->get_data_dir().$local.'/'.$id;
			if(file_exists($file))
    				call_user_func($func,$id,$file,$row['original'],$add_args,$row['created_on']);
		}
	}
	
	public static function add($group,$permission,$user,$note=null,$oryg=null,$file=null,$func=null,$args=null,$sticky=false,$note_title='',$crypted=false) {
		if(($oryg && !$file) || ($file && !$oryg))
		    trigger_error('Invalid add attachment call: missing original filename or temporary filepath',E_USER_ERROR);
		$link = array('local'=>$group,'permission'=>$permission,'permission_by'=>$user,'func'=>serialize($func),'args'=>serialize($args),'sticky'=>$sticky?1:0,'title'=>$note_title,'crypted'=>$crypted?1:0);
		DB::Execute('INSERT INTO utils_attachment_link(local,permission,permission_by,func,args,sticky,title,crypted) VALUES (%s,%d,%d,%s,%s,%b,%s,%b)',array_values($link));
		$link['id'] = $id = DB::Insert_ID('utils_attachment_link','id');
		DB::Execute('INSERT INTO utils_attachment_note(attach_id,text,created_by,revision) VALUES(%d,%s,%d,0)',array($id,$note,$user));
		if($file)
			self::add_file($link, $user, $oryg, $file);
		$param = explode('/', $group);
		if (isset($param[1]) && Utils_WatchdogCommon::get_category_id($param[0])!==null) {
			Utils_WatchdogCommon::new_event($param[0],$param[1],'N_+_'.$id);
		}
		return $id;
	}
	
	public static function add_file($note, $user, $oryg, $file) {
		if (is_numeric($note)) $note = Utils_RecordBrowserCommon::get_record('utils_attachment',$note);
        //DB::GetRow('SELECT * FROM utils_attachment_link WHERE id=%d', array($note));
		if($oryg===null) $oryg='';
		$local = self::Instance()->get_data_dir().$note['local'];
		if(!file_exists($local))
			mkdir($local,0777,true);
		DB::Execute('INSERT INTO utils_attachment_file(attach_id,original,created_by) VALUES(%d,%s,%d)',array($note['id'],$oryg,$user));
		$id = DB::Insert_ID('utils_attachment_file','id');
		$dest_file = $local.'/'.$id;
		rename($file,$dest_file);
	}

	public static function count($group=null,$group_starts_with=false) {
		return DB::GetOne('SELECT count(ual.id) FROM utils_attachment_link ual WHERE ual.deleted=0 AND '.self::get_where($group,$group_starts_with));
	}

	public static function get($group=null,$group_starts_with=false) {
		return DB::GetAll('SELECT ual.sticky,(SELECT l.login FROM user_login l WHERE ual.permission_by=l.id) as permission_owner,ual.permission,ual.permission_by,ual.local,uac.revision as note_revision,ual.id,uac.created_on as note_on,(SELECT l.login FROM user_login l WHERE uac.created_by=l.id) as note_by,uac.text FROM utils_attachment_link ual INNER JOIN utils_attachment_note uac ON uac.attach_id=ual.id WHERE '.self::get_where($group,$group_starts_with).' AND uac.revision=(SELECT max(x.revision) FROM utils_attachment_note x WHERE x.attach_id=uac.attach_id) AND ual.deleted=0');
	}

	public static function get_files($group=null,$group_starts_with=false) {
		return DB::GetAll('SELECT uaf.attach_id as note_id, uaf.id as file_id, created_by as upload_by, created_on as upload_on, original, (SELECT count(*) FROM utils_attachment_download uad WHERE uaf.id=uad.attach_file_id) as downloads FROM utils_attachment_file uaf INNER JOIN utils_attachment_link ual ON uaf.attach_id=ual.id WHERE '.self::get_where($group,$group_starts_with).' AND ual.deleted=0 AND uaf.deleted=0');
	}

	public static function search_group($group,$word,$view_func=false,$limit=-1) {
		$ret = array();
		$r = DB::SelectLimit('SELECT ual.local,ual.id,ual.func,ual.args FROM utils_attachment_link ual WHERE ual.deleted=0 AND '.
				'(0!=(SELECT count(uan.id) FROM utils_attachment_note AS uan WHERE uan.attach_id=ual.id AND uan.text '.DB::like().' '.DB::Concat(DB::qstr('%'),'%s',DB::qstr('%')).' AND uan.revision=(SELECT MAX(xxx.revision) FROM utils_attachment_note xxx WHERE xxx.attach_id=ual.id)) OR '.
				'0!=(SELECT count(uaf.id) FROM utils_attachment_file AS uaf WHERE uaf.attach_id=ual.id AND uaf.original '.DB::like().' '.DB::Concat(DB::qstr('%'),'%s',DB::qstr('%')).' AND uaf.deleted=0) OR '.
                '0!=(SELECT count(uaf.id) FROM utils_attachment_link AS uaf WHERE uaf.id=ual.id AND uaf.title '.DB::like().' '.DB::Concat(DB::qstr('%'),'%s',DB::qstr('%')).')) '.
				'AND '.self::get_where($group), $limit, -1, array($word,$word,));
		while($row = $r->FetchRow()) {
			$view = '';
			if($view_func) {
				$func = unserialize($row['func']);
				if($func) {
					$view = call_user_func_array($func,unserialize($row['args']));
				}
				if(!$view) continue;
				$ret[$row['id']] = __('Note').': '.$view;
			} else {
				$ret[] = array('id'=>$row['id'],'group'=>$row['local']);
			}
		}
		return $ret;
	}
	
	public static function search($word) {
        $limit = Base_SearchCommon::get_recordset_limit_records();
		$attachs = Utils_AttachmentCommon::search_group('',$word,true,$limit);
		return $attachs;
	}

	public static function move_notes($to_group, $from_group) {
		DB::Execute('UPDATE utils_attachment_link SET local=%s WHERE local=%s', array($to_group, $from_group));
        $local = self::Instance()->get_data_dir().dirname($to_group);
        if(!file_exists($local))
            mkdir($local,0777,true);
		if (is_dir(self::Instance()->get_data_dir().$from_group)) rename(self::Instance()->get_data_dir().$from_group, self::Instance()->get_data_dir().$to_group);
	}

	public static function copy_notes($from_group, $to_group) {
		$notes = self::get_files($from_group);
		$mapping = array();
		foreach ($notes as $n) {
			$file = self::Instance()->get_data_dir().$from_group.'/'.$n['file_id'];
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
		return DATA_DIR.'/Utils_Attachment/temp/'.Acl::get_user();
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

    public static function display_note($row, $nolink = false) {
        $inline_img = '';
        $link_href = '';
        $link_img = '';
        $icon = '';
        if(!$row['crypted'] || isset($_SESSION['client']['cp'.$row['id']])) {
            $files = DB::GetAll('SELECT id, created_by, created_on, original, (SELECT count(*) FROM utils_attachment_download uad WHERE uaf.id=uad.attach_file_id) as downloads FROM utils_attachment_file uaf WHERE uaf.attach_id=%d AND uaf.deleted=0', array($row['id']));
            foreach ($files as $f) {
                $f_filename = DATA_DIR.'/Utils_Attachment/'.$row['local'].'/'.$f['id'];
                if(file_exists($f_filename)) {
                    $filename = $f['original'];
                    $filetooltip = __('Filename: %s',array($filename)).'<br>'.__('File size: %s',array(filesize_hr($f_filename))).'<hr>'.
                        __('Last uploaded by %s', array(Base_UserCommon::get_user_label($f['created_by'], true))).'<br/>'.
                        __('On: %s',array(Base_RegionalSettingsCommon::time2reg($f['created_on']))).'<br/>'.
                        __('Number of downloads: %d',array($f['downloads']));
                    $view_link = '';
                    $lb = array();
                    $lb['local'] = $row['local'];
                    $lb['crypted'] = $row['crypted'];
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

        if($row['crypted']) {
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
        if ($text && $inline_img) $text = '<br/>';

        $text = $icon.$text.$inline_img;
        if($row['sticky']) $text = '<img src="'.Base_ThemeCommon::get_template_file('Utils_Attachment','sticky.png').'" hspace=3 align="left"> '.$text;

        return $text;
    }

    public static function QFfield_note(&$form, $field, $label, $mode, $default, $desc, $rb_obj) {
        if ($mode=='add' || $mode=='edit') {
            $fck = $form->createElement('ckeditor', $field, $label);
            $fck->setFCKProps('99%','300',true);

            load_js('modules/Utils/Attachment/js/lib/plupload.js');
            load_js('modules/Utils/Attachment/js/lib/plupload.flash.js');
            load_js('modules/Utils/Attachment/js/lib/plupload.browserplus.js');
            load_js('modules/Utils/Attachment/js/lib/plupload.html4.js');
            load_js('modules/Utils/Attachment/js/lib/plupload.html5.js');
            load_js('modules/Utils/Attachment/attachments.js');
            if (!isset($_SESSION['client']['utils_attachment'][CID])) $_SESSION['client']['utils_attachment'][CID] = array('files'=>array());
            eval_js('Utils_Attachment__init_uploader()');
            eval_js_once('var Utils_Attachment__delete_button = "'.Base_ThemeCommon::get_template_file('Utils_Attachment', 'delete.png').'";');
            eval_js_once('var Utils_Attachment__restore_button = "'.Base_ThemeCommon::get_template_file('Utils_Attachment', 'restore.png').'";');
            eval_js('Utils_Attachment__submit_note = function() {'.$form->get_submit_form_js().'}');

            $files = $form->createElement('static','files','','<div id="multiple_attachments">'.'<div id="filelist"></div></div>');
            Base_ActionBarCommon::add('add',__('Select files'),'id="pickfiles" href="javascript:void(0);"');
            $del = $form->addElement('hidden', 'delete_files', null, array('id'=>'delete_files'));
            $add = $form->addElement('hidden', 'clipboard_files', null, array('id'=>'clipboard_files'));
            $form->addGroup(array($fck,$files),$field,$label);

            Libs_QuickFormCommon::add_on_submit_action('if(uploader.files.length){uploader.start();return;}');

            if(isset($rb_obj->record['id'])) {
                $files = DB::GetAssoc('SELECT id, original FROM utils_attachment_file uaf WHERE uaf.attach_id=%d AND uaf.deleted=0', array($rb_obj->record['id']));
                foreach($files as $id=>$name) {
                    eval_js('Utils_Attachment__add_file_to_list("'.Epesi::escapeJS($name,true,false).'", null, '.$id.');');
			    }
            }

            if ($mode=='edit') $form->setDefaults(array($field=>array($field=>$default)));
        } else {
            $form->addElement('static', $field, $label);
            $form->setDefaults(array($field=>self::display_note($rb_obj->record)));
        }
    }

    public static function QFfield_crypted(&$form, $field, $label, $mode, $default, $desc, $rb_obj) {
        if ($mode=='view') {
            $elem = $form->addElement('checkbox', $field, $label,'', array('id'=>$field));
            $form->setDefaults(array($field=>$default));
            $elem->freeze(1);
        } else {
            $elems = array();
            $elems[] = $form->createElement('checkbox', $field, $label,'', array('id'=>$field,'onChange'=>'this.form.elements["note_password"].disabled=this.form.elements["note_password2"].disabled=!this.checked;'));
            $elems[] = $form->createElement('password','note_password',__('Password'), array('id'=>'note_password'));
            $elems[] = $form->createElement('password','note_password2',__('Confirm Password'), array('id'=>'note_password2'));
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
        $form->addElement('static', $field, $label);
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
        switch ($mode) {
            case 'adding':
                $values['date'] = time();
                return $values;
            case 'add':
            case 'edit':
                if(isset($values['crypted']['crypted']) && $values['crypted']['crypted']) {
                    $old_pass = isset($_SESSION['client']['cp'.$values['id']])?$_SESSION['client']['cp'.$values['id']]:'';
                    if($values['crypted']['note_password']=='*@#old@#*')
                        $values['crypted']['note_password'] = $old_pass;
                    $crypted = 1;
                }

                if($mode=='edit' && $old_pass!=$values['crypted']['note_password']) {
                    //reencrypt old revisions
                    $old_notes = DB::GetAssoc('SELECT hd.edit_id,hd.old_value FROM utils_attachment_edit_history h INNER JOIN utils_attachment_edit_history_data hd ON h.id=hd.edit_id WHERE h.utils_attachment_id=%d AND hd.field="note"', array($values['id']));
                    foreach($old_notes as $old_id=>$old_note) {
                        if($old_pass!==false) $old_note = Utils_AttachmentCommon::decrypt($old_note,$old_pass);
                        if($old_note===false) continue;
                        if($crypted && $values['crypted']['note_password']) $old_note = Utils_AttachmentCommon::encrypt($old_note,$values['crypted']['note_password']);
                        if($old_note===false) continue;
                        DB::Execute('UPDATE utils_attachment_edit_history_data SET old_value=%s WHERE edit_id=%d AND field="note"',array($old_note,$old_id));
                    }
                    //file reencryption
                    $old_files = DB::GetCol('SELECT uaf.id as id FROM utils_attachment_file uaf WHERE uaf.attach_id=%d',array($values['id']));
                    foreach($old_files as $old_id) {
                        $filename = DATA_DIR.'/Utils_Attachment/'.$values['local'].'/'.$old_id;
                        $content = @file_get_contents($filename);
                        if($content===false) continue;
                        if($old_pass!==false) $content = Utils_AttachmentCommon::decrypt($content,$old_pass);
                        if($content===false) continue;
                        if($crypted && $values['crypted']['note_password']) $content = Utils_AttachmentCommon::encrypt($content,$values['crypted']['note_password']);
                        if($content===false) continue;
                        file_put_contents($filename,$content);
                    }
                }

                if($crypted) {
                    $values['note'] = Utils_AttachmentCommon::encrypt($values['note'],$values['crypted']['note_password']);
                    $values['note_password']=$values['crypted']['note_password'];
                    $values['crypted'] = 1;
                } else {
                    $values['crypted'] = 0;
                }

                $values['note'] = $values['note']['note'];
                break;
            case 'added':
                $_SESSION['client']['cp'.$values['id']] = $values['note_password'];
                break;
            case 'display':
                if(DB::GetOne('SELECT 1 FROM utils_attachment_file WHERE attach_id=%d AND deleted=1',array($values['id']))) {
                    $ret = array();
                    $ret['new'] = array();
                    $ret['new']['crm_filter'] = '<a '.Utils_TooltipCommon::open_tag_attrs(__('File history')).' '.Module::create_href(array('set_crm_filter'=>1)).'>F</a>';
                    //if (isset($_REQUEST['set_crm_filter']))
                    //    CRM_FiltersCommon::set_profile('c'.$values['id']);
                    return $ret;
                }
        }
        switch($mode) {
            case 'edit':
            case 'added':
                $current_files = DB::GetAssoc('SELECT id, id FROM utils_attachment_file uaf WHERE uaf.attach_id=%d AND uaf.deleted=0', array($values['id']));
                $remaining_files = $current_files;
                //Epesi::alert(print_r($values,true));
                $deleted_files = array_filter(explode(';',$values['delete_files']));
                foreach ($deleted_files as $k=>$v) {
                    $deleted_files[$k] = intVal($v);
                    if (!isset($remaining_files[$v])) unset($deleted_files[$k]);
                    else unset($remaining_files[$v]);
                }
                $note_id = $values['id'];
                foreach ($deleted_files as $v)
                    DB::Execute('UPDATE utils_attachment_file SET deleted=1 WHERE id=%d', array($v));

                $clipboard_files = array_filter(explode(';',$values['clipboard_files']));
                foreach ($clipboard_files as $cf_id) {
                    $cf = DB::GetOne('SELECT filename FROM utils_attachment_clipboard WHERE id=%d', array($cf_id));
                    if($values['crypted'])
                        file_put_contents($cf,Utils_AttachmentCommon::encrypt(file_get_contents($cf),$values['note_password']));
                    Utils_AttachmentCommon::add_file($note_id, Acl::get_user(), __('clipboard').'.png', $cf);
                }

                $files = $_SESSION['client']['utils_attachment'][CID]['files'];
                $_SESSION['client']['utils_attachment'][CID]['files'] = array();
                foreach ($files as $f) {
                    if($values['crypted'])
                        file_put_contents($f,Utils_AttachmentCommon::encrypt(file_get_contents($f),$values['note_password']));
                    Utils_AttachmentCommon::add_file($note_id, Acl::get_user(), basename($f), $f);
                }

                break;
        }
        return $values;
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
        $f_filename = DATA_DIR.'/Utils_Attachment/'.$row['local'].'/'.$row['id'];
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
            $export_url = 'https://spreadsheets.google.com/feeds/download/spreadsheets/Export?id='.$edit_url.'&exportFormat=csv';

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
        } else $ext = 'csv';

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

}

?>
