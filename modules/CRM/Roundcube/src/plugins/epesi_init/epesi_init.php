<?php
//function on_init() {}
/**
 * Sample plugin to add a new address book
 * with just a static list of contacts
 */
class epesi_init extends rcube_plugin
{
  public function init()
  {
    $d = getcwd();
    chdir('../../../../');
    require_once('include/epesi.php');
    require_once('include/variables.php');
    require_once('include/misc.php');
    require_once('include/acl.php');
    require_once('include/module_acl.php');
    require_once('include/module_primitive.php');
    require_once('include/module_install.php');
    require_once('include/module_common.php');
    require_once('include/module.php');
    require_once('include/module_manager.php');
    ModuleManager::load_modules();
    chdir($d);
    global $E_SESSION;
    $_SESSION['user'] = $E_SESSION['user'];
  }

}
