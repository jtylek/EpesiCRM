<?php
/**
 * Wizard class.
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com> and Kuba Slawinski <kslawinski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 1.0
 * @license MIT
 * @package epesi-utils
 * @subpackage wizard
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_Wizard extends Module {
	private $counter;
	private $curr_page;
	private $form = array();
	private $renderers = array();
	private $data;
	private $history;
	private $aliases = array();
	private $r_aliases = array();
	private $displayed;
	private $captions = array();
	private $to_del = array();
	
	/**
	 * Module constructor.
	 * You can choose starting page while creating new instance of this module.
	 * 
	 * @param integer starting page number 
	 */
	public function construct($start_page=0) {
		$this->counter = 0;
		$this->curr_page = $this->get_module_variable('curr_page',$start_page);
		$this->data = & $this->get_module_variable('data',array());
		$this->history = $this->get_module_variable('history',array());
		if($this->is_back()) {
    		        $this->curr_page = array_pop($this->history);
			$this->set_module_variable('curr_page',$this->curr_page);
			$this->set_module_variable('history',$this->history);
		}
	}
	
	public function set_caption($caption, $level=0) {
		$this->captions[$this->counter]['caption'] = $caption;
		$this->captions[$this->counter]['level'] = $level;
	}
	
	/**
	 * Starts new wizard step.
	 * This method returns QuickForm object which you should use 
	 * to create wizard step.
	 * 
	 * @param string alias for the page
	 * @return object QuickForm object
	 */
	public function begin_page($name=null, $always_return_valid_form=true) {
		if(isset($this->form[$this->counter])) $this->next_page();
		
		if(isset($name)) {
			$this->r_aliases[$name] = $this->counter;
			$this->aliases[$this->counter] = $name;
			if(is_string($this->curr_page) && $this->curr_page==$name) $this->curr_page = $this->counter;
		}
		
		if($always_return_valid_form || $this->curr_page===$this->counter) {
			$args = func_get_args();
			array_shift($args);
			array_shift($args);
			$this->form[$this->counter] =  $this->init_module('Libs/QuickForm',$args,isset($name)?$name:$this->counter);
			if(isset($this->data[$this->counter]) && is_array($this->data[$this->counter]))
				$this->form[$this->counter]->setDefaults($this->data[$this->counter]);
		} else $this->form[$this->counter] = false;
		ob_start();
		return $this->form[$this->counter];
	}
	
	public function callback_page($func,$name=null,array $begin_page_args=null,array $func_args=null) {
		if(!isset($begin_page_args)) $begin_page_args=array();
		if(!isset($func_args)) $func_args=array();
		call_user_func_array(array($this,'begin_page'),array_merge(array($name,false),$begin_page_args));
		
		if($this->curr_page===$this->counter) 
			call_user_func_array($func,array_merge(array($this->form[$this->counter],$this->get_data()),$func_args));
	}
	
	/**
	 * Sets renderer that will be used to display current step.
	 * 
	 * @param object HTML QuickForm renderer object
	 */
	public function set_alternative_renderer(& $rend) {
		$this->renderers[$this->counter] = & $rend;
	}
	
	/**
	 * Finishes current page.
	 * You can also choose specific page (by number or alias).
	 * 
	 * @param mixed next page
	 */
	public function next_page($func=null,array $func_args=null) {
		if(!isset($this->form[$this->counter])) return;
		if(!isset($func_args)) $func_args=array();
		$cont = ob_get_contents();
		ob_end_clean();
		if($this->curr_page===$this->counter) {
			//trigger_error($this->counter,E_USER_ERROR);
			if($this->form[$this->curr_page]->getSubmitValue('submited') && $this->form[$this->curr_page]->validate()) {
				$this->form[$this->curr_page]->process(array($this,'submit')); 
	
				$this->history[] = $this->curr_page;
				if(is_int($func)) {
					$this->curr_page = $func;
				} elseif(is_string($func)) {
					if(isset($this->r_aliases[$func]))
						$this->curr_page = $this->r_aliases[$func];
					else
						$this->curr_page = $func;
				} elseif(is_callable($func)) {
					$args = array();
					$args[0] = & $this->data[$this->curr_page];
					$ret = call_user_func_array($func, array_merge($args,$func_args));
					if(isset($ret)) {
						if(is_int($ret))
							$this->curr_page = $ret;
						elseif(is_string($ret)) {
							if(!isset($this->r_aliases[$ret]))
								$this->curr_page = $ret;
							else
								$this->curr_page = $this->r_aliases[$ret]; 
						} else $this->curr_page++;
					} else $this->curr_page++;
				} else $this->curr_page++;
					
				$this->set_module_variable('curr_page',$this->curr_page);
				$this->set_module_variable('history',$this->history);
				if(!is_string($this->curr_page) && $this->curr_page<=$this->counter) location(array());
			} else {
				if(empty($this->history)) {
					$this->form[$this->curr_page]->addElement('submit', 'button_next', Base_LangCommon::ts('Utils/Wizard','Next'));
				} else {
					$button_prev = HTML_QuickForm::createElement('button', 'button_prev', Base_LangCommon::ts('Utils/Wizard','Prev'), $this->create_back_href());
					$button_next = HTML_QuickForm::createElement('submit', 'button_next', Base_LangCommon::ts('Utils/Wizard','Next'));
					$this->form[$this->curr_page]->addGroup(array($button_prev, $button_next));
				}
				
				if($cont)
					$this->displayed = $cont;
				ob_start();
				if(isset($this->renderers[$this->curr_page]))
					$this->renderers[$this->curr_page]->display();
				else	
					$this->form[$this->curr_page]->display();
				$this->displayed .= ob_get_contents();
				ob_end_clean();
			}
		} 
		
		$this->counter++;
	}
	
	/**
	 * Gets data submited till now.
	 * @return array
	 */
	public function get_data() {
		return $this->data;
	}
	
	/**
	 * For internal use only.
	 */
	public function submit($d) {
		$this->data[$this->curr_page] = $d;
		if(isset($this->aliases[$this->curr_page])) $this->data[$this->aliases[$this->curr_page]] = & $this->data[$this->curr_page];
	}
	
	/**
	 * Delete page
	 * @param page name or number
	 */
	public function delete_page($x) {
		$this->to_del[] = $x;
	}
	
	private function flush_deleted() {
		foreach($this->to_del as $x) {
			if(is_string($x)) {
				if(!isset($this->r_aliases[$x]))
					trigger_error('No such page: '.$x,E_USER_ERROR);
				$id = $this->r_aliases[$x];
				$name = $x;
			} elseif(is_int($x)) {
				if(isset($this->aliases[$x]))
					$name = $this->aliases[$x];
				$id = $x;
			} else
				trigger_error('Invalid page id: '.$x,E_USER_ERROR);
			
			unset($this->data[$id]);
			unset($this->captions[$id]);
			if(isset($name))
				unset($this->data[$name]);
			if(isset($this->r_aliases[$name])) {
				unset($this->r_aliases[$name]);
				unset($this->aliases[$id]);
			}
		}
	}
	
	/**
	 * Displays wizard current step.
	 * You can also specify function to process the data from all the pages.
	 * 
	 * @param method method to process the data
	 */
	public function body($func) {
		$this->next_page();
		$this->flush_deleted();

		if(!isset($this->displayed) || (is_int($this->curr_page) && $this->curr_page>=$this->counter) || (is_string($this->curr_page) && !isset($this->r_aliases[$this->curr_page]))) {
			if(is_callable($func)) {
				$args = func_get_args();
				$args[0] = $this->data;
				if(!call_user_func_array($func, $args))
					print('<br><input '.$this->create_back_href().' type="button" value="back">'); 
			} else 
				print(Base_LangCommon::ts('Utils/Wizard','Wizard complete! No more pages to display...'));
		} else {
			$t = & $this->init_module('Base/Theme');
			
			$t->assign('page',$this->displayed);
			$t->assign('captions',$this->captions);
			$t->assign('curr_page',$this->curr_page);
			
			$keys = array_keys($this->captions);
			$x=key($this->captions);
			for($i=0; $i<count($keys);$i++)
				if($this->curr_page>=$keys[$i] && (!isset($keys[$i+1]) || $this->curr_page<$keys[$i+1])) {
					$x=$keys[$i];
					break;
				}
			$t->assign('active_caption_key',$x);
			$t->display();
		}
	}
}
?>


