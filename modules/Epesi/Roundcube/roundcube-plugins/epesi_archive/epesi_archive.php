<?php

require_once __DIR__.'/epesi_archive_matcher.php';

/**
 * Files mail into Epesi's archive — the port of Epesi's epesi_archive plugin.
 *
 * The Archive button first asks this plugin (plugin.epesi_archive) whether
 * each selected message is already archived, and whether its From, To or Cc
 * belongs to a contact or company. If one doesn't, it warns once, as Epesi
 * did: clicking Archive again archives it anyway. Then it hands the folder
 * and UIDs to the Mailbox page around this frame, which archives each message
 * from that folder (MailFetcher::archiveFrom(), MailArchiver: links,
 * threads, attachments into file storage, de-duplication), moves the
 * archived ones into the account's archive folder, and tells this frame to
 * refresh. Nothing is archived without that click (AI-shared/Mail-archiving.md).
 *
 * A compose toggle saves the sent copy into the archive folder, and the page
 * archives it from there by Message-ID.
 */
class epesi_archive extends rcube_plugin
{
    /** Each message is read and stored while the user waits: MailFetcher::MAX_PER_CLICK. */
    public const MAX_PER_CLICK = 200;

    public $task = 'login|mail';

    public function init()
    {
        $this->add_hook('storage_init', [$this, 'storage_init']);
        $this->add_hook('login_after', [$this, 'ensure_folder']);
        $this->add_hook('message_sent', [$this, 'message_sent']);
        $this->register_action('plugin.epesi_archive', [$this, 'check_and_move']);

        $rcmail = rcmail::get_instance();
        $folder = $this->folder();

        if ($rcmail->task !== 'mail' || $folder === null || ! in_array($rcmail->action, ['', 'show', 'compose'], true)) {
            return;
        }

        // Epesi translates the labels and sends them with the login, rather
        // than localization/*.inc files, which a module archive may not contain.
        $rcmail->load_language($_SESSION['language'] ?? null, [
            'epesi_archive.archive' => $this->label('archive', 'Archive'),
            'epesi_archive.archivetitle' => $this->label('archive_title', 'Archive to the CRM'),
            'epesi_archive.archivesenttitle' => $this->label('archive_sent_title', 'Archive this message after sending'),
        ]);

        $this->include_script('epesi_archive.js');
        $this->include_stylesheet('epesi_archive.css');
        $rcmail->output->set_env('epesi_archive_folder', $folder);

        if ($rcmail->action === 'compose') {
            $rcmail->output->set_env('epesi_archive_on_sending', (bool) ($_SESSION['epesi']['archive_on_sending'] ?? false));
            $this->add_button($this->button('plugin.epesi_archive_toggle', 'archivesenttitle'), 'toolbar');

            return;
        }

        $this->add_button($this->button('plugin.epesi_archive', 'archivetitle'), 'toolbar');

        if (! empty($_SESSION['epesi_archive_fetch'])) {
            $rcmail->output->set_env('epesi_archive_fetch', array_values((array) $_SESSION['epesi_archive_fetch']));
            unset($_SESSION['epesi_archive_fetch']);
        }
    }

    /**
     * Fetch Message-ID with the other headers: without it Roundcube gives each
     * message "mid:<position>", and the already-archived check compared those.
     */
    public function storage_init($args)
    {
        $args['fetch_headers'] = epesi_archive_matcher::fetch_headers($args['fetch_headers'] ?? '');

        return $args;
    }

    /** Create and subscribe the archive folder, as Epesi did on first use. */
    public function ensure_folder($args)
    {
        $folder = $this->folder();

        if ($folder !== null) {
            $storage = rcmail::get_instance()->get_storage();

            if (! $storage->folder_exists($folder)) {
                $storage->create_folder($folder, true);
            } elseif (! $storage->folder_exists($folder, true)) {
                $storage->subscribe($folder);
            }
        }

        return $args;
    }

    /**
     * A copy saved into the archive folder is archived on the next page load,
     * after this request has stored it.
     */
    public function message_sent($args)
    {
        $target = rcube_utils::get_input_string('_store_target', rcube_utils::INPUT_POST, true);
        $messageId = (string) ($args['headers']['Message-ID'] ?? '');

        if ($target !== '' && $target === $this->folder() && $messageId !== '') {
            $_SESSION['epesi_archive_fetch'] = [...(array) ($_SESSION['epesi_archive_fetch'] ?? []), $messageId];
        }

        return $args;
    }

