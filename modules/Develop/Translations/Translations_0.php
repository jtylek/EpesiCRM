<?php
/**
 * Develop_Translations class.
 * 
 * @author Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-develop
 * @subpackage translations
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Develop_Translations extends Module {
	public function body() {
	    $tb = $this->init_module('Utils/TabbedBrowser');
	    $tb->set_tab('General', array($this, 'show_general'));
	    $tb->set_tab('Contributors', array($this, 'show_contributors'));
	    $tb->set_tab('Translations', array($this, 'show_translations'));
	    $tb->set_tab('Credits', array($this, 'contributors_summary'));
	    $tb->set_tab('Possible Infringement', array($this, 'suspicious_translations'));
	    $this->display_module($tb);
	    $tb->tag();
	}
	
	public function suspicious_translations() {
        print __('Below you can see strings where original contains EPESI word, but translated doesn\'t');
	    $pattern = DB::like().' '.DB::Concat(DB::qstr('%'), DB::qstr('EPESI'), DB::qstr('%'));
        $ret = DB::Execute('SELECT * FROM develop_trans_contribs dtc LEFT JOIN develop_trans_users dtu ON dtc.user_id=dtu.id WHERE org '.$pattern.' AND trans NOT '.$pattern.' AND trans!=""');
		$gb = $this->init_module('Utils_GenericBrowser', null, 'translations');
		$gb->set_table_columns(array(
			array('name'=>__('User'), 'width'=>'190px'), 
			array('name'=>__('IP'), 'width'=>'80px'), 
			array('name'=>__('Language'), 'width'=>'50px'), 
			array('name'=>__('Original')),
			array('name'=>__('Translated'))
		));
	    while ($row = $ret->FetchRow()) {
			$gb->add_row(
				$row['first_name'].' '.$row['last_name'],
				$row['ip'],
				$row['lang'],
				$row['org'],
				'<a target="_blank" href="http://translate.google.com/#'.$row['lang'].'/en/'.$row['trans'].'">'.$row['trans'].'</a>'
			);
	    }
	    $this->display_module($gb);
	}
	
	public function contributors_summary() {
        $limit = 30;
        $ret = DB::Execute('SELECT *, COUNT(dtc.trans) AS count FROM develop_trans_contribs dtc LEFT JOIN develop_trans_users dtu ON dtc.user_id=dtu.id WHERE dtu.credits=1 AND dtc.used=1 GROUP BY dtu.id, dtc.lang');
        print('<span class="important_notice">Current limit: '.$limit.' accepted translations</span>');
        $langs = Base_LangCommon::get_base_languages();
        $credits = array();
	    while ($row = $ret->FetchRow()) {
	        if ($row['count']<30) continue;
	        $lang = $row['lang'];
	        if (!isset($langs[$lang])) $langs[$lang] = $lang;
	        $key = isset($langs[$row['lang']])?$langs[$row['lang']]:$row['lang'];
            $credits[$langs[$row['lang']]][str_pad($row['count'], 16, '0', STR_PAD_LEFT).'_'.$row['user_id']] = $row;
	    }
	    ksort($credits);
	    $html = '<ul>'."\n";
	    foreach ($credits as $lang=>$trans) {
	        $html .= "\t".'<li>'.$lang.' - '."\n";
	        krsort($trans);
	        $cts = array();
	        foreach ($trans as $ts) {
	            $itm = "\t\t".'<b>'.ucfirst($ts['first_name']).' '.ucfirst($ts['last_name']).'</b>';
	            if ($site = $ts['credits_website']) {
                    if (strpos(strtolower($site), 'http://')===false && strpos(strtolower($site), 'https://')===false) $url = 'http://'.$site;
                    else $url = $site;
                    $site = strtolower(str_replace(array('http://', 'https://'),'',$site));
	                $itm .= ' (<a href="'.$url.'" target="_blank">'.$site.'</a>)';
	            }
	            $cts[] = $itm;
	        }
	        $html .= implode(', '."\n", $cts);
	        $html .= "\n\t".'</li>'."\n";
	    }
	    $html .= '</ul>';
	    print('<div style="text-align:left;width:700px;margin:auto;">');
	    print($html);
	    print('</div>');
	    print('<textarea style="width:700px;height:400px;">'.$html.'</textarea>');
	    Base_ActionBarCommon::add('save', 'Update credits', $this->create_callback_href(array($this, 'update_credits'), array($html)));
	}
	public function update_credits($html) {
	    file_put_contents('modules/Base/About/translations_credits.html', $html);
        print('<span class="important_notice">Credits were updated successfully</span>');
	}

	public function show_contributors() {
		$form_u = $this->init_module('Libs/QuickForm');
		$form_u->addElement('select','credits',__('Credits'), array(''=>'---', 0=>'No', 1=>'Yes'), array('onchange'=>$form_u->get_submit_form_js()));
		
		if ($form_u->validate()) {
		    $this->set_module_variable('credits', $form_u->exportValue('credits'));
		}
		$credits = $this->get_module_variable('credits','');
		$form_u->setDefaults(array('credits'=>$credits));
		$form_u->display_as_row();
		
		$gb_c = $this->init_module('Utils_GenericBrowser', null, 'contributors');
		$gb_c->set_table_columns(array(
			array('name'=>__('Last Name')), 
			array('name'=>__('First Name')), 
			array('name'=>__('IP')),
			array('name'=>__('Credits')),
			array('name'=>__('Credits website')),
			array('name'=>__('Contact e-mail')),
			array('name'=>__('Sent')),
			array('name'=>__('Accepted'))
		));
		switch ($credits) {
		    case '': $where = ''; break;
		    case 0: $where = 'WHERE credits=0 '; break;
		    case 1: $where = 'WHERE credits=1 '; break;
		}
        $ret = DB::Execute('SELECT * FROM develop_trans_users '.$where.'ORDER BY last_name ASC, first_name ASC');
        while ($row = $ret->FetchRow()) {
            switch ($row['credits']) {
                case '': $credits = '?'; break;
                case 1: $credits = 'Yes'; break;
                case 0: $credits = 'No'; break;
            }
            $sent_c = DB::GetAll('SELECT lang, used, COUNT(*) AS qty FROM develop_trans_contribs WHERE user_id=%d GROUP BY lang, used', array($row['id']));;
            
            if (empty($sent_c)) {
	            //DB::Execute('DELETE FROM develop_trans_users WHERE id=%d', array($row['id']));
	            continue;
            }

            $accepted = array();
            $sent = array();
            foreach ($sent_c as $v) {
                if ($v['used']) $accepted[] = $v['lang'].' (<b>'.$v['qty'].'</b>)';
                $sent[$v['lang']][$v['used']] = $v['qty'];
            }
            foreach ($sent as $k=>$v) {
                $qty = 0;
                if (isset($v[0])) $qty += $v[0];
                if (isset($v[1])) $qty += $v[1];
                $sent[$k] = $k.' (<b>'.$qty.'</b>)';
            }
            $accepted = implode(', ', $accepted);
            $sent = implode(', ', $sent);

            $gbr = $gb_c->get_new_row();
            
            $gbr->add_data($row['last_name'], $row['first_name'], $row['ip'], $credits, $row['credits_website'], $row['contact_email'], $sent, $accepted);
            
	        if (isset($_SESSION['client']['Develop_Translations']['merge_target'])) {
	                if ($_SESSION['client']['Develop_Translations']['merge_target'] == $row['id'])
                            $gbr->add_action($this->create_callback_href(array($this, 'mark_merge_target'), array(null)), 'delete', 'Stop merging');
                    else
                            $gbr->add_action($this->create_confirm_callback_href('Are you sure you want to merge these contributors?', array($this, 'merge'), array($row['id'])), 'restore', 'Merge');
	        } else {
                $gbr->add_action($this->create_callback_href(array($this, 'mark_merge_target'), array($row['id'])), 'edit', 'Mark merge target');
            }
        }
		
	    $this->display_module($gb_c);
	}

	public function merge($id) {
	    error_log(implode(', ',DB::GetRow('SELECT * FROM develop_trans_users WHERE id=%d', array($id)))."\n",3,'data/Develop_Translations/deleted_users');
	    DB::Execute('UPDATE develop_trans_contribs SET user_id=%d WHERE user_id=%d', array($_SESSION['client']['Develop_Translations']['merge_target'], $id));
	    DB::Execute('DELETE FROM develop_trans_users WHERE id=%d', array($id));
	    return false;
	}
	
	public function mark_merge_target($id) {
	    if ($id)
	        $_SESSION['client']['Develop_Translations']['merge_target'] = $id;
	    else
	        unset($_SESSION['client']['Develop_Translations']['merge_target']);
	    return false;
	}

	public function show_translations() {
	    load_js('modules/Develop/Translations/js/main.js');
		$form_u = $this->init_module('Libs/QuickForm');
		$all_langs = Base_LangCommon::get_all_languages();
        foreach ($all_langs as $code => $label) {
            $all_langs[$code] = $label . " ($code)";
        }
		$tmp = DB::GetAssoc('SELECT lang, COUNT(*) FROM develop_trans_contribs WHERE used=0 AND discarded=0 GROUP BY lang');
		$langs = array();
		foreach ($tmp as $k=>$v) {
            $langname = isset($all_langs[$k]) ? $all_langs[$k] : "INVALID ($k)";
		    $langs[$k] = $langname.' ('.$v.')';
        }
		$form_u->addElement('select','lang',__('Language'), $langs, array('onchange'=>$form_u->get_submit_form_js()));
		$form_u->addElement('select','switch_lang',__('Enable switching to language'), array(''=>'---')+$all_langs, array('onchange'=>$form_u->get_submit_form_js()));
		
		if ($form_u->validate()) {
		    $this->set_module_variable('lang', $form_u->exportValue('lang'));
		    $this->set_module_variable('switch_lang', $form_u->exportValue('switch_lang'));
		}
		$lang = $this->get_module_variable('lang',key($langs));
        if (!isset($langs[$lang])) {
            $lang = key($langs);
            $this->set_module_variable('lang', $lang);
        }
		$switch_lang = $this->get_module_variable('switch_lang','');
		$form_u->setDefaults(array('lang'=>$lang, 'switch_lang'=>$switch_lang));
		$form_u->display_as_row();

		$contribs = DB::GetCol('SELECT DISTINCT(user_id) FROM develop_trans_contribs WHERE used=0 AND discarded=0 AND lang=%s', array($lang));

		$gb = $this->init_module('Utils_GenericBrowser', null, 'trans');
		$header = array(
			array('name'=>__('Original'), 'width'=>'140px'), 
			array('name'=>__('Current'), 'width'=>'140px') 
		);
		$user_labels = array();
		$user_map = array();
		foreach ($contribs as $uid) {
		    $label = DB::GetOne('SELECT '.DB::Concat('last_name', DB::qstr(' '), 'first_name').' AS name FROM develop_trans_users WHERE id=%d', array($uid));
		    $user_map[$uid] = count($header);
		    $user_labels[$uid] = $label;
		    $header[] = array('name'=>$label);
		}
		$gb->set_table_columns($header);

		$dict_trans = array();
		$dict_dir = 'modules/Develop/Translations/dictionaries';
        global $translations;
		$translations = array();
		if (is_file($dict_dir.'/'.$lang.'.php'))
    		require($dict_dir.'/'.$lang.'.php');
		$dict_trans[$lang] = $translations;
		Base_LangCommon::load();

        $actions = array();
        $actions[] = array('value'=>'Actions', 'attrs'=>'colspan="2"');
        $actions[] = array('value'=>'Actions', 'dummy'=>true);
        foreach ($contribs as $uid) {
            $actions[] = array('value'=>
                '<a '.$this->create_confirm_callback_href('Are you sure you want to APPROVE all translations?', array($this, 'set_all_by_lang_user'), array(1, $lang, $uid)).'>'.'Accept all'.'</a><br>'.
                '<a '.$this->create_confirm_callback_href('Are you sure you want to APPROVE all new translations?', array($this, 'set_new_by_lang_user'), array(1, $lang, $uid)).'>'.'Accept only those where current is empty'.'</a><br>'.
                '<a '.$this->create_confirm_callback_href('Are you sure you want to DISCARD all translations?', array($this, 'set_all_by_lang_user'), array(0, $lang, $uid)).'>'.'Discard all'.'</a><br>'.
                '<a '.$this->create_confirm_callback_href('Are you sure you want to DISCARD all matching translations?', array($this, 'set_matching_by_lang_user'), array(0, $lang, $uid)).'>'.'Discard those, where current is the same'.'</a>'.
                ($switch_lang?'<br>'.'<a '.$this->create_confirm_callback_href('Are you sure you want to MOVE all translations to \''.$switch_lang.'\' language pack?', array($this, 'change_lang_by_lang_user'), array($switch_lang, $lang, $uid)).'>'.'Move to \''.$switch_lang.'\''.'</a>':'')
            );
        }
        $gbr = $gb->get_new_row();
		$gbr->add_data_array($actions);
		
		DB::StartTrans();
		$limit = DB::GetOne('SELECT COUNT(*) FROM develop_trans_contribs WHERE used=0 AND discarded=0 AND lang=%s', array($lang));
		$limit = $gb->get_limit($limit);
		$ret = DB::SelectLimit('SELECT * FROM develop_trans_contribs WHERE used=0 AND discarded=0 AND lang=%s ORDER BY BINARY(org), received_on ASC', $limit['numrows'], $limit['offset'], array($lang));
		$last_org = null;
		$next_row = null;
		$next_opts = array();
		$del = 0;
		while ($row = $ret->FetchRow()) {
		    if ($row['org']!=$last_org) {
		        if ($next_row) {
		            array_unshift($next_opts, urlencode($next_row[1]));
		            foreach ($next_row as $k=>$v)
		                if (is_array($v)) {
		                    $next_row[$k] = implode('<hr>', $v);
		                }
				    $link = '<a target="_blank" href="http://translate.google.com/#'.$lang.'/en/'.implode('%0A', $next_opts).'">'.'?'.'</a>';
		            $next_row[1] .= ' '.$link;
		            $next_opts = array();
	                $gbr = $gb->get_new_row();
	                $gbr->add_data_array($next_row);
		        }
		        $next_row = array_fill(0, count($header), array());
		        $last_org = $row['org'];
		        $next_row[0] = $last_org;
		        $next_row[1] = isset($dict_trans[$lang][$last_org])?$dict_trans[$lang][$last_org]:'---';
		    }
		    if (isset($next_row[$user_map[$row['user_id']]][$row['trans']]) || $row['trans']==='') {
		        DB::Execute('DELETE FROM develop_trans_contribs WHERE id=%d', array($row['id']));
		        $del++;
		    }
		    $trans2 = '<a href="javascript:void(0);" onclick="develop_use_trans('.$row['id'].', 1, this);">'.$row['trans'].'</a>';
		    $trans2 .= '<a href="javascript:void(0);" onclick="develop_use_trans('.$row['id'].', 0, this);">'.'<img class="action_button" src="'.Base_ThemeCommon::get_template_file('Utils/GenericBrowser','delete.png').'" border="0" />'.'</a>';
		    if ($switch_lang)
		        $trans2 .= '<a href="javascript:void(0);" onclick="develop_move_to_lang('.$row['id'].', \''.$switch_lang.'\', this);">'.'<img class="action_button" src="'.Base_ThemeCommon::get_template_file('Utils/GenericBrowser','restore.png').'" border="0" />'.'</a>';
		    if (isset($dict_trans[$lang][$last_org]) && $row['trans']==$dict_trans[$lang][$last_org])
		        $trans2 .= '&nbsp;<img class="action_button" src="data/Base_Theme/templates/default/images/checkbox_on.png" />';
		    $next_row[$user_map[$row['user_id']]][$row['trans']] = $trans2;
		    $next_opts[$row['trans']] = urlencode($row['trans']);
	        if ($ret->EOF) {
		            $gbr = $gb->get_new_row();
		            array_unshift($next_opts, urlencode($next_row[1]));
		            foreach ($next_row as $k=>$v)
		                if (is_array($v)) {
		                    $next_row[$k] = implode('<hr>', $v);
		                }
				    $link = '<a target="_blank" href="http://translate.google.com/#'.$lang.'/en/'.implode('%0A', $next_opts).'">'.'?'.'</a>';
		            $next_row[1] .= ' '.$link;
		            $next_opts = array();
		            $gbr = $gb->get_new_row();
		            $gbr->add_data_array($next_row);
	        }
		}
		DB::CompleteTrans();
		if ($del) print('Deleted '.$del.' duplicates');

	    $this->display_module($gb);
	}
	
	public function change_lang_by_lang_user($target, $from, $uid) {
	    set_time_limit(0);
	    DB::Execute('UPDATE develop_trans_contribs SET lang=%s WHERE used=0 AND discarded=0 AND lang=%s AND user_id=%d', array($target, $from, $uid));
	    return false;
	}
	
	public function set_all_by_lang_user($status, $lang, $uid) {
	    set_time_limit(0);
	    DB::StartTrans();
	    $ret = DB::Execute('SELECT id, org, trans FROM develop_trans_contribs WHERE used=0 AND discarded=0 AND lang=%s AND user_id=%d ORDER BY received_on ASC', array($lang, $uid));
	    while ($row = $ret->FetchRow()) {
	        $res = Develop_TranslationsCommon::set($row['id'], $status);
	    }
        DB::CompleteTrans();
	    return false;
	}

    private function get_translations($lang = null) {
        $dict_dir = 'modules/Develop/Translations/dictionaries/';
        if ($lang === null) {
            $files = scandir($dict_dir);
        } else {
            $files = array($lang . '.php');
        }
        $dict_trans = array();
        foreach ($files as $file) {
            if ($file == '.' || $file == '..') continue;

            global $translations;
            $translations = array();
            if (!is_file($dict_dir . $file)) continue;
            require($dict_dir . $file);
            $lang_code = basename($file, '.php');
            $dict_trans[$lang_code] = $translations;
        }
        Base_LangCommon::load();
        if ($lang === null)
            return $dict_trans;
        if (isset($dict_trans[$lang]))
            return $dict_trans[$lang];
        return array();
    }

    public function set_matching_by_lang_user($status, $lang, $uid) {
        // load specific lang
        $dict_trans = $this->get_translations($lang);

        DB::StartTrans();
        $ret = DB::Execute('SELECT id, org, trans FROM develop_trans_contribs WHERE used=0 AND discarded=0 AND lang=%s AND user_id=%d ORDER BY received_on ASC', array($lang, $uid));
        while ($row = $ret->FetchRow()) {
            if (isset($dict_trans[$row['org']]) && $row['trans'] == $dict_trans[$row['org']]) {
                Develop_TranslationsCommon::set($row['id'], $status);
            }
        }
        DB::CompleteTrans();
    }

    public function set_new_by_lang_user($status, $lang, $uid) {
        // load specific lang
        $dict_trans = $this->get_translations($lang);

        DB::StartTrans();
        $ret = DB::Execute('SELECT id, org, trans FROM develop_trans_contribs WHERE used=0 AND discarded=0 AND lang=%s AND user_id=%d ORDER BY received_on ASC', array($lang, $uid));
        while ($row = $ret->FetchRow()) {
            if (!isset($dict_trans[$row['org']]) || !$dict_trans[$row['org']]) {
                Develop_TranslationsCommon::set($row['id'], $status);
            }
        }
        DB::CompleteTrans();
    }

	public function show_general() {
		$gb_p = $this->init_module('Utils_GenericBrowser', null, 'problems');
		$gb_p->set_table_columns(array(
			array('name'=>__('String')), 
			array('name'=>__('Problem')), 
			array('name'=>__('Files'))
		));
		$strings = $this->get_pattern();
		$clean = array();
		foreach ($strings as $s=>$files) {
			$first = $s[0];
			$last = substr($s, -1, 1);
			if ($first!='"' && $first!="'") {
				$f = reset($files);
				if (count($files)==2 && $f=='modules/Base/Lang/LangCommon_0.php' && $s = '$string') // method definition
					continue;
				$gb_p->add_row($s, 'Not starting with a quote', implode('<br>',$files));
				continue;
			}
			if ($last!='"' && $last!="'") {
				$gb_p->add_row($s, 'Not ending with a quote', implode('<br>',$files));
				continue;
			}
			if ($last!=$first) {
				$gb_p->add_row($s, 'Starting and ending quotes don\'t match', implode('<br>',$files));
				continue;
			}
			if ($last===$first && preg_match('/[^\\\\]'.$first.'/i', substr($s, 1, -1))>=1) {
				$gb_p->add_row($s, 'Quote found in the middle of sentence', implode('<br>',$files));
				continue;
			}
			if (strip_tags($s)!=$s) {
				$gb_p->add_row(htmlspecialchars($s), 'HTML in the translation', implode('<br>',$files));
				continue;
			}
			$s = substr($s, 1, -1);
			if ($first=='"') $s = addcslashes($s,'\\\'');
			if ($first=="'") $s = str_replace('\\\'','\'',$s);
			if (isset($clean[$s])) {
				continue;
			}
			$clean[$s] = $files;
		}
        $exceptions = array('Assigned to','No.','Email','empty','Longterm','Projects_Report_%s','AMOUNT DUE','Earnings_Report_%s','Shipment - type','Timesheet_Report_%s','Shipment - ETA','Sales_Report_%s','Companies_Report_%s');

        $similar = array();
        foreach ($clean as $s=>$files) {
            if (in_array($s, $exceptions)) continue;
            $ss = strtolower(preg_replace('/[^a-zA-Z0-9%#]/', '', $s));
            $similar[$ss][$s] = (isset($similar[$ss][$s])?$similar[$ss][$s]:array()) + $files;
        }
        foreach ($similar as $sm=>$v) {
            if (count($v)>1) {
                $row = array(array(), 'Similar strings', array());
                foreach ($v as $s=>$files) {
                    $row[0][] = $s.' ('.count($files).')';
                    $files_h = array_merge(array("<strong>$s:</strong>"), $files);
                    $row[2] = array_merge($row[2],$files_h);
                }
                $row[0] = implode('<br>', $row[0]);
                $row[2] = implode('<br>', $row[2]);
                $gb_p->add_row_array($row);
            }
        }

		
		$gb = $this->init_module('Utils_GenericBrowser', null, 'summary');
		$gb->set_table_columns(array(
			array('name'=>__('Property')), 
			array('name'=>__('Last Action')), 
			array('name'=>__('Action'))
		));
        $gb->add_row(
           'Git Update',
           $this->display_date(Variable::get('translations_last_git_up', false)),
           '<a '.$this->create_callback_href(array($this, 'git_up')).'>'.__('Execute').'</a>'
        );
        $gb->add_row(
			'SVN Update',
			$this->display_date(Variable::get('translations_last_svn_up', false)),
			'<a '.$this->create_callback_href(array($this, 'svn_up')).'>'.__('Execute').'</a>'
		);
		$gb->add_row(
			'Translations Pattern',
			$this->display_date(Variable::get('translations_last_pattern', false)),
			'<a '.$this->create_callback_href(array($this, 'rebuild_pattern')).'>'.__('Rebuild pattern').'</a>'
		);
		$gb->add_row(
			'Translations',
			$this->display_date(Variable::get('translations_last_update', false)),
			'<a '.$this->create_callback_href(array($this, 'update_translations'), array($clean)).'>'.__('Update translations').'</a>'
		);
	    $gb->add_row(
			'Git Commit & Push',
			$this->display_date(Variable::get('translations_last_git_commit', false)),
			'<a '.$this->create_callback_href(array($this, 'git_commit')).'>'.__('Execute').'</a>'
	    );
		$gb->add_row(
			'SVN Commit',
			$this->display_date(Variable::get('translations_last_svn_commit', false)),
			'<a '.$this->create_callback_href(array($this, 'svn_commit')).'>'.__('Execute').'</a>'
		);
		$this->display_module($gb);



		$this->display_module($gb_p);
		
		$gb = $this->init_module('Utils_GenericBrowser', null, 'translations');
		$gb->set_table_columns(array(
			array('name'=>__('User'), 'width'=>'190px'), 
			array('name'=>__('IP'), 'width'=>'80px'), 
			array('name'=>__('Language'), 'width'=>'50px'), 
			array('name'=>__('Original')),
			array('name'=>__('Translated')),
			array('name'=>__('Last Translation')),
			array('name'=>__('Status'), 'width'=>'120px')
		));
		
		$form_u = $this->init_module('Libs/QuickForm');
		$form_u->addElement('checkbox','show_all',__('Show all'), null, array('onchange'=>$form_u->get_submit_form_js()));
		$authors = array(null=>'---')+DB::GetAssoc('SELECT id, '.DB::Concat('last_name', DB::qstr(' '), 'first_name').' AS name FROM develop_trans_users ORDER BY name ASC');
		$form_u->addElement('select','user',__('Author'), $authors, array('onchange'=>$form_u->get_submit_form_js()));
		
		if ($form_u->validate()) {
		    $this->set_module_variable('show_all', $form_u->exportValue('show_all'));
		    $this->set_module_variable('user', $form_u->exportValue('user'));
		}
		$show_all = $this->get_module_variable('show_all',0);
		$user = $this->get_module_variable('user',null);
		$form_u->setDefaults(array('show_all'=>$show_all, 'user'=>$user));
		$form_u->display_as_row();

        $where = array();
        if (!$show_all) $where[] = 'used=0 AND discarded=0';
        if ($user) $where[] = 'tu.id='.intVal($user);
        if (!empty($where)) $where = ' WHERE '.implode(' AND ',$where);
        else $where = '';
        $count = DB::GetOne('SELECT COUNT(tc.id) FROM develop_trans_contribs tc LEFT JOIN develop_trans_users tu ON tc.user_id=tu.id'.$where);
        $limit = $gb->get_limit($count);
		$ret = DB::SelectLimit('SELECT tc.user_id, tc.received_on, tc.id as c_id, lang, first_name, last_name, ip, org, trans, used, discarded FROM develop_trans_contribs tc LEFT JOIN develop_trans_users tu ON tc.user_id=tu.id'.$where.' ORDER BY tc.received_on DESC', $limit['numrows'], $limit['offset']);
		eval_js('approve_translation = function(id) {change_translation_status(id, 1);}');
		eval_js('discard_translation = function(id) {change_translation_status(id, 0);}');
		eval_js('change_translation_status = function(v_id,v_status) {
					$("translation_actions_"+v_id).innerHTML="...";
					new Ajax.Request("modules/Develop/Translations/use_translation.php",{
					method:"post",
					parameters:{
						id: v_id,
						status: v_status,
						cid: Epesi.client_id
					},
					onComplete:function(t) {
						eval(t.responseText);
					}});}');
		$dict_dir = 'modules/Develop/Translations/dictionaries';
		$dict = scandir($dict_dir);
		$dict_trans = array();
		foreach ($dict as $name) {
			if ($name == '.' || $name == '..') continue;
			global $translations;
			$translations = array();
			if (!is_file($dict_dir.'/'.$name)) continue;
			require($dict_dir.'/'.$name);
			$lang = basename($name, '.php');
			$dict_trans[$lang] = $translations;
		}
		Base_LangCommon::load();
		while ($row = $ret->FetchRow()) {
			$row['id'] = $row['c_id'];
			$button = '<div id="translation_actions_'.$row['id'].'">';
			$button .= '<a href="javascript:void(0);" onclick="approve_translation('.$row['id'].');">'.'[Approve]'.'</a>';
			$button .= '<a href="javascript:void(0);" onclick="discard_translation('.$row['id'].');">'.'[Discard]'.'</a>';
			$button .= '</div>';

			$button_all = '<a '.$this->create_callback_href(array($this, 'set_all'), array($row['user_id'], $row['lang'], 1)).'>'.'[Approve]'.'</a>';
			$button_all .= '<a '.$this->create_callback_href(array($this, 'set_all'), array($row['user_id'], $row['lang'], 0)).'>'.'[Discard]'.'</a>';
			$last = (isset($dict_trans[$row['lang']]) && isset($dict_trans[$row['lang']][$row['org']]))?$dict_trans[$row['lang']][$row['org']]:'---';
			$gb->add_row(
				$row['first_name'].' '.$row['last_name'].' '.$button_all,
				$row['ip'],
				$row['lang'],
				$row['org'],
				'<a target="_blank" href="http://translate.google.com/#'.$row['lang'].'/en/'.$row['trans'].'%0A'.$last.'">'.$row['trans'].'</a>',
				'<a target="_blank" href="http://translate.google.com/#'.$row['lang'].'/en/'.$row['trans'].'%0A'.$last.'">'.$last.'</a>',
				($row['used']?'Approved':($row['discarded']?'Discarded':$button))
			);
		}
		$this->display_module($gb);
	}
	public function set_all($user_id, $lang, $status) {
	    $ret = DB::Execute('SELECT * FROM develop_trans_contribs WHERE user_id=%d AND lang=%s AND used=0 AND discarded=0 ORDER BY received_on ASC', array($user_id, $lang));
	    while ($row = $ret->FetchRow()) {
            Develop_TranslationsCommon::set($row['id'], $status);
	    }
	}

	function display_date($d=null) {
		if (!$d) return '---';
		$ret = Base_RegionalSettingsCommon::time2reg($d);
		if ($d<date('Y-m-d H:i:s', strtotime('-2 weeks'))) $ret = '<span style="color: red;font-weight:bold;">'.$ret.'</span>';
		return $ret;
	}
	function get_pattern() {
		$ret = unserialize(@file_get_contents('modules/Develop/Translations/pattern'));
		if (!$ret) $ret = array();
		return $ret;
	}
	function svn_up() {
		set_time_limit(0);
		require_once('modules/Develop/Translations/svn_config.php');
        $sout = array();
        foreach (array('modules/Premium', 'modules/Custom', 'modules/Develop') as $f) {
            $command = 'svn --no-auth-cache --non-interactive --username ' . escapeshellarg(SVN_USER) . ' --password ' . escapeshellarg(SVN_PASSWORD) . ' up '.$f.' 2>&1';
            $ret = 1;
            $out = array();
            exec($command, $out, $ret);
            $sout[]= implode("<br>", $out);
        }
        $sout = implode("<br><br>", $sout);
        print('<span class="important_notice" style="height:200px;overflow:scroll;">'.$sout.'</span>');
		Variable::set('translations_last_svn_up', date('Y-m-d H:i:s'));
		return false;
	}
	function git_up() {
		set_time_limit(0);
        $command = '(git stash && git pull --rebase && git stash pop) 2>&1';
        $ret = 1;
        exec($command, $out, $ret);
        $out = implode("<br>", $out);
        print('<span class="important_notice" style="height:200px;overflow:scroll;">'.$out.'</span>');
		Variable::set('translations_last_git_up', date('Y-m-d H:i:s'));
		return false;
	}
	function rebuild_pattern() {
		set_time_limit(0);
		$this->recursive_scan('modules');
		file_put_contents('modules/Develop/Translations/pattern', serialize($this->strings));
		Variable::set('translations_last_pattern', date('Y-m-d H:i:s'));
		location(array());
		return false;
	}
	function is_premium($file) {
		$file = preg_replace('#^modules/#', '', $file);
		if (substr($file, 0, 8) == 'Premium/') return true;
		if (substr($file, 0, 7) == 'Custom/') return true;
		if (substr($file, 0, 8) == 'Develop/') return true;
		if (substr($file, 0, 6) == 'Tests/') return true;
		return false;
	}

    /**
     * @param $strings_with_files array where key is string to translate and value is array of files where this string was found
     * @return bool
     */
    function update_translations($strings_with_files) {
		if ($this->is_back())
			return false;
		set_time_limit(0);

		$modules = array();
		foreach ($strings_with_files as $s=>$files) {
			$is_premium = true;
			foreach ($files as $f) {
				$is_premium &= $this->is_premium($f);
			}
			if(!$is_premium) {
				$modules['Base/Lang'][$s] = true;
				continue;
			}
			foreach ($files as $f) {
				$org = $f;
				$f = preg_replace('#^modules/#', '', $f);
				do {
					$old = $f;
					$f = preg_replace('/\/[^\/]*$/', '', $f);  // is this just dirname of file? including preg_replace above we should get module name
				} while($old!=$f && $f && !DB::GetOne('SELECT name FROM available_modules WHERE name=%s', array(str_replace('/','_',$f))));
				if (!$f) print('No module found for: '.$org.'<br>');
				else $modules[$f][$s] = true;
			}
		}
		$dict_dir = 'modules/Develop/Translations/dictionaries';
		$dict = scandir($dict_dir);
		$trans = array();
		$sums = array();
		$sections = array();
		$skipped = array();
		foreach ($dict as $name) {
			if ($name == '.' || $name == '..') continue;
			global $translations;
			$translations = array();
			if (!is_file($dict_dir.'/'.$name)) continue;
			require($dict_dir.'/'.$name);
			$lang = basename($name, '.php');
			$trans[$lang] = array();
			$sums[$lang] = array();
			foreach ($modules as $m=>$ts) {
				$section = substr($m, 0, strpos($m, '/'));
				$sections[$section] = $section;
				if (!isset($trans[$lang][$section])) $trans[$lang][$section] = 0;
				if (!isset($sums[$lang][$section])) $sums[$lang][$section] = 0;
			
				$output = array();
				foreach ($ts as $s=>$v) {
					if (isset($translations[$s])) $trans[$lang][$section]++;
					$sums[$lang][$section]++;
					$output[$s] = isset($translations[$s])?$translations[$s]:'';
				}
				@mkdir('modules/'.$m.'/lang/');
			    if ($lang!='en' && count($translations)<100) {
			        $skipped[$lang] = true;
			        continue;
			    }
				$f = fopen('modules/'.$m.'/lang/'.$lang.'.php', 'w');
				if(!$f)	return false;

				fwrite($f, "<?php\n");
				fwrite($f, "/**\n * Translation file.\n * @package epesi-translations\n * @subpackage $lang\n */\n");
				fwrite($f, 'global $translations;'."\n");
				foreach($output as $k=>$t)
						fwrite($f, '$translations[\''.addcslashes($k,'\\\'').'\']=\''.addcslashes($t,'\\\'')."';\n");

				fclose($f);
			}
		}
		Base_LangCommon::load();

		$gb = $this->init_module('Utils_GenericBrowser', null, 'summary');
		$header = array(
			array('name'=>__('Language'))
		);
		if (in_array('', $sections)) print('It seems module database was not refreshed');
		foreach ($sections as $k=>$s)
			if ($s!='Tests' && $s!='Develop') $header[] = array('name'=>$s);
			else unset($sections[$k]);
		$gb->set_table_columns($header);
		$complete = array();
        $all_langs = Base_LangCommon::get_all_languages();
		foreach ($sums as $lang=>$val) {
			if ($lang=='en') continue;
            $lang_name = $lang;
            $lang_name .= (isset($all_langs[$lang]) ? " - " . $all_langs[$lang] : '');
            $lang_name .= (isset($skipped[$lang]) ? ' (skipped)' : '');
            $arr = array($lang_name);
			foreach ($sections as $section) {
				$percent = number_format(100*$trans[$lang][$section]/$sums[$lang][$section],1);
				if ($section=='Base' && $percent>70) $complete[] = $lang;
				$arr[] = array('value'=>'<div style="position:relative;"><div style="position:absolute;left:0px;top:0px;height:20px;width:'.$percent.'%;background-color:lightgreen;float:left;"></div><div style="position:absolute;right:5px;">'.$percent.' %</div></div>', 'style'=>'text-align:right;padding:0px;background-color:tomato;');
			}
			$gb->add_row_array($arr);
		}
		$this->display_module($gb);
		file_put_contents('modules/Base/Lang/complete', implode(',', $complete));

		Base_ActionBarCommon::add('back', __('Back'), $this->create_back_href());
		Variable::set('translations_last_update', date('Y-m-d H:i:s'));
		return true;
	}
	function svn_commit() {
		set_time_limit(0);
		require_once('modules/Develop/Translations/svn_config.php');
		$sout = array();
		foreach (array('modules/Premium', 'modules/Custom', 'modules/Develop') as $f) {
            $command = 'cd '.$f.' && svn --no-auth-cache --non-interactive --username ' . escapeshellarg(SVN_USER) . ' --password ' . escapeshellarg(SVN_PASSWORD) . ' add --force --auto-props --depth infinity -q . 2>&1';
//            print($command.'<br>');
            $ret = 1;
            exec($command, $out, $ret);
            $out = '';
            $command = 'svn --no-auth-cache --non-interactive --username ' . escapeshellarg(SVN_USER) . ' --password ' . escapeshellarg(SVN_PASSWORD) . ' -m "Translations updated with automated tool" ci '.$f.' 2>&1';
            $ret = 1;
            exec($command, $out, $ret);
            $sout[]= implode("<br>", $out);
        }
        $sout = implode('<br><br>', $sout);
        print('<span class="important_notice" style="height:200px;overflow:scroll;">'.$sout.'</span>');
		Variable::set('translations_last_svn_commit', date('Y-m-d H:i:s'));
		return false;
	}
	function git_commit() {
		set_time_limit(0);
        // git is using ssh key of http user, that is added on github/deploy keys
        $branch = 'git checkout dev';
        $add = 'git add .';
        $commit = 'git commit -a -m "Translations updated with automated tool"';
        $push = 'git push origin dev';
        $command = "($branch && $add && $commit && $push) 2>&1";
        $ret = 1;
        exec($command, $out, $ret);
        $out = implode("<br>", $out);
        print('<span class="important_notice" style="height:200px;overflow:scroll;">'.$out.'</span>');
		Variable::set('translations_last_git_commit', date('Y-m-d H:i:s'));
		return false;
	}

	private $strings = array();
	function scan_file($file) {
	    $ext = substr($file, -4, 4);
		if ($ext==='.php') {
			$c = file_get_contents($file);
			$i = 0;
			$sc = strlen($c);
			$string = '';
			$parenthesis = 0;
			$inquote = false;
			$inquote_d = false;
			while ($i<$sc) {
				$fnc = substr($c, $i, 3);
				if ($fnc=='_'.'_(' || $fnc=='_'.'M(') {
					$i += 3;
					if ($c[$i]==')') continue;
					do {
						if ($c[$i]=="'" && $c[$i-1]!='\\' && !$inquote_d) $inquote = !$inquote;
						if ($c[$i]=='"' && $c[$i-1]!='\\' && !$inquote) $inquote_d = !$inquote_d;
						if ($c[$i]=="(" && !$inquote && !$inquote_d) $parenthesis++;
						if ($c[$i]==")" && !$inquote && !$inquote_d) $parenthesis--;
						$string .= $c[$i];
						$i++;
						if (!isset($c[$i])) die($file.'<hr>Fatal: '.$string);
					} while(($c[$i]!=')' && $c[$i]!=',') || $inquote || $inquote_d || $parenthesis != 0);
					$string = trim($string);
					$this->strings[$string][] = $file;
					$string = '';
				}
				$i++;
			}
		} elseif($ext==='.tpl') {
			$c = file_get_contents($file);
			$i = 0;
			$sc = strlen($c);
			$string = '';
			$parenthesis = 0;
			$inquote = false;
			$inquote_d = false;
			while ($i<$sc) {
				$fnc = substr($c, $i, 3);
				if ($fnc=='|t}') {
				    $i -= 1;
					do {
						if ($c[$i]=="'" && $c[$i-1]!='\\' && !$inquote_d) $inquote = !$inquote;
						if ($c[$i]=='"' && $c[$i-1]!='\\' && !$inquote) $inquote_d = !$inquote_d;
//						if ($c[$i]=="(" && !$inquote && !$inquote_d) $parenthesis++;
//						if ($c[$i]==")" && !$inquote && !$inquote_d) $parenthesis--;
						$string = $c[$i].$string;
						$i--;
						if (!isset($c[$i])) die($file.'<hr>Fatal: '.$string);
					} while(($c[$i]!='{') || $inquote || $inquote_d || $parenthesis != 0);
					$string = trim($string);
					$i += strlen($string)+2;
					$this->strings[$string][] = $file;
					$string = '';
				}
				$i++;
			}
		}
	}

	function recursive_scan($path) {
		if (!is_dir($path)) {
			$this->scan_file($path);
			return;
		}
		$path = rtrim($path, '/');
		$content = scandir($path);
		foreach ($content as $name) {
			if ($name == '.' || $name == '..')
				continue;
			$name = $path . '/' . $name;
			if (is_dir($name) && is_link($name)==false) {
				$this->recursive_scan($name);
			} else
				$this->scan_file($name);
		}
	}
}
?>