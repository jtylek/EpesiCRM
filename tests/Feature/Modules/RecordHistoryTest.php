<?php

namespace Tests\Feature\Modules;

use App\Enums\RecordPermission;
use App\Enums\RecordPriority;
use App\Enums\RecordStatus;
use App\Models\User;
use App\Services\FileStorage;
use Epesi\Modules\Attachments\Filament\Resources\Attachments\AttachmentResource;
use Epesi\Modules\Attachments\Filament\Resources\Attachments\Pages\ViewAttachment;
use Epesi\Modules\Attachments\Models\Attachment;
use Epesi\Modules\CRM\Contacts\Models\Contact;
use Epesi\Modules\CRM\PhoneCalls\Filament\Resources\PhoneCalls\Pages\ViewPhoneCall;
use Epesi\Modules\CRM\PhoneCalls\Models\PhoneCall;
use Epesi\Modules\RecordBrowser\Filament\RelationManagers\HistoryRelationManager;
use Epesi\Modules\RecordBrowser\History\TextDiff;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Concerns\SignsInUsers;
use Tests\TestCase;

/**
 * The History addon reads like the record: field labels and the values the
 * View page shows, not columns and what they store — Epesi's edit history.
 */
class RecordHistoryTest extends TestCase
{
    use RefreshDatabase, SignsInUsers;

    public function test_changes_show_labels_and_values_not_columns_and_keys(): void
    {
        $this->actingAs($this->userWithRole('employee'));
        Filament::setCurrentPanel(Filament::getPanel('main'));

        $ann = Contact::create(['first_name' => 'Ann', 'last_name' => 'Buyer', 'permission' => RecordPermission::Public]);
        $call = PhoneCall::create([
            'subject' => 'Offer',
            'called_at' => '2026-10-01 10:00:00',
            'contact_id' => $ann->id,
            'status' => RecordStatus::Open,
            'priority' => RecordPriority::Medium,
            'permission' => RecordPermission::Public,
        ]);
        $call->update([
            'status' => RecordStatus::InProgress,
            'priority' => RecordPriority::Low,
            'permission' => RecordPermission::PublicReadOnly,
        ]);

        Livewire::test(HistoryRelationManager::class, ['ownerRecord' => $call, 'pageClass' => ViewPhoneCall::class])
            ->assertSeeText('Status: Open → In Progress')
            ->assertSeeText('Priority: Medium → Low')
            ->assertSeeText('Permission: Public → Public, Read-Only')
            ->assertDontSeeText('status: 0 → 1')
            // Marked like a diff: the old value red, the new one green.
            ->assertSeeHtml('Status: <span class="epesi-history-old">Open</span> → <span class="epesi-history-new">In Progress</span>')
            // Created: the new values alone, no red "-" before each.
            ->assertSeeHtml('Contact: <span class="epesi-history-new">Ann Buyer</span>')
            ->assertSeeHtml('Other Customer (not in system): <span class="epesi-history-new">No</span>')
            ->assertSeeHtml('Subject: <span class="epesi-history-new">Offer</span>')
            ->assertDontSeeText('Subject: - →');
    }

    /**
     * Notes/Attachments isn't built on the engine — its own RichEditor and a
     * plain Filament resource — so this exercises the historyFields() hook
     * that hands History its field labels and value formatting anyway.
     */
    public function test_note_history_shows_plain_text_and_labels_not_raw_html_or_stored_codes(): void
    {
        $this->actingAs($this->userWithRole('employee'));
        Filament::setCurrentPanel(Filament::getPanel('main'));

        $note = Attachment::create([
            'title' => 'Test',
            'note' => '<p>First draft</p>',
            'permission' => RecordPermission::Private,
            'sticky' => false,
        ]);
        $note->update([
            'note' => '<p>This is a test</p>',
            'permission' => RecordPermission::Public,
            'sticky' => true,
        ]);

        Livewire::test(HistoryRelationManager::class, ['ownerRecord' => $note, 'pageClass' => ViewAttachment::class])
            // Long text is a word diff, not "old → new" (see TextDiff).
            ->assertSeeHtml('Note: <del class="epesi-history-old">First draft</del> <ins class="epesi-history-new">This is a test</ins>')
            ->assertSeeText('Permission: Private → Public')
            ->assertSeeText('Sticky: No → Yes')
            ->assertDontSeeHtml('&lt;p&gt;');
    }

