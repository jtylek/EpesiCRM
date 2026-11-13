<?php

namespace Tests\Unit;

use epesi_sso_folders;
use PHPUnit\Framework\TestCase;

require_once __DIR__.'/../../modules/Epesi/Roundcube/roundcube-plugins/epesi_sso/epesi_sso_folders.php';

/** The spam folder Mark as junk uses on a server without the SPECIAL-USE \Junk flag. */
class RoundcubeJunkFolderTest extends TestCase
{
    public function test_a_top_level_spam_folder_is_found_in_any_case(): void
    {
        $this->assertSame('SPAM', epesi_sso_folders::junk_folder(['INBOX', 'Sent', 'SPAM', 'Trash'], '/'));
        $this->assertSame('Junk E-mail', epesi_sso_folders::junk_folder(['INBOX', 'Junk E-mail'], '/'));
    }

    public function test_courier_folders_under_inbox_are_found(): void
    {
        $this->assertSame('INBOX.spam', epesi_sso_folders::junk_folder(['INBOX', 'INBOX.Sent', 'INBOX.spam'], '.'));
    }

    public function test_junk_beats_spam_and_top_level_beats_inbox(): void
    {
        $this->assertSame('INBOX.Junk', epesi_sso_folders::junk_folder(['Spam', 'INBOX.Junk'], '.'));
        $this->assertSame('Junk', epesi_sso_folders::junk_folder(['INBOX.Junk', 'Junk'], '.'));
    }

    public function test_deeper_or_lookalike_folders_do_not_count(): void
    {
        $this->assertNull(epesi_sso_folders::junk_folder(['INBOX', 'Clients/Spam', 'Spam reports', 'Junkyard'], '/'));
        $this->assertNull(epesi_sso_folders::junk_folder(['INBOX', 'INBOX.Clients.Spam'], '.'));
        $this->assertNull(epesi_sso_folders::junk_folder([], '/'));
    }

    public function test_a_flat_server_without_a_delimiter(): void
    {
        $this->assertSame('Spam', epesi_sso_folders::junk_folder(['INBOX', 'Spam'], ''));
    }
}
