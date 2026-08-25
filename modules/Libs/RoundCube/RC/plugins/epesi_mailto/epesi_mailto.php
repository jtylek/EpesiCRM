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
    $this->add_hook('message_compose_body', array($this, 'set_body'));
    $this->add_hook('message_compose', array($this, 'set_to'));

    if(!isset($_SESSION['epesi_mailto'])) return;
    $rcmail = rcmail::get_instance();

    if ((($rcmail->task!='mail' && $rcmail->task!='utils') || $rcmail->action == '') && !isset($_GET['mailto'])) {
        unset($_SESSION['epesi_mailto']);
        print('<script>parent._chj("'.http_build_query(array('base_box_pop_main'=>1)).'","Loading...");</script>');//setTimeout(function(){},3000);
    }
  }
  
  function startup($args)
  {
    if(isset($_GET['mailto'])) {
        if (strpos($_GET['mailto'], 'mailto:') === 0) {
            $_POST['_url'] = http_build_query(array('task' => 'mail', '_action' => 'compose', '_to' => $_GET['mailto']));
        } else {
            $_POST['_url'] = http_build_query(array('task' => 'mail', '_action' => 'compose'));
        }
        $_SESSION['epesi_mailto'] = 1;
    }

  }  

  function logged($args)
  {
    if(isset($_GET['mailto'])) {
        $_SESSION['epesi_mailto'] = 1;
    }
  }  
  
  function set_body($args) {
    global $E_SESSION;
    if(isset($E_SESSION['rc_body']) && isset($_SESSION['epesi_mailto'])) {
        $args['body'] = $E_SESSION['rc_body'];
    }
    if(isset($E_SESSION['rc_reply'])) {
        global $COMPOSE;
        $COMPOSE['reply_msgid'] = '<'.$E_SESSION['rc_reply'].'>';
        if(isset($E_SESSION['rc_references'])) {
            $COMPOSE['references'] = trim($E_SESSION['rc_references'].' <'.$E_SESSION['rc_reply'].'>');
        }
    }
    return $args;
  }
  
  function set_to($c) {
    if(!isset($_SESSION['epesi_mailto'])) return $c;
    global $E_SESSION;
    if(isset($E_SESSION['rc_to'])) {
        $c['param']['to'] = $E_SESSION['rc_to'];
    }
    if(isset($E_SESSION['rc_subject'])) {
        $c['param']['subject'] = $E_SESSION['rc_subject'];
    }
    return $c;
  }
}
