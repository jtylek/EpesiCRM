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
		$f = $this->init_module('Utils/FileUpload');
		$f->addElement('header',null,$this->lang->t('Contacts & Companies'));
		$this->display_module($f,array(array($this,'upload_contacts')));
	}
	
	private function get_add_user($name) {
		static $mail;
		if(!isset($mail))
			$mail = Base_User_LoginCommon::get_mail(Acl::get_user());
		$name = str_replace(array(' ','\''),array('_',''), strtolower($name));
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
			$contacts[] = $x;
			if(!isset($created_map[$x[$header['CREATEUSERID']]]))
				$created_map[$x[$header['CREATEUSERID']]] = $this->get_add_user($x[$header['CREATEUSERID']]);
		}
		
		fclose($f);
		
		foreach($contacts as $x) {
			//companies
			$time = strtotime($x[$header['Edit Date']]?$x[$header['Edit Date']]:$x[$header['Create Date']]);
			if($x[$header['Company']]) {
				if(!$x[$header['City']]) $x[$header['City']] = 'n/a';
				$kk = DB::GetRow('SELECT cic.created_on,cic.id FROM crm_import_company cic WHERE cic.original=%s',array($x[$header['CONTACTID']]));
				if(!$kk) {
					$kk = CRM_ContactsCommon::get_companies(array('company_name'=>$x[$header['Company']],'city'=>$x[$header['City']]));
					if(empty($kk))
						$kk = CRM_ContactsCommon::get_companies(array('company_name'=>$x[$header['Company']]));
					if(!empty($kk)) {
						$kk = array_pop($kk);
						$ccc = $kk['id'];
						unset($kk);
					}
					$imported_on = 0;
				} else {
					$imported_on = strtotime($kk['created_on']);
					$ccc = $kk['id'];
					unset($kk);
				}
				if(isset($ccc)) {
					$r = Utils_RecordBrowserCommon::get_record_info('company',$ccc);
					$edited_on = isset($r['edited_on'])?$r['edited_on']:0;
				}
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


				if($imported_on == 0 && !isset($ccc)) { //it wasn't imported and there is no such company
					$ccc = Utils_RecordBrowserCommon::new_record('company', $v);
					DB::Replace('crm_import_company',array('created_on'=>DB::DBTimeStamp($time),'id'=>$ccc,'original'=>DB::qstr($x[$header['CONTACTID']])),'id');
				} else if($imported_on == 0 && isset($ccc)) {//not imported but company exists
					if($edited_on>$time) //it was edited locally later then in act!
						print('Skipping company: "'.$x[$header['Company']].'" because it was edited in epesi.<br>');
					else {
						Utils_RecordBrowserCommon::update_record('company', $ccc, $v,true);
						DB::Replace('crm_import_company',array('created_on'=>DB::DBTimeStamp($time),'id'=>$ccc),'id');				
					}
				} else if($edited_on>$imported_on || $edited_on>$time) { //it was imported and later edited in epesi
					print('Skipping company: "'.$x[$header['Company']].'" because it was edited in epesi.<br>');
				} else if($imported_on<$time) { //it was imported and import edit time is newer then last import time
					Utils_RecordBrowserCommon::update_record('company', $ccc, $v,true);
					DB::Replace('crm_import_company',array('created_on'=>DB::DBTimeStamp($time),'id'=>$ccc),'id');
				}
			} else {
				$ccc = null;
			}

			//groups
			$gg = array();
			for($kk=1; $kk<5; $kk++) {
				if(!$x[$header['User '.$kk]]) continue;
				if(!isset($groups[$x[$header['User '.$kk]]])) {
					$path = "/Contacts_Groups/".strtolower($x[$header['User '.$kk]]);
					Utils_CommonDataCommon::set_value($path,$x[$header['User '.$kk]]);
					$groups[$x[$header['User '.$kk]]] = Utils_CommonDataCommon::get_id($path);
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
					print('Skipping contact: "'.$first_last.'"<br>');
				else if($kk['created_on']<$time) {
					Utils_RecordBrowserCommon::update_record('contact',$kk['id'],$this_contact);
					DB::Replace('crm_import_contact',array('created_on'=>DB::DBTimeStamp($time),'id'=>$kk['id']),'id');
				}

			}
			
			$created_by = $created_map[$x[$header['CREATEUSERID']]];
			$create_date = strtotime($x[$header['Create Date']]);
			Utils_RecordBrowserCommon::set_record_properties('contact',$id,array('created_by'=>$created_by,'created_on'=>$create_date));
		}
	}

}

?>