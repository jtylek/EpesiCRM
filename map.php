<?php
global $virtual_hosts;
$virtual_hosts = array(
'^http[s]?://trialtest\.' => array('error'=>'TRIAL_END', 'alias'=>'trialtest')
);
?>
