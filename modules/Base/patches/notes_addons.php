<?php

if (ModuleManager::is_installed('Utils_RecordBrowser')==-1) return;

if (Utils_RecordBrowserCommon::delete_addon('company', 'CRM/Contacts', 'company_attachment_addon'))
	Utils_AttachmentCommon::new_addon('company');
	
if (Utils_RecordBrowserCommon::delete_addon('contact', 'CRM/Contacts', 'contact_attachment_addon'))
	Utils_AttachmentCommon::new_addon('contact');

if (Utils_RecordBrowserCommon::delete_addon('crm_assets', 'CRM/Assets', 'assets_attachment_addon'))
	Utils_AttachmentCommon::new_addon('crm_assets');

if (Utils_RecordBrowserCommon::delete_addon('crm_meeting', 'CRM/Meeting', 'meeting_attachment_addon'))
	Utils_AttachmentCommon::new_addon('crm_meeting');

if (Utils_RecordBrowserCommon::delete_addon('phonecall', 'CRM/PhoneCall', 'phonecall_attachment_addon'))
	Utils_AttachmentCommon::new_addon('phonecall');

if (Utils_RecordBrowserCommon::delete_addon('task', 'CRM/Tasks', 'task_attachment_addon'))
	Utils_AttachmentCommon::new_addon('task');

if (Utils_RecordBrowserCommon::delete_addon('premium_projects', 'Premium/Projects', 'premium_projects_attachment_addon'))
	Utils_AttachmentCommon::new_addon('premium_projects');

/** PREMIUM **/

if (Utils_RecordBrowserCommon::delete_addon('cades_diagnosis', 'Custom/CADES/Diagnosis', 'attachment_addon'))
	Utils_AttachmentCommon::new_addon('cades_diagnosis');

if (Utils_RecordBrowserCommon::delete_addon('cades_diet', 'Custom/CADES/Diet', 'attachment_addon'))
	Utils_AttachmentCommon::new_addon('cades_diet');

if (Utils_RecordBrowserCommon::delete_addon('cades_hospitalizations', 'Custom/CADES/Hospitalizations', 'attachment_addon'))
	Utils_AttachmentCommon::new_addon('cades_hospitalizations');

if (Utils_RecordBrowserCommon::delete_addon('cades_immunizations', 'Custom/CADES/Immunizations', 'attachment_addon'))
	Utils_AttachmentCommon::new_addon('cades_immunizations');

if (Utils_RecordBrowserCommon::delete_addon('cades_insurance', 'Custom/CADES/Insurance', 'attachment_addon'))
	Utils_AttachmentCommon::new_addon('cades_insurance');

if (Utils_RecordBrowserCommon::delete_addon('cades_issues', 'Custom/CADES/Issues', 'attachment_addon'))
	Utils_AttachmentCommon::new_addon('cades_issues');

if (Utils_RecordBrowserCommon::delete_addon('cades_medicaltests', 'Custom/CADES/MedicalTests', 'attachment_addon'))
	Utils_AttachmentCommon::new_addon('cades_medicaltests');

if (Utils_RecordBrowserCommon::delete_addon('cades_medications', 'Custom/CADES/Medications', 'attachment_addon'))
	Utils_AttachmentCommon::new_addon('cades_medications');

if (Utils_RecordBrowserCommon::delete_addon('cades_reviews', 'Custom/CADES/Reviews', 'attachment_addon'))
	Utils_AttachmentCommon::new_addon('cades_reviews');

if (Utils_RecordBrowserCommon::delete_addon('cades_services', 'Custom/CADES/Services', 'attachment_addon'))
	Utils_AttachmentCommon::new_addon('cades_services');

if (Utils_RecordBrowserCommon::delete_addon('cades_toileting', 'Custom/CADES/Toileting', 'attachment_addon'))
	Utils_AttachmentCommon::new_addon('cades_toileting');

if (Utils_RecordBrowserCommon::delete_addon('cades_vitalsigns', 'Custom/CADES/VitalSigns', 'attachment_addon'))
	Utils_AttachmentCommon::new_addon('cades_vitalsigns');

if (Utils_RecordBrowserCommon::delete_addon('custom_jobsearch_advertisinglog', 'Custom/JobSearch/AdvertisingLog', 'custom_jobsearch_advertisinglog_attachment'))
	Utils_AttachmentCommon::new_addon('custom_jobsearch_advertisinglog');

if (Utils_RecordBrowserCommon::delete_addon('custom_jobsearch', 'Custom/JobSearch', 'custom_jobsearch_attachment_addon'))
	Utils_AttachmentCommon::new_addon('custom_jobsearch');

if (Utils_RecordBrowserCommon::delete_addon('custom_merlin_licence', 'Custom/MerlinSMS', 'attachment_addon'))
	Utils_AttachmentCommon::new_addon('custom_merlin_licence');

if (Utils_RecordBrowserCommon::delete_addon('custom_monthlycost', 'Custom/MonthlyCost', 'attachment_addon'))
	Utils_AttachmentCommon::new_addon('custom_monthlycost');

if (Utils_RecordBrowserCommon::delete_addon('custom_changeorders', 'Custom/Projects/ChangeOrders', 'changeorder_attachment_addon'))
	Utils_AttachmentCommon::new_addon('custom_changeorders');

if (Utils_RecordBrowserCommon::delete_addon('custom_equipment', 'Custom/Projects/ChangeOrders', 'equipment_attachment_addon'))
	Utils_AttachmentCommon::new_addon('custom_equipment');

