/*
 * Archive plugin script
 * @version @package_version@
 */

function rcmail_epesi_auto_archive(prop)
{
  // Elastic renders this toolbar button as an <a> (no .src image to swap, which is why
  // the old code was a no-op). Toggle a CSS class instead; archive.css dims it when OFF.
  var btn = $('#epesi_auto_archive_button');
  var enabled = !btn.hasClass('pressed');
  btn.toggleClass('pressed', enabled);

  // Fire-and-forget: just persist the toggle in the session. No busy/'loading' lock — this
  // is a lightweight state save, and locking the UI made rapid toggles hang on "loading"
  // (the server toggle branch returns no AJAX payload to release the lock).
  rcmail.http_post('plugin.epesi_archive', '_enabled_auto_archive='+(enabled?1:0));
}

function rcmail_epesi_archive(prop)
{
  if (!rcmail.env.uid && (!rcmail.message_list || !rcmail.message_list.get_selection().length))
    return;

  var uids = rcmail.env.uid ? rcmail.env.uid : rcmail.message_list.get_selection().join(',');

  var msgid = rcmail.set_busy(true, 'loading');
  rcmail.http_post('plugin.epesi_archive', '_uid='+uids+'&_mbox='+urlencode(rcmail.env.mailbox),msgid);
}

// callback for app-onload event
if (window.rcmail) {
  rcmail.addEventListener('init', function(evt) {

    // register command (directly enable in message view mode)
    rcmail.register_command('plugin.epesi_archive', rcmail_epesi_archive, (rcmail.env.uid && rcmail.env.mailbox != rcmail.env.archive_mailbox && rcmail.env.mailbox != rcmail.env.archive_sent_mailbox && rcmail.env.mailbox != rcmail.env.drafts_mailbox));

    // add event-listener to message list
    if (rcmail.message_list)
      rcmail.message_list.addEventListener('select', function(list){
        rcmail.enable_command('plugin.epesi_archive', (list.get_selection().length > 0 && rcmail.env.mailbox != rcmail.env.drafts_mailbox && rcmail.env.mailbox != rcmail.env.archive_mailbox && rcmail.env.mailbox != rcmail.env.archive_sent_mailbox));
      });

    // set css style for archive folder
    var li;
    if (rcmail.env.archive_mailbox && (li = rcmail.get_folder_li(rcmail.env.archive_mailbox, '', true)))
      $(li).addClass('archive');

    if (rcmail.env.archive_sent_mailbox && (li = rcmail.get_folder_li(rcmail.env.archive_sent_mailbox,'',true)))
      $(li).addClass('archive');

    // add archive button to compose window
    if(rcmail.gui_objects.messageform) {
        rcmail.register_command('plugin.epesi_auto_archive', rcmail_epesi_auto_archive, true);
        rcmail.env.compose_commands.push('plugin.epesi_auto_archive');
    }

  })
}

