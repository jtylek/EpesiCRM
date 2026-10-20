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
 * The global modal defaults from AppServiceProvider::styleFormModalButtons():
 * Create/Edit modals get the pages' Save and Cancel buttons, and every modal
 * keeps its buttons at the bottom, where Filament puts them.
 */
class ModalButtonsTest extends TestCase
{
    use RefreshDatabase, SignsInUsers;

    public function test_form_modals_keep_their_buttons_at_the_bottom(): void
    {
        $this->actingAs($this->userWithRole('employee'));
        $contact = Contact::create(['last_name' => 'Smith', 'first_name' => 'Ann']);
        $notes = fn (): Testable => Livewire::test(NotesRelationManager::class, ['ownerRecord' => $contact, 'pageClass' => ViewContact::class]);

        $create = $notes()->mountAction(TestAction::make('create')->table())->instance()->getMountedAction();
        $this->assertAtTheBottom($create);
        $this->assertSame(
            [['Save', 'success', Heroicon::OutlinedCheck], ['Cancel', 'gray', Heroicon::OutlinedXMark]],
            array_map(fn (Action $action): array => [$action->getLabel(), $action->getColor(), $action->getIcon()], array_values($create->getVisibleModalFooterActions())),
        );

        $notes()->callAction(TestAction::make('create')->table(), data: ['title' => 'Recap', 'note' => '<p>Went well</p>']);
        $note = Attachment::sole();

        $edit = $notes()->mountAction(TestAction::make('edit')->table($note))->instance()->getMountedAction();
        $this->assertAtTheBottom($edit);
        $this->assertSame('Save', $edit->getModalSubmitAction()->getLabel());

        $this->assertAtTheBottom($notes()->mountAction(TestAction::make('delete')->table($note))->instance()->getMountedAction());
    }

    private function assertAtTheBottom(Action $action): void
    {
        $this->assertStringNotContainsString('epesi-modal-actions-in-header', (string) $action->getExtraModalWindowAttributeBag()->get('class'), "{$action->getName()} marker class");
    }
}
