<?php

require_once __DIR__.'/epesi_sso_ticket.php';

/**
 * Logs in to Roundcube with a ticket from Epesi's Mailbox page — the port of
 * Epesi's epesi_autologon and epesi_autorelogon plugins — and supplies the rest
 * of the account: its SMTP server, the identity, the global signature.
 *
 * There is no login without a ticket. Roundcube never shows its own login
 * form, so it can't be used as a second way in with a mail password.
 *
 * What the other epesi_* plugins need from the login is in $_SESSION['epesi'];
 * this plugin has to be listed before them.
 */
class epesi_sso extends rcube_plugin
{
    /** @var array<string, mixed>|null the ticket redeemed by this request */
    private $login;

    public function init()
    {
        $this->add_hook('startup', [$this, 'startup']);
        $this->add_hook('authenticate', [$this, 'authenticate']);
        $this->add_hook('login_after', [$this, 'login_after']);
        $this->add_hook('user_create', [$this, 'user_create']);
        $this->add_hook('identity_create', [$this, 'identity_create']);
        $this->add_hook('unauthenticated', [$this, 'unauthenticated']);
        $this->add_hook('smtp_connect', [$this, 'smtp_connect']);
        $this->add_hook('message_outgoing_body', [$this, 'add_signature']);
    }

    public function startup($args)
    {
        if (! isset($_GET['_epesi_ticket'])) {
            return $args;
        }

        // Every ticket is a fresh login, as Epesi logged in again each time
        // the mail screen opened: it may be another account, or another user.
        if (! empty($_SESSION['user_id'])) {
            $rcmail = rcmail::get_instance();
            $rcmail->logout_actions();
            $rcmail->kill_session();
        }

        $args['task'] = 'login';
        $args['action'] = 'login';

        return $args;
    }

    public function authenticate($args)
    {
        $token = (string) rcube_utils::get_input_string('_epesi_ticket', rcube_utils::INPUT_GET);
        $this->login = $token === '' ? null : $this->redeem($token);

        if (! $this->login) {
            $args['abort'] = true;

            return $args;
        }

        $args['user'] = $this->login['imap']['user'];
        $args['pass'] = $this->login['imap']['pass'];
        $args['host'] = $this->login['imap']['host'];
        // The ticket stands in for the login form's request token, and the
        // first request inside a new iframe may carry no cookie yet.
        $args['valid'] = true;
        $args['cookiecheck'] = false;

        return $args;
    }

    public function login_after($args)
    {
        if (! $this->login) {
            return $args;
        }

        $smtp = $this->login['smtp'] ?? null;

        if ($smtp) {
            $smtp['pass'] = rcmail::get_instance()->encrypt((string) $smtp['pass']);
        }

        $_SESSION['epesi'] = ['smtp' => $smtp] + array_diff_key($this->login, ['imap' => true, 'smtp' => true]);

        // The user's Epesi language, whatever Roundcube last remembered.
        if (! empty($this->login['language'])) {
            rcmail::get_instance()->load_language((string) $this->login['language']);
        }

        return $args;
    }

    public function user_create($args)
    {
        if ($this->login) {
            $args['user_name'] = (string) $this->login['name'];
            $args['user_email'] = (string) $this->login['email'];
        }

        return $args;
    }

    /** The first identity carries the account's signature from Epesi. */
    public function identity_create($args)
    {
        if ($this->login && ! empty($args['login']) && (string) $this->login['signature'] !== '') {
            $args['record']['signature'] = (string) $this->login['signature'];
            $args['record']['html_signature'] = 1;
        }

        return $args;
    }

    public function unauthenticated($args)
    {
        // A valid ticket the mail server turned down: say so, rather than ask
        // Epesi for another ticket that would fail the same way.
        $refused = $this->login !== null;
        $text = $refused
            ? (string) ($this->login['labels']['login_refused'] ?? 'Your mail server refused the login.')
            : 'Open Mailbox in Epesi to read your mail.';

        header('Content-Type: text/html; charset=UTF-8');
        header('Cache-Control: no-store');

        echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Mailbox</title><style>'
            .'body{margin:0;height:100vh;display:flex;align-items:center;justify-content:center;'
            .'font:0.875rem system-ui,sans-serif;color:#71717a;background:transparent}'
            .'</style></head><body><p>'.rcube::Q($text).'</p>'
            // The Mailbox page takes a frame without this (or Roundcube's own
            // rcmail) for one the web server didn't hand to Roundcube.
            .'<script>window.epesiRoundcube = true;'
            .($refused ? '' : 'if (window.parent !== window) {'
                .'window.parent.postMessage({epesiRoundcube: "login-required"}, window.location.origin);}')
            .'</script>'
            .'</body></html>';

        exit;
    }

    public function smtp_connect($args)
    {
        $smtp = $_SESSION['epesi']['smtp'] ?? null;

        if ($smtp) {
            $args['smtp_host'] = $smtp['host'];
            $args['smtp_user'] = $smtp['user'];
            $args['smtp_pass'] = $smtp['user'] === '' ? '' : rcmail::get_instance()->decrypt($smtp['pass']);
        }

        return $args;
    }

    /** Epesi's global signature goes under every message, as MailSender adds it. */
    public function add_signature($args)
    {
        $signature = (string) ($_SESSION['epesi']['global_signature'] ?? '');

        // The text alternative is converted from the HTML part, which already has it.
        if ($signature === '' || $args['type'] === 'alternative') {
            return $args;
        }

        $args['body'] .= $args['type'] === 'html'
            ? '<br><br>'.$signature
            : "\r\n\r\n".rcmail::get_instance()->html2text($signature, ['width' => 0]);

        return $args;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function redeem(string $token): ?array
    {
        $rcmail = rcmail::get_instance();
        $db = $rcmail->get_dbh();

        return epesi_sso_ticket::redeem(
            $token,
            (string) hex2bin((string) $rcmail->config->get('epesi_sso_key')),
            function (string $sql, array $params) use ($db): ?array {
                $row = $db->fetch_assoc($db->query($sql, ...$params));

                return $row ?: null;
            },
            fn (string $sql, array $params): int => (int) $db->affected_rows($db->query($sql, ...$params)),
        );
    }
}
