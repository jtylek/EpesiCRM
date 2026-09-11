function rb_admin_sort_fields_init(table_md5) {
    jq("#table_" + table_md5 + " .Utils_GenericBrowser__tbody").sortable(
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
            items: "> div.sortable",
            update: function (event, ui) {
                // Anchor on the preceding row's field name, not a DOM index - the field list is
                // paginated (Records per page), so a raw index is only valid within the current
                // page and silently reorders the wrong fields once there's more than one page.
                var anchor = ui.item.prev().attr("field_name") || "General";
                _chj(jq.param({"field_pos": [ui.item.attr("field_name"), anchor]}), "", "");
            }
        }
    );
}
