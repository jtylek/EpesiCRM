<?php
class ErrorObserver
{
}

class ErrorHandler {
	public static function add_observer() {
	}
}

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
    if(Base_RegionalSettingsCommon::time_12h()) {
        $time = 'h:i a';
    } else {
        $time = 'H:i';
    }
    $date=Base_RegionalSettingsCommon::date_format();
    switch($date) {
        case '%Y-%m-%d':
            $date = 'Y-m-d';
            break;
        case '%m/%d/%Y':
            $date = 'm/d/Y';
            break;
        case '%d %B %Y':
            $date = 'd F Y';
            break;
        case '%d %b %Y':
            $date = 'd M Y';
            break;
        case '%b %d, %Y':
            $date = 'M d, Y';
            break;
    }
    rcmail::get_instance()->config->set('date_short','D '.$time);
    rcmail::get_instance()->config->set('date_long',$date.' '.$time);
    rcmail::get_instance()->config->set('date_today',$time);        
    
    $this->add_hook('outgoing_message_body', array($this, 'add_signature'));
  }
  
  public function add_signature($b) {
    $footer = "Message sent with EpesiBIM - managing business your way!<br /><a href=\"http://www.epesibim.com\">http://www.epesibim.com</a>";
    $sig = Variable::get('crm_roundcube_global_signature',false);
    if($sig) $footer = $sig.'<br />---<br />'.$footer;
    if($b['type']=='plain') {
        $b['body'] .= "\r\n".strip_tags(preg_replace('/<[bh]r\s*\/?>/i',"\r\n",$footer));
    } else {
        $b['body'] .= '<br />'.$footer;
    }
    return $b;
  }

}
