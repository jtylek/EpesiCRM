<?php

/**
 * Sample plugin to try out some hooks.
 * This performs an automatic login if accessed from localhost
 */
class epesi_autologon extends rcube_plugin
{
  public $task = 'login';

  function init()
  {
    $this->add_hook('startup', array($this, 'startup'));
    $this->add_hook('authenticate', array($this, 'authenticate'));
  }

  function startup($args)
  {
    $rcmail = rcmail::get_instance();
    global $account;
    // change action to login
    if (empty($_SESSION['user_id']) && !empty($account)) {
      $args['action'] = 'login';
    }

    return $args;
  }

  function authenticate($args)
  {
    global $account;
    if (!empty($account)) {
      $args['user'] = $account['f_login'];
      $args['pass'] = $account['f_password'];
      $args['cookiecheck'] = false;
      $args['valid'] = true;
    }

    return $args;
  }
}