if (Utils_RecordBrowserCommon::delete_addon('custom_projects', 'Custom/Projects', 'project_attachment_addon'))
	Utils_AttachmentCommon::new_addon('custom_projects');

if (Utils_RecordBrowserCommon::delete_addon('custom_shopequipment', 'Custom/Projects/ShopEquipment', 'shopequipment_attachment_addon'))
	Utils_AttachmentCommon::new_addon('custom_shopequipment');

if (Utils_RecordBrowserCommon::delete_addon('custom_tickets', 'Custom/Projects/Tickets', 'custom_tickets_attachment_addon'))
	Utils_AttachmentCommon::new_addon('custom_tickets');

if (Utils_RecordBrowserCommon::delete_addon('premium_apartments_agent', 'Premium/Apartments', 'agent_attachment_addon'))
	Utils_AttachmentCommon::new_addon('premium_apartments_agent');

if (Utils_RecordBrowserCommon::delete_addon('premium_apartments_apartment', 'Premium/Apartments', 'apartment_attachment_addon'))
	Utils_AttachmentCommon::new_addon('premium_apartments_apartment');

if (Utils_RecordBrowserCommon::delete_addon('premium_apartments_rental', 'Premium/Apartments', 'rental_attachment_addon'))
	Utils_AttachmentCommon::new_addon('premium_apartments_rental');

if (Utils_RecordBrowserCommon::delete_addon('gc_changeorders', 'Premium/GCProjects/ChangeOrders', 'changeorder_attachment_addon'))
	Utils_AttachmentCommon::new_addon('gc_changeorders');

if (Utils_RecordBrowserCommon::delete_addon('gc_projects', 'Premium/GCProjects', 'project_attachment_addon'))
	Utils_AttachmentCommon::new_addon('gc_projects');

if (Utils_RecordBrowserCommon::delete_addon('gc_equipment', 'Premium/GCProjects/ChangeOrders', 'equipment_attachment_addon'))
	Utils_AttachmentCommon::new_addon('gc_equipment');

if (Utils_RecordBrowserCommon::delete_addon('gc_shopequipment', 'Premium/GCProjects/ShopEquipment', 'shopequipment_attachment_addon'))
	Utils_AttachmentCommon::new_addon('gc_shopequipment');

if (Utils_RecordBrowserCommon::delete_addon('gc_tickets', 'Premium/GCProjects/Tickets', 'gc_tickets_attachment_addon'))
	Utils_AttachmentCommon::new_addon('gc_tickets');

if (Utils_RecordBrowserCommon::delete_addon('premium_listmanager', 'Premium/ListManager', 'premium_listmanager_attachment_addon'))
	Utils_AttachmentCommon::new_addon('premium_listmanager');

if (Utils_RecordBrowserCommon::delete_addon('premium_ecommerce_pages_data', 'Premium/Warehouse/eCommerce', 'attachment_page_addon'))
	Utils_AttachmentCommon::new_addon('premium_ecommerce_pages_data');

if (Utils_RecordBrowserCommon::delete_addon('premium_ecommerce_pages_data', 'Premium/Warehouse/eCommerce', 'attachment_page_desc_addon'))
	Utils_AttachmentCommon::new_addon('premium_ecommerce_pages_data');

if (Utils_RecordBrowserCommon::delete_addon('premium_ecommerce_products', 'Premium/Warehouse/eCommerce', 'attachment_product_addon'))
	Utils_AttachmentCommon::new_addon('premium_ecommerce_products');

if (Utils_RecordBrowserCommon::delete_addon('premium_ecommerce_descriptions', 'Premium/Warehouse/eCommerce', 'attachment_product_desc_addon'))
	Utils_AttachmentCommon::new_addon('premium_ecommerce_descriptions');

if (Utils_RecordBrowserCommon::delete_addon('premium_warehouse_items', 'Premium/Warehouse/Items', 'attachment_addon'))
	Utils_AttachmentCommon::new_addon('premium_warehouse_items');

if (Utils_RecordBrowserCommon::delete_addon('premium_warehouse_items_orders', 'Premium/Warehouse/Items/Orders', 'attachment_addon'))
	Utils_AttachmentCommon::new_addon('premium_warehouse_items_orders');

if (Utils_RecordBrowserCommon::delete_addon('premium_warehouse', 'Premium/Warehouse', 'attachment_addon'))
	Utils_AttachmentCommon::new_addon('premium_warehouse');

if (Utils_RecordBrowserCommon::delete_addon('premium_warehouse_distributor', 'Premium/Warehouse/Wholesale', 'attachment_addon'))
	Utils_AttachmentCommon::new_addon('premium_warehouse_distributor');

if (Utils_RecordBrowserCommon::delete_addon('bugtrack', 'Tests/Bugtrack', 'bugtrack_attachment_addon'))
	Utils_AttachmentCommon::new_addon('bugtrack');

if (Utils_RecordBrowserCommon::delete_addon('premium_schoolregister_lesson', 'Premium/SchoolRegister', 'lesson_notes_addon')) {
	Utils_AttachmentCommon::new_addon('premium_schoolregister_lesson');
	DB::Execute('UPDATE utils_attachment_link SET local='.DB::Concat(DB::qstr('premium_schoolregister_lesson/'), 'local').' WHERE local NOT LIKE '.DB::Concat(DB::qstr('%'),DB::qstr('/'),DB::qstr('%')));
}

?>
