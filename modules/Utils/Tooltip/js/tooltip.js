Utils_Tooltip = {
    timeout_obj: false,
    enable_tooltips: function () {
        jQuery('body').tooltip({selector: '[data-toggle="tooltip"]', html: true, trigger: "hover", container: "body"})
    },
    load_ajax: function (tooltip_id) {
        jQuery('[data-ajaxtooltip="' + tooltip_id + '"]').on('shown.bs.tooltip', function () {
            var el = jq(this);
            if (el.data('ajaxtooltip')) {
                jq.ajax({
                    type: 'POST',
                    url: 'modules/Utils/Tooltip/req.php',
                    data: {
                        tooltip_id: el.data('ajaxtooltip'),
                        cid: Epesi.client_id
                    },
                    success: function (t) {
                        el
                            .attr('title', t)
                            .tooltip('fixTitle');
                        el.data('ajaxtooltip', null);
                        if (el.hasClass('lbOn')) {
                            Utils_Tooltip.leightbox_mode(el);
                            if (!jq('#tooltip_leightbox_mode_content').is(':visible')) {
                                el.tooltip('show');
                            }
                        } else {
                            el.tooltip('show');
                        }
                    }
                });
            }
        });
    },
    leightbox_mode: function (o) {
        var jo = jq(o);
        var tip = jo.attr('data-original-title');
        jq('#tooltip_leightbox_mode_content').html(tip);
    }
}

