<?php
/**
 * Notes Aggregate for companies, contacts and sales opportunities
 * 
 * @author Arkadiusz Bisaga, Janusz Tylek
 * @copyright Copyright &copy; 2008, Janusz Tylek
 * @license MIT
 * @version 1.9.0
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