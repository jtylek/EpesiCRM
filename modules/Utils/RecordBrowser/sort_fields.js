function rb_admin_sort_fields_init(table_md5) {
    jq("#table_" + table_md5 + " tbody").sortable(
        {
            helper: function (e, tr) {
                var $originals = tr.children();
                var $helper = tr.clone();
                $helper.children().each(function (index) {
                    // Set helper cell sizes to match the original sizes
                    jq(this).width($originals.eq(index).width());
                });
                return $helper;
            },
            handle: ".move-handle",
            containment: "parent",
            items: "> tr.sortable",
            update: function (event, ui) {
                _chj(jq.param({"field_pos": [ui.item.attr("field_name"), ui.item.index()]}), "", "");
            }
        }
    );
}
