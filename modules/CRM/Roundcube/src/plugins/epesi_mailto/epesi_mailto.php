<?php

/**
 * Sample plugin to try out some hooks.
 * This performs an automatic login if accessed from localhost
 */
class epesi_mailto extends rcube_plugin
{
//  public $task = 'mail|settings|addressbook';

  function init()
  {
    $this->add_hook('startup', array($this, 'startup'));
    $this->add_hook('login_after', array($this, 'logged'));
    if(!isset($_SESSION['epesi_mailto'])) return;
    $rcmail = rcmail::get_instance();
    
/*    if($rcmail->task=='mail') { //rcube_template.php line 900
        $this->include_script('archive.js');
        $skin_path = $rcmail->config->get('skin_path');
        $this->add_button(
        array(
            'command' => 'plugin.epesi_auto_archive',
//            'imagepas' => $skin_path.'/archive_pas.png',
            'imageact' => $skin_path.'/archive_act.png',
            'title' => 'Archive this message in Epesi on message sending',
            'domain' => $this->ID,
            'id'=>'epesi_auto_archive_button'
        ),
        'toolbar');
    }*/

    if (($rcmail->task!='mail' || $rcmail->action == '') && !isset($_GET['mailto'])) {
        unset($_SESSION['epesi_mailto']);
        print('<script>parent._chj("'.http_build_query(array('base_box_pop_main'=>1)).'","loading...");</script>');
    }
  }
  
  function startup($args)
  {
    if(isset($_GET['mailto'])) {
        $_POST['_url'] = http_build_query(array('task' => 'mail', '_action' => 'compose', '_to' => $_GET['mailto']));
        $_SESSION['epesi_mailto'] = 1;
    }

  }  

  function logged($args)
  {
    if(isset($_GET['mailto'])) {
        $_SESSION['epesi_mailto'] = 1;
    }
  }  
}
