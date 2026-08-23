function rb_indexer(token) {
    jQuery.getScript('modules/Utils/RecordBrowser/indexer.php?cid='+Epesi.client_id+'&token='+token);
}