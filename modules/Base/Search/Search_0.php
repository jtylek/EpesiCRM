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

		$form = $this->init_module('Libs/QuickForm',__('Searching'));
		$theme = $this->pack_module('Base/Theme');

		$form->addElement('header', 'quick_search_header', __('Quick Search'));
		$form->addElement('text', 'quick_search',  __('Keyword'), array('id'=>'quick_search_text'));
		$form->addRule('quick_search', __('Field required'), 'required');
		
		$form->addElement('header','categories_header',__('Categories'));
		$modules_with_search = ModuleManager::check_common_methods('search');
		$search_categories = ModuleManager::call_common_methods('search_categories');
		$search_categories_checkboxes = array();
		$defaults = array();
		$defaults['search_categories'] = array();
		$categories_tmp = & $this->get_module_variable('categories',array());
		foreach($search_categories as $mod=>$cats) {
		    foreach($cats as $cat_id=>$cat_name) {
		        $search_categories_checkboxes[] = $form->createElement('checkbox', $mod.'#'.$cat_id,  '', $cat_name);
		        if(!$categories_tmp || isset($categories_tmp[$mod.'#'.$cat_id]))
		            $defaults['search_categories'][$mod.'#'.$cat_id]=1;
		    }
		}
		$form->addGroup($search_categories_checkboxes,'search_categories','','</li><li>');
		
		$form->addElement('submit', 'quick_search_submit',  __('Search'), array('class'=>'submit'));
		$form->addElement('button', 'quick_search_select_none',  __('Deselect all'), array('onClick'=>'jq("#'.$form->getAttribute('name').' input[type=checkbox]").removeAttr("checked")','class'=>'submit'));
		$form->addElement('button', 'quick_search_select_all',  __('Select all'), array('onClick'=>'jq("#'.$form->getAttribute('name').' input[type=checkbox]").attr("checked","checked")','class'=>'submit'));

		$defaults['quick_search']=$qs_keyword;

		$form->setDefaults($defaults);

		$form->assign_theme('form', $theme);
		$theme->assign('form_mini', 'no');
		$theme->display('Search');

		if ($form->validate() || $qs_keyword) {
			if ($form->exportValue('submited')==1)
				$keyword = $form->exportValue('quick_search');
			elseif(isset($_POST['qs_keyword']))
				$keyword = $_POST['qs_keyword'];
			elseif(isset($qs_keyword))
				$keyword = $qs_keyword;
			if($keyword) {
				$categories_tmp = $form->exportValue('search_categories');
				$categories = array();
				if($categories_tmp) foreach($categories_tmp as $cat=>$val) {
					list($mod,$cat_id) = explode('#',$cat,2);
					if(!isset($categories[$mod])) $categories[$mod] = array();
					$categories[$mod][]=$cat_id;
				}
				unset($categories_tmp);
				$links = array();
				$this->set_module_variable('quick_search',$keyword);
				$count = 0;
				foreach($modules_with_search as $k) {
					$results = call_user_func(array($k.'Common','search'),$keyword,isset($categories[$k])?$categories[$k]:array());
					if (!empty($results))
						foreach ($results as $rk => $rv) {
							$count++;
							if ($count == 101) {
								$warning = __('Only 100 results are displayed.');
								break;
							}
							$links[] = $rv;
						}
				}
				$qs_theme = $this->pack_module('Base/Theme');
				$qs_theme->assign('header', __('Search results'));
				$qs_theme->assign('links', $links);
				$qs_theme->assign('warning', isset($warning)?$warning:null);
				$qs_theme->display('Results');
				return;
			}
		}
	}

/*
	public static function search_menu(){
		return '<form action="javascript:load_page(\'href=Base_Search&qs_keyword=\'+$(\'qs_keyword\').value);" method=POST><input type=text name=qs_keyword /><input type=submit value=Search /></form>';
	}
	*/
	public function mini() {
		if (!Base_AclCommon::check_permission('Search')) return '';
		$form = $this->init_module('Libs/QuickForm',__('Searching'));

		$form->addElement('text', 'quick_search', __('Quick Search'), array('x-webkit-speech'=>'x-webkit-speech', 'lang'=>Base_LangCommon::get_lang_code(), 'onwebkitspeechchange'=>$form->get_submit_form_js()));
		$form->addElement('submit', 'quick_search_submit', __('Search'), array('class'=>'mini_submit'));

		$theme = $this->pack_module('Base/Theme');
		$theme->assign('submit_href', $form->get_submit_form_href());
		$theme->assign('submit_label', __('Search'));
		$form->assign_theme('form', $theme);
		$theme->assign('form_mini', 'yes');
		$theme->display('Search');

		if($form->validate()) {
			$search = $form->exportValues();
			Base_BoxCommon::location('Base_Search',null,null,null,array('quick_search'=>$search['quick_search']));
		}
	}

	public function caption() {
		return __('Search');
	}
}

?>
