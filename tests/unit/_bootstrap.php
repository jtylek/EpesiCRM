<?php

define('DATA_DIR', 'data');
define('CID', false);
define('SET_SESSION', false);

include 'include.php';

ModuleManager::load_modules();
Base_AclCommon::set_sa_user();

$db_config = & \Codeception\Configuration::$defaultSuiteSettings['modules']['config']['Db'];
$db_config['dsn'] = 'mysql:host=' . DATABASE_HOST . ';dbname=' . DATABASE_NAME;
$db_config['user'] = DATABASE_USER;
$db_config['password'] = DATABASE_PASSWORD;
