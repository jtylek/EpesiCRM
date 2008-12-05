<?php
/**
 * DirtyRead class.
 * 
 * This class delivers functions protecting against dirty reads.
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 1.0
 * @license MIT
 * @package epesi-utils
 * @subpackage DirtyRead
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

/**
 * This class delivers functions protecting against dirty reads.
 * For example, if we have a table 'inventory' where $id is some value of integer primary key 'id' in this table:
 * <pre>
 * if(DirtyRead::get_dirty('inventory', $id)) {
 *		print($this->lang->t('Someone edited this item after you read it, please <a '.$this->create_back_href().'>reedit it</a>.'));
 *		return false;
 * }
 * if(!DB::Execute('UPDATE inventory SET description=%s, cond=%s, location=%s, item_no=%s WHERE id=%d',$data['description'], $data['condition'], $data['location'], $data['item_no'], $id)) {
 *		DirtyRead::unmodified();
 *		print($this->lang->t('Unable to update item.'));
 *		return false;
 * }
 * DirtyRead::modified('inventory', $id);
 * </pre>
 * See InventoryInit class' source for database table creation examples.
 * Note you should use InnoDB engine for editable tables. 
 * 
 */
class Utils_DirtyRead extends Module {

	public function body(& $arg) {
		$this->rt = $this->get_module_variable('read_time');
		if(!isset($this->rt)) {
			$this->rt = microtime(true);
			$this->set_module_variable('read_time', $this->rt);
		}
		if(isset($arg))
			$this->init_form($arg);
	}
	
	
	public function init_form(& $f) {
		$f->addElement('hidden', '_read_time', $this->rt);
		$f->addRule('_read_time', '','numeric');
	}
	
	/**
	 * Check if you can write to this table(s) and start update transaction.
	 * 
	 * @param mixed string if you wonna pass only one table, array(<table_name>=><id>) otherthise
	 * @param integer id if you are passing only one table
	 * @return true - someone else modified record
	 */
	public function get_dirty($table, $id=null) {
		if(!is_array($table))
			$table = array($table=>$id);
		
		$read_time = $_REQUEST['_read_time'];
		if(!isset($read_time)) {
			print(Base_Lang::ts('DirtyRead','Unable to get read time, invalid form submit.'));
			return true;
		}
		
		DB::$ado->StartTrans();
		foreach($table as $t=>$i) {
			$ret = DB::Execute('SELECT UNIX_TIMESTAMP(edited_on) FROM '.$t.' WHERE id=%d',$i);
			if(!$ret || !($row = $ret->FetchRow())) {
				DB::$ado->CompleteTrans();
				print('Invalid table '.$t.' or entry id '.$i);
				return true;
			}
			//print(intval($read_time).' mniejsze? '.intval($row[0]).'<hr>');
			if(floatval($read_time)<floatval($row[0])) {
				DB::$ado->CompleteTrans();
				return true;
			}
		}
		return false;
	}
	
	/**
	 * Mark table(s) as modified and commit transaction.
	 * 
	 * @param mixed string if you want to pass only one table, array(<table_name>=><id>) otherthise
	 * @param integer id if you are passing only one table
	 */
	public function modified($table, $id=null) {
		if(!is_array($table))
			$table = array($table=>$id);
		
		foreach($table as $t=>$i) {
			DB::Execute('UPDATE '.$t.' SET edited_on = CURRENT_TIMESTAMP, edited_by=%d WHERE id=%d',array(Acl::get_user(),$i));
		}
		DB::$ado->CompleteTrans();
		$this->unset_module_variable('read_time');
	}
	
	/**
	 * Rollback transaction.
	 */
	public function unmodified() {
		DB::$ado->FailTrans();
		DB::$ado->CompleteTrans();
		$this->unset_module_variable('read_time');
	}
}

?>
