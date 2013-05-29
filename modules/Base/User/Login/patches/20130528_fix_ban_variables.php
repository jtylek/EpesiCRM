<?php

$nr = Variable::get('host_ban_nr_of_tries', false);
if (!is_numeric($nr))
    Variable::set('host_ban_nr_of_tries', 3);

?>