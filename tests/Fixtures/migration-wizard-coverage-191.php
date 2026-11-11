<?php

/**
 * Synthetic Epesi 1.9.1 coverage inventory. Column lists of the shipped tables are
 * copied from legacy-1.9.1-schema.sql; row/byte estimates, the
 * Custom_Invoice module and the file areas are invented.
 */
return [
    'version' => '1.9.1',
    'modules' => [
        ['name' => 'Base', 'version' => 1],
        ['name' => 'CRM_Contacts', 'version' => 1],
        ['name' => 'CRM_Tasks', 'version' => 1],
        ['name' => 'Custom_Invoice', 'version' => 1],
    ],
    'tables' => [
        'variables' => ['columns' => ['name', 'value'], 'estimated_rows' => 2, 'estimated_bytes' => 128],
        'modules' => ['columns' => ['name', 'version', 'priority', 'state'], 'estimated_rows' => 4, 'estimated_bytes' => 256],
        'user_login' => ['columns' => ['id', 'login', 'active', 'admin'], 'estimated_rows' => 1, 'estimated_bytes' => 64],
        'user_password' => ['columns' => ['user_login_id', 'password', 'mail'], 'estimated_rows' => 1, 'estimated_bytes' => 64],
        'company_data_1' => ['columns' => [
            'id', 'created_on', 'created_by', 'indexed', 'active', 'f_company_name', 'f_short_name', 'f_phone',
            'f_fax', 'f_email', 'f_web_address', 'f_group', 'f_permission', 'f_address_1', 'f_address_2',
            'f_city', 'f_country', 'f_zone', 'f_postal_code', 'f_tax_id',
        ], 'estimated_rows' => 2, 'estimated_bytes' => 384],
        'contact_data_1' => ['columns' => [
            'id', 'created_on', 'created_by', 'indexed', 'active', 'f_last_name', 'f_first_name',
            'f_company_name', 'f_related_companies', 'f_group', 'f_title', 'f_work_phone', 'f_mobile_phone',
            'f_fax', 'f_email', 'f_web_address', 'f_address_1', 'f_address_2', 'f_city', 'f_country', 'f_zone',
            'f_postal_code', 'f_permission', 'f_home_phone', 'f_home_address_1', 'f_home_address_2',
            'f_home_city', 'f_home_country', 'f_home_zone', 'f_home_postal_code', 'f_birth_date', 'f_login',
            'f_access',
        ], 'estimated_rows' => 3, 'estimated_bytes' => 512],
        'task_data_1' => ['columns' => [
            'id', 'created_on', 'created_by', 'indexed', 'active', 'f_title', 'f_description', 'f_employees',
            'f_customers', 'f_status', 'f_priority', 'f_permission', 'f_longterm', 'f_deadline', 'f_timeless',
            'f_related',
        ], 'estimated_rows' => 2, 'estimated_bytes' => 320],
        'custom_invoice' => ['columns' => ['id', 'reference'], 'estimated_rows' => 1, 'estimated_bytes' => 128],
    ],
    'files' => [
        'data/CRM_Contacts_Photo' => ['count' => 2, 'bytes' => 2048],
        'data/Custom_Invoice' => ['count' => 1, 'bytes' => 512],
        'data/orphaned' => ['count' => 1, 'bytes' => 64],
    ],
    'known_modules' => [
        ['legacy_module' => 'Base', 'destination_module' => 'App', 'source_tables' => ['variables', 'modules', 'user_login', 'user_password'], 'source_file_areas' => []],
        ['legacy_module' => 'CRM_Contacts', 'destination_module' => 'Epesi/CRM/Contacts', 'source_tables' => ['company_data_1', 'contact_data_1'], 'source_file_areas' => ['data/CRM_Contacts_Photo']],
        ['legacy_module' => 'CRM_Tasks', 'destination_module' => 'Epesi/CRM/Tasks', 'source_tables' => ['task_data_1'], 'source_file_areas' => []],
    ],
];