    public function test_a_text_diff_keeps_a_few_words_around_each_change(): void
    {
        $gap = '<span class="epesi-history-gap">…</span>';

        // Eight unchanged words on each side of the change, "…" for the rest.
        $this->assertSame(
            "{$gap} ".self::words('a', 13, 20).' <del class="epesi-history-old">old</del> <ins class="epesi-history-new">new</ins> '.self::words('b', 1, 8)." {$gap}",
            TextDiff::render(self::words('a', 1, 20).' old '.self::words('b', 1, 30), self::words('a', 1, 20).' new '.self::words('b', 1, 30)),
        );

        // Between two changes: eight after the first, eight before the next.
        $between = TextDiff::render('x '.self::words('b', 1, 30).' y', 'X '.self::words('b', 1, 30).' Y');
        $this->assertStringContainsString(self::words('b', 1, 8)." {$gap} ".self::words('b', 23, 30), (string) $between);
        $this->assertStringNotContainsString(' b15 ', (string) $between);

        // The Show modal's whole diff leaves nothing out.
        $this->assertStringNotContainsString($gap, (string) TextDiff::render('x '.self::words('b', 1, 30).' y', 'X '.self::words('b', 1, 30).' Y', whole: true));

        // A couple of words would be no saving: kept rather than cut.
        $this->assertSame(
            self::words('a', 1, 10).' <del class="epesi-history-old">old</del> <ins class="epesi-history-new">new</ins>',
            TextDiff::render(self::words('a', 1, 10).' old', self::words('a', 1, 10).' new'),
        );
    }

    public function test_a_text_diff_reads_as_whole_changes_lines_and_escaped_words(): void
    {
        // One word kept between two changes joins them into one.
        $this->assertSame(
            '<del class="epesi-history-old">red and blue</del> <ins class="epesi-history-new">green and yellow</ins>',
            TextDiff::render('red and blue', 'green and yellow'),
        );

        // Paragraphs stay apart, in the plain text and in the diff.
        $this->assertSame("inline.\nRemoving it", TextDiff::plainText('<p>inline.</p><p>Removing&nbsp;it</p>'));
        $this->assertSame(
            'inline.<br><del class="epesi-history-old">Removing</del> <ins class="epesi-history-new">Dropping</ins> it',
            TextDiff::render("inline.\nRemoving it", "inline.\nDropping it"),
        );

        // A paragraph added is a line break, then the paragraph on green —
        // not a "¶" among the added words.
        $this->assertSame(
            'a<br><ins class="epesi-history-new">b c</ins><br>d',
            TextDiff::render("a\nd", "a\nb c\nd"),
        );

        // An image is a word, so adding one is a change, not formatting.
        $this->assertSame(
            'Call the bank<br><ins class="epesi-history-new">[image]</ins>',
            TextDiff::render(TextDiff::plainText('<p>Call the bank</p>'), TextDiff::plainText('<p>Call the bank</p><p><img src="x.png"></p>')),
        );

        // Words are text: markup typed into a note shows as typed.
        $this->assertSame(
            'a <del class="epesi-history-old">&lt;b&gt;</del> <ins class="epesi-history-new">&lt;script&gt;</ins>',
            TextDiff::render('a <b>', 'a <script>'),
        );

        // Nothing to show when the words are the same.
        $this->assertNull(TextDiff::render('same  text', "same text\n"));

        // A long insertion is cut short in the table, whole in the modal.
        $this->assertSame(
            '<ins class="epesi-history-new">'.self::words('w', 1, 30).' <span class="epesi-history-gap">…</span></ins>',
            TextDiff::render('', self::words('w', 1, 40)),
        );
        $this->assertStringContainsString('w40', (string) TextDiff::render('', self::words('w', 1, 40), whole: true));

        // A blank line (a paragraph break) is a gap, not an ordinary line
        // break: it comes back as two <br>, not one, and however many blank
        // lines were there, never more than two.
        $this->assertSame("a\n\nb", TextDiff::normalize("a\n\n\n\nb"));
        $this->assertSame(
            'a<br><br><ins class="epesi-history-new">b</ins>',
            TextDiff::render('a', "a\n\nb"),
        );
    }

