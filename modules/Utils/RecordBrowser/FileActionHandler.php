<?php

require_once __DIR__ . '/../FileStorage/ActionHandler.php';

class Utils_RecordBrowser_FileActionHandler
    extends Utils_FileStorage_ActionHandler
{
    protected function hasAccess($action, $request)
    {
        $tab = $request->get('tab');
        $recordId = $request->get('record');
        $field = $request->get('field');
        $filestorageId = $request->get('id');
        if (!($tab && $recordId && $field && $filestorageId)) {
            return false;
        }

        $access = $this->checkRecordAccess($tab, $recordId, $field, $filestorageId);
        return $access;
    }

    protected function checkRecordAccess($tab, $recordId, $field, $filestorageId)
    {
        $record = Utils_RecordBrowserCommon::get_record_respecting_access($tab, $recordId);
        $access = isset($record[$field]) && !is_null($record[$field]) && in_array($filestorageId, $record[$field]);
        return $access;
    }


}