    /**
     * The Archive button's request, Epesi's checks in Epesi's order:
     *
     * 1. A message that is already archived, by anyone, needs no contact
     *    check: it goes on as it is. The archive keeps one copy; the Mailbox
     *    page links it to whoever archives it, moves it and says "Message
     *    already archived".
     * 2. A message that would be linked to no contact or company is held back
     *    once with a warning; the next click archives it anyway.
     * 3. The rest go to the Mailbox page, which archives them and then
     *    moves them to the archive folder.
     */
    public function check_and_move()
    {
        $rcmail = rcmail::get_instance();
        $folder = $this->folder();
        $mbox = (string) rcube_utils::get_input_string('_mbox', rcube_utils::INPUT_POST, true);
        $uids = (string) rcube_utils::get_input_string('_uid', rcube_utils::INPUT_POST);

        if ($folder === null || $mbox === '' || $mbox === $folder || $mbox === (string) $rcmail->config->get('drafts_mbox')) {
            $rcmail->output->show_message($this->label('invalid_folder', 'Cannot move to archive from this folder'), 'error');
            $rcmail->output->send();
        }

        $storage = $rcmail->get_storage();
        $list = $uids === '*' ? $storage->index($mbox)->get() : array_filter(explode(',', $uids));
        $db = $rcmail->get_dbh();
        $selectOne = function (string $sql, array $params) use ($db): ?array {
            $row = $db->fetch_assoc($db->query($sql, ...$params));

            return $row ?: null;
        };

        if (count($list) > self::MAX_PER_CLICK) {
            $rcmail->output->show_message(str_replace(':count', (string) self::MAX_PER_CLICK,
                $this->label('too_many', 'Select at most :count messages to archive at once')), 'error');
            $rcmail->output->send();
        }

        $move = [];
        $unmatched = [];

        foreach ($storage->fetch_headers($mbox, $list, false) as $header) {
            if (epesi_archive_matcher::archived([(string) $header->messageID], $selectOne) !== []) {
                $move[] = $header->uid;

                continue;
            }

            $emails = [];

            foreach ([$header->from, $header->to, $header->cc] as $field) {
                foreach (rcube_mime::decode_address_list((string) $field, null, true, $header->charset) as $address) {
                    $emails[] = (string) ($address['mailto'] ?? '');
                }
            }

            if (! epesi_archive_matcher::found($emails, $selectOne)) {
                $unmatched[] = $mbox.'/'.$header->uid;
            }

            $move[] = $header->uid;
        }

        // Epesi's force_archive: a message warned about once is archived on
        // the next click. All of a selection's strays share one warning.
        $warned = (array) ($_SESSION['epesi_archive_warned'] ?? []);

        if (array_diff($unmatched, $warned) !== []) {
            $_SESSION['epesi_archive_warned'] = array_values(array_unique([...$warned, ...$unmatched]));
            $rcmail->output->show_message($this->label('contact_not_found',
                'Matching contact or company not found. Click again to force archive without contact association.'), 'warning', null, true, 10);
            $rcmail->output->send();
        }

        $_SESSION['epesi_archive_warned'] = array_values(array_diff($warned, $unmatched));

        if ($move !== []) {
            // Epesi archives them from this folder, then moves them to the
            // archive folder, and tells this page to refresh.
            $rcmail->output->command('plugin.epesi_archive_go', ['folder' => $mbox, 'uids' => array_map('intval', $move)]);
        }

        $rcmail->output->send();
    }

    /** A label Epesi translated and sent with the login; English until then. */
    private function label(string $key, string $english): string
    {
        return (string) ($_SESSION['epesi']['labels'][$key] ?? $english);
    }

    /**
     * @return array<string, mixed>
     */
    private function button(string $command, string $title): array
    {
        return [
            'type' => 'link',
            'command' => $command,
            'label' => 'archive',
            'title' => $title,
            'domain' => $this->ID,
            'class' => 'button archive disabled',
            'classact' => 'button archive',
            'innerclass' => 'inner',
        ];
    }

    /** The account's archive folder, in Roundcube's own UTF7-IMAP form. */
    private function folder(): ?string
    {
        $name = (string) ($_SESSION['epesi']['archive_folder'] ?? '');

        return $name === '' ? null : rcube_charset::convert($name, 'UTF-8', 'UTF7-IMAP');
    }
}
