<?php
/**
 * Lang class.
 * 
 * This class provides translations manipulation.
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com> and Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 1.0
 * @licence SPL
 * @package epesi-base-extra
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

/**
 * This class provides translations manipulation.
 * Translation files are kept in 'modules/Lang/translations'. 
 * Http server user should have write access to those files.
 * 
 * @package epesi-base-extra
 * @subpackage lang
 */
class Base_Lang extends Module {
	private $parent_module;
	private $lang_code;
	
	public function construct() {
		$this->set_fast_process();
		$this->parent_module = $this->get_parent_type();
	}
	
	public function body($arg) {
/*		global $translations;
		if(!Acl::check('Administration','Modules') || !Base_MaintenanceModeCommon::get_mode()) return;
	
		$original = $this->get_module_variable_or_unique_href_variable('original');
		
		if(!isset($original)) return;
		if($this->is_back()) {
			$this->unset_module_variable('original');
			return;
		}
			
		$trans = $translations[$this->parent_module][$original];
		
		$form = & $this->init_module('Libs/QuickForm');
		$form->addElement('header', null, $original);
		$form->addElement('hidden', 'trans_original', $original);
		$form->addElement('text','trans_text','Translation');
		$form->setDefaults(array('trans_text'=>$trans));
		
		$ok_b = HTML_QuickForm::createElement('submit', 'submit_button', 'OK');
		$cancel_b = HTML_QuickForm::createElement('button', 'cancel_button', 'Cancel', $this->create_back_href());
		$form->addGroup(array($ok_b, $cancel_b));
		
		if($form->validate()) {
			$form->process(array(&$this, 'translate'));
		} else
			$form->display();*/
	}
	
	/**
	 * For internal use only.
	 */
	public function translate($data) {
		global $translations;
		$trans = $data['trans_text'];
		$orig = $data['trans_original'];
		$prev = $translations[$this->parent_module][$orig];
		
		$translations[$this->parent_module][$orig] = $trans;
		
		$this->unset_module_variable('original');
		
		if(!Base_LangCommon::save()) {
			print('Unable to save translation file. Check http server user privileges for directory "data/translations" and files inside.');
			$translations[$this->parent_module][$orig] = $prev;
			return false;
		}
		return true;
	}
	
	
	/**
	 * Use this function to translate desired string.
	 * If you want to hide translation link '[*]', use ht() instead. 
	 * This function can be used only when you pack 'Lang' module inside your module previously.
	 * This function supports printf-like arguments.
	 * 
	 * Example
	 * <pre>
	 * $lang = & $this->pack_module('Lang');
	 * ...
	 * print($lang->t('some text and %s',array($some_string)));
	 * </pre>
	 * 
	 * @param string text that will be translated
	 * @param array array of arguments to put in the text
	 * @return string translated version of given text
	 */
	public function t($original, array $arg=array()) {
		return $this->trans($original,$arg,false);
	}
	
	/**
	 * Use this function to translate desired string, 
	 * but in opposition to t() it will hide translation link '[*]'. 
	 * It's useful inside buttons.
	 * This function can be used only when you pack 'Lang' module inside your module previously.
	 * This function supports printf-like arguments.
	 * 
	 * Example
	 * <pre>
	 * $lang = & $this->pack_module('Lang');
	 * ...
	 * print($lang->ht('some text and %s',array($some_string)));
	 * </pre>
	 * 
	 * @param string text that will be translated
	 * @param array array of arguments to put in the text
	 * @return string translated version of given text
	 */
	public function ht($original, array $arg=array()) {
		return $this->trans($original,$arg,true);
	}

	/**
	 * For internal use only.
	 */
	public function trans($original, array $arg=array(), $hidden=false) {
		global $translations, $base;

		if(!array_key_exists($this->parent_module, $translations) || 
			!array_key_exists($original, $translations[$this->parent_module])) {
			$translations[$this->parent_module][$original] = '';
			//only first display of the string is not in translations database... slows down loading of the page only once...
			Base_LangCommon::save();
		}
		$trans_oryg = $translations[$this->parent_module][$original];
		if(!isset($trans_oryg) || $trans_oryg=='') $trans = $original;
			else $trans=$trans_oryg;
		
		if(Acl::check('Administration','Modules') && !$hidden && Base_MaintenanceModeCommon::get_mode()) {
			$id = 'trans_'.md5($this->parent_module.$original);
			$trans = '<span id="'.$id.'">'.$trans.'</span><a href="javascript:void(0)"  onClick="var oryg=\''.escapeJS($original).'\';var oryg_trans=this.getAttribute(\'oryginal_trans\');if(oryg_trans==null)oryg_trans=\''.escapeJS($trans_oryg).'\';var x=prompt(oryg,oryg_trans);if(x!=null){var sp=document.getElementById(\''.$id.'\');if(x==\'\')sp.innerHTML=oryg;else sp.innerHTML=x;this.setAttribute(\'oryginal_trans\',x);'.$base->run('translate(client_id,\''.escapeJS($this->parent_module).'\',oryg,x)','modules/Base/Lang/submit_trans.php').'}">[*]</a>';
		} else
			$trans = vsprintf($trans,$arg);
		return $trans;
	}

}
?>