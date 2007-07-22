<?php
/**
 * Wizard class.
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com> and Kuba Slawinski <kslawinski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 0.9
 * @licence SPL
 * @package epesi-utils
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

/**
 * This is a wizard creator helper wizard.
 * Enjoy.
 * 
 * @package epesi-utils
 * @subpackage generic-browse
 */

class Utils_Wizard extends Module {
	private $counter;
	private $curr_page;
	private $form = array();
	private $renderers = array();
	private $data;
	private $history;
	private $next = array();
	private $aliases = array();
	private $r_aliases = array();
	
	public function construct($start_page=0) {
		$this->counter = 0;
		$this->curr_page = $this->get_module_variable('curr_page',$start_page);
		$this->data = $this->get_module_variable('data',array());
		$this->history = $this->get_module_variable('history',array());
		if($this->is_back()) {
    		        $this->curr_page = array_pop($this->history);
			$this->set_module_variable('curr_page',$this->curr_page);
			$this->set_module_variable('history',$this->history);
		}
	}
	
	public function begin_page($name) {
		$args = func_get_args();
		array_shift($args);
		$this->form[$this->counter] = & $this->init_module('Libs/QuickForm',$args,$this->counter);
		
		if(isset($name)) {
			$this->r_aliases[$name] = $this->counter;
			$this->aliases[$this->counter] = $name;
		}
		return $this->form[$this->counter];
	}
	
	public function set_alternative_renderer(& $rend) {
		$this->renderers[$this->counter] = & $rend;
	}
	
	public function end_page($func) {
		$this->next[$this->counter] = func_get_args();
			
		$this->counter++;
	}
	
	public function submit($d) {
		$this->data[$this->curr_page] = $d;
		if(isset($this->aliases[$this->curr_page])) $this->data[$this->aliases[$this->curr_page]] = & $this->data[$this->curr_page]; 
	}
	
	public function body($func) {
		if($this->curr_page>=$this->counter) $this->curr_page = array_pop($this->history);
		if($this->form[$this->curr_page]->getSubmitValue('submited') && $this->form[$this->curr_page]->validate()) {
				$this->form[$this->curr_page]->process(array($this,'submit')); 
	
	    		$this->history[] = $this->curr_page;
				if(is_int($this->next[$this->curr_page][0])) {
					$this->curr_page = $this->next[$this->curr_page][0];
				} elseif(is_string($this->next[$this->curr_page][0])) {
					$this->curr_page = $this->r_aliases[$this->next[$this->curr_page][0]];
				} elseif(is_callable($this->next[$this->curr_page][0])) {
					$args = $this->next[$this->curr_page][0];
					$args[0] = & $this->data[$this->curr_page];
    	    		$ret = call_user_func_array($this->next[$this->curr_page][0], $args);
    	    		if(isset($ret)) {
						if(is_int($ret))
			    			$this->curr_page = $ret;
			    		elseif(is_string($ret))
			    			$this->curr_page = $this->r_aliases[$ret];
			    		else
			    			$this->curr_page++;
			    	} 
			        else
				        $this->curr_page++;
				} else
					$this->curr_page++;
					
		    	$this->set_module_variable('curr_page',$this->curr_page);
				$this->set_module_variable('data',$this->data);
				$this->set_module_variable('history',$this->history);
			}
		
		if($this->curr_page<$this->counter && $this->curr_page>=0) {
			if(empty($this->history)) {
				$this->form[$this->curr_page]->addElement('submit', 'button_next', Base_LangCommon::ts('Wizard','Next'));
			} else {
				$button_prev = HTML_QuickForm::createElement('button', 'button_prev', Base_LangCommon::ts('Wizard','Prev'), $this->create_back_href());
				$button_next = HTML_QuickForm::createElement('submit', 'button_next', Base_LangCommon::ts('Wizard','Next'));
				$this->form[$this->curr_page]->addGroup(array($button_prev, $button_next));
			}

		    if(is_array($this->data[$this->curr_page]))
			    $this->form[$this->curr_page]->setDefaults($this->data[$this->curr_page]);
			if(isset($this->renderers[$this->curr_page]))
				$this->renderers[$this->curr_page]->display();
		    else	
				$this->form[$this->curr_page]->display();
		} else {
			if(is_callable($func)) {
				$args = func_get_args();
				$args[0] = $this->data;
				call_user_func_array($func, $args);
			} else 
			    print(Base_LangCommon::ts('Wizard','Wizard complete! No more pages to display...'));
		}
	}
}
?>


