<?php
/**
 * Notes Aggregate for companies, contacts and sales opportunities
 * 
 * @author Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-crm
 * @subpackage contacts-notesaggregate
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class CRM_Contacts_NotesAggregateCommon extends ModuleCommon {
	public static function user_settings() {
		return array(__('Notes Aggregate')=>array(
				array('name'=>'show_all_notes','label'=>__('Include Record Notes in Aggregate'),'type'=>'checkbox','default'=>false)
					));
	}
}

?>