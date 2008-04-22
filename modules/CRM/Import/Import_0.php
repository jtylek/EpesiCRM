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
		print('<h2>This process can take some time, please be patient... Time limit has been disabled.</h2>');
		$tb = $this->init_module('Utils/TabbedBrowser');
		$tb->set_tab($this->lang->t('Contacts & Companies'),array($this,'contacts'));
		$tb->set_tab($this->lang->t('History'),array($this,'history'));
		$tb->set_tab($this->lang->t('Notes'),array($this,'notes'));
		$tb->set_tab($this->lang->t('Attachments'),array($this,'attachments'));
		$tb->set_tab($this->lang->t('Activities'),array($this,'activities'));
		$this->display_module($tb);
		$tb->tag();
	}
	
	public function contacts() {
		$f = $this->init_module('Utils/FileUpload',null,'cc');
//		$f->addElement('header',null,$this->lang->t('Contacts & Companies'));
		$i = DB::GetOne('SELECT count(*) FROM crm_import_contact');
		$i2 = DB::GetOne('SELECT count(*) FROM crm_import_company');
		$f->addElement('static',null,$this->lang->t('Warning'),$this->lang->t('Please upload CONTACT.CSV file'));
		$f->addElement('static',null,$this->lang->t('Imported contact records'),($i?$i:$this->lang->t('none')));
		$f->addElement('static',null,$this->lang->t('Imported company records'),($i2?$i2:$this->lang->t('none')));
		$this->display_module($f,array(array($this,'upload_contacts')));
	}
	
	public function history() {
		$f2 = $this->init_module('Utils/FileUpload',null,'hist');
//		$f2->addElement('header',null,$this->lang->t('History'));
		$i = DB::GetOne('SELECT count(*) FROM crm_import_history');
		$f2->addElement('static',null,$this->lang->t('Imported records'),($i?$i:$this->lang->t('none')));
		$this->display_module($f2,array(array($this,'upload_history')));	
	}
	
	public function notes() {
		$f2 = $this->init_module('Utils/FileUpload',null,'notes');
//		$f2->addElement('header',null,$this->lang->t('Notes'));
		$i = DB::GetOne('SELECT count(*) FROM crm_import_note');
		$f2->addElement('static',null,$this->lang->t('Imported records'),($i?$i:$this->lang->t('none')));
		$this->display_module($f2,array(array($this,'upload_note')));
	}
	
	public function attachments() {
		if(!class_exists('ZipArchive')) {
			print($this->lang->t('Unable to import attachments without ZipArchive module.'));
		} else {
			$f2 = $this->init_module('Utils/FileUpload',null,'attach_files');
			$f2->addElement('header',null,$this->lang->t('Files'));
			$f2->addElement('static',null,$this->lang->t('Warning'),$this->lang->t('Please upload zip file with attachment files in root of archive (without subdirectories!)'));
			$i = count(glob($this->get_data_dir() . "attachments/*"));
			if(file_exists($this->get_data_dir() . "attachments/index.html")) $i--;
			$f2->addElement('static',null,$this->lang->t('Files in queue'),($i?$i:$this->lang->t('none')));
			$this->display_module($f2,array(array($this,'upload_attach_files')));
			
			$f2 = $this->init_module('Utils/FileUpload',null,'attach_db');
			$f2->addElement('header',null,$this->lang->t('Database'));
			$i = DB::GetOne('SELECT count(*) FROM crm_import_attach');
			$f2->addElement('static',null,$this->lang->t('Imported records'),($i?$i:$this->lang->t('none')));
			$this->display_module($f2,array(array($this,'upload_attachments')));
		}
	}
	
	public function activities() {
		$f2 = $this->init_module('Utils/FileUpload',null,'activities');
//		$f2->addElement('header',null,$this->lang->t('Activities'));
		$i = DB::GetOne('SELECT count(*) FROM crm_import_task');
		$i2 = DB::GetOne('SELECT count(*) FROM crm_import_event');
		$f2->addElement('static',null,$this->lang->t('Imported tasks'),($i?$i:$this->lang->t('none')));
		$f2->addElement('static',null,$this->lang->t('Imported calendar events'),($i2?$i2:$this->lang->t('none')));
		$this->display_module($f2,array(array($this,'upload_activities')));
	}

	private function get_add_user($name) {
		static $mail;
		if(!isset($mail))
			$mail = Base_User_LoginCommon::get_mail(Acl::get_user());
		$name = str_replace(array(' ','\'','.'),array('_','',''), strtolower($name));
		$id = Base_UserCommon::get_user_id($name);
		if($id===false) {
			$this->logit('Adding user: '.$name);
			Base_User_LoginCommon::add_user($name,$mail,$name,false);
			$id = Base_UserCommon::get_user_id($name);
		}
		return $id;
	}
	
	private $log_file = '';
	private function set_log_file($file) {
		$this->log_file = $this->get_data_dir().$file;
	}
	private function logit($message) {
		if(!$this->log_file) return;
		error_log(date('[Y-m-d H:i:s] ',time()).$message."\n",3,$this->log_file);
	}

	public function upload_contacts($file, $oryginal_file) {
		set_time_limit(0);
		ini_set("memory_limit","512M");
		$f = fopen($file,'r');
		$header = fgetcsv($f);
		if(!$header) {
			Epesi::alert("Invalid csv file",false);
			return 0;
		}
		$this->set_log_file('contacts');
		$this->logit('=========================== upload ==========================');
		$header = array_flip($header);
		$groups = Utils_CommonDataCommon::get_array("/Contacts_Groups");
		$contacts = array();
		$created_map = array();
		$login_in_user = array();
		$ch = count($header);
		while($x=fgetcsv($f)) {
			if(count($x)!=$ch) continue;
			$contacts[] = $x;
			if(!isset($created_map[$x[$header['CREATEUSERID']]]))
				$created_map[$x[$header['CREATEUSERID']]] = $this->get_add_user($x[$header['CREATEUSERID']]);
		}
		$this->logit('Read '.count($contacts).' contacts.');

		fclose($f);

		if(function_exists('memory_get_usage'))
			print($this->lang->t("Memory usage: %s",array(filesize_hr(memory_get_usage(true)))).'<br>');
			
		$added_contacts = 0;
		$updated_contacts = 0;
		$skipped_contacts = 0;
		$added_companies = 0;
		$updated_companies = 0;
		$skipped_companies = 0;

		foreach($contacts as $x) {
			//companies
			$timeh = $x[$header['Edit Date']]?$x[$header['Edit Date']]:$x[$header['Create Date']];
			$time = strtotime($timeh);
			$timeh = date('Y-m-d H:i:s',$time);
			$created_by = $created_map[$x[$header['CREATEUSERID']]];
			$create_date = strtotime($x[$header['Create Date']]);
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
				$this->logit('Company: '.$x[$header['Company']].' ('.$x[$header['City']].'), last edit on '.$timeh);
				if(!$kk) {
					$this->logit('Never imported before.');
					$kk = CRM_ContactsCommon::get_companies(array('company_name'=>$x[$header['Company']],'city'=>$x[$header['City']]));
					if(empty($kk))
						$kk = CRM_ContactsCommon::get_companies(array('company_name'=>$x[$header['Company']]));
					if(!empty($kk)) {
						$this->logit('Found company with the same name in epesi database.');

						$kk = array_pop($kk);
						$ccc = $kk['id'];
						unset($kk);

						$kk = DB::GetOne('SELECT cic.original FROM crm_import_company cic WHERE cic.id=%d',array($ccc));
						if($kk!==false) {
							$this->logit('Company assigned to another user. Skipping.');
						} else {
							$r = Utils_RecordBrowserCommon::get_record_info('company',$ccc);
							if(isset($r['edited_on'])) {
								$edited_on = $r['edited_on'];
								$this->logit('Edited in epesi: '.$edited_on);
							} else
								$edited_on = 0;

							if($edited_on<$time) {
								$this->logit('Updating.');
								$updated_companies++;
								Utils_RecordBrowserCommon::update_record('company', $ccc, $v,false,$time);
								DB::Replace('crm_import_company',array('created_on'=>DB::DBTimeStamp($time),'id'=>$ccc,'original'=>DB::qstr($x[$header['CONTACTID']])),'id');
								Utils_RecordBrowserCommon::set_record_properties('company',$ccc,array('created_by'=>$created_by,'created_on'=>$create_date));
							} else {
								$this->logit('Skipping.');
								$skipped_companies++;
							}
						}
					} else {
						$this->logit('Adding.');
						$added_companies++;
						$ccc = Utils_RecordBrowserCommon::new_record('company', $v);
						Utils_RecordBrowserCommon::set_record_properties('company',$ccc,array('created_by'=>$created_by,'created_on'=>$create_date));
						DB::Replace('crm_import_company',array('created_on'=>DB::DBTimeStamp($time),'id'=>$ccc,'original'=>DB::qstr($x[$header['CONTACTID']])),'id');
					}
				} else {
					$this->logit('Imported before: '.$kk['created_on']);

					$imported_on = strtotime($kk['created_on']);
					$ccc = $kk['id'];
					unset($kk);

					$r = Utils_RecordBrowserCommon::get_record_info('company',$ccc);
					if(isset($r['edited_on'])) {
						$edited_on = $r['edited_on'];
						$this->logit('Edited in epesi: '.$edited_on);
					} else
						$edited_on = 0;

					if($edited_on>=$imported_on || $edited_on>=$time || $imported_on>=$time) { //it was imported and later edited in epesi or imported file is older then last import
						$this->logit('Skipping.');
						$skipped_companies++;
					} else {
						$this->logit('Updating.');
						$updated_companies++;
						Utils_RecordBrowserCommon::update_record('company', $ccc, $v,false,$time);
						DB::Replace('crm_import_company',array('created_on'=>DB::DBTimeStamp($time),'id'=>$ccc,'original'=>DB::qstr($x[$header['CONTACTID']])),'id');
						Utils_RecordBrowserCommon::set_record_properties('company',$ccc,array('created_by'=>$created_by,'created_on'=>$create_date));
					}
				}
			} else {
				$ccc = null;
			}

			//'ID/Status' jako grupa!!!
			//'General  contractors' => 'GC'

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
			$gg = array_unique($gg);

			if(isset($ccc)) {
				$comp = Utils_RecordBrowserCommon::get_record('company', $ccc);
				$r = Utils_RecordBrowserCommon::get_record_info('company',$ccc);
				Utils_RecordBrowserCommon::update_record('company',$ccc,array('group'=>array_unique(array_merge($comp['group'],$gg))),false,$r['edited_on']?$r['edited_on']:$r['created_on']);
				$gg = array();
				
				if($comp['phone']=='') {
					$phone = ($x[$header['Phone']].($x[$header['Phone Ext-']]?' ext '.$x[$header['Phone Ext-']]:''));
					if($phone!='')
						Utils_RecordBrowserCommon::update_record('company',$ccc,array('phone'=>$phone),false,$r['edited_on']?$r['edited_on']:$r['created_on']);
				}
				
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
			$this->logit('Contact: '.$first_last.' last edit on '.$timeh);
			if(isset($created_map[$first_last]) && !isset($login_in_use[$first_last])) {
				$this->logit('Contact has epesi account: '.Base_UserCommon::get_user_login($created_map[$first_last]));
				$this_contact['login'] = $created_map[$first_last];
				$login_in_user[$first_last] = 1;
			}
			$kk = DB::GetRow('SELECT cic.created_on,cic.id FROM crm_import_contact cic WHERE cic.original=%s',array($x[$header['CONTACTID']]));
			if(empty($kk)) {
				$this->logit('Never imported before.');
				$this->logit('Adding.');
				$added_contacts++;
				$id = Utils_RecordBrowserCommon::new_record('contact',$this_contact);
				DB::Replace('crm_import_contact',array('created_on'=>DB::DBTimeStamp($time),'id'=>$id,'original'=>DB::qstr($x[$header['CONTACTID']])),'id');
			} else {
				$this->logit('Imported before: '.$kk['created_on']);
				$imported_on  = strtotime($kk['created_on']);
				$id = $kk['id'];
				$r = Utils_RecordBrowserCommon::get_record_info('contact',$kk['id']);
				if(isset($r['edited_on'])) {
					$edited_on = $r['edited_on'];
					$this->logit('Edited in epesi: '.$edited_on);
				} else
					$edited_on = 0;

//				$this->logit('imported_on='.$imported_on.' time='.$time);
				if($edited_on>=$time || $imported_on>=$time || $edited_on>=$imported_on) {
					$this->logit('Skipping.');
					$skipped_contacts++;
					continue;
				} else if($kk['created_on']<$time) {
					$this->logit('Updating.');
					$updated_contacts++;
					Utils_RecordBrowserCommon::update_record('contact',$kk['id'],$this_contact,false,$time);
					DB::Replace('crm_import_contact',array('created_on'=>DB::DBTimeStamp($time),'id'=>$kk['id'],'original'=>DB::qstr($x[$header['CONTACTID']])),'id');
				}

			}

			Utils_RecordBrowserCommon::set_record_properties('contact',$id,array('created_by'=>$created_by,'created_on'=>$create_date));
		}

		$this->logit('added contacts='.$added_contacts.', updated contacts='.$updated_contacts.', skipped contacts='.$skipped_contacts);
		$this->logit('added companies='.$added_companies.', updated companies='.$updated_companies.', skipped companies='.$skipped_companies);
		$this->logit('=============================================================');
		$this->set_log_file('');
		
//		print($this->lang->t("Data imported successfully. ").'<a '.$this->create_href(array()).'>'.$this->lang->t("Back").'</a>');
		Epesi::alert($this->lang->t("Data imported successfully. "),false);
		location(array());
	}

	public function upload_history($file, $oryginal_file) {
		set_time_limit(0);
		ini_set("memory_limit","512M");
		$f = fopen($file,'r');
		$header = fgetcsv($f);
		if(!$header) {
			Epesi::alert("Invalid csv file",false);
			return 0;
		}
		$this->set_log_file('history');
		$this->logit('=========================== upload ==========================');
		$header = array_flip($header);
		$updated = 0;
		$skipped = 0;
		$ch = count($header);
		while($x=fgetcsv($f)) {
			if(count($x)!=$ch) continue;
			$cid = DB::GetOne('SELECT id FROM crm_import_contact WHERE original=%s',array($x[$header['CONTACTID']]));
			if($cid===false) {
				$this->logit('Contact with id "'.$x[$header['CONTACTID']].'" doesn\'t exists. Skipping.');
				$skipped++;
				continue;
			}
			$updated++;
			DB::Replace('crm_import_history',array('original'=>DB::qstr($x[$header['HISTORYID']]),'contact_id'=>$cid,'created_on'=>DB::DBTimeStamp($x[$header['Create Date']]),'edited_on'=>DB::DBTimeStamp($x[$header['Edit Date']]),'created_by'=>$this->get_add_user($x[$header['CREATEUSERID']])),'original');
		}
		fclose($f);

		$this->logit('updated='.$updated.', skipped='.$skipped);
		$this->logit('=============================================================');
		$this->set_log_file('');

		Epesi::alert($this->lang->t("Data imported successfully. "),false);
		location(array());
	}

	public function upload_note($file, $oryginal_file) {
		set_time_limit(0);
		ini_set("memory_limit","512M");
		$f = fopen($file,'r');
		$header = fgetcsv($f);
		if(!$header) {
			Epesi::alert("Invalid csv file",false);
			return 0;
		}

		$this->set_log_file('note');
		$this->logit('=========================== upload ==========================');

		$added = 0;
		$updated = 0;
		$skipped = 0;

		$header = array_flip($header);
		$ch = count($header);

		while($v=fgetcsv($f)) {
			if(count($v)!=$ch) continue;
			$time = strtotime($v[$header['Edit Date']]?$v[$header['Edit Date']]:$v[$header['Create Date']]);
			$timeh = date('Y-m-d H:i:s',$time);
			
			if($v[$header['CONTACTID']]=='') {
				$this->logit('Note without contact id. Skipping.');
				continue;

			}
			$user_note = DB::GetOne('SELECT id FROM crm_import_contact WHERE original=%s',array($v[$header['CONTACTID']]));
			if($user_note===false) {
				$this->logit('Contact with id "'.$v[$header['CONTACTID']].'" doesn\'t exists. Skipping.');
				continue;
			}

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

			$this->logit('Note "'.$note.'" created by "'.Base_UserCommon::get_user_login($created_by).'", on "'.date('Y-m-d H:i:s',$created_on).'" to user with id '.$user_note);
			
			$kk = DB::GetRow('SELECT created_on,id FROM crm_import_note WHERE original=%s',array($v[$header['NOTEID']]));
			if(empty($kk)) {
				$this->logit('Adding.');
				$added++;
				DB::Execute('INSERT INTO utils_attachment_link(attachment_key,local,permission,permission_by,other_read) VALUES(%s,%s,%d,%d,%b)',array($key,$group,$permission,$created_by,$other_read));
				$id = DB::Insert_ID('utils_attachment_link','id');
				DB::Execute('INSERT INTO utils_attachment_file(attach_id,original,created_by,created_on,revision) VALUES(%d,%s,%d,%T,0)',array($id,'',$created_by,$created_on));
				DB::Execute('INSERT INTO utils_attachment_note(attach_id,text,created_by,created_on,revision) VALUES(%d,%s,%d,%T,0)',array($id,$note,$created_by,$created_on));
			} else {
				$this->logit('Imported before: '.$kk['created_on']);
				$id = $kk['id'];
				$edited_on = DB::GetOne('SELECT max(created_on) FROM utils_attachment_note WHERE attach_id=%d',array($id));
				if($edited_on===null) {
					$edited_on=0;
				} else {
					$this->logit('Edited in epesi: '.$edited_on);				
				}
				
				if($edited_on>=$time || $edited_on>=$kk['created_on'] || $time<=$kk['created_on']) {
					$this->logit('Skipping.');
					$skipped++;
					continue;
				} else {
					$this->logit('Updating.');
					$updated++;
					DB::Execute('INSERT INTO utils_attachment_note(attach_id,text,created_by,created_on,revision) VALUES(%d,%s,%d,%T,(SELECT max(revision)+1 FROM utils_attachment_note WHERE attach_id=%d))',array($id,$note,$created_by,$created_on,$id));
					DB::Execute('UPDATE utils_attachment_link SET attachment_key=%s,local=%s,permission=%d,permission_by=%d,other_read=%b WHERE id=%d',array($key,$group,$permission,$created_by,$other_read,$id));
				}
			}
			DB::Replace('crm_import_note',array('id'=>$id,'original'=>DB::qstr($v[$header['NOTEID']]),'contact_id'=>$user_note,'created_on'=>$created_on,'created_by'=>$created_by),'id');
		}
		fclose($f);

		$this->logit('added='.$added.', updated='.$updated.', skipped='.$skipped);
		$this->logit('=============================================================');
		$this->set_log_file('');

		Epesi::alert($this->lang->t("Data imported successfully. "),false);
		location(array());
	}
	
	public function upload_attach_files($file, $oryginal_file) {
		set_time_limit(0);
		$zip = new ZipArchive;
		@mkdir($this->get_data_dir().'attachments',0777,true);
		if ($zip->open($file) == 1) {
    			$zip->extractTo($this->get_data_dir().'attachments');
		} else {
			Epesi::alert("Invalid zip file",false);
		}
		location(array());
	}

	public function upload_attachments($file, $oryginal_file) {
		set_time_limit(0);
		ini_set("memory_limit","512M");
		$f = fopen($file,'r');
		$header = fgetcsv($f);
		if(!$header) {
			Epesi::alert("Invalid csv file",false);
			return 0;
		}
		$header = array_flip($header);
		$ch = count($header);

		$adir = $this->get_data_dir().'attachments/';

		$this->set_log_file('attachment');
		$this->logit('=========================== upload ==========================');

		$added = 0;
		$updated = 0;
		$skipped = 0;

		while($v=fgetcsv($f)) {
			if(count($v)!=$ch) continue;
			$file = $adir.$v[$header['Physical Filename']];
			$oryg = $v[$header['Display Name']];
			$r = DB::GetRow('SELECT edited_on,created_on FROM crm_import_history WHERE original=%s',array($v[$header['HISTORYID']]));
			if($r===false || empty($r)) $time = 999999999999999;
				else $time = strtotime($r['edited_on']?$r['edited_on']:$r['created_on']);
			$this->logit('File named "'.$oryg.'", '.($time==999999999999999?'without edition history.':'last edited on '.date('Y-m-d H:i:s',$time).'.'));
			if($v[$header['NOTEID']]!=='') {
				$this->logit('Attached to note ('.$v[$header['NOTEID']].').');
				$r = DB::GetRow('SELECT * FROM crm_import_note WHERE original=%s',array($v[$header['NOTEID']]));
				if($r===false || empty($r)) {
					$this->logit('Note doesn\'t exists. Skipping.');
					$skipped++;
					continue;
				}
				$id = $r['id'];
				$user_note = $r['contact_id'];
				$group = 'CRM/Contact/'.$user_note;
				$created_by = $r['created_by'];
				$created_on = $r['created_on'];

				$edited_on = DB::GetOne('SELECT created_on FROM utils_attachment_file WHERE attach_id=%d',array($id));
				if($edited_on>$time) {
					$this->logit('Skipping.');
					$skipped++;
					continue;
				} else {
					$this->logit('Updating.');
					$updated++;
					$rev = DB::GetOne('SELECT max(revision)+1 FROM utils_attachment_file WHERE attach_id=%d',array($id));
					if($file && file_exists($file)) {
						DB::Execute('INSERT INTO utils_attachment_file(attach_id,original,created_by,created_on,revision) VALUES(%d,%s,%d,%T,%d)',array($id,$oryg,$created_by,$created_on,$rev));
						$local = 'data/Utils_Attachment/'.$group;
						@mkdir($local,0777,true);
						rename($file,$local.'/'.$id.'_'.$rev);
					}
				}
			} else {
				$this->logit('Attached to contact.');
				$r = DB::GetRow('SELECT created_by,edited_on,created_on,contact_id FROM crm_import_history WHERE original=%s',array($v[$header['HISTORYID']]));
				if($r===false || empty($r)) {
					$this->logit('History entry doesn\'t exists. Skipping.');
					continue;
				}
				$user_note = $r['contact_id'];
				$this->logit('Contact id='.$user_note.'. Adding note.');
				$created_by = $r['created_by'];
				$created_on = strtotime($r['created_on']);
				$key = md5($user_note);
				$group = 'CRM/Contact/'.$user_note;
				$permission = '0';
				$other_read = false;
				$note = $v[$header['Display Name']];

				$kk = DB::GetRow('SELECT created_on,id FROM crm_import_attach WHERE original=%s',array($v[$header['ATTACHMENTID']]));
				if(empty($kk)) {
					if($file && file_exists($file)) {
						$this->logit('Adding.');
						$added++;
						DB::Execute('INSERT INTO utils_attachment_link(attachment_key,local,permission,permission_by,other_read) VALUES(%s,%s,%d,%d,%b)',array($key,$group,$permission,$created_by,$other_read));
						$id = DB::Insert_ID('utils_attachment_link','id');
						DB::Execute('INSERT INTO utils_attachment_note(attach_id,text,created_by,created_on,revision) VALUES(%d,%s,%d,%T,0)',array($id,$note,$created_by,$created_on));
						DB::Execute('INSERT INTO utils_attachment_file(attach_id,original,created_by,created_on,revision) VALUES(%d,%s,%d,%T,0)',array($id,$oryg,$created_by,$created_on));
						$local = 'data/Utils_Attachment/'.$group;
						@mkdir($local,0777,true);
						rename($file,$local.'/'.$id.'_0');
					} else {
						$this->logit('File doesn\'t exists. Skipping.');
						$skipped++;
					}
				} else {
					$id = $kk['id'];
					$edited_on = DB::GetOne('SELECT max(created_on) FROM utils_attachment_file WHERE attach_id=%d',array($id));
					if($edited_on)
						$this->logit('Edited in epesi: '.$edited_on);
					if($edited_on>=$time || $edited_on>=$kk['created_on'] || $kk['created_on']>=$time) {
						$this->logit('Skipping.');
						$skipped++;
						continue;
					} else {
						if($file && file_exists($file)) {
							$this->logit('Updating.');
							$updated++;
							$rev = DB::GetOne('SELECT max(revision)+1 FROM utils_attachment_file WHERE attach_id=%d',array($id));
							DB::Execute('UPDATE utils_attachment_link SET attachment_key=%s,local=%s,permission=%d,permission_by=%d,other_read=%b WHERE id=%d',array($key,$group,$permission,$created_by,$other_read,$id));
							DB::Execute('INSERT INTO utils_attachment_file(attach_id,original,created_by,created_on,revision) VALUES(%d,%s,%d,%T,%d)',array($id,$oryg,$created_by,$created_on,$rev));
							$local = 'data/Utils_Attachment/'.$group;
							@mkdir($local,0777,true);
							rename($file,$local.'/'.$id.'_'.$rev);
						} else {
							$this->logit('File doesn\'t exists. Skipping.');
							$skipped++;
						}
					}
				}
			}
			DB::Replace('crm_import_attach',array('created_on'=>DB::DBTimeStamp($time),'id'=>$id,'original'=>DB::qstr($v[$header['ATTACHMENTID']])),'id');
		}
		fclose($f);

		$this->logit('added='.$added.', updated='.$updated.', skipped='.$skipped);
		$this->logit('=============================================================');
		$this->set_log_file('');

		Epesi::alert($this->lang->t("Data imported successfully. "),false);
		location(array());
	}

	public function upload_activities($file, $oryginal_file) {
		set_time_limit(0);
		ini_set("memory_limit","512M");
		$f = fopen($file,'r');
		$header = fgetcsv($f);
		if(!$header) {
			Epesi::alert("Invalid csv file",false);
			return 0;
		}
		$header = array_flip($header);
		$ch = count($header);
		$mid = md5('crm_tasks');

		$adir = $this->get_data_dir().'attachments/';
		$num_rec = 0;
		$num_call = 0;

		$added_tasks = 0;
		$skipped_tasks = 0;
		$updated_tasks = 0;

		$added_events = 0;
		$skipped_events = 0;
		$updated_events = 0;

		$this->set_log_file('activities');
		$this->logit('=========================== upload ==========================');
		
		while($v=fgetcsv($f)) {
			if(count($v)!=$ch) continue;
			if($v[$header['Recurring Period']]>0) {
				$num_rec++;
				continue;
			}
			

			$prio = (2-$v[$header['PRIORITYID']]);
			$stat = $v[$header['STATUSNUM']];
			$desc = $v[$header['Details']].($v[$header['Location']]?"\n Location: ".$v[$header['Location']]:'');
			$title = $v[$header['Regarding']];
			$start = strtotime($v[$header['Start Date/Time']]);
			$end = strtotime($v[$header['End Date/Time']]);
			$timeless = $v[$header['Timeless']];
			$access = ($v[$header['Private Activity']]?2:0);
			$created_by = $this->get_add_user($v[$header['CREATEUSERID']]);
			$created_by_contact = CRM_ContactsCommon::get_contact_by_user_id($created_by);
			$time = strtotime($v[$header['Edit Date']]?$v[$header['Edit Date']]:$v[$header['Create Date']]);
			$created_on = strtotime($v[$header['Create Date']]);
			$contact = DB::GetOne('SELECT id FROM crm_import_contact WHERE original=%s',array($v[$header['CONTACTID']]));

			$this->logit($v[$header['ACTIVITY_NAME']].' on '.date('Y-m-d H:i:s',$start).' regarding "'.$title.'" ('.date('Y-m-d H:i:s',$time).').');

			if($created_by_contact===null) {
				$this->logit('Contact for user "'.$v[$header['CREATEUSERID']].'" not found. Skipping.');
				switch($v[$header['ACTIVITY_NAME']]) {
					case 'Call':
						$num_call++;
						break;
					case 'To-do':
						$skipped_tasks++;
						break;
					case 'Vacation':
					case 'Seminar-Training':
					case 'Personal Activity':
						break;
					case 'Meeting':
					default:
						$skipped_events++;
						break;
				}
				continue;
			}
			$created_by_contact = $created_by_contact['id'];
		
			switch($v[$header['ACTIVITY_NAME']]) {
				case 'Call':
		//			$id = Utils_RecordBrowserCommon::new_record('phonecall',array('subject'=>$title,'description'=>$desc,''));
		//			Utils_RecordBrowserCommon::set_record_properties('phonecall',$id,array('created_by'=>$created_by,'created_on'=>$created_on));
					$this->logit('Skipping.');
					$num_call++;
					break;
				case 'To-do':
					$kk = DB::GetRow('SELECT created_on,id FROM crm_import_task WHERE original=%s',array($v[$header['ACTIVITYID']]));
					$rec = array(	'title'=>$title,
							'description'=>$desc,
							'priority'=>$prio,
							'deadline'=>$end,
							'is_deadline'=>($end!=null),
							'status'=>($stat==0)?0:2,
							'longterm'=>false,
							'page_id'=>$mid,
							'permission'=>$access,
							'employees'=>array($created_by_contact),
							'customers'=>$contact!=false?array($contact):array()
						);
					if(empty($kk)) {
						$id = Utils_RecordBrowserCommon::new_record('task', $rec); 
						Utils_RecordBrowserCommon::set_record_properties('task', $id, array('created_by'=>$created_by,'created_on'=>$created_on));					
						$this->logit('Adding.');
						$added_tasks++;
					} else {
						$id = $kk['id'];
						$info = Utils_RecordBrowserCommon::get_record_info('task',$id);
						$edited_on = $info['edited_on'];
						if($edited_on)
							$this->logit('Edited in epesi: '.$edited_on);
						else
							$edited_on = 0;
						if($edited_on>=$time || $edited_on>=$kk['created_on'] || $kk['created_on']>=$time) {
							$this->logit('Skipping.');
							$skipped_tasks++;
							continue;
						} else {
							$this->logit('Updating.');
							$updated_tasks++;
							Utils_RecordBrowserCommon::update_record('task', $id, $rec, false, $time); 
							Utils_RecordBrowserCommon::set_record_properties('task', $id, array('created_by'=>$created_by,'created_on'=>$created_on));					
						}
					}

					DB::Replace('crm_import_task',array('created_on'=>DB::DBTimeStamp($time),'id'=>$id,'original'=>DB::qstr($v[$header['ACTIVITYID']])),'id');
					break;
				case 'Vacation':
				case 'Seminar-Training':
				case 'Personal Activity':
					break;
				case 'Meeting':
				default:
					$kk = DB::GetRow('SELECT created_on,id FROM crm_import_event WHERE original=%s',array($v[$header['ACTIVITYID']]));
					if(empty($kk)) {
						DB::Execute('INSERT INTO crm_calendar_event(title,description,start,end,timeless,access,priority,created_on,created_by) VALUES'.
								'(%s,%s,%d,%d,%b,%d,%d,%T,%d)',array($title,$desc,$start,$end,$timeless,$access,$prio,$created_on,$created_by));
	
						$id = DB::Insert_ID('crm_calendar_event','id');
						$this->logit('Adding.');
						$added_events++;
					} else {
						$id = $kk['id'];
						$edited_on = DB::GetOne('SELECT edited_on FROM crm_calendar_event WHERE id=%d',array($id));
						if($edited_on)
							$this->logit('Edited in epesi: '.$edited_on);
						else
							$edited_on = 0;
						if($edited_on>=$time || $edited_on>=$kk['created_on'] || $kk['created_on']>=$time) {
							$this->logit('Skipping.');
							$skipped_events++;
							continue;
						} else {
							$this->logit('Updating.');
							$updated_tasks++;
							DB::Execute('UPDATE crm_calendar_event SET title=%s,description=%s,start=%d,end=%d,timeless=%b,access=%d,priority=%d,created_on=%T,created_by=%d,edited_on=%T,edited_by=%d WHERE id=%d',array($title,$desc,$start,$end,$timeless,$access,$prio,$created_on,$created_by,$time,$created_by,$id));
							DB::Execute('DELETE FROM crm_calendar_event_group_emp WHERE id=%d',array($id));
							DB::Execute('DELETE FROM crm_calendar_event_group_cus WHERE id=%d',array($id));
						}
					}

					DB::Execute('INSERT INTO crm_calendar_event_group_emp(id,contact) VALUES(%d,%d)',array($id,$created_by_contact));
					if($contact!==false)
						DB::Execute('INSERT INTO crm_calendar_event_group_cus(id,contact) VALUES(%d,%d)',array($id,$contact));

					DB::Replace('crm_import_event',array('created_on'=>DB::DBTimeStamp($time),'id'=>$id,'original'=>DB::qstr($v[$header['ACTIVITYID']])),'id');
					break;

			}
		}

		$this->logit('skipped activities with recurring period='.$num_rec);
		$this->logit('added tasks='.$added_tasks.', updated tasks='.$updated_tasks.', skipped tasks='.$skipped_tasks);
		$this->logit('added events='.$added_events.', updated events='.$updated_events.', skipped events='.$skipped_events);
		$this->logit('=============================================================');
		$this->set_log_file('');
		
		Epesi::alert($this->lang->t("Data imported successfully. "),false);
		location(array());
	}
}

?>
