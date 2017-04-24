<?php

//---------------------------------------------------------------------
//  SET DEFAULT EPESI CONSTANTS
//---------------------------------------------------------------------

define('DATA_DIR', 'data');
define('CID', false);
define('SET_SESSION', false);

//---------------------------------------------------------------------
// Load EPESI composer autoloader with classmap to fix
// class loading issue
//---------------------------------------------------------------------

$fakeLoader = __DIR__ . '/../_fakeLoader/vendor/autoload.php';
if (!file_exists($fakeLoader)) {
    $fakeLoaderDir = realpath(dirname(dirname($fakeLoader)));
    throw new Exception("Please initialize fake composer autoloader\nOpen $fakeLoaderDir and invoke `composer install`");
}
$loader = require_once $fakeLoader;
$composerLoader = new \Go\ParserReflection\Locator\ComposerLocator($loader);
$loader->unregister();

//---------------------------------------------------------------------
// Init AspectMock Kernel with cache directory
//---------------------------------------------------------------------

$kernel = \AspectMock\Kernel::getInstance();
$options = [
    'debug'        => true,
    'includePaths' => [__DIR__ . '/../../include/', __DIR__ . '/../../modules/'],
    'excludePaths' => [__DIR__]
];
$cacheDir = sys_get_temp_dir() . '/aspect_mock_cache' . dirname(dirname(__DIR__));
@mkdir($cacheDir, 0777, true);
if (file_exists($cacheDir)) {
    $options['cacheDir'] = $cacheDir;
}
$kernel->init($options);

//---------------------------------------------------------------------
// Initialize custom class locator with created composerLoader
//---------------------------------------------------------------------

\Go\ParserReflection\ReflectionEngine::init(new \Go\ParserReflection\Locator\CallableLocator(
    function ($className) use ($composerLoader) {
        $className = ltrim($className, '\\');
        return $composerLoader->locateClass($className);
    }
));

//---------------------------------------------------------------------
// Load EPESI
//---------------------------------------------------------------------

$kernel->loadFile('include.php');

ModuleManager::load_modules();
Base_AclCommon::set_sa_user();

$db_config = & \Codeception\Configuration::$defaultSuiteSettings['modules']['config']['Db'];
$db_config['dsn'] = 'mysql:host=' . DATABASE_HOST . ';dbname=' . DATABASE_NAME;
$db_config['user'] = DATABASE_USER;
$db_config['password'] = DATABASE_PASSWORD;