    /**
     * The Show modal's version of a rich-text note keeps its formatting
     * throughout — for the words that changed too, marked with <ins>/<del>
     * as usual (see test_show_has_the_whole_change_for_an_edit…), not just
     * the ones around them.
     */
    public function test_show_keeps_a_rich_notes_formatting_for_what_did_not_change(): void
    {
        $this->assertSame(
            'Call the <strong>bank</strong> today <ins class="epesi-history-new">and tomorrow</ins>',
            TextDiff::renderRich('<p>Call the <strong>bank</strong> today</p>', '<p>Call the <strong>bank</strong> today and tomorrow</p>'),
        );

        // A link stays a link around the change.
        $this->assertSame(
            'See <a href="https://x.test">the site</a> <del class="epesi-history-old">now</del> <ins class="epesi-history-new">later</ins>',
            TextDiff::renderRich('<p>See <a href="https://x.test">the site</a> now</p>', '<p>See <a href="https://x.test">the site</a> later</p>'),
        );

        // Still null on a formatting-only edit.
        $this->assertNull(TextDiff::renderRich('<p>Call the bank</p>', '<p>Call the <strong>bank</strong></p>'));

        // Two separate paragraphs are a gap too, not an ordinary line break:
        // the same spacing sanitizeHtml() gives them on the View page and
        // the "As created" side of this same modal.
        $this->assertSame(
            'First. <ins class="epesi-history-new"><br> <br>Second.</ins>',
            TextDiff::renderRich('<p>First.</p>', '<p>First.</p><p>Second.</p>'),
        );

        // A word right against a closing tag ("<span>s" for the plural) is
        // one written word, not a formatted one and a plain one with a gap
        // between — a diff must not add a space that was never there.
        $this->assertSame(
            'The bar <code>&lt;span&gt;</code>s and a <code>&lt;td&gt;</code>: <del class="epesi-history-old">old</del> <ins class="epesi-history-new">new</ins>',
            TextDiff::renderRich(
                '<p>The bar <code>&lt;span&gt;</code>s and a <code>&lt;td&gt;</code>: old</p>',
                '<p>The bar <code>&lt;span&gt;</code>s and a <code>&lt;td&gt;</code>: new</p>',
            ),
        );

        // A blank line added within a paragraph (the editor's own way of
        // writing one) is a gap here too, not a "¶" among the added words.
        $this->assertSame(
            'Noted. <ins class="epesi-history-new"><br> <br>More.</ins>',
            TextDiff::renderRich('<p>Noted.</p>', '<p>Noted.<br><br>More.</p>'),
        );
    }

    public function test_note_history_shows_the_words_that_changed_not_the_whole_note(): void
    {
        $this->actingAs($this->userWithRole('employee'));
        Filament::setCurrentPanel(Filament::getPanel('main'));

        $note = Attachment::create(['note' => '<p>'.self::words('a', 1, 40).'</p><p>'.self::words('b', 1, 30).'</p>', 'permission' => RecordPermission::Public]);
        $note->update(['note' => '<p>'.self::words('a', 1, 40).'</p><p>'.str_replace('b10 ', 'changed ', self::words('b', 1, 30)).'</p>']);

        Livewire::test(HistoryRelationManager::class, ['ownerRecord' => $note, 'pageClass' => ViewAttachment::class])
            ->assertSeeHtml('b9 <del class="epesi-history-old">b10</del> <ins class="epesi-history-new">changed</ins> b11')
            // Created: the start of the note, not all of it.
            ->assertSeeHtml('Note: <ins class="epesi-history-new">'.self::words('a', 1, 30).' <span class="epesi-history-gap">…</span></ins>')
            // Far from the change, and past the start: in neither entry.
            ->assertDontSeeText('a35')
            ->assertDontSeeText('b25');
    }

