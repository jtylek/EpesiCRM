Utils_Tooltip = {
    enable_tooltips: function () {
        jQuery('body')
            .tooltip(            {
                selector: '[data-toggle="tooltip"]',
                html: true,
                trigger: "hover focus",
                title: Utils_Tooltip.load_title, // use method to load title from attribute or ajax call
                placement: "auto",
                container: "body" // attach to body to avoid issues with width
            })
            // remove all tooltips before showing next one
            .on('show.bs.tooltip', function() { jQuery('[role="tooltip"]').remove() });
        jQuery(document).on('e:loading', function () {
            jQuery('[role="tooltip"]').remove()
        });
    },
    load_title: function () {
        var el = jQuery(this);
        // check and load if ajaxtooltip
        Utils_Tooltip.load_ajax(el);
        return el.attr('data-epesi-tooltip');
    },
    load_ajax: function (el) {
        if (el.data('ajaxtooltip')) {
            var tooltip_id = el.data('ajaxtooltip');

            // title function may be called twice, by bootstrap, remove ajaxtooltip ASAP to avoid double ajax call
            el.data('ajaxtooltip', null);

            // perform ajax call
            jq.ajax({
                type: 'POST',
                url: 'modules/Utils/Tooltip/req.php',
                data: {
                    tooltip_id: tooltip_id,
                    cid: Epesi.client_id
                },
                success: function (t) {
                    el.attr('data-epesi-tooltip', t);

                    // if leightbox has been opened and tooltip is still loading then, fill lb content again
                    if (el.hasClass('lbOn') && jq('#tooltip_leightbox_mode_content').is(':visible')) {
                        Utils_Tooltip.leightbox_mode(el);
                    } else if(el.is(':hover')) {
                        el.tooltip('show');
                    }
                },
                error: function () {
                    // restore ajaxtooltip data if loading failed
                    el.data('ajaxtooltip', tooltip_id);
                }
            });
        }
    },
    leightbox_mode: function (o) {
        var jo = jq(o);
        var tip = jo.attr('data-epesi-tooltip');
        // copy tooltip text to leightbox
        jq('#tooltip_leightbox_mode_content').html(tip);
        // hide tooltip
        jo.tooltip('hide');
    }
};

