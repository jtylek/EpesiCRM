<?php
/**
 * CRM Contacts class.
 *
 * @author Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-crm
 * @subpackage contacts
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class CRM_ContactsCommon extends ModuleCommon {
    public static $paste_or_new = 'new';

    public static function get_contacts($crits = array(), $cols = array(), $order = array(), $limit = array()) {
        return Utils_RecordBrowserCommon::get_records('contact', $crits, $cols, $order, $limit);
    }
    public static function get_companies($crits = array(), $cols = array(), $order = array(), $limit = array()) {
        return Utils_RecordBrowserCommon::get_records('company', $crits, $cols, $order, $limit);
    }
    public static function no_contact_message() {
        print(Base_LangCommon::ts('CRM_Contacts','Your user doesn\'t have contact, please assign one'));
    }
    public static function get_contact_by_user_id($uid) {
        static $cache = array();
        if (isset($cache[$uid])) {
            if ($cache[$uid] == -1) return null;
            else return $cache[$uid];
        }
        $cid = Utils_RecordBrowserCommon::get_id('contact','login',$uid);
        if ($cid === false || $cid === null){
            $cache[$uid] = -1;
            return null;
        }
        $cache[$uid] = $ret = CRM_ContactsCommon::get_contact($cid);
        return $ret;
    }
    public static function get_contact($id) {
        static $cache;
        if(!isset($cache[$id]))
            $cache[$id] = Utils_RecordBrowserCommon::get_record('contact', $id);
        return $cache[$id];
    }
    public static function get_company($id) {
        static $cache;
        if(!isset($cache[$id]))
            $cache[$id] = Utils_RecordBrowserCommon::get_record('company', $id);
        return $cache[$id];
    }
    public static function get_main_company() {
        try {
            return Variable::get('main_company');
        } catch(NoSuchVariableException $e) {
            return null;
        }
    }
    public static function get_my_record() {
        $me = self::get_contact_by_user_id(Acl::get_user());
        if ($me===null) $me = array('id'=>-1, 'first_name'=>'', 'last_name'=>'', 'company_name'=>array(), 'login'=>-1);
        return $me;
    }
    public static function access_company($action, $param=null){
        $i = self::Instance();
        switch ($action) {
            case 'browse_crits':    if ($i->acl_check('browse companies')) return array('(!permission'=>2, '|:Created_by'=>Acl::get_user());
                                    $me = self::get_my_record();
                                    if ($me) return array('company_name'=>$me['company_name']);
                                    return false;
            case 'browse':  return true;
            case 'view':    if ($i->acl_check('view company') && ($param['permission']!=2 || $param['created_by']==Acl::get_user())) return true;
                            $me = self::get_my_record();
                            if ($me && $param['id']==$me['company_name']) return true;
                            return false;
            case 'clone':
            case 'add':     return $i->acl_check('edit company');
            case 'edit':    if ($param['permission']>=1 && $param['created_by']!=Acl::get_user()) return false;
                            $me = self::get_my_record();
                            if ($me && in_array($param['id'],$me['company_name']) && $i->acl_check('edit my company')) return true; //my company
                            if (!$i->acl_check('edit company')) return false;
							return array('permission'=>$param['created_by']==Acl::get_user());
            case 'delete':  if ($param['created_by']==Acl::get_user()) return true;
                            return $i->acl_check('delete company');
        }
        return false;
    }
    public static function access_contact($action, $param=null){
        $i = self::Instance();
        switch ($action) {
            case 'browse_crits':    if ($i->acl_check('browse contacts')) return array('(!permission'=>2, '|login'=>Acl::get_user(), '|:Created_by'=>Acl::get_user());
                                    else return array('login'=>Acl::get_user());
            case 'browse':  return true;
            case 'view':    if (!$i->acl_check('view contact')) {
                                return $param['login']==Acl::get_user();
                            }
                            return ($param['permission']!=2 || $param['login']==Acl::get_user() || $param['created_by']==Acl::get_user());
            case 'clone':
            case 'add':     return $i->acl_check('edit contact');
            case 'edit':    if ($param['login']==Acl::get_user()) return true; //me
                            if ($param['permission']>=1 && $param['created_by']!=Acl::get_user()) return false;
                            if ($i->acl_check('edit contact')) return array('permission'=>$param['created_by']==Acl::get_user());
                            if ($i->acl_check('edit my company contacts')) {
                                $me = self::get_my_record();
                                foreach($param['company_name'] as $cid)
                                    if(in_array($cid,$me['company_name'])) return true; //customer
                            }
                            return false;
            case 'delete':  if ($param['created_by']==Acl::get_user()) return true;
                            return $i->acl_check('delete company');
        }
        return false;
    }

    /*--------------------------------------------------------------------*/
    public static function menu() {
        $ret = array(
            'CRM'=>array(
                '__submenu__'=>1,
                'Contacts'=>array('mode'=>'contact','__icon__'=>'contacts.png','__icon_small__'=>'contacts-small.png'),
                'Companies'=>array('mode'=>'company','__icon__'=>'companies.png','__icon_small__'=>'companies-small.png')
            ));
        $ret['My settings']=array('__submenu__'=>1);

        $me = self::get_my_record();
        if($me['id']!=-1) {
            $ret['My settings']['My Contact']=array('mode'=>'my_contact','__icon__'=>'contacts.png','__icon_small__'=>'contacts-small.png');
        }
        if(self::Instance()->acl_check('view company')) {
            $ret['My settings']['Main Company']=array('mode'=>'main_company','__icon__'=>'companies.png','__icon_small__'=>'companies-small.png');
        }
        if(count($ret['My settings'])==1)
            unset($ret['My settings']);
        return $ret;
    }
    public static function caption() {
        return 'Companies & Contacts';
    }
    public static function admin_caption() {
        return 'Main Company';
    }
    public static function compare_strings($a, $b) {
        $let_a = array();
        $sa = strlen($a);
        for ($i=0; $i<$sa; $i++) {
            $let = $a{$i};
            if (!isset($let_a[$let])) $let_a[$let] = 0;
            $let_a[$let]--;
        }
        $sb = strlen($b);
        for ($i=0; $i<$sb; $i++) {
            $let = $b{$i};
            if (!isset($let_a[$let])) $let_a[$let] = 0;
            $let_a[$let]++;
        }
        $score = 0;
        foreach ($let_a as $v) $score += abs($v);
        $len = max($sa, $sb);
        if ($score>$len) return 0;
        $score = $len-$score;
        return $score/$len;
    }
    public static function check_for_duplicates($data, $js_function=null){
        return false;
        $submit_js = Utils_RecordBrowser::$rb_obj->form->get_submit_form_js();
        if ($js_function===null) {
            eval_js('contact_duplicate_fill = function (cont_id, comp_id) {'.
                        'if(comp_id==null){'.
                            'if($("company"))$("company").disabled=1;'.
                            '$("contact").disabled=1;'.
                        '}else{'.
                            'if($("company")){'.
                                '$("company").value=comp_id;'.
                                '$("company").fire("e_cs:load");'.
                                '$("company").fire("native:change");'.
                                'if(cont_id==null)$("contact").disabled=1;'.
                                'else{'.
                                    'setTimeout(function(){$("contact").value=cont_id;'.$submit_js.'},1000);'. // TODO: poor solution
                                '}'.
                            '}else{'.
                                '$("contact").value=cont_id;'.$submit_js.';'.
                            '}'.
                        '}'.
                    '}');
            $js_function = 'contact_duplicate_fill';
        }
        $submit_js = '';
        $crits = array();
        $op = '(';
        $comp_ids = array();
        $is_company = false;
        if (isset($data['company']) && $data['company'] && $data['company']!=-1) {
            $comp_ids[] = $data['company'];
            $crits[$op.'company_name'] = $comp_ids;
            $op = '|';
            $is_company = true;
        } elseif (isset($data['company_name']) && $data['company_name']) {
            $companies = Utils_RecordBrowserCommon::get_records('company', array('~"company_name'=>DB::Concat(DB::qstr('%'),DB::qstr($data['company_name']),DB::qstr('%'))));
            foreach ($companies as $k=>$v)
                $comp_ids[] = $v['id'];
            $crits[$op.'company_name'] = $comp_ids;
            $op = '|';
            $is_company = true;
        }
        if ($data['first_name']) {
            $crits[$op.'~"first_name'] = DB::Concat(DB::qstr('%'),DB::qstr($data['first_name']),DB::qstr('%'));
            $op = '|';
        }
        if ($data['last_name']) {
            $crits[$op.'~"last_name'] = DB::Concat(DB::qstr('%'),DB::qstr($data['last_name']),DB::qstr('%'));
            $op = '|';
        }
        $contacts = Utils_RecordBrowserCommon::get_records('contact', $crits);
        if (empty($contacts))
            return false;
        $contacts_final = array();
        $output = array();
        $precission = 1.4;
        foreach ($contacts as $k=>$v) {
            $score = 0;
            if ($is_company)
                foreach ($v['company_name'] as $l=>$e)
                    if (in_array($e, $comp_ids)) {
                        $score+=0.5;
                        break;
                    }
            else
                $score += 0.3;
            $score += self::compare_strings($v['first_name'],$data['first_name']);
            $score += self::compare_strings($v['last_name'],$data['last_name']);
            if ($score<$precission) continue;
            $first_comp = array_pop($v['company_name']);
            if ($first_comp) array_push($v['company_name'],$first_comp);
            $output[$score] = '<a href="javascript:void(0);" onmouseup="'.$js_function.'('.$v['id'].','.($first_comp?$first_comp:'\'\'').');leightbox_deactivate(\'crm_contact_duplicates\');'.$submit_js.'">[Use this]</a> '.self::contact_format_default($v,true).'<br>';
        }
        if (empty($output)) return false;
        ksort($output);
        $output = implode('', $output);
        $output = '<a href="javascript:void(0);" onmouseup="'.$js_function.'(null,null);leightbox_deactivate(\'crm_contact_duplicates\');'.$submit_js.'">[Add as new]</a><br>'.$output;
        Libs_LeightboxCommon::display('crm_contact_duplicates', $output, 'Contact duplicate found.');
        print('<a '.Libs_LeightboxCommon::get_open_href('crm_contact_duplicates').' style="display:none;">L</a>');
        eval_js('leightbox_activate("crm_contact_duplicates");');
        return true;
    }
    public static function crm_company_datatype($field = array()) {
        if (!isset($field['QFfield_callback'])) $field['QFfield_callback'] = array('CRM_ContactsCommon', 'QFfield_company');
        if (!isset($field['display_callback'])) $field['display_callback'] = array('CRM_ContactsCommon', 'display_company');
        $field['type'] = $field['param']['field_type'];
        $param = 'company::Company Name';
        if (isset($field['param']['crits'])) $param .= ';'.implode('::',$field['param']['crits']);
        else $param .= ';::';
        $field['param'] = $param;
        return $field;
    }
    public static function crm_contact_datatype($field = array()) {
        if (!isset($field['QFfield_callback'])) $field['QFfield_callback'] = array('CRM_ContactsCommon', 'QFfield_contact');
        if (!isset($field['display_callback'])) $field['display_callback'] = array('CRM_ContactsCommon', 'display_contact');
        $field['type'] = $field['param']['field_type'];
        $param = 'contact::Last Name|First Name';
        if (isset($field['param']['format'])) $param .= ';'.implode('::',$field['param']['format']);
        else $param .= ';::';
        if (isset($field['param']['crits'])) $param .= ';'.implode('::',$field['param']['crits']);
        else $param .= ';::';
        $field['param'] = $param;
        return $field;
    }
    public static function crm_company_contact_datatype($field = array()) {
        if (!isset($field['QFfield_callback'])) $field['QFfield_callback'] = array('CRM_ContactsCommon', 'QFfield_company_contact');
        if (!isset($field['display_callback'])) $field['display_callback'] = array('CRM_ContactsCommon', 'display_company_contact');
        $field['type'] = $field['param']['field_type'];
        $param = '';
        if ($field['type']=='select') {
            $field['type'] = 'text';
            $param = 64;
        }
        $field['param'] = $param;
        return $field;
    }

    public static function display_company_contact($record, $nolink, $desc) {
        $v = $record[$desc['id']];
        $def = '';
        if (!is_array($v)) $v = array($v);
        foreach($v as $k=>$w){
            if ($def) $def .= '<br>';
            $def .= Utils_RecordBrowserCommon::no_wrap(self::autoselect_company_contact_format($w, $nolink));
        }
        if (!$def)  $def = '---';
        return $def;
    }
    public static function autoselect_company_contact_format($arg, $nolink=false) {
        $x = explode(':', $arg);
        if(count($x)==2) {
            list($rset, $id) = $x;
        } else {
            $id = $x[0];
            $rset = 'P';
        }
        if (!$id) return '---';
        if ($rset=='P') $val = self::contact_format_default($id, $nolink);
        else $val = self::company_format_default($id, $nolink);
        $val = '['.$rset.'] '.$val;
        return $val;
    }
    public static function auto_company_contact_suggestbox($str, $fcallback) {
        $str = explode(' ', trim($str));
        $ret = array();
        foreach (array('contact'=>'P', 'company'=>'C') as $k=>$v) {
            $crits = array();
            foreach ($str as $v2) if ($v2) {
                $v2 = DB::Concat(DB::qstr('%'),DB::qstr($v2),DB::qstr('%'));
                switch ($k) {
                    case 'contact':
                        $crits = Utils_RecordBrowserCommon::merge_crits($crits, array('(~"last_name'=>$v2,'|~"first_name'=>$v2));
                        $order = array('last_name'=>'ASC', 'first_name'=>'ASC');
                        break;
                    case 'company':
                        $crits = Utils_RecordBrowserCommon::merge_crits($crits, array('~"company_name'=>$v2));
                        $order = array('company_name'=>'ASC');
                        break;
                }
            }
            $recs = Utils_RecordBrowserCommon::get_records($k, $crits, array(), $order);
            foreach($recs as $v2) {
                $key = $v.':'.$v2['id'];
                $ret[$key] = self::autoselect_company_contact_format($key,true);
            }
        }
        asort($ret);
        $ret = array_slice($ret, 0, 10, true);
        return $ret;
    }

    public static function QFfield_company_contact(&$form, $field, $label, $mode, $default, $desc, $rb_obj = null) {
        $cont = array();
        if ($mode=='add' || $mode=='edit') {
            $fcallback = array('CRM_ContactsCommon','autoselect_company_contact_format');
            if ($desc['type']=='multiselect') {
                $form->addElement('automulti', $field, $label, array('CRM_ContactsCommon','auto_company_contact_suggestbox'), array($fcallback), $fcallback);
            } else {
                $form->addElement('autoselect', $field, $label, $cont, array(array('CRM_ContactsCommon','auto_company_contact_suggestbox'), array($fcallback)), $fcallback, array('id'=>$field));
            }
            $form->setDefaults(array($field=>$default));
        } else {
            $callback = $rb_obj->get_display_method($desc['name']);
            if (!is_callable($callback)) $callback = array('CRM_ContactsCommon','display_company_contact');
            $def = call_user_func($callback, $rb_obj->record, false, $desc);
//          $def = call_user_func($callback, array($field=>$default), false, $desc);
            $form->addElement('static', $field, $label, $def);
        }
    }
    public static function display_contact($record, $nolink=false, $desc=array()) {
        $v = $record[$desc['id']];
        $def = '';
        $first = true;
        $param = @explode(';',$desc['param']);
        if (!isset($param[1]) || $param[1] == '::') $callback = array('CRM_ContactsCommon', 'contact_format_default');
        else $callback = explode('::', $param[1]);
        if (!is_array($v)) $v = array($v);
        foreach($v as $k=>$w){
            if ($w=='') break;
            if ($first) $first = false;
            else $def .= '<br>';
            $def .= Utils_RecordBrowserCommon::no_wrap(call_user_func($callback, self::get_contact($w), $nolink));
        }
        if (!$def)  $def = '---';
        return $def;
    }

    public static function company_get_tooltip($record) {
        if(isset($record['group']) && is_array($record['group'])) {
            $group = Utils_CommonDataCommon::get_nodes('Companies_Groups',$record['group']);
            if($group)
                $group = implode(', ',$group);
            else
                $group = '';
        } else {
            $group = '';
        }
        return Utils_TooltipCommon::format_info_tooltip(array('Group'=>$group,
                'Phone'=>$record['phone'],
                'Fax'=>$record['fax'],
                'Email'=>$record['email'],
                'Web address'=>$record['web_address'],
                'Address 1'=>$record['address_1'],
                'Address 2'=>$record['address_2'],
                'City'=>$record['city'],
                'Zone'=>$record['zone']?Utils_CommonDataCommon::get_value('Countries/'.$record['country'].'/'.$record['zone']):'---',
                'Country'=>Utils_CommonDataCommon::get_value('Countries/'.$record['country']),
                'Postal Code'=>$record['postal_code']),'Utils_RecordBrowser');
    }
    public static function company_format_default($record,$nolink=false) {
        if (is_numeric($record)) $record = self::get_company($record);
        if (!$record) return null;
        $ret = '';
        if (!$nolink) {
            $ret .= Utils_RecordBrowserCommon::record_link_open_tag('company', $record['id']);
            $ret .= Utils_TooltipCommon::create($record['company_name'],self::company_get_tooltip($record));
            $ret .= Utils_RecordBrowserCommon::record_link_close_tag();
        } else {
            $ret .= $record['company_name'];
        }
        return $ret;
    }
    public static function contact_get_tooltip($record) {
        if(!is_array($record) || empty($record) || !isset($record['work_phone'])) return '';
        if(isset($record['group']) && is_array($record['group'])) {
            $group = Utils_CommonDataCommon::get_nodes('Contacts_Groups',$record['group']);
            if($group)
                $group = implode(', ',$group);
            else
                $group = '';
        } else {
            $group = '';
        }
        return Utils_TooltipCommon::format_info_tooltip(array(
                'Contact'=>'<STRONG>'.$record['last_name'].', '.$record['first_name'].'</STRONG>',
                'Work Phone'=>$record['work_phone'],
                'Mobile Phone'=>$record['mobile_phone'],
                'Fax'=>$record['fax'],
                'Email'=>$record['email'],
                'Web address'=>$record['web_address'],
                'Address 1'=>$record['address_1'],
                'Address 2'=>$record['address_2'],
                'City'=>$record['city'],
                'Zone'=>$record['zone']?Utils_CommonDataCommon::get_value('Countries/'.$record['country'].'/'.$record['zone']):'---',
                'Country'=>Utils_CommonDataCommon::get_value('Countries/'.$record['country']),
                'Postal Code'=>$record['postal_code'],
                'Group'=>$group
                ),'Utils_RecordBrowser');
    }
    public static function contact_format_default($record, $nolink=false){
        if (is_numeric($record)) $record = self::get_contact($record);
        if (!$record) return null;
        $ret = '';
		$format = Base_User_SettingsCommon::get('CRM_Contacts','contact_format');
		$label = str_replace(array('##l##','##f##'), array($record['last_name'], $record['first_name']), $format);
        if (!$nolink) {
            $ret .= Utils_RecordBrowserCommon::record_link_open_tag('contact', $record['id']);
            $ret .= Utils_TooltipCommon::create($label,self::contact_get_tooltip($record));
            $ret .= Utils_RecordBrowserCommon::record_link_close_tag();
        } else {
            $ret .= $label;
        }
        if (!empty($record['company_name'])) {
            $first_comp = reset($record['company_name']);
            $ret .= ' ['.Utils_RecordBrowserCommon::create_linked_label('company', 'Company Name', $first_comp, $nolink).']';
        }
        return $ret;
    }
    public static function contact_format_no_company($record, $nolink=false){
        if (is_numeric($record)) $record = self::get_contact($record);
        if (!$record || $record=='__NULL__') return null;
        $ret = '';
		$format = Base_User_SettingsCommon::get('CRM_Contacts','contact_format');
		$label = str_replace(array('##l##','##f##'), array($record['last_name'], $record['first_name']), $format);
        if (!$nolink) {
            $ret .= Utils_RecordBrowserCommon::record_link_open_tag('contact', $record['id']);
            $ret .= Utils_TooltipCommon::create($label,self::contact_get_tooltip($record));
            $ret .= Utils_RecordBrowserCommon::record_link_close_tag();
        } else {
            $ret .= $label;
        }
        return $ret;
    }
    public static function contacts_chainedselect_crits($default, $desc, $format_func, $ref_field){
        Utils_ChainedSelectCommon::create($desc['id'],array($ref_field),'modules/CRM/Contacts/update_contact.php', array('format'=>implode('::', $format_func), 'required'=>$desc['required']), $default);
        return null;
    }
    public function compare_names($a, $b) {
        return strcasecmp(strip_tags($a),strip_tags($b));
    }

    public static function automulti_contact_suggestbox($str, $crits=array(), $no_company=false) {
        $str = explode(' ', trim($str));
        foreach ($str as $k=>$v)
            if ($v) {
                $v = DB::Concat(DB::qstr('%'),DB::qstr($v),DB::qstr('%'));
                $recs = Utils_RecordBrowserCommon::get_records('company', array('~"company_name'=>$v), array(), array('company_name'=>'ASC'));
                $comp_ids = array();
                foreach ($recs as $w) $comp_ids[$w['id']] = $w['id'];
                $crits = Utils_RecordBrowserCommon::merge_crits($crits, array('(~"last_name'=>$v,'|~"first_name'=>$v, '|company_name'=>$comp_ids));
//              $crits = Utils_RecordBrowserCommon::merge_crits($crits, array('(~"last_name'=>$v,'|~"first_name'=>$v));
            }
        $recs = Utils_RecordBrowserCommon::get_records('contact', $crits, array(), array('last_name'=>'ASC'), 10);
        $ret = array();
        foreach($recs as $v) {
            if ($no_company)
                $ret[$v['id']] = self::contact_format_no_company($v, true);
            else
                $ret[$v['id']] = self::contact_format_default($v, true);
        }
        return $ret;
    }
    public static function autoselect_contact_suggestbox($str, $crits, $format_callback, $inc_companies = false) {
        $str = explode(' ', trim($str));
        foreach ($str as $k=>$v)
            if ($v) {
                $v = DB::Concat(DB::qstr('%'),DB::qstr($v),DB::qstr('%'));
                if ($inc_companies) {
                    $recs = Utils_RecordBrowserCommon::get_records('company', array('~"company_name'=>$v), array(), array('company_name'=>'ASC'));
                    $comp_ids = array();
                    foreach ($recs as $w) $comp_ids[$w['id']] = $w['id'];
                    $crits = Utils_RecordBrowserCommon::merge_crits($crits, array('(~"last_name'=>$v,'|~"first_name'=>$v, '|company_name'=>$comp_ids));
                } else {
                    $crits = Utils_RecordBrowserCommon::merge_crits($crits, array('(~"last_name'=>$v,'|~"first_name'=>$v));
                }
            }
        $recs = Utils_RecordBrowserCommon::get_records('contact', $crits, array(), array('last_name'=>'ASC'), 10);
        $ret = array();
        foreach($recs as $v) {
            $ret[$v['id']] = call_user_func($format_callback, $v, true);
        }
        return $ret;
    }
    public static function autoselect_company_suggestbox($str, $crits, $format_callback) {
        $str = explode(' ', trim($str));
        foreach ($str as $k=>$v)
            if ($v) {
                $v = DB::Concat(DB::qstr('%'),DB::qstr($v),DB::qstr('%'));
                $crits = Utils_RecordBrowserCommon::merge_crits($crits, array('~"company_name'=>$v));
            }
        $recs = Utils_RecordBrowserCommon::get_records('company', $crits, array(), array('company_name'=>'ASC'), 10);
        $ret = array();
        foreach($recs as $v) {
            $ret[$v['id']] = call_user_func($format_callback, $v, true);
        }
        return $ret;
    }
	
	public static function autoselect_contact_filter($rb, $field, $label, $crits = array(), $fcallback=null) {
        if ($fcallback==null) $fcallback = array('CRM_ContactsCommon','contact_format_no_company');
        $rb->set_custom_filter($field, array('type'=>'autoselect','label'=>$label,'args'=>array(), 'args_2'=>array(array('CRM_ContactsCommon','autoselect_contact_suggestbox'), array($crits, $fcallback)), 'args_3'=>$fcallback, 'trans_callback'=>array('CRM_ContactsCommon','autoselect_contact_filter_trans')));
	}
	
	public static function autoselect_contact_filter_trans($val, $field) {
        if ($val!='__NULL__' && $val) return array($field=>$val);
        else return array();
	}
	
	public static function autoselect_company_filter_trans($val, $field) {
        if ($val!='__NULL__' && $val) return array($field=>$val);
        else return array();
	}

    public static function QFfield_contact(&$form, $field, $label, $mode, $default, $desc, $rb_obj = null) {
        $cont = array();
        $param = explode(';',$desc['param']);
        if ($mode=='add' || $mode=='edit') {
            $adv_crits = null;
            if ($param[1] == '::') $callback = array('CRM_ContactsCommon', 'contact_format_default');
            else $callback = explode('::', $param[1]);
            if ($param[2] != '::') {
                $crit_callback = explode('::',$param[2]);
                if ($crit_callback[0]=='ChainedSelect') {
                    $crits = null;
                } else {
                    $crits = call_user_func($crit_callback, false);
                    $adv_crits = call_user_func($crit_callback, true);
                    if ($adv_crits === $crits) $adv_crits = null;
                }
            } else $crits = array();
            if ($crits===true) $crits = $adv_crits;
            if ($desc['type']!='multiselect' && (!isset($crit_callback) || $crit_callback[0]!='ChainedSelect')) $cont[''] = '---';
            $limit = false;
            if ($crits!==null) {
                $amount = Utils_RecordBrowserCommon::get_records_count('contact', $crits);
                $base_crits = $crits;
                if ($amount>Utils_RecordBrowserCommon::$options_limit) {
                    $limit = Utils_RecordBrowserCommon::$options_limit;
                    if ($desc['type']=='select') {
                        $present = false;
                        foreach ($crits as $k=>$v)
                            if (strstr($k, ':Recent')) {
                                $present = true;
                                break;
                            }
                        if (!$present) $base_crits = Utils_RecordBrowserCommon::merge_crits($base_crits, array(':Recent'=>true));
                    }
                }

                $contacts = self::get_contacts($base_crits, array(), array(), $limit);
                if (!is_array($default)) {
                    if ($default!='') $default = array($default); else $default=array();
                }
                $ext_rec = array_flip($default);
                foreach ($contacts as $v) {
                    $cont[$v['id']] = call_user_func($callback, $v, true);
                    unset($ext_rec[$v['id']]);
                }
                foreach($ext_rec as $k=>$v) {
                    $c = CRM_ContactsCommon::get_contact($k);
                    if ($c===null) continue;
                    $cont[$k] = call_user_func($callback, $c, true);
                }
                uasort($cont, array('CRM_ContactsCommon', 'compare_names'));
            }
            if ($desc['type']=='select') {
                if (is_numeric($limit)) {
                    unset($cont['']);
                    $form->addElement('autoselect', $field, $label, $cont, array(array('CRM_ContactsCommon','autoselect_contact_suggestbox'), array($crits, $callback)), $callback, array('id'=>$field));
                } else
                    $form->addElement($desc['type'], $field, $label, $cont, array('id'=>$field));
            } else {
                if ($adv_crits !== null || is_numeric($limit)) {
                    $form->addElement('automulti', $field, $label, array('CRM_ContactsCommon','autoselect_contact_suggestbox'), array($adv_crits!==null?$adv_crits:$crits, $callback), $callback);
                } else {
                    $form->addElement($desc['type'], $field, $label, $cont, array('id'=>$field));
                }
            }
            $form->setDefaults(array($field=>$default));
            if ($param[2] != '::')
                if ($crit_callback[0]=='ChainedSelect') {
                    if ($form->exportValue($field)) $default = $form->exportValue($field);
                    self::contacts_chainedselect_crits($default, $desc, $callback, $crit_callback[1]);
                }
        } else {
            $callback = $rb_obj->get_display_method($desc['name']);
            if (!is_callable($callback)) $callback = array('CRM_ContactsCommon','display_contact');
            $def = call_user_func($callback, $rb_obj->record, false, $desc);
//          $def = call_user_func($callback, array($field=>$default), false, $desc);
            $form->addElement('static', $field, $label, $def);
        }
    }

    public static function display_company($record, $nolink=false, $desc=null) {
        if ($desc!==null) $v = $record[$desc['id']];
        elseif(is_array($record)) $v = $record['id'];
        else $v = $record;
        if ((!is_numeric($v) && !is_array($v)) || $v==-1) return '---';
        $def = '';
        $first = true;
        if (!is_array($v)) $v = array($v);
        foreach($v as $k=>$w){
            if ($w=='') break;
            if ($first) $first = false;
            else $def .= '<br>';
            $def .= Utils_RecordBrowserCommon::no_wrap(Utils_RecordBrowserCommon::create_linked_label('company', 'Company Name', $w, $nolink));
        }
        if (!$def) return '---';
        return $def;
    }
    public static function QFfield_company(&$form, $field, $label, $mode, $default, $desc, $rb, $display_callbacks) {
        $comp = array();
        $param = explode(';',$desc['param']);
        if ($mode=='add' || $mode=='edit') {
            if ($param[1] != '::') $crits = call_user_func(explode('::',$param[1]));
            else $crits = array();
            if (isset($crits['_no_company_option'])) {
                $no_company_option = true;
                unset($crits['_no_company_option']);
            } else $no_company_option = false;
            $count = Utils_RecordBrowserCommon::get_records_count('company', $crits);
            if ($count>Utils_RecordBrowserCommon::$options_limit)
                $companies = array();
            else {
                $companies = self::get_companies($crits);
                if (!is_array($default)) {
                    if ($default!='') $default = array($default); else $default=array();
                }
                $ext_rec = array_flip($default);
                foreach ($companies as $v) {
                    $comp[$v['id']] = $v['company_name'];
                    unset($ext_rec[$v['id']]);
                }
                foreach($ext_rec as $k=>$v) {
                    $c = CRM_ContactsCommon::get_company($k);
                    $comp[$k] = $c['company_name'];
                }
                natcasesort($comp);
                $key = '';
                if ($no_company_option) {
                    $comp = array(''=>'['.Base_LangCommon::ts('CRM_Contacts','w/o company').']') + $comp;
                    $key = -1;
                }
                if ($desc['type']!=='multiselect') $comp = array($key => '---') + $comp;
            }
            if ($count>Utils_RecordBrowserCommon::$options_limit) {
                $callback = array('CRM_ContactsCommon','display_company');
                if ($desc['type']!=='multiselect')
                    $form->addElement('autoselect', $field, $label, array(), array(array('CRM_ContactsCommon','autoselect_company_suggestbox'), array($crits, $callback)), $callback, array('id'=>$field));
                else
                    $form->addElement('automulti', $field, $label, array('CRM_ContactsCommon','autoselect_company_suggestbox'), array($crits, $callback), $callback);
//                  $form->addElement($desc['type'], $field, $label, $comp, array('id'=>$field));
            } else $form->addElement($desc['type'], $field, $label, $comp, array('id'=>$field));
            if ($mode!=='add') $form->setDefaults(array($field=>$default));
        } else {
            /*$def = '';
            $first = true;
            if (is_numeric($default) || is_array($default)) {
                if (!is_array($default)) $default = array($default);
                foreach($default as $k=>$v){
                    if ($v=='') break;
                    if ($first) $first = false;
                    else $def .= '<br>';
                    $def .= Utils_RecordBrowserCommon::no_wrap(Utils_RecordBrowserCommon::create_linked_label('company', 'Company Name', $v));
                }
            }
            if (!$def)  $def = '---';
            $form->setDefaults(array($field=>$def));*/
            if (isset($display_callbacks[$desc['name']])) $callback = $display_callbacks[$desc['name']];
            else $callback = array('CRM_ContactsCommon', 'display_company');
            $form->addElement('static', $field, $label, call_user_func($callback, array('company'=>$default), false, array('id'=>'company')));
        }
    }

    public static function QFfield_webaddress(&$form, $field, $label, $mode, $default) {
        if ($mode=='add' || $mode=='edit') {
            $form->addElement('text', $field, $label);
            if ($mode=='edit') $form->setDefaults(array($field=>$default));
        } else {
            $form->addElement('static', $field, $label);
            $form->setDefaults(array($field=>self::display_webaddress(array('webaddress'=>$default), null, array('id'=>'webaddress'))));
        }
    }
    public static function QFfield_email(&$form, $field, $label, $mode, $default) {
        if ($mode=='add' || $mode=='edit') {
            $form->addElement('text', $field, $label);
            $form->addRule($field, Base_LangCommon::ts('CRM_Contacts','Invalid e-mail address'), 'email');//'/^[\._a-zA-Z0-9\-]+@[\.a-zA-Z0-9\-]+\.[a-zA-Z]{2,3}$/');
            if ($mode=='edit') $form->setDefaults(array($field=>$default));
        } else {
            $form->addElement('static', $field, $label);
            $form->setDefaults(array($field=>self::display_email(array('email'=>$default), null, array('id'=>'email'))));
        }
    }
    public static function check_new_company_name($data){
        if (isset($data['create_company_name'])) $data['create_company_name'] = trim($data['create_company_name']);
        if (isset($data['create_company']) && $data['create_company'] && (!isset($data['create_company_name']) || !$data['create_company_name'])) return array('create_company_name'=>Base_LangCommon::ts('Libs/QuickForm','Field requried'));
        return true;
    }
    public static function QFfield_login(&$form, $field, $label, $mode, $default, $desc, $rb=null) {
        if ($mode=='view') {
            $form->addElement('static', $field, $label);
            $form->setDefaults(array($field=>self::display_login(array('login'=>$default), null, array('id'=>'login'))));
            return;
        }
        if ($mode=='add' || $mode=='edit') {
            if (self::$paste_or_new=='new') {
                $form->addElement('checkbox', 'create_company', Base_LangCommon::ts('CRM/Contacts','Create new company'), null, 'onClick="document.getElementsByName(\'company_name\')[0].disabled=this.checked;document.getElementsByName(\'create_company_name\')[0].disabled=!this.checked;" '.Utils_TooltipCommon::open_tag_attrs(Base_LangCommon::ts('CRM_Contacts','Create a new company for this contact')));
                $form->addElement('text', 'create_company_name', Base_LangCommon::ts('CRM/Contacts','New company name'), array('disabled'=>1));
                $form->addFormRule(array('CRM_ContactsCommon', 'check_new_company_name'));
                if (isset($rb) && isset($rb->record['last_name']) && isset($rb->record['first_name'])) $form->setDefaults(array('create_company_name'=>$rb->record['last_name'].' '.$rb->record['first_name']));
                eval_js('Event.observe(\'last_name\',\'change\', update_create_company_name_field);'.
                        'Event.observe(\'first_name\',\'change\', update_create_company_name_field);'.
                        'function update_create_company_name_field() {'.
                            'document.forms[\''.$form->getAttribute('name').'\'].create_company_name.value = document.forms[\''.$form->getAttribute('name').'\'].last_name.value+" "+document.forms[\''.$form->getAttribute('name').'\'].first_name.value;'.
                        '}');
                eval_js('document.getElementsByName("company_name")[0].disabled=document.getElementsByName("create_company")[0].checked;document.getElementsByName("create_company_name")[0].disabled=!document.getElementsByName("create_company")[0].checked;');
            } else {
                $comp = self::get_company(self::$paste_or_new);
                $paste_company_info =
                    'document.getElementsByName("address_1")[0].value="'.$comp['address_1'].'";'.
                    'document.getElementsByName("address_2")[0].value="'.$comp['address_2'].'";'.
                    'document.getElementsByName("work_phone")[0].value="'.$comp['phone'].'";'.
                    'document.getElementsByName("fax")[0].value="'.$comp['fax'].'";'.
                    'document.getElementsByName("city")[0].value="'.$comp['city'].'";'.
                    'document.getElementsByName("postal_code")[0].value="'.$comp['postal_code'].'";'.
                    'var country = $(\'country\');'.
                    'var k = 0; while (k < country.options.length) if (country.options[k].value=="'.$comp['country'].'") break; else k++;'.
                    'country.selectedIndex = k;'.
                    'country.fire(\'e_u_cd:load\');'.
                    'zone = $(\'zone\');'.
                    'setTimeout("'.
                    'k = 0; while (k < zone.options.length) if (zone.options[k].value==\''.$comp['zone'].'\') break; else k++;'.
                    'zone.selectedIndex = k;'.
                    '",900);'.
                    'document.getElementsByName("web_address")[0].value="'.$comp['web_address'].'";';
                ;
                $form->addElement('button', 'paste_company_info', 'Paste Company Info', array('onClick'=>$paste_company_info));
            }
        }
        if ($mode!='view' && Base_AclCommon::i_am_admin()) {
            $form->addElement('checkbox', 'create_new_user', Base_LangCommon::ts('CRM/Contacts','Create new user'), null, 'id="crm_contacts_new_user" onClick="document.getElementsByName(\''.$field.'\')[0].disabled=this.checked;$(\'crm_contacts_new_login\').style.display=this.checked?\'inline\':\'none\';$(\'crm_contacts_select_user\').style.display=this.checked?\'none\':\'inline\';" '.Utils_TooltipCommon::open_tag_attrs(Base_LangCommon::ts('CRM_Contacts','Create a new epesi user for this contact')) );
            $form->addElement('text', 'new_login', Base_LangCommon::ts('CRM/Contacts','New user login'), array('id'=>'crm_contacts_new_login'));
            eval_js('$("crm_contacts_new_login").style.display=$("crm_contacts_new_user").checked?"inline":"none";');
            eval_js('$("crm_contacts_select_user").style.display=$("crm_contacts_new_user").checked?"none":"inline";');
            $form->addFormRule(array('CRM_ContactsCommon','check_new_username'));
        }
        if (($default!=='' && !Base_AclCommon::i_am_admin()) || $mode=='view') {
            $form->addElement('select', $field, $label, array($default=>($default!=='')?Base_UserCommon::get_user_login($default):'---'));
            $form->setDefaults(array($field=>$default));
            $form->freeze($field);
        } else {
            $ret = DB::Execute('SELECT id, login FROM user_login ORDER BY login');
            $users = array(''=>'---');
            while ($row=$ret->FetchRow()) {
                $contact_id = Utils_RecordBrowserCommon::get_id('contact','login',$row['id']);
                if ($contact_id===false || $contact_id===null || ($row['id']===$default && $mode!='add'))
                    if (Base_AclCommon::i_am_admin() || $row['id']==Acl::get_user())
                        $users[$row['id']] = $row['login'];
            }
            $form->addElement('select', $field, $label, $users, array('id'=>'crm_contacts_select_user'));
            $form->setDefaults(array($field=>$default));
        }
    }

    public static function check_new_username($arg) {
        if (!isset($arg['create_new_user']) || !$arg['create_new_user']) return true;
        if (!$arg['email']) return array('login'=>Base_LangCommon::ts('CRM/Contacts','E-mail is required when creating new user.'));
        if (!$arg['new_login']) return array('login'=>Base_LangCommon::ts('Libs/QuickForm','Field required'));
        if (Base_UserCommon::get_user_id($arg['new_login'])) return array('login'=>Base_LangCommon::ts('Base/User/Administrator','Username already taken'));
        return true;
    }

    public static function create_map_href($r) {
        return 'href="http://maps.google.com/maps?'.http_build_query(array('q'=>$r['address_1'].' '.$r['address_2'].', '.$r['city'].', '.$r['postal_code'].', '.Utils_CommonDataCommon::get_value('Countries/'.$r['country']))).'" target="_blank"';
    }

    public static function create_home_map_href($r) {
        return 'href="http://maps.google.com/maps?'.http_build_query(array('q'=>$r['home_address_1'].' '.$r['home_address_2'].', '.$r['home_city'].', '.$r['home_postal_code'].', '.Utils_CommonDataCommon::get_value('Countries/'.$r['home_country']))).'" target="_blank"';
    }

    public static function maplink($r,$nolink,$desc) {
        if (!$nolink) return Utils_TooltipCommon::create('<a '.self::create_map_href($r).'>'.$r[$desc['id']].'</a>',Base_LangCommon::ts('CRM_Contacts','Click here to search this location using google maps'));
        return $r[$desc['id']];
    }

    public static function home_maplink($r,$nolink,$desc) {
        if (!$nolink) return Utils_TooltipCommon::create('<a '.self::create_home_map_href($r).'>'.$r[$desc['id']].'</a>',Base_LangCommon::ts('CRM_Contacts','Click here to search this location using google maps'));
        return $r[$desc['id']];
    }

    public static function display_phone($r,$nolink,$desc) {
//      Does not return href="tel:
//      if(MOBILE_DEVICE && IPHONE && !$nolink && preg_match('/^([0-9\t\+-]+)/',$r[$desc['id']],$args))
//          return '<a href="tel:'.$args[1].'">'.$r[$desc['id']].'</a>';

        if(MOBILE_DEVICE && IPHONE && preg_match('/^([0-9\t\+-]+)/',$r[$desc['id']],$args))
            return '<a href="tel:'.$args[1].'">'.$r[$desc['id']].'</a>';
        $num = $r[$desc['id']];
        if($num && strpos($num,'+')===false) {
            if($r['country']) {
                $calling_code = Utils_CommonDataCommon::get_value('Calling_Codes/'.$r['country']);
                if($calling_code)
                    $num = $calling_code.$num;
            }
        }
        return CRM_CommonCommon::get_dial_code($num);
    }

    public static function display_fname($v, $nolink) {
        return Utils_RecordBrowserCommon::create_linked_label_r('contact', 'First Name', $v, $nolink);
    }
    public static function display_lname($v, $nolink) {
        return Utils_RecordBrowserCommon::create_linked_label_r('contact', 'Last Name', $v, $nolink);
    }
    public static function display_cname($v, $nolink) {
        return Utils_RecordBrowserCommon::create_linked_label_r('company', 'Company Name', $v, $nolink);
    }
    public static function display_webaddress($record, $nolink, $desc) {
        $v = $record[$desc['id']];
        if ($nolink) return $v;
        $v = trim($v, ' ');
        if ($v=='') return '';
        if (strpos(strtolower($v), 'http://')===false &&
            strpos(strtolower($v), 'https://')===false &&
            $v) $v = 'http://'.$v;
        return '<a href="'.$v.'" target="_blank">'.$v.'</a>';
    }
    public static function display_email($record, $nolink, $desc) {
        $v = $record[$desc['id']];
        if ($nolink) return $v;
        return '<a href="mailto:'.$v.'">'.$v.'</a>';
    }
    public static function display_login($record, $nolink, $desc) {
        $v = $record[$desc['id']];
        if (isset($_REQUEST['crm_contacts_edit_user']) &&
            is_numeric($_REQUEST['crm_contacts_edit_user']) &&
            Base_AclCommon::i_am_admin() &&
            Base_UserCommon::get_user_login($_REQUEST['crm_contacts_edit_user'])) {
            $x = ModuleManager::get_instance('/Base_Box|0');
            if (!$x) trigger_error('There is no base box module instance',E_USER_ERROR);
            $x->push_main('CRM/Contacts','edit_user_form',array($_REQUEST['crm_contacts_edit_user']));
            unset($_REQUEST['crm_contacts_edit_user']);
        }
        if (!$v)
            return '---';
        else {
            $login = Base_UserCommon::get_user_login($v);
            if (!$nolink && Base_AclCommon::i_am_admin()) return '<a '.Module::create_href(array('crm_contacts_edit_user'=>$v)).'>'.$login.'</a>';
            else return $login;
        }
    }
	public static function submit_company($values, $mode) {
        switch ($mode) {
			case 'display':
				$me = CRM_ContactsCommon::get_my_record();
				$emp = array($me['id']);
				$cus = array('C:'.$values['id']);
				$ret = array();
				if(self::Instance()->acl_check('"new" actions')) {
					if (ModuleManager::is_installed('CRM/Calendar')!==-1) $ret['new']['event'] = '<a '.Utils_TooltipCommon::open_tag_attrs(Base_LangCommon::ts('CRM/Contacts','New Event')).' '.Utils_RecordBrowserCommon::create_new_record_href('crm_meeting', array('employees'=>$emp,'customers'=>$cus,'status'=>0, 'priority'=>1, 'permission'=>0)).'><img border="0" src="'.Base_ThemeCommon::get_template_file('CRM_Calendar','icon-small.png').'"></a>';
					if (ModuleManager::is_installed('CRM/Tasks')!==-1) $ret['new']['task'] = '<a '.Utils_TooltipCommon::open_tag_attrs(Base_LangCommon::ts('CRM/Contacts','New Task')).' '.Utils_RecordBrowserCommon::create_new_record_href('task', array('employees'=>$emp,'customers'=>$cus,'status'=>0, 'priority'=>1, 'permission'=>0)).'><img border="0" src="'.Base_ThemeCommon::get_template_file('CRM_Tasks','icon-small.png').'"></a>';
					if (ModuleManager::is_installed('CRM/PhoneCall')!==-1) $ret['new']['phonecall'] = '<a '.Utils_TooltipCommon::open_tag_attrs(Base_LangCommon::ts('CRM/Contacts','New Phonecall')).' '.Utils_RecordBrowserCommon::create_new_record_href('phonecall', array('date_and_time'=>date('Y-m-d H:i:s'),'customer'=>'C:'.$values['id'],'employees'=>$me['id'],'status'=>0, 'permission'=>0, 'priority'=>1),'none',array('date_and_time')).'><img border="0" src="'.Base_ThemeCommon::get_template_file('CRM_PhoneCall','icon-small.png').'"></a>';
				}
				return $ret;
		}
		return $values;
	}
	
    public static function submit_contact($values, $mode) {
        switch ($mode) {
        case 'display':
            // display copy company data button and do update if needed
            self::copy_company_data_subroutine($values);

            $is_employee = false;
            if (is_array($values['company_name']) && in_array(CRM_ContactsCommon::get_main_company(), $values['company_name'])) $is_employee = true;
            $me = CRM_ContactsCommon::get_my_record();
            $emp = array($me['id']);
            $cus = array();
            if ($is_employee) $emp[] = $values['id'];
            else $cus[] = 'P:'.$values['id'];
            $ret = array();
            if(self::Instance()->acl_check('"new" actions')) {
                if (ModuleManager::is_installed('CRM/Calendar')!==-1) $ret['new']['event'] = '<a '.Utils_TooltipCommon::open_tag_attrs(Base_LangCommon::ts('CRM/Contacts','New Event')).' '.Utils_RecordBrowserCommon::create_new_record_href('crm_meeting', array('employees'=>$emp,'customers'=>$cus,'status'=>0, 'priority'=>1, 'permission'=>0)).'><img border="0" src="'.Base_ThemeCommon::get_template_file('CRM_Calendar','icon-small.png').'"></a>';
                if (ModuleManager::is_installed('CRM/Tasks')!==-1) $ret['new']['task'] = '<a '.Utils_TooltipCommon::open_tag_attrs(Base_LangCommon::ts('CRM/Contacts','New Task')).' '.Utils_RecordBrowserCommon::create_new_record_href('task', array('employees'=>$emp,'customers'=>$cus,'status'=>0, 'priority'=>1, 'permission'=>0)).'><img border="0" src="'.Base_ThemeCommon::get_template_file('CRM_Tasks','icon-small.png').'"></a>';
                if (ModuleManager::is_installed('CRM/PhoneCall')!==-1) $ret['new']['phonecall'] = '<a '.Utils_TooltipCommon::open_tag_attrs(Base_LangCommon::ts('CRM/Contacts','New Phonecall')).' '.Utils_RecordBrowserCommon::create_new_record_href('phonecall', array('date_and_time'=>date('Y-m-d H:i:s'),'customer'=>'P:'.$values['id'],'employees'=>$me['id'],'status'=>0, 'permission'=>0, 'priority'=>1),'none',false).'><img border="0" src="'.Base_ThemeCommon::get_template_file('CRM_PhoneCall','icon-small.png').'"></a>';
            }
            return $ret;
        case 'add':
            if ($values['email']=='' && $values['login']!=0 && $mode=='add')
                $values['email'] = DB::GetOne('SELECT mail FROM user_password WHERE user_login_id=%d', array($values['login']));
        case 'edit':
            if (isset($values['create_company'])) {
                $comp_id = Utils_RecordBrowserCommon::new_record('company',
                    array(  'company_name'=>$values['create_company_name'],
                            'address_1'=>$values['address_1'],
                            'address_2'=>$values['address_2'],
                            'country'=>$values['country'],
                            'city'=>$values['city'],
                            'zone'=>isset($values['zone'])?$values['zone']:'',
                            'postal_code'=>$values['postal_code'],
                            'phone'=>$values['work_phone'],
                            'fax'=>$values['fax'],
                            'web_address'=>$values['web_address'],
                            'permission'=>$values['permission'])
                );
                if (!isset($values['company_name'])) $values['company_name'] = array();
                if (!is_array($values['company_name'])) $values['company_name'] = array($values['company_name']);
                $values['company_name'][] = $comp_id;
            }
            if (Base_AclCommon::i_am_admin() && isset($values['create_new_user']) && $values['create_new_user']) {
                Base_User_LoginCommon::add_user($values['new_login'], $values['email']);
                $values['login'] = Base_UserCommon::get_user_id($values['new_login']);
            }
        }
        return $values;
    }

    public static function search_format_contact($id) {
        if(!self::Instance()->acl_check('browse contacts')) return false;
        $row = self::get_contacts(array('id'=>$id));
        if(!$row) return false;
        $row = array_pop($row);
        return Utils_RecordBrowserCommon::record_link_open_tag('contact', $row['id']).Base_LangCommon::ts('CRM_Contacts', 'Contact (attachment) #%d, %s %s', array($row['id'], $row['first_name'], $row['last_name'])).Utils_RecordBrowserCommon::record_link_close_tag();
    }

    public static function search_format_company($id) {
        if(!self::Instance()->acl_check('browse companies')) return false;
        $row = self::get_companies(array('id'=>$id));
        if(!$row) return false;
        $row = array_pop($row);
        return Utils_RecordBrowserCommon::record_link_open_tag('company', $row['id']).Base_LangCommon::ts('CRM_Contacts', 'Company (attachment) #%d, %s', array($row['id'], $row['company_name'])).Utils_RecordBrowserCommon::record_link_close_tag();
    }

    public static function search($word){
        $ret = array();
        if(self::Instance()->acl_check('browse contacts')) {
            $wo = explode(' ', $word);

            $crits = array();
            foreach ($wo as $w)
                $crits = Utils_RecordBrowserCommon::merge_crits($crits, array('("~first_name'=>DB::Concat(DB::qstr('%'),DB::qstr($w),DB::qstr('%')), '|"~last_name'=>DB::Concat(DB::qstr('%'),DB::qstr($w),DB::qstr('%'))));

            $result = self::get_contacts($crits);

            foreach ($result as $row)
                $ret[$row['id']] = Utils_RecordBrowserCommon::record_link_open_tag('contact', $row['id']).Base_LangCommon::ts('CRM_Contacts', 'Contact #%d, %s %s', array($row['id'], $row['first_name'], $row['last_name'])).Utils_RecordBrowserCommon::record_link_close_tag();
        }
        if(self::Instance()->acl_check('browse companies')) {
            $result = self::get_companies(array('"~company_name'=>DB::Concat('\'%\'',DB::qstr($word),'\'%\'')));

            foreach ($result as $row)
                $ret[$row['id']] = Utils_RecordBrowserCommon::record_link_open_tag('company', $row['id']).Base_LangCommon::ts('CRM_Contacts', 'Company #%d, %s', array($row['id'], $row['company_name'])).Utils_RecordBrowserCommon::record_link_close_tag();

            $result = self::get_companies(array('"~short_name'=>DB::Concat('\'%\'',DB::qstr($word),'\'%\'')));

            foreach ($result as $row)
                $ret[$row['id']] = Utils_RecordBrowserCommon::record_link_open_tag('company', $row['id']).Base_LangCommon::ts('CRM_Contacts', 'Company #%d, %s', array($row['id'], $row['company_name'])).Utils_RecordBrowserCommon::record_link_close_tag();
        }

        return $ret;
    }

    public static function contact_watchdog_label($rid = null, $events = array(), $details = true) {
        return Utils_RecordBrowserCommon::watchdog_label(
                'contact',
                Base_LangCommon::ts('CRM_Contacts','Contacts'),
                $rid,
                $events,
                array('CRM_ContactsCommon','contact_format_default'),
                $details
            );
    }
    public static function company_watchdog_label($rid = null, $events = array(), $details = true) {
        return Utils_RecordBrowserCommon::watchdog_label(
                'company',
                Base_LangCommon::ts('CRM_Contacts','Companies'),
                $rid,
                $events,
                'company_name',
                $details
            );
    }

    public static function contact_bbcode($text, $param, $opt) {
        return Utils_RecordBrowserCommon::record_bbcode('contact', array('first_name','last_name'), $text, $param, $opt);
    }

    public static function company_bbcode($text, $param, $opt) {
        return Utils_RecordBrowserCommon::record_bbcode('company', array('company_name'), $text, $param, $opt);
    }

    public static function get_html_record_info($created_by,$created_on,$edited_by=null,$edited_on=null, $id=null) {
        if ($created_by!==null) {
            $contact = CRM_ContactsCommon::contact_format_no_company(CRM_ContactsCommon::get_contact_by_user_id($created_by),true);
            if ($contact!='') $created_by = $contact;
            else $created_by = Base_UserCommon::get_user_login($created_by);
        } else $created_by = '';
        // If the record was edited get user contact info
        if ($edited_by!=null) {
            if ($edited_by!=$created_by) $contact = CRM_ContactsCommon::contact_format_no_company(CRM_ContactsCommon::get_contact_by_user_id($edited_by),true);
            if ($contact!='') $edited_by = $contact;
            else $edited_by = Base_UserCommon::get_user_login($edited_by);
        }

        $htmlinfo=array();
		if ($id) $htmlinfo['Record ID:'] = $id;
		$htmlinfo['Created by:'] = $created_by;
		$htmlinfo['Created on:'] = Base_RegionalSettingsCommon::time2reg($created_on);
            
		if ($edited_by!=null) {
			$htmlinfo=$htmlinfo+array(
				'Edited by:'=>$edited_by,
				'Edited on:'=>Base_RegionalSettingsCommon::time2reg($edited_on)
				);
        }
        return  Utils_TooltipCommon::format_info_tooltip($htmlinfo,'CRM_Contacts');
    }

    private static function copy_company_data_subroutine($values) {
        /* First click should generate html code for leightbox and show it.
         * This function is rarely used and we don't want to increase page size.
         * To do this we use REQUEST variable UCD.
         *
         * We use module variable UCD to indicate that form was shown and we
         * must check if it was submitted. If yes - do action. If it wasn't
         * we should come back to initial state - do not print LB.
         */
        if(isset($_REQUEST['UCD']) || Module::static_get_module_variable('CRM/Contacts', 'UCD', 0)) {
            $ucd = Module::static_get_module_variable('CRM/Contacts', 'UCD', 0);
            $ucd ++;
            if($ucd > 1) Module::static_unset_module_variable('CRM/Contacts', 'UCD');
            else Module::static_set_module_variable('CRM/Contacts', 'UCD', $ucd);

            $lid = 'UCDprompt';

            $form = ModuleManager::new_instance('Libs_QuickForm', null, 'QFUCDprompt');
            $form->construct();

            $sel_val = array();
            foreach($values['company_name'] as $id) {
                $sel_val[$id] = self::company_format_default(self::get_company($id), true);
            }
            $form->addElement('select', 'company', 'Select company:', $sel_val);
            unset($sel_val);

            $form->addElement('html', 'Select which fields should be copied:');
            $data = array( /* Source ID, Target ID, Text, Checked state */
                    array('sid'=>'address_1', 'tid'=>'address_1', 'text'=>'Address 1', 'checked'=>true),
                    array('sid'=>'address_2', 'tid'=>'address_2', 'text'=>'Address 2', 'checked'=>true),
                    array('sid'=>'city', 'tid'=>'city', 'text'=>'City', 'checked'=>true),
                    array('sid'=>'country', 'tid'=>'country', 'text'=>'Country', 'checked'=>true),
                    array('sid'=>'zone', 'tid'=>'zone', 'text'=>'Zone', 'checked'=>true),
                    array('sid'=>'postal_code', 'tid'=>'postal_code', 'text'=>'Postal Code', 'checked'=>true),
                    array('sid'=>'phone', 'tid'=>'work_phone', 'text'=>'Phone as Work Phone', 'checked'=>false),
                    array('sid'=>'fax', 'tid'=>'fax', 'text'=>'Fax', 'checked'=>false),
            );
            foreach($data as $row) {
                $form->addElement('checkbox', $row['sid'], $row['text'], '', $row['checked'] ? array('checked'=>'checked'): array());
            }

            $ok = $form->createElement('submit', 'submit', 'Confirm', array('onclick'=>'leightbox_deactivate("'.$lid.'")'));
            $cancel = $form->createElement('button', 'cancel', 'Cancel', array('onclick'=>'leightbox_deactivate("'.$lid.'")'));
            $form->addGroup(array($ok, $cancel));

            if($form->validate()) {
                $Uvalues = $form->exportValues();
                $fields = array();
                foreach($data as $row) {
                    if(array_key_exists($row['sid'], $Uvalues)) {
                        $fields[$row['tid']] = $row['sid'];
                    }
                }

                if(isset($Uvalues['company'])) {
                    $company = CRM_ContactsCommon::get_company($Uvalues['company']);
                    $new_data = array();
                    foreach($fields as $k => $v) {
                        $new_data[$k] = $company[$v];
                    }
                    Utils_RecordBrowserCommon::update_record('contact', $values['id'], $new_data);
                }

                Module::static_unset_module_variable('CRM/Contacts', 'UCD');
                location(array());
            }

            // set default to main company
            if(($mc = self::get_main_company()))
                $form->setDefaults(array('company'=>$mc));

            $html = $form->toHtml();

            Libs_LeightboxCommon::display($lid, $html);
            Base_ActionBarCommon::add('edit', 'Copy company data', Libs_LeightboxCommon::get_open_href($lid));
            eval_js('leightbox_activate(\''.$lid.'\')');
        }
        if( ! (isset($_REQUEST['UCD']) || Module::static_get_module_variable('CRM/Contacts', 'UCD', 0)) ) {
            if(count($values['company_name'])) Base_ActionBarCommon::add('edit', 'Copy company data', Module::create_href(array('UCD'=>true)));
        }
    }

    public static function applet_caption() {
        return "Contacts";
    }

    public static function applet_info() {
        return "Displays recent/favourites contacts";
    }

    public static function applet_settings() {
        return array_merge(Utils_RecordBrowserCommon::applet_settings(),array(
                array('name'=>'conds','label'=>'Display','type'=>'select','default'=>'fav','rule'=>array(array('message'=>'Field required', 'type'=>'required')),'values'=>array('fav'=>Base_LangCommon::ts('CRM_Contacts','favourites'),'rec'=>Base_LangCommon::ts('CRM_Contacts','recent')))));
    }
	public function user_settings() {
		$opts = array(
			'##f## ##l##' => '[First name] [Last Name]',
			'##l## ##f##' => '[Last Name] [First name]',
			'##l##, ##f##' => '[Last Name], [First name]'
		);
		return array('Regional settings'=>array(
				array('name'=>'contact_header', 'label'=>'Contacts display', 'type'=>'header'),
				array('name'=>'contact_format','label'=>'Contact format','type'=>'select','values'=>$opts,'default'=>'##l## ##f##')
					),
					'Filters'=>array( // Until there's an option to define user_settings variables and redirect the display to custom method at the same time, it's the best solution at hand
				array('name'=>'show_all_contacts_in_filters','label'=>'Show All Contacts in Filters','type'=>'hidden','default'=>1)
					));
	}

    public static function applet_info_format($r){
        $args=array(
                    'Work phone:'=>$r['work_phone'],
                    'Mobile phone:'=>$r['mobile_phone'],
                    'Home phone:'=>$r['home_phone'],
                    'Fax:'=>$r['fax'],
                    'E-mail:'=>$r['email']
                    );

        $ret = array('notes'=>Utils_TooltipCommon::format_info_tooltip($args,'CRM_Contacts'));
        return $ret;
    }

    //////////////////////////
    // mobile devices
    public function mobile_menu() {
        if(!Acl::is_user())
            return array();
        return array('Contacts'=>array('func'=>'mobile_contacts','color'=>'red'),'Companies'=>array('func'=>'mobile_companies','color'=>'black'));
    }

    public function mobile_contacts() {
        $sort = array('last_name'=>'ASC', 'first_name'=>'ASC');
        $info = array('company_name'=>0,'work_phone'=>1,'mobile_phone'=>1);
        $defaults = array('country'=>Base_User_SettingsCommon::get('Base_RegionalSettings','default_country'),
                        'zone'=>Base_User_SettingsCommon::get('Base_RegionalSettings','default_state'),
                        'permission'=>'0',
                        'home_country'=>Base_User_SettingsCommon::get('Base_RegionalSettings','default_country'),
                        'home_zone'=>Base_User_SettingsCommon::get('Base_RegionalSettings','default_state'));
        Utils_RecordBrowserCommon::mobile_rb('contact',array(),$sort,$info,$defaults);
    }

    public function mobile_companies() {
        $info = array('phone'=>1);
        $sort = array('company_name'=>'ASC');
        $defaults = array('country'=>Base_User_SettingsCommon::get('Base_RegionalSettings','default_country'),
                        'zone'=>Base_User_SettingsCommon::get('Base_RegionalSettings','default_state'),
                        'permission'=>'0');
        Utils_RecordBrowserCommon::mobile_rb('company',array(),$sort,$info,$defaults);
    }
}
?>