    public function test_a_formatting_only_edit_says_so(): void
    {
        $this->actingAs($this->userWithRole('employee'));
        Filament::setCurrentPanel(Filament::getPanel('main'));

        $note = Attachment::create(['note' => '<p>Call the bank</p>', 'permission' => RecordPermission::Public]);
        $note->update(['note' => '<p>Call the <strong>bank</strong></p>']);

        Livewire::test(HistoryRelationManager::class, ['ownerRecord' => $note, 'pageClass' => ViewAttachment::class])
            ->assertSeeText('Note: Only the formatting changed');
    }

    public function test_note_history_names_the_files_added_and_removed(): void
    {
        $this->actingAs($this->userWithRole('employee'));
        Filament::setCurrentPanel(Filament::getPanel('main'));

        $storage = app(FileStorage::class);
        [$draft, $kept, $final] = [$storage->put('draft', 'draft.txt'), $storage->put('kept', 'kept.txt'), $storage->put('final', 'final.txt')];

        $note = Attachment::create(['note' => 'Contract', 'files' => [$draft->id, $kept->id], 'permission' => RecordPermission::Public]);
        $note->update(['files' => [$kept->id, $final->id]]);

        // Taken off the note, the file is gone: its name comes from the log.
        $this->assertModelMissing($draft);

        Livewire::test(HistoryRelationManager::class, ['ownerRecord' => $note, 'pageClass' => ViewAttachment::class])
            ->assertSeeHtml('Files: <del class="epesi-history-old">− draft.txt</del> <ins class="epesi-history-new">+ final.txt</ins>')
            ->assertSeeHtml('Files: <ins class="epesi-history-new">+ draft.txt</ins> <ins class="epesi-history-new">+ kept.txt</ins>');

        // An entry from before names were logged: ids, looked up where they can be.
        $this->assertSame(
            '<del class="epesi-history-old">− deleted file</del> <ins class="epesi-history-new">+ final.txt</ins>',
            AttachmentResource::historyFileChanges([$draft->id, $kept->id], [$kept->id, $final->id], [])?->toHtml(),
        );
    }

    public function test_show_has_the_whole_change_for_an_edit_and_the_note_as_it_read_when_created(): void
    {
        $this->actingAs($this->userWithRole('employee'));
        Filament::setCurrentPanel(Filament::getPanel('main'));

        $note = Attachment::create(['note' => '<p>'.self::words('a', 1, 40).'</p>', 'permission' => RecordPermission::Private]);
        $note->update(['note' => '<p>'.self::words('a', 1, 40).' and <strong>more</strong></p>']);
        $note->update(['permission' => RecordPermission::Public]);

        [$permission, $edit, $created] = $note->activities()->latest('id')->take(3)->get()->all();

        // An edit shows only the diff — the words that changed — not the note in full.
        Livewire::test(HistoryRelationManager::class, ['ownerRecord' => $note, 'pageClass' => ViewAttachment::class])
            ->assertActionHidden(TestAction::make('show')->table($permission))
            ->mountAction(TestAction::make('show')->table($edit))
            ->assertMountedActionModalSee('a1 a2 a3')
            // The added word keeps its own formatting too, not just the
            // unchanged words around it.
            ->assertMountedActionModalSeeHtml('<ins class="epesi-history-new">and <strong>more</strong></ins>')
            ->assertMountedActionModalDontSee('After this edit');

        // There is no earlier version to diff against when the record was created,
        // so that entry shows the note in full instead.
        Livewire::test(HistoryRelationManager::class, ['ownerRecord' => $note, 'pageClass' => ViewAttachment::class])
            ->mountAction(TestAction::make('show')->table($created))
            ->assertMountedActionModalSee('As created')
            ->assertMountedActionModalSee('a1 a2 a3');
    }

