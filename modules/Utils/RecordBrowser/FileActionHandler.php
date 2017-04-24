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
        $fieldId = $this->getFieldId($tab, $field);
        $access = $fieldId && $record
            && isset($record[$fieldId]) && !is_null($record[$fieldId])
            && in_array($filestorageId, $record[$fieldId]);
        return $access;
    }

    private function getFieldId($tab, $field)
    {
        if (preg_match('/^[0-9]+$/', strval($field))) { // is integer
            $fields = Utils_RecordBrowserCommon::init($tab);
            foreach ($fields as $def) {
                if ($def['pkey'] == $field) {
                    return $def['id'];
                }
            }
            return false;
        }
        return $field;
    }

}
