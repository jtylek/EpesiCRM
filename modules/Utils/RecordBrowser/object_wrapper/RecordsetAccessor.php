<?php

/**
 * Use this class to access already created recordset.
 * It will allow you to operate on recordset and records in objective manner.
 *
 * @author Adam Bukowski <abukowski@telaxus.com>
 */
class RBO_RecordsetAccessor extends RBO_Recordset {

    private $tab;

    public function fields() {
        trigger_error('RBO_RecordsetAccessor has not defined fields. Please implement your own implementation of RBO_Recordset.', E_USER_ERROR);
    }

    public function table_name() {
        return $this->tab;
    }

    public function __construct($tab) {
        $this->tab = $tab;

        parent::__construct();
    }

}

?>