    public function test_restore_puts_a_version_back_as_a_logged_edit(): void
    {
        $this->actingAs($this->userWithRole('employee'));
        Filament::setCurrentPanel(Filament::getPanel('main'));

        $note = Attachment::create(['note' => '<p>First draft</p>', 'permission' => RecordPermission::Public]);
        $note->update(['note' => '<p>Second draft</p>']);
        [$edit, $created] = $note->activities()->latest('id')->get()->all();

        $history = fn () => Livewire::test(HistoryRelationManager::class, ['ownerRecord' => $note, 'pageClass' => ViewAttachment::class]);

        // Not offered where the version is the text the note has now.
        $history()->mountAction(TestAction::make('show')->table($edit))
            ->assertMountedActionModalDontSee('Restore this version');

        $history()->mountAction(TestAction::make('show')->table($created))
            ->assertMountedActionModalSee('Restore this version');

        $history()->callAction(TestAction::make('show')->table($created)->arguments(['restore' => 'note']))
            ->assertNotified('Version restored')
            ->assertDispatched('refresh-page');

        $this->assertSame('<p>First draft</p>', $note->fresh()->note);
        // An edit like any other: what it replaced can be restored in turn.
        $this->assertSame('<p>Second draft</p>', $note->activities()->latest('id')->first()->properties['old']['note']);
    }

    public function test_restore_is_only_for_someone_who_may_edit_the_record(): void
    {
        $this->actingAs($this->userWithRole('employee'));
        Filament::setCurrentPanel(Filament::getPanel('main'));

        $note = Attachment::create(['note' => '<p>First draft</p>', 'permission' => RecordPermission::PublicReadOnly]);
        $note->update(['note' => '<p>Second draft</p>']);
        $created = $note->activities()->where('event', 'created')->sole();

        // Read-only to a colleague: the history, not Restore — and asking
        // for it anyway changes nothing.
        $this->actingAs($this->userWithRole('employee'));

        Livewire::test(HistoryRelationManager::class, ['ownerRecord' => $note, 'pageClass' => ViewAttachment::class])
            ->mountAction(TestAction::make('show')->table($created))
            ->assertMountedActionModalDontSee('Restore this version');

        Livewire::test(HistoryRelationManager::class, ['ownerRecord' => $note, 'pageClass' => ViewAttachment::class])
            ->callAction(TestAction::make('show')->table($created)->arguments(['restore' => 'note']))
            ->assertNotNotified('Version restored');

        $this->assertSame('<p>Second draft</p>', $note->fresh()->note);
    }

    private static function words(string $prefix, int $from, int $to): string
    {
        return implode(' ', array_map(fn (int $i): string => $prefix.$i, range($from, $to)));
    }

    public function test_history_imported_under_class_names_is_found_again(): void
    {
        $user = $this->userWithRole('employee');
        $this->actingAs($user);

        $call = PhoneCall::create(['subject' => 'Offer', 'called_at' => '2026-10-01 10:00:00', 'permission' => RecordPermission::Public]);
        $call->update(['subject' => 'Offer sent']);

        // As import:legacy wrote it before it used the aliases. Only the
        // call's own rows: creating the user above is logged too.
        DB::table('activity_log')->where('subject_type', $call->getMorphClass())->update(['subject_type' => PhoneCall::class, 'causer_type' => User::class]);
        $this->assertSame(0, $call->activities()->count());

        (require base_path('database/migrations/2026_09_27_030000_map_module_morph_types_to_aliases.php'))->up();

        $this->assertSame(2, $call->activities()->count());
        $this->assertTrue($call->activities()->first()->causer->is($user));
    }
}
