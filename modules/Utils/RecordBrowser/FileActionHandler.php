<?php

require_once __DIR__ . '/../FileStorage/ActionHandler.php';

class Utils_RecordBrowser_FileActionHandler
    extends Utils_FileStorage_ActionHandler
{
    protected function getHandlingScript()
    {
        return get_epesi_url() . '/modules/Utils/RecordBrowser/file.php';
    }

    /**
     * Get Action urls for RB file leightbox
     *
     * @param int    $filestorageId Filestorage ID
     * @param string $tab           Recordset name. e.g. company
     * @param int    $recordId      Record ID
     * @param string $field         Field identifier. e.g. company_name
     *
     * @return array
     */
    public function getActionUrlsRB($filestorageId, $tab, $recordId, $field)
    {
        $params = ['tab' => $tab, 'record' => $recordId, 'field' => $field];
        return $this->getActionUrls($filestorageId, $params);
    }

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
