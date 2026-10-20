/**
 * The Archive button and the compose toggle (see epesi_archive.php). The
 * server first checks the selection and either warns or answers
 * plugin.epesi_archive_go with the folder and UIDs. The Epesi page around
 * this frame then archives those messages from that folder, moves them to
 * the archive folder on the mail server, and posts `refresh` back here.
 */
(function () {
    var lock = null,
        timer = null;

    function tellEpesi(data) {
        if (window.parent !== window) {
            window.parent.postMessage(data, window.location.origin);
        }
    }

    function done() {
        if (lock !== null) {
            rcmail.set_busy(false, null, lock);
            lock = null;
        }
        clearTimeout(timer);
    }

    // The archived messages left this folder: show the list without them.
    function refresh() {
        done();

        if (rcmail.env.action === 'show') {
            rcmail.list_mailbox(rcmail.env.mailbox);
            return;
        }

        rcmail.show_contentframe(false);
        rcmail.list_mailbox(rcmail.env.mailbox, rcmail.env.current_page);
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
            tellEpesi({ epesiRoundcube: 'archived', messageIds: rcmail.env.epesi_archive_fetch });
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

    // The server's go-ahead: exactly the messages it checked. Epesi archives
    // them first; only then are they moved, by Epesi, to the archive folder.
    rcmail.addEventListener('plugin.epesi_archive_go', function (data) {
        done();
        lock = rcmail.set_busy(true, 'loading');
        // Never stay busy if the page doesn't answer.
        timer = setTimeout(done, 120000);
        tellEpesi({ epesiRoundcube: 'archive', folder: String(data.folder), uids: data.uids || [] });
    });

    window.addEventListener('message', function (event) {
        if (event.origin !== window.location.origin || event.source !== window.parent) {
            return;
        }

        if (event.data && event.data.epesiRoundcube === 'refresh') {
            refresh();
        }

        if (event.data && event.data.epesiRoundcube === 'archive-done') {
            done();
        }
    });
})();
