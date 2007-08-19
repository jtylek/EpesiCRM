<?php
/**
 * Wizard class.
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com> and Kuba Slawinski <kslawinski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 0.9
 * @licence SPL
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
	private $next = array();
	private $aliases = array();
	private $r_aliases = array();
	
	/**
	 * Module constructor.
	 * You can choose starting page while creating new instance of this module.
	 * 
	 * @param integer starting page number 
	 */
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
	
	/**
	 * Starts new wizard step.
	 * This method returns QuickForm object which you should use 
	 * to create wizard step.
	 * 
	 * @param string alias for the page
	 * @return object QuickForm object
	 */
	public function begin_page($name=null) {
		$args = func_get_args();
		array_shift($args);
		$this->form[$this->counter] = & $this->init_module('Libs/QuickForm',$args,$this->counter);
		
		if(isset($name)) {
			$this->r_aliases[$name] = $this->counter;
			$this->aliases[$this->counter] = $name;
		}
		return $this->form[$this->counter];
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
	public function end_page($func=null) {
		$this->next[$this->counter] = $func;
		$this->counter++;
	}
	
	/**
	 * For internal use only.
	 */
	public function submit($d) {
		$this->data[$this->curr_page] = $d;
		if(isset($this->aliases[$this->curr_page])) $this->data[$this->aliases[$this->curr_page]] = & $this->data[$this->curr_page]; 
	}
	
	/**
	 * Displays wizard current step.
	 * You can also specify function to process the data from all the pages.
	 * 
	 * @param method method to process the data
	 */
	public function body($func) {
		if($this->curr_page>=$this->counter) $this->curr_page = array_pop($this->history);
		if($this->form[$this->curr_page]->getSubmitValue('submited') && $this->form[$this->curr_page]->validate()) {
				$this->form[$this->curr_page]->process(array($this,'submit')); 
	
	    		$this->history[] = $this->curr_page;
				if(is_int($this->next[$this->curr_page])) {
					$this->curr_page = $this->next[$this->curr_page];
				} elseif(is_string($this->next[$this->curr_page])) {
					$this->curr_page = $this->r_aliases[$this->next[$this->curr_page]];
				} elseif(is_callable($this->next[$this->curr_page])) {
					$args = $this->next[$this->curr_page];
					$args[0] = & $this->data[$this->curr_page];
    	    		$ret = call_user_func_array($this->next[$this->curr_page], $args);
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

		    if(isset($this->data[$this->curr_page]) && is_array($this->data[$this->curr_page]))
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


