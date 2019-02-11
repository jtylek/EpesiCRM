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

	public static function new_addon($table, $caption = 'Notes') {
		Utils_RecordBrowserCommon::new_addon($table, Utils_Attachment::module_name(), 'body', $caption);
	}

	public static function delete_addon($table) {
		Utils_RecordBrowserCommon::delete_addon($table, Utils_Attachment::module_name(), 'body');
	}

	public static function user_settings() {
		if(Acl::is_user()) {
            $info = '%D - ' . __('Date') . '<br>%T - ' . __('Time') . '<br>%U - ' . __('User');
            $help = ' <img src="'.Base_ThemeCommon::get_icon('info').'" '.Utils_TooltipCommon::open_tag_attrs($info, false).'/>';
			return array(
				__('Notes')=>array(
					array('name'=>'editor','label'=>__('Editor'), 'type'=>'select', 'default'=>0, 'values'=>array(__('Simple'),__('Advanced'))),
                    array('name' => 'edited_on_format', 'label' => __('Edited on format') . $help, 'type' => 'text', 'default' => '%D<br><br>%T<br><br>%U')
				)
			);
		}
		return array();
	}

	public static function get_where($group,$group_starts_with=true) {
		if($group_starts_with)
			return DB::GetCol('SELECT id FROM utils_attachment_data_1 WHERE f_attached_to '.DB::like().' \'\_\_'.DB::addq($group).'%\'');
		else
			return DB::GetCol('SELECT id FROM utils_attachment_data_1 WHERE f_attached_to='.DB::qstr('__' . $group. '__'));
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
        	$note = self::get_note($id);
            DB::StartTrans();            
            foreach($note['files'] as $fsid) {
                Utils_FileStorageCommon::delete($fsid);
            }
            DB::CompleteTrans();
            Utils_RecordBrowserCommon::delete_record('utils_attachment',$id,true);
        }
	}
	
	public static function call_user_func_on_file($group,$func,$group_starts_with=false, $add_args=array()) {
		$ids = self::get_where($group,$group_starts_with);
		if(!$ids) return;
		foreach($ids as $id) {
			$note = self::get_note($id);
			
			foreach($note['files'] as $fsid) {
				try {
					$meta = Utils_FileStorageCommon::meta($fsid);
					call_user_func($func,$id,$meta['file'],$meta['filename'],$add_args,$meta['created_on']);
				} catch(Exception $e) {}
			}			
		}
	}

    public static function watchdog_label($rid = null, $events = array(), $details = true) {
        if ($rid !== null && !self::get_access($rid)) {
            return null;
        }
        $ret = Utils_RecordBrowserCommon::watchdog_label(
            'utils_attachment',
            __('Note'),
            $rid,
            $events,
            null,
            $details
        );
        if ($rid && $ret) {
            $r = self::get_note($rid);
            $of = Utils_RecordBrowserCommon::get_val('utils_attachment', 'attached_to', $r);
            $ret['title'] .= " [ $of ]";
        }
        return $ret;
    }

	public static function add($group,$permission,$user,$note=null,$oryg=null,$file=null,$func=null,$args=null,$sticky=false,$note_title='',$crypted=false) {
		if(($oryg && !$file) || ($file && !$oryg))
		    trigger_error('Invalid add attachment call: missing original filename or temporary filepath',E_USER_ERROR);

        $old_user = Acl::get_user();
        if($old_user!=$user) Acl::set_user($user);
        $id = Utils_RecordBrowserCommon::new_record('utils_attachment',array('attached_to'=>$group,'note'=>$note,'permission'=>$permission,'func'=>serialize($func),'args'=>serialize($args),'sticky'=>$sticky?1:0,'title'=>$note_title,'crypted'=>$crypted?1:0));
        if($old_user!=$user) Acl::set_user($old_user);

		if($file)
			self::add_file($id, $user, $oryg, $file);
		return $id;
	}
	
	public static function add_file($note, $user, $oryg, $file) {
		if($oryg===null) $oryg='';
		$note = is_numeric($note)? self::get_note($note): $note;
		if ($note['crypted']) {
			if  (isset($_SESSION['client']['cp'.$note['id']]))
				file_put_contents($file,self::encrypt(file_get_contents($file),$_SESSION['client']['cp'.$note['id']]));
			else trigger_error('Cannot add file to encrypted note', E_USER_ERROR);
		}
		$fsid = Utils_FileStorageCommon::write_file($oryg, $file, null, 'rb:utils_attachment/' . $note['id'], null, $user);
		Utils_RecordBrowserCommon::update_record('utils_attachment', $note['id'], array('files' => array_merge($note['files'], array($fsid))));
        @unlink($file);
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
		$ids = self::get_where($group,$group_starts_with);
		if(!$ids) return [];
		$files = [];
		foreach (self::get_notes(['id' => $ids]) as $id => $note) {
			foreach($note['files']?? [] as $fsid) {
				$meta = Utils_FileStorageCommon::meta($fsid);
				$files[] = array_merge($meta, $note, array(
						'id' => $fsid,
						'note_id' => $id,
						'file_id' => null,
						'upload_by' => $meta['created_by'],
						'upload_on' => $meta['created_by'],
						'original' => $meta['filename'],
						'filestorage_id' => $fsid,
						'downloads' => Utils_FileStorageCommon::get_downloads_count($fsid),
				));
			}
		}
	       	
        return $files;
	}

	public static function move_notes($to_group, $from_group, $notes = []) {
		$notes = $notes?: self::get_where($from_group, false);
		foreach ($notes as $note) {
			if (!self::detach_note($note, $from_group)) continue;
			self::attach_note($note, $to_group);			
		}
	}

	public static function copy_notes($from_group, $to_group) {
		$notes = self::get_files($from_group);
		$mapping = array();
		foreach ($notes as $n) {
			$mapping[$n['note_id']] = @self::add($to_group,$n['permission'],Acl::get_user(),$n['text'],$n['original'],$n['file']);
		}
		return $mapping;
	}	
	
	public static function detach_note($note, $group) {
		$note = is_numeric($note)? self::get_note($note): $note;
		if (($key = array_search($group, $note['attached_to'])) === false) return false;
		unset($note['attached_to'][$key]);
		DB::Execute('UPDATE utils_attachment_data_1 SET f_attached_to=%s WHERE id=%d',array(Utils_RecordBrowserCommon::encode_multi($note['attached_to']), $note['id']));
		self::new_watchdog_event($group, '-', $note['id']);
		return true;
	}
	
	public static function attach_note($note, $group) {
		$note = is_numeric($note)? self::get_note($note): $note;
		if (array_search($group, $note['attached_to']) !== false) return false;
		$note['attached_to'][] = $group;
		DB::Execute('UPDATE utils_attachment_data_1 SET f_attached_to=%s WHERE id=%d',array(Utils_RecordBrowserCommon::encode_multi($note['attached_to']), $note['id']));
		self::new_watchdog_event($group, '+', $note['id']);
		return true;
	}	
	
	public static function is_image($note) {
		if (!is_string($note)) $note = $note['original'];
		return preg_match('/\.(jpg|jpeg|gif|png|bmp)$/i',$note);
	}

    public static function encrypt($input,$password, $hint = '') {
        $iv = '';
        $input .= md5($input);
        $encrypted = base64_encode(self::crypt($input,$password,self::ENCRYPT,$iv));
        return $encrypted."\n".base64_encode($iv) . "\n" . $hint;
    }

    public static function decrypt($input,$password) {
        list($note,$iv) = explode("\n",$input);
        $iv1 = base64_decode($iv);
        $ret = rtrim(self::crypt(base64_decode($note), $password, self::DECRYPT, $iv1), "\0"); //we can trim, because on the end there is md5 sum (100% text character is last char in file)
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
        $info = Utils_RecordBrowserCommon::get_record_info('utils_attachment',$row['id']);
        $by = Base_UserCommon::get_user_label($info['edited_by']?$info['edited_by']:$info['created_by'], $nolink);
        $format = Base_User_SettingsCommon::get(Utils_Attachment::module_name(), 'edited_on_format');
        return str_replace(array('%D', '%T', '%U'), array($date, $time, $by), $format);
    }

    public static function display_note($row, $nolink = false, $desc = null,$tab = null, $view = false) {
        $crypted = Utils_RecordBrowserCommon::get_value('utils_attachment',$row['id'],'crypted');

        if($crypted) {
            $text = false;
            if(isset($_SESSION['client']['cp'.$row['id']])) {
                $note_pass = $_SESSION['client']['cp'.$row['id']];
                $decoded = self::decrypt($row['note'],$note_pass);
                if($decoded!==false) {
                    $text = $decoded;
                    Utils_WatchdogCommon::notified('utils_attachment', $row['id']); // notified only when decrypted
                }
            }
            if($text===false) {
                $hint = self::get_password_hint($row['note']);
                $hint = $hint ? ' (' . __('Hint: %s', array($hint)) . ')' : '';
                $text = '<div id="note_value_'.$row['id'].'"><a href="javascript:void(0);" onclick="utils_attachment_password(\''.Epesi::escapeJS(__('Password').$hint.':').'\',\''.Epesi::escapeJS(__('OK')).'\','.$row['id'].')" style="color:red">'.__('Note encrypted').'</a></div>';
            } else {
                $text = Utils_BBCodeCommon::parse($text);
            }
        } else {
            $text = $row['note'];
            $text = Utils_BBCodeCommon::parse($text);
	    Utils_SafeHtml_SafeHtml::setSafeHtml(new Utils_SafeHtml_HtmlPurifier);
            $text = Utils_SafeHtml_SafeHtml::outputSafeHtml($text);
            // mark as read all 'browsed' records
            foreach (self::$mark_as_read as $note_id) {
                Utils_WatchdogCommon::notified('utils_attachment', $note_id);
            }
            self::$mark_as_read = array();
        }

        $text = (!$view && $row['title']?'<b style="float:left;margin-right:30px;">'.$row['title'].'</b> ':''). $text;
        
        if($row['sticky']) $text = '<img src="'.Base_ThemeCommon::get_template_file('Utils_Attachment','sticky.png').'" hspace=3 align="left"> '.$text;

        $files = self::display_files($row, $nolink);
        
        return implode('<br><br>', array_filter([$text, $files]));
    }
    
    public static function display_files($row, $nolink = false, $desc = null, $tab = null) {
    	$crypted = Utils_RecordBrowserCommon::get_value('utils_attachment',$row['id'],'crypted');
    	
    	if($crypted && !isset($_SESSION['client']['cp'.$row['id']])) return '';
    	
    	$labels = [];
    	$inline_nodes = [];
    	$fileStorageIds = Utils_RecordBrowserCommon::decode_multi($row['files']);
    	$fileHandler = new Utils_Attachment_FileActionHandler();
    	foreach($fileStorageIds as $fileStorageId) {
    		if(!empty($fileStorageId)) {
    			$actions = $fileHandler->getActionUrlsAttachment($fileStorageId, 'utils_attachment', $row['id'], 'files', $row['crypted']);
    			$labels[]= Utils_FileStorageCommon::get_file_label($fileStorageId, $nolink, true, $actions);
    			$inline_nodes[]= Utils_FileStorageCommon::get_file_inline_node($fileStorageId, $actions);
    		}
    	}
    	$inline_nodes = array_filter($inline_nodes);

    	return implode('<br>', $labels) . ($inline_nodes? '<hr>': '') . implode('&nbsp;', $inline_nodes);
    }    		

    public static function description_callback($row,$nolink=false) {
        if($row['title']) $ret = $row['title'];
        elseif($row['crypted']) $ret = $row['id'].' ('.__('encrypted note').')';
        else {
            $ret = Utils_BBCodeCommon::strip(strip_tags($row['note']));
            $ret = substr($ret,0,50);
        }
        if(!$ret) $ret = $row['id'];
        return $ret;
    }

    public static function QFfield_note(&$form, $field, $label, $mode, $default, $desc, $rb_obj) {
        load_js(self::Instance()->get_module_dir() . 'attachments.js');

        if($rb_obj->record['crypted']) {
            if(!(isset($rb_obj->record['id']) && isset($_SESSION['client']['cp'.$rb_obj->record['id']])) && !(isset($rb_obj->record['clone_id']) && isset($_SESSION['client']['cp'.$rb_obj->record['clone_id']]))) {
                /*Epesi::alert(__('Note encrypted.'));
                $x = ModuleManager::get_instance('/Base_Box|0');
                if(!$x) trigger_error('There is no base box module instance',E_USER_ERROR);
                return $x->pop_main();*/
                $form->addElement('static', $field, $label);
                // change here
                $hint = isset($rb_obj->record['note']) ? self::get_password_hint($rb_obj->record['note']) : '';
                $hint = $hint ? ' (' . __('Hint: %s', array($hint)) . ')' : '';
                $txt = '<div id="note_value_'.$rb_obj->record['id'].'"><a href="javascript:void(0);" onclick="utils_attachment_password(\''.Epesi::escapeJS(__('Password').$hint.':').'\',\''.Epesi::escapeJS(__('OK')).'\','.$rb_obj->record['id'].',1)" style="color:red">'.__('Note encrypted').'</a></div>';
                $form->setDefaults(array($field=>$txt));
                return;
            } else {
                if(isset($rb_obj->record['id']) && isset($_SESSION['client']['cp'.$rb_obj->record['id']]))
                    $note_pass = $_SESSION['client']['cp'.$rb_obj->record['id']];
                else
                    $note_pass = $_SESSION['client']['cp'.$rb_obj->record['clone_id']];
                $decoded = self::decrypt($default,$note_pass);
                if($decoded!==false) $default = $decoded;
                else {
                    Epesi::alert(__('Note encrypted.'));
                    return Base_BoxCommon::pop_main();
                }
            }
        }
        if ($mode=='add' || $mode=='edit') {

            $fck = $form->addElement('ckeditor', $field, $label);
            $fck->setFCKProps('99%','300',Base_User_SettingsCommon::get(self::Instance()->get_type(),'editor'));

            $form->setDefaults(array($field=>$default));
        } else {
            $form->addElement('static', $field, $label);
            $form->setDefaults(array($field=>self::display_note($rb_obj->record,false,null,$rb_obj->tab,true)));
            if(class_exists('ZipArchive')) {
            	if($rb_obj->record['files']) {
            		$fileHandler = new Utils_Attachment_FileActionHandler();
            		$urls = $fileHandler->getActionUrlsAttachment(Utils_RecordBrowserCommon::decode_multi($rb_obj->record['files']), $rb_obj->tab, $rb_obj->record['id'], $field, $rb_obj->record['crypted']);
            		Base_ActionBarCommon::add('download', __('Download all attachments'), 'href="'.$urls['download'].'" target="_blank"');
            	}
            }
        }
    }
    
    public static function QFfield_files(&$form, $field, $label, $mode, $default, $desc, $rb_obj) {
    	if ($mode=='add' || $mode=='edit')
    		Utils_RecordBrowserCommon::QFfield_file($form, $field, $label, $mode, $default, $desc, $rb_obj);
    }

    public static function QFfield_crypted(&$form, $field, $label, $mode, $default, $desc, $rb_obj) {
        if ($mode=='view') {
            $elem = $form->addElement('checkbox', $field, $label,'', array('id'=>$field));
            $form->setDefaults(array($field=>$default));
            $elem->freeze(1);
        } else {
            $elems = array();
            $elems[] = $form->createElement('checkbox', $field, '','', array('id'=>$field,'onChange'=>'this.form.elements["note_password"].disabled=this.form.elements["note_password2"].disabled=this.form.elements["note_password_hint"].disabled=!this.checked;','style'=>'margin-right:40px;'));
            $elems[] = $form->createElement('static','note_password_label','',__('Password').':');
            $elems[] = $form->createElement('password','note_password',__('Password'), array('id'=>'note_password','style'=>'width:200px;margin-right:20px;'));
            $elems[] = $form->createElement('static','note_password2_label','',__('Confirm Password').':');
            $elems[] = $form->createElement('password','note_password2',__('Confirm Password'), array('id'=>'note_password2','style'=>'width:200px;margin-right:20px;'));
            $elems[] = $form->createElement('static','note_password_hint_label','',__('Password Hint').':');
            $elems[] = $form->createElement('text','note_password_hint',__('Password Hint'), array('id'=>'note_password_hint','style'=>'width:200px'));
            $form->addGroup($elems,$field,__('Encryption'));

            if($default) {
                $hint = isset($rb_obj->record['note']) ? self::get_password_hint($rb_obj->record['note']) : '';
                $form->setDefaults(array('crypted'=>array('crypted'=>$default,'note_password'=>'*@#old@#*','note_password2'=>'*@#old@#*', 'note_password_hint'=>$hint)));
            }
            $crypted = $form->exportValue($field);
            if(!$crypted) eval_js('$("note_password").disabled=1;$("note_password2").disabled=1;$("note_password_hint").disabled=1;');

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
        return array();
    }

    private static $mark_as_read = array();
    public static function submit_attachment($values, $mode) {
        static $new_values, $old_password;
        switch ($mode) {
            case 'browse':
                if (isset($values['id']) && isset($values['crypted']) && $values['crypted'] == false) {
                    // store to mark as read. Do not mark it here, because
                    // we won't get red eye in the table view
                    self::$mark_as_read[] = $values['id'];
                }
                return $values;
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
                
                //change of password
                if(is_array($values['crypted']) && isset($values['crypted']['note_password']) && $mode=='edit' && $old_pass!=$values['crypted']['note_password']) {
                    //reencrypt old revisions
                    $old_notes = DB::GetAssoc('SELECT hd.edit_id,hd.old_value FROM utils_attachment_edit_history h INNER JOIN utils_attachment_edit_history_data hd ON h.id=hd.edit_id WHERE h.utils_attachment_id=%d AND hd.field="note"', array($values['id']));
                    foreach($old_notes as $old_id=>$old_note) {
                        if($old_pass!=='') $old_note = self::decrypt($old_note,$old_pass);
                        if($old_note===false) continue;
                        if($crypted && $values['crypted']['note_password']) $old_note = self::encrypt($old_note,$values['crypted']['note_password'],$values['crypted']['note_password_hint']);
                        if($old_note===false) continue;
                        DB::Execute('UPDATE utils_attachment_edit_history_data SET old_value=%s WHERE edit_id=%d AND field="note"',array($old_note,$old_id));
                    }
                    //reencrypt old files
                    $old_files = self::get_all_files($values['id']);
                    foreach($old_files as $fsid) {
                    	try {
                    		$meta = Utils_FileStorageCommon::meta($fsid);
                    	} catch(Exception $e) { continue; }
                    	$content = @file_get_contents($meta['file']);
                    	if($content===false) continue;
                    	if($old_pass!=='') $content = self::decrypt($content,$old_pass);
                    	if($content===false) continue;
                    	if($crypted && $values['crypted']['note_password']) $content = self::encrypt($content,$values['crypted']['note_password'],$values['crypted']['note_password_hint']);
                    	if($content===false) continue;
                    	Utils_FileStorageCommon::set_content($fsid, $content);
                    }
                }

                if($crypted) {
                    if(is_array($values['crypted']) && isset($values['crypted']['note_password'])) {
                        $values['note'] = self::encrypt($values['note'],$values['crypted']['note_password'],$values['crypted']['note_password_hint']);
                        $values['note_password']=$values['crypted']['note_password'];
                        $values['note_password_hint'] = $values['crypted']['note_password_hint'];

                        foreach ($values['files'] as $file) {
                        	//encrypt only newly uploaded files
                        	if (!isset($file['file'])) continue;
                        	file_put_contents($file['file'],self::encrypt(file_get_contents($file['file']),$values['note_password'], $values['note_password_hint']));
                        }                        
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
                $new_values = $values;
                break;
            case 'edit_changes':
                if(isset($values['note']) && isset($values['crypted']) && $new_values['crypted']!=$values['crypted']) {
                    if($new_values['crypted'] && isset($new_values['note_password'])) {
                        $values['note'] = self::encrypt($values['note'],$new_values['note_password'], $new_values['note_password_hint']);
                    } elseif(!$new_values['crypted'] && isset($_SESSION['client']['cp'.$new_values['id']])) {
                        $values['note'] = self::decrypt($values['note'],$_SESSION['client']['cp'.$new_values['id']]);
                        unset($_SESSION['client']['cp'.$new_values['id']]);
                    }
                } elseif(isset($new_values['note_password']) && isset($old_password) && $new_values['note_password']!=$old_password) {
                    $values['note'] = self::decrypt($values['note'],$old_password);
                    $values['note'] = self::encrypt($values['note'],$new_values['note_password'],$new_values['note_password_hint']);
                }
                unset($values['edited_on']);
                break;
            case 'view':
                $ret = self::get_access($values['id']);
                if(!$ret) print(__('Access denied'));
                return $ret;
            case 'display':
                if(self::get_all_files($values['id'])) {
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
                if(count($values['attached_to'])>1) {
                	if(isset($_SESSION['client']['utils_attachment_group'])) {
                		self::detach_note($values, $_SESSION['client']['utils_attachment_group']);
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

                if($mode=='added' && isset($values['clone_id'])) { //on cloning
					$clone = self::get_note($values['clone_id']);
                    foreach($clone['files'] as $fsid) {
                    	$meta = Utils_FileStorageCommon::meta($fsid);
                    	$content = Utils_FileStorageCommon::read_content($fsid);
                    	if(isset($_SESSION['client']['cp'.$values['clone_id']]) && $_SESSION['client']['cp'.$values['clone_id']])
                    		$content = self::decrypt($content,$_SESSION['client']['cp'.$values['clone_id']]);
                    	
                    	if($values['crypted'])
                    		$content = self::encrypt($content,$values['note_password'],$new_values['note_password_hint']);
                    		
                    	Utils_FileStorageCommon::write_content($meta['filename'], $content, null, 'rb:utils_attachment/' . $note_id, $meta['created_on'], $meta['created_by']);
                    }
                }

                foreach ($values['attached_to'] as $token) {
                	$token = Utils_RecordBrowserCommon::decode_record_token($token);
                   	$subscribers = Utils_WatchdogCommon::get_subscribers($token['tab'], $token['id']);
                    foreach ($subscribers as $user_id) {
                        Utils_WatchdogCommon::user_subscribe($user_id, 'utils_attachment', $note_id);
                    }
                }

                break;
        }
        return $values;
    }
    
    /**
     * @param integer $id
     * @return mixed - returns all fsids of all files associated with the record (including deleted ones)
     */
    public static function get_all_files($id) {
    	return DB::GetCol('SELECT id FROM utils_filestorage WHERE backref=' . DB::qstr('rb:utils_attachment/' . $id) . ' OR backref ' . DB::like() . ' ' . DB::Concat(DB::qstr('rb:utils_attachment/' . $id .'/'), DB::qstr('%')));
    }
    
    public static function get_access($id) {
    	$note = self::get_note($id);
        $ret = false;
        foreach($note['attached_to'] as $token) {
        	$token = Utils_RecordBrowserCommon::decode_record_token($token);
        	if(!Utils_RecordBrowserCommon::check_table_name($token['tab'], false, false)
               || Utils_RecordBrowserCommon::get_access($token['tab'],'view',$token['id'])) {
                $ret = true;
                break;
            }
        }
        return $ret;
    }
    
    public static function get_note($id) {
    	static $cache;
    	if (!isset($cache[$id])) {
    		$cache[$id] = Utils_RecordBrowserCommon::get_record('utils_attachment', $id);
    	}
    	return $cache[$id];
    }
    
    public static function get_notes($crits = array(), $cols = array(), $order = array(), $limit = array(), $admin = false) {
    	return Utils_RecordBrowserCommon::get_records('utils_attachment', $crits, $cols, $order, $limit, $admin);
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

    /**
     * Retrieve password hint from encrypted text
     *
     * @param string $note Encrypted note
     *
     * @return string hint
     */
    public static function get_password_hint($note) {
        $exp = explode("\n", $note);
        if (isset($exp[2]) && $exp[2]) {
            return $exp[2];
        }
        return '';
    }

    /**
     * Deny access to notes on records where user has no access
     * 
     * @param string $action
	 * @param array $record
	 * @param string $tab
     * @return boolean
     */
    public static function rb_access($action, $record, $tab)
    {
        if ($action == 'view' && isset($record['id']) && $record['id'] > 0) {
            $access = self::get_access($record['id']);
            if ($access == false) return false;
        }
    }
}

?>
