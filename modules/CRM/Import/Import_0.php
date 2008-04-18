<?php
/**
 * Import data from csv file
 * @author shacky@poczta.fm
 * @copyright shacky@poczta.fm
 * @license SPL
 * @version 0.1
 * @package crm-import
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class CRM_Import extends Module {
	private $lang;

	public function construct() {
		$this->lang = $this->init_module('Base/Lang');
	}

	public function body() {
		print('<h2>This process can take some time, please be patient... Time limit has been disabled.</h2><hr>');
		$f = $this->init_module('Utils/FileUpload',null,'cc');
		$f->addElement('header',null,$this->lang->t('Contacts & Companies'));
		$i = DB::GetOne('SELECT count(*) FROM crm_import_contact');
		$i2 = DB::GetOne('SELECT count(*) FROM crm_import_company');
		$f->addElement('static',null,$this->lang->t('Imported contact records'),($i?$i:$this->lang->t('none')));
		$f->addElement('static',null,$this->lang->t('Imported company records'),($i2?$i2:$this->lang->t('none')));
		$this->display_module($f,array(array($this,'upload_contacts')));

		$f2 = $this->init_module('Utils/FileUpload',null,'hist');
		$f2->addElement('header',null,$this->lang->t('History'));
		$i = DB::GetOne('SELECT count(*) FROM crm_import_history');
		$f2->addElement('static',null,$this->lang->t('Imported records'),($i?$i:$this->lang->t('none')));
		$this->display_module($f2,array(array($this,'upload_history')));

		$f2 = $this->init_module('Utils/FileUpload',null,'notes');
		$f2->addElement('header',null,$this->lang->t('Notes'));
		$i = DB::GetOne('SELECT count(*) FROM crm_import_note');
		$f2->addElement('static',null,$this->lang->t('Imported records'),($i?$i:$this->lang->t('none')));
		$this->display_module($f2,array(array($this,'upload_note')));

		if(!class_exists('ZipArchive')) {
			print($this->lang->t('Unable to import attachments without ZipArchive module.'));
		} else {
			$f2 = $this->init_module('Utils/FileUpload',null,'attach_files');
			$f2->addElement('header',null,$this->lang->t('Attachment files'));
			$f2->addElement('static',null,$this->lang->t('Warning'),$this->lang->t('Please upload zip file with attachment files in root of archive (without subdirectories!)'));
			$i = count(glob($this->get_data_dir() . "attachments/*"));
			if(file_exists($this->get_data_dir() . "attachments/index.html")) $i--;
			$f2->addElement('static',null,$this->lang->t('Files in queue'),($i?$i:$this->lang->t('none')));
			$this->display_module($f2,array(array($this,'upload_attach_files')));
			
			$f2 = $this->init_module('Utils/FileUpload',null,'attach_db');
			$f2->addElement('header',null,$this->lang->t('Attachments'));
			$i = DB::GetOne('SELECT count(*) FROM crm_import_attach');
			$f2->addElement('static',null,$this->lang->t('Imported records'),($i?$i:$this->lang->t('none')));
			$this->display_module($f2,array(array($this,'upload_attachments')));
			
		}
	}

	private function get_add_user($name) {
		static $mail;
		if(!isset($mail))
			$mail = Base_User_LoginCommon::get_mail(Acl::get_user());
		$name = str_replace(array(' ','\'','.'),array('_','',''), strtolower($name));
		$id = Base_UserCommon::get_user_id($name);
		if($id===false) {
			Base_User_LoginCommon::add_user($name,$mail,$name,false);
			$id = Base_UserCommon::get_user_id($name);
		}
		return $id;
	}

	public function upload_contacts($file, $oryginal_file) {
		set_time_limit(0);
		ini_set("memory_limit","512M");
		$f = fopen($file,'r');
		$header = fgetcsv($f);
		if(!$header) {
			Epesi::alert("Invalid csv file");
			return 0;
		}
		$header = array_flip($header);
		$groups = Utils_CommonDataCommon::get_array("/Contacts_Groups");
		$contacts = array();
		$created_map = array();
		while($x=fgetcsv($f)) {
			if(count($x)!=87) continue;
			$contacts[] = $x;
			if(!isset($created_map[$x[$header['CREATEUSERID']]]))
				$created_map[$x[$header['CREATEUSERID']]] = $this->get_add_user($x[$header['CREATEUSERID']]);
		}

		fclose($f);

		if(function_exists('memory_get_usage'))
			print($this->lang->t("Memory usage: %s",array(filesize_hr(memory_get_usage(true)))).'<br>');

		foreach($contacts as $x) {
			//companies
			$time = strtotime($x[$header['Edit Date']]?$x[$header['Edit Date']]:$x[$header['Create Date']]);
			if($x[$header['Company']]) {
				if(!$x[$header['City']]) $x[$header['City']] = 'n/a';
				$v = array('company_name'=>$x[$header['Company']],
						'short_name'=>$x[$header['Company']],
						'city'=>$x[$header['City']],
						'country'=>'US',
						'zone'=>$x[$header['State']],
						'web_address'=>$x[$header['Web Site']],
						'fax'=>$x[$header['Fax']],
						'address_1'=>$x[$header['Address']],
						'address_2'=>$x[$header['Address 2']].($x[$header['Address 3']]?' '.$x[$header['Address 3']]:''),
						'postal_code'=>$x[$header['Zip Code -1-']]
					);

				$kk = DB::GetRow('SELECT cic.created_on,cic.id FROM crm_import_company cic WHERE cic.original=%s',array($x[$header['CONTACTID']]));
				if(!$kk) {
					$kk = CRM_ContactsCommon::get_companies(array('company_name'=>$x[$header['Company']],'city'=>$x[$header['City']]));
					if(empty($kk))
						$kk = CRM_ContactsCommon::get_companies(array('company_name'=>$x[$header['Company']]));
					if(!empty($kk)) {
						$kk = array_pop($kk);
						$ccc = $kk['id'];
						unset($kk);

						$r = Utils_RecordBrowserCommon::get_record_info('company',$ccc);
						$edited_on = isset($r['edited_on'])?$r['edited_on']:0;

						if($edited_on<$time) {
							Utils_RecordBrowserCommon::update_record('company', $ccc, $v,true);
							DB::Replace('crm_import_company',array('created_on'=>DB::DBTimeStamp($time),'id'=>$ccc),'id');
						}
					} else {
						$ccc = Utils_RecordBrowserCommon::new_record('company', $v);
						DB::Replace('crm_import_company',array('created_on'=>DB::DBTimeStamp($time),'id'=>$ccc,'original'=>DB::qstr($x[$header['CONTACTID']])),'id');
					}
				} else {
					$imported_on = strtotime($kk['created_on']);
					$ccc = $kk['id'];
					unset($kk);

					$r = Utils_RecordBrowserCommon::get_record_info('company',$ccc);
					$edited_on = isset($r['edited_on'])?$r['edited_on']:0;

					if($edited_on>$imported_on || $imported_on>=$time) { //it was imported and later edited in epesi or imported file is older then last import
						//none
					} else {
						Utils_RecordBrowserCommon::update_record('company', $ccc, $v,true);
						DB::Replace('crm_import_company',array('created_on'=>DB::DBTimeStamp($time),'id'=>$ccc),'id');
					}
				}
			} else {
				$ccc = null;
			}

			//groups
			$gg = array();
			for($kk=1; $kk<5; $kk++) {
				if(!$x[$header['User '.$kk]]) continue;
				if(!isset($groups[$x[$header['User '.$kk]]])) {
					$key = strtolower($x[$header['User '.$kk]]);
					Utils_CommonDataCommon::set_value("/Contacts_Groups/".$key,$x[$header['User '.$kk]]);
					Utils_CommonDataCommon::set_value("/Companies_Groups/".$key,$x[$header['User '.$kk]]);
					$groups[$x[$header['User '.$kk]]] = $key;
				}
				$gg[] = $groups[$x[$header['User '.$kk]]];
			}

			if(isset($ccc)) {
				$comp = Utils_RecordBrowserCommon::get_record('company', $ccc);
				Utils_RecordBrowserCommon::update_record('company',$ccc,array('group'=>array_merge($comp['group'],$gg)));
				$gg = array();
				$ccc2 = array($ccc);
			} else
				$ccc2 = array();

			$this_contact = array('company_name'=>$ccc2,
				'first_name'=>$x[$header['First Name']].($x[$header['Middle Name']]!=''?' '.$x[$header['Middle Name']]:''),
				'last_name'=>$x[$header['Last Name']].($x[$header['Name Suffix']]!=''?' '.$x[$header['Name Suffix']]:''),
				'city'=>$x[$header['City']],
				'country'=>'US',
				'zone'=>$x[$header['State']],
				'web_address'=>$x[$header['Web Site']],
				'title'=>$x[$header['Title']],
				'work_phone'=>($x[$header['Phone']].($x[$header['Phone Ext-']]?' ext '.$x[$header['Phone Ext-']]:'')),
				'mobile_phone'=>($x[$header['Mobile Phone']]?$x[$header['Mobile Phone']]:($x[$header['Cell Phone']]?$x[$header['Cell Phone']]:$x[$header['Alt Phone']])),
				'fax'=>$x[$header['Fax']],
				'email'=>$x[$header['E-mail']],
				'address_1'=>$x[$header['Address']],
				'address_2'=>$x[$header['Address 2']].($x[$header['Address 3']]?' '.$x[$header['Address 3']]:''),
				'postal_code'=>$x[$header['Zip Code -1-']],
				'group'=>$gg,
				'home_phone'=>$x[$header['Home Phone']],
				'home_city'=>$x[$header['Home City']],
				'home_postal_code'=>$x[$header['Home Zip']],
				'home_address_1'=>$x[$header['Home Address 1']],
				'home_address_2'=>$x[$header['Home Address 2']],
				'home_country'=>'US',
				'home_zone'=>$x[$header['Home State']]
				);
			if($this_contact['first_name']=='')
				$this_contact['first_name'] = 'n/a';
			if($this_contact['last_name']=='')
				$this_contact['last_name'] = 'n/a';
			$first_last = $this_contact['first_name'].' '.$this_contact['last_name'];
			if(isset($created_map[$first_last])) {
				$uid = CRM_ContactsCommon::get_contact_by_user_id($created_map[$first_last]);
				if($uid===null)
					$this_contact['login'] = $created_map[$first_last];
			}
			$kk = DB::GetRow('SELECT cic.created_on,cic.id FROM crm_import_contact cic WHERE cic.original=%s',array($x[$header['CONTACTID']]));
			if(empty($kk)) {
				$id = Utils_RecordBrowserCommon::new_record('contact',$this_contact);
				DB::Replace('crm_import_contact',array('created_on'=>DB::DBTimeStamp($time),'id'=>$id,'original'=>DB::qstr($x[$header['CONTACTID']])),'id');
			} else {
				$id = $kk['id'];
				$r = Utils_RecordBrowserCommon::get_record_info('contact',$kk['id']);
				$edited_on = isset($r['edited_on'])?$r['edited_on']:0;
				if($edited_on>$time || $edited_on>$kk['created_on'])
					continue;
				else if($kk['created_on']<$time) {
					Utils_RecordBrowserCommon::update_record('contact',$kk['id'],$this_contact);
					DB::Replace('crm_import_contact',array('created_on'=>DB::DBTimeStamp($time),'id'=>$kk['id']),'id');
				}

			}

			$created_by = $created_map[$x[$header['CREATEUSERID']]];
			$create_date = strtotime($x[$header['Create Date']]);
			Utils_RecordBrowserCommon::set_record_properties('contact',$id,array('created_by'=>$created_by,'created_on'=>$create_date));
		}

//		print($this->lang->t("Data imported. ").'<a '.$this->create_href(array()).'>'.$this->lang->t("Back").'</a>');
		Epesi::alert($this->lang->t("Data imported. "));
		location(array());
	}

	public function upload_history($file, $oryginal_file) {
		set_time_limit(0);
		ini_set("memory_limit","512M");
		$f = fopen($file,'r');
		$header = fgetcsv($f);
		if(!$header) {
			Epesi::alert("Invalid csv file");
			return 0;
		}
		$header = array_flip($header);
		while($x=fgetcsv($f)) {
			$cid = DB::GetOne('SELECT id FROM crm_import_contact WHERE original=%s',array($x[$header['CONTACTID']]));
			if($cid===false) continue;
			DB::Replace('crm_import_history',array('original'=>DB::qstr($x[$header['HISTORYID']]),'contact_id'=>$cid,'created_on'=>DB::DBTimeStamp($x[$header['Create Date']]),'edited_on'=>DB::DBTimeStamp($x[$header['Edit Date']]),'created_by'=>$this->get_add_user($x[$header['CREATEUSERID']])),'original');
		}
		fclose($f);

		Epesi::alert($this->lang->t("Data imported. "));
		location(array());
	}

	public function upload_note($file, $oryginal_file) {
		set_time_limit(0);
		ini_set("memory_limit","512M");
		$f = fopen($file,'r');
		$header = fgetcsv($f);
		if(!$header) {
			Epesi::alert("Invalid csv file");
			return 0;
		}
		$header = array_flip($header);
		while($v=fgetcsv($f)) {
			$time = strtotime($v[$header['Edit Date']]?$v[$header['Edit Date']]:$v[$header['Create Date']]);
			$last_import_time = DB::GetOne('SELECT created_on FROM crm_import_note WHERE original=%s',array($v[$header['NOTEID']]));
			if($v[$header['CONTACTID']]=='' || ($last_import_time!==false && $time<$last_import_time))
				continue;

			$user_note = DB::GetOne('SELECT id FROM crm_import_contact WHERE original=%s',array($v[$header['CONTACTID']]));
			if($user_note===false) continue;
			$key = md5($user_note);
			$group = 'CRM/Contact/'.$user_note;
			$created_by = $this->get_add_user($v[$header['CREATEUSERID']]);
			$created_on = strtotime($v[$header['Create Date']]);

			if($v[$header['Private Note']])
				$permission = '2';
			else
				$permission = '0';
			$other_read = false;
			$note = $v[$header['Note']];
			
			$kk = DB::GetRow('SELECT created_on,id FROM crm_import_note WHERE original=%s',array($v[$header['NOTEID']]));
			if(empty($kk)) {
				DB::Execute('INSERT INTO utils_attachment_link(attachment_key,local,permission,permission_by,other_read) VALUES(%s,%s,%d,%d,%b)',array($key,$group,$permission,$created_by,$other_read));
				$id = DB::Insert_ID('utils_attachment_link','id');
				DB::Execute('INSERT INTO utils_attachment_file(attach_id,original,created_by,created_on,revision) VALUES(%d,%s,%d,%T,0)',array($id,'',$created_by,$created_on));
				DB::Execute('INSERT INTO utils_attachment_note(attach_id,text,created_by,created_on,revision) VALUES(%d,%s,%d,%T,0)',array($id,$note,$created_by,$created_on));
			} else {
				$id = $kk['id'];
				$edited_on = DB::GetOne('SELECT created_on FROM utils_attachment_note WHERE attach_id=%d',array($id));
				if($edited_on>$time || $edited_on>$kk['created_on'])
					continue;
				else if($kk['created_on']<$time) {
					DB::Execute('INSERT INTO utils_attachment_note(attach_id,text,created_by,created_on,revision) VALUES(%d,%s,%d,%T,(SELECT max(revision)+1 FROM utils_attachment_note WHERE attach_id=%d))',array($id,$note,$created_by,$created_on,$id));
					DB::Execute('UPDATE utils_attachment_link SET attachment_key=%s,local=%s,permission=%d,permission_by=%d,other_read=%b WHERE id=%d',array($key,$group,$permission,$created_by,$other_read,$id));
				}
			}
			DB::Replace('crm_import_note',array('id'=>$id,'original'=>DB::qstr($v[$header['NOTEID']]),'contact_id'=>$user_note,'created_on'=>$created_on,'created_by'=>$created_by),'id');
		}
		fclose($f);

		Epesi::alert($this->lang->t("Data imported. "));
		location(array());
	}
	
	public function upload_attach_files($file, $oryginal_file) {
		set_time_limit(0);
		$zip = new ZipArchive;
		@mkdir($this->get_data_dir().'attachments',0777,true);
		if ($zip->open($file) == 1) {
    			$zip->extractTo($this->get_data_dir().'attachments');
		} else {
			Epesi::alert("Invalid zip file");
		}
		location(array());
	}

	public function upload_attachments($file, $oryginal_file) {
		set_time_limit(0);
		ini_set("memory_limit","512M");
		$f = fopen($file,'r');
		$header = fgetcsv($f);
		if(!$header) {
			Epesi::alert("Invalid csv file");
			return 0;
		}
		$header = array_flip($header);
		$adir = $this->get_data_dir().'attachments/';
		while($v=fgetcsv($f)) {
			$file = $adir.$v[$header['Physical Filename']];
			$oryg = $v[$header['Display Name']];
			$r = DB::GetRow('SELECT edited_on,created_on FROM crm_import_history WHERE original=%s',array($v[$header['HISTORYID']]));
			if($r===false || empty($r)) $time = 999999999999999;
				else $time = strtotime($r['edited_on']?$r['edited_on']:$r['created_on']);
			if($v[$header['NOTEID']]!=='') {
				$r = DB::GetRow('SELECT * FROM crm_import_note WHERE original=%s',array($v[$header['NOTEID']]));
				if($r===false || empty($r)) continue;
				$id = $r['id'];
				$user_note = $r['contact_id'];
				$group = 'CRM/Contact/'.$user_note;
				$created_by = $r['created_by'];
				$created_on = $r['created_on'];

				$edited_on = DB::GetOne('SELECT created_on FROM utils_attachment_file WHERE attach_id=%d',array($id));
				if($edited_on>$time)
					continue;
				else {
					$rev = DB::GetOne('SELECT max(revision)+1 FROM utils_attachment_file WHERE attach_id=%d',array($id));
					if($file && file_exists($file)) {
						DB::Execute('INSERT INTO utils_attachment_file(attach_id,original,created_by,created_on,revision) VALUES(%d,%s,%d,%T,%d)',array($id,$oryg,$created_by,$created_on,$rev));
						$local = 'data/Utils_Attachment/'.$group;
						@mkdir($local,0777,true);
						rename($file,$local.'/'.$id.'_'.$rev);
					}
				}
			} else {
				$r = DB::GetRow('SELECT created_by,edited_on,created_on,contact_id FROM crm_import_history WHERE original=%s',array($v[$header['HISTORYID']]));
				if($r===false || empty($r)) continue;
				$user_note = $r['contact_id'];
				$created_by = $r['created_by'];
				$created_on = strtotime($r['created_on']);
				$key = md5($user_note);
				$group = 'CRM/Contact/'.$user_note;
				$permission = '0';
				$other_read = false;
				$note = $v[$header['Display Name']];

				$kk = DB::GetRow('SELECT created_on,id FROM crm_import_attach WHERE original=%s',array($v[$header['ATTACHMENTID']]));
				if(empty($kk)) {
					DB::Execute('INSERT INTO utils_attachment_link(attachment_key,local,permission,permission_by,other_read) VALUES(%s,%s,%d,%d,%b)',array($key,$group,$permission,$created_by,$other_read));
					$id = DB::Insert_ID('utils_attachment_link','id');
					DB::Execute('INSERT INTO utils_attachment_note(attach_id,text,created_by,created_on,revision) VALUES(%d,%s,%d,%T,0)',array($id,$note,$created_by,$created_on));
					if($file && file_exists($file)) {
						DB::Execute('INSERT INTO utils_attachment_file(attach_id,original,created_by,created_on,revision) VALUES(%d,%s,%d,%T,0)',array($id,$oryg,$created_by,$created_on));
						$local = 'data/Utils_Attachment/'.$group;
						@mkdir($local,0777,true);
						rename($file,$local.'/'.$id.'_0');
					}	
				} else {
					$id = $kk['id'];
					$edited_on = DB::GetOne('SELECT created_on FROM utils_attachment_file WHERE attach_id=%d',array($id));
					if($edited_on>$time || $edited_on>$kk['created_on'])
						continue;
					else if($kk['created_on']<$time) {
						$rev = DB::GetOne('SELECT max(revision)+1 FROM utils_attachment_file WHERE attach_id=%d',array($id));
						DB::Execute('UPDATE utils_attachment_link SET attachment_key=%s,local=%s,permission=%d,permission_by=%d,other_read=%b WHERE id=%d',array($key,$group,$permission,$created_by,$other_read,$id));
						if($file && file_exists($file)) {
							DB::Execute('INSERT INTO utils_attachment_file(attach_id,original,created_by,created_on,revision) VALUES(%d,%s,%d,%T,%d)',array($id,$oryg,$created_by,$created_on,$rev));
							$local = 'data/Utils_Attachment/'.$group;
							@mkdir($local,0777,true);
							rename($file,$local.'/'.$id.'_'.$rev);
						}	
					}
				}
			}
			DB::Replace('crm_import_attach',array('created_on'=>DB::DBTimeStamp($time),'id'=>$id,'original'=>DB::qstr($v[$header['ATTACHMENTID']])),'id');
		}
		fclose($f);

		Epesi::alert($this->lang->t("Data imported. "));
		location(array());
	}
}

?>
