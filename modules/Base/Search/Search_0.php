<?php
/**
 * Search class.
 *
 * Provides for search functionality in a module.
 *
 * @author Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-base
 * @subpackage search
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_Search extends Module {

	public function body() {
		$qs_keyword = $this->get_module_variable('qs_keyword', '');
		if (isset($_REQUEST['quick_search'])) $qs_keyword=$_REQUEST['quick_search'];
		$this->set_module_variable('qs_keyword', $qs_keyword);

		$form = & $this->init_module('Libs/QuickForm',$this->ht('Searching'));
		$theme =  & $this->pack_module('Base/Theme');

		$modules_with_search = ModuleManager::check_common_methods('search');
		$modules_with_adv_search = array();
		$cmr = ModuleManager::check_common_methods('advanced_search');
		foreach($cmr as $name) {
			if(ModuleManager::check_access($name,'advanced_search'))
				$modules_with_adv_search[$name] = $this->ht(str_replace('_',': ',$name));
		}

		$form->addElement('header', 'quick_search_header', $this->t('Quick search'));
		$form->addElement('text', 'quick_search',  $this->ht('Keyword'), array('id'=>'quick_search_text'));
		$form->addRule('quick_search', $this->t('Field required'), 'required');
		$form->addElement('submit', 'quick_search_submit',  $this->ht('Search'), array('class'=>'submit','onclick'=>'var elem=getElementById(\''.$form->getAttribute('name').'\').elements[\'advanced_search\'];if(elem)elem.value=0;'));

		if (!empty($modules_with_adv_search)) {
			$modules_with_adv_search_['__null__'] = '('.$this->ht('Select module').')';
			ksort($modules_with_adv_search);
			foreach($modules_with_adv_search as $k=>$v) $modules_with_adv_search_[$k] = $v;
			$form->addElement('static', 'advanced_search_header', $this->t('Advanced search'));
			$form->addElement('select', 'advanced_search', 'Module:', $modules_with_adv_search_, array('onChange'=>$form->get_submit_form_js(false),'id'=>'advanced_search_select'));
			$advanced_search = $form->exportValue('advanced_search');
			if ($advanced_search != $this->get_module_variable('advanced_search')) $qs_keyword = null;
			if ($advanced_search === '__null__') $advanced_search = false;
		} else $advanced_search = false;

		$defaults['quick_search']=$qs_keyword;
		if (!$qs_keyword) {
			if (!isset($advanced_search)) $advanced_search = $this->get_module_variable('advanced_search');
			$defaults = array('advanced_search'=>$advanced_search?$advanced_search:'__null__');
		} else {
			$this->unset_module_variable('advanced_search');
			$advanced_search = null;
		}

		$form->setDefaults($defaults);

		$form->assign_theme('form', $theme);
		$theme->assign('form_mini', 'no');
		$theme->display('Search');

		if (($form->validate() || $qs_keyword) && !$advanced_search) {
			if ($form->exportValue('submited')==1)
				$keyword = $form->exportValue('quick_search');
			elseif(isset($_POST['qs_keyword']))
				$keyword = $_POST['qs_keyword'];
			elseif(isset($qs_keyword))
				$keyword = $qs_keyword;
			if($keyword) {
				$links = array();
				$this->set_module_variable('quick_search',$keyword);
				$count = 0;
				foreach($modules_with_search as $k) {
					$results = call_user_func(array($k.'Common','search'),$keyword);
					if (!empty($results))
						foreach ($results as $rk => $rv) {
							$count++;
							if ($count == 101) {
								$warning = $this->t('Only 100 results are displayed.');
								break;
							}
							$links[] = $rv;
						}
				}
				$qs_theme =  & $this->pack_module('Base/Theme');
				$qs_theme->assign('header', $this->t('Search results'));
				$qs_theme->assign('links', $links);
				$qs_theme->assign('warning', isset($warning)?$warning:null);
				$qs_theme->display('Results');
					eval_js('var elem=$(\'advanced_search_select\');if(elem){for(i=0; i<elem.length; i++) if(elem.options[i].value==\'__null__\') {elem.options[i].selected=true;break;};};');
				return;
			}
		}
		if ($advanced_search) {
			$qs_theme =  & $this->pack_module('Base/Theme');
			$qs_theme->assign('header', $this->t('Advanced search'));
			$qs_theme->display('Results');
			$this->pack_module($advanced_search,null,'advanced_search');
			$this->set_module_variable('advanced_search',$advanced_search);
		}

	}

/*
	public static function search_menu(){
		return '<form action="javascript:load_page(\'href=Base_Search&qs_keyword=\'+$(\'qs_keyword\').value);" method=POST><input type=text name=qs_keyword /><input type=submit value=Search /></form>';
	}
	*/
	public function mini() {
		$form = & $this->init_module('Libs/QuickForm',$this->ht('Searching'));

		$form->addElement('text', 'quick_search', $this->t('Quick search'));
		$form->addElement('submit', 'quick_search_submit', $this->ht('Search'), array('class'=>'mini_submit'));

		$theme =  & $this->pack_module('Base/Theme');
		$form->assign_theme('form', $theme);
		$theme->assign('form_mini', 'yes');
		$theme->display('Search');

		if($form->validate()) {
			$search = $form->exportValues();
			Base_BoxCommon::location('Base_Search',null,null,null,array('quick_search'=>$search['quick_search'],'advanced_search'=>0));
		}
	}

	public function caption() {
		return "Search";
	}
}

?>
