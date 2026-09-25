<?php

namespace Tests\Feature;

use Epesi\Modules\Attachments\Filament\RelationManagers\NotesRelationManager;
use Epesi\Modules\Attachments\Models\Attachment;
use Epesi\Modules\CRM\Contacts\Filament\Resources\Contacts\Pages\ViewContact;
use Epesi\Modules\CRM\Contacts\Models\Contact;
use Filament\Actions\Action;
use Filament\Actions\Testing\TestAction;
use Filament\Support\Icons\Heroicon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\Concerns\SignsInUsers;
use Tests\TestCase;

/**
 * The global modal default from AppServiceProvider::putModalActionsOnHeadingLine():
 * form modals get their actions on the heading line, confirmations don't.
 */
class ModalHeaderActionsTest extends TestCase
{
    use RefreshDatabase, SignsInUsers;

    public function test_form_modals_put_their_actions_on_the_heading_line(): void
    {
        $this->actingAs($this->userWithRole('employee'));
        $contact = Contact::create(['last_name' => 'Smith', 'first_name' => 'Ann']);
        $notes = fn (): Testable => Livewire::test(NotesRelationManager::class, ['ownerRecord' => $contact, 'pageClass' => ViewContact::class]);

        $create = $notes()->mountAction(TestAction::make('create')->table())->instance()->getMountedAction();
        $this->assertOnHeadingLine($create, true);
        $this->assertSame(
            [['Create', 'success', Heroicon::OutlinedCheck], ['Create & create another', 'gray', Heroicon::OutlinedPlus], ['Cancel', 'gray', Heroicon::OutlinedXMark]],
            array_map(fn (Action $action): array => [$action->getLabel(), $action->getColor(), $action->getIcon()], array_values($create->getVisibleModalFooterActions())),
        );

        $notes()->callAction(TestAction::make('create')->table(), data: ['title' => 'Recap', 'note' => '<p>Went well</p>']);
        $note = Attachment::sole();

        $edit = $notes()->mountAction(TestAction::make('edit')->table($note))->instance()->getMountedAction();
        $this->assertOnHeadingLine($edit, true);
        $this->assertSame('Save', $edit->getModalSubmitAction()->getLabel());

        $delete = $notes()->mountAction(TestAction::make('delete')->table($note))->instance()->getMountedAction();
        $this->assertOnHeadingLine($delete, false);
    }

    private function assertOnHeadingLine(Action $action, bool $expected): void
    {
        $this->assertSame($expected, str_contains((string) $action->getExtraModalWindowAttributeBag()->get('class'), 'epesi-modal-actions-in-header'), "{$action->getName()} marker class");
        $this->assertSame(! $expected, $action->hasModalCloseButton(), "{$action->getName()} close button");
    }
}
