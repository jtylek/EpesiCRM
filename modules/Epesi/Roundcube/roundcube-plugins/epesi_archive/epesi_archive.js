/**
 * The Archive button and the compose toggle (see epesi_archive.php). The
 * server first checks the selection for contacts and companies, and either
 * warns or answers plugin.epesi_archive_move. Moves go through Roundcube's
 * own move_messages(); once the server has done one, the Epesi page around
 * this frame is told, and archives the folder.
 */
(function () {
    var archiving = false;

    function tellEpesi(type) {
        if (window.parent !== window) {
            window.parent.postMessage({ epesiRoundcube: type }, window.location.origin);
        }
    }

    // Epesi never archived from the archive folder itself or from Drafts.
    function cannotArchive() {
        var folder = rcmail.env.epesi_archive_folder,
            mailbox = String(rcmail.env.mailbox);

        return mailbox === folder || mailbox.indexOf(folder + rcmail.env.delimiter) === 0
            || (!!rcmail.env.drafts_mailbox && mailbox === rcmail.env.drafts_mailbox);
    }

    function archive() {
        var post = rcmail.selection_post_data();

        if (cannotArchive() || !post._uid) {
            return;
        }

        rcmail.http_post('plugin.epesi_archive', { _uid: post._uid, _mbox: post._mbox }, rcmail.set_busy(true, 'loading'));
    }

    function composeToggle() {
        var select = $('#compose-store-target'),
            folder = rcmail.env.epesi_archive_folder,
            other = select.val(),
            on = !!rcmail.env.epesi_archive_on_sending,
            button = $('a.button.archive', '#compose-toolbar');

        // Without the folder among the choices, choosing it would save no copy at all.
        if (!select.find('option').filter(function () { return this.value === folder; }).length) {
            rcmail.enable_command('plugin.epesi_archive_toggle', false);
            return;
        }

        if (other === folder) {
            other = rcmail.env.sent_mbox || select.find('option').first().val();
            on = true;
        }

        function apply() {
            select.val(on ? folder : other);
            button.toggleClass('epesi-archive-off', !on).attr('aria-pressed', on ? 'true' : 'false');
        }

        rcmail.register_command('plugin.epesi_archive_toggle', function () {
            on = !on;
            apply();
        }, true);

        select.on('change', function () {
            on = select.val() === folder;
            if (!on) {
                other = select.val();
            }
            button.toggleClass('epesi-archive-off', !on).attr('aria-pressed', on ? 'true' : 'false');
        });

        apply();
    }

    if (!window.rcmail) {
        return;
    }

    rcmail.addEventListener('init', function () {
        if (rcmail.env.epesi_archive_fetch) {
            tellEpesi('archived');
        }

        if (rcmail.env.action === 'compose') {
            composeToggle();
            return;
        }

        rcmail.register_command('plugin.epesi_archive', archive, !!rcmail.env.uid && !cannotArchive());

        if (rcmail.message_list) {
            rcmail.message_list.addEventListener('select', function (list) {
                rcmail.enable_command('plugin.epesi_archive', list.get_selection().length > 0 && !cannotArchive());
            });
        }
    });

    // The server's go-ahead: exactly the messages it checked ("*" for a whole folder).
    rcmail.addEventListener('plugin.epesi_archive_move', function (data) {
        archiving = true;
        rcmail.move_messages(rcmail.env.epesi_archive_folder, null, data.uids === '*' ? undefined : String(data.uids).split(','));
    });

    rcmail.addEventListener('responseaftermove', function () {
        if (archiving) {
            archiving = false;
            tellEpesi('archived');
        }
    });
})();
