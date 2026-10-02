<?php

namespace Tests\Feature\Modules;

use Epesi\Modules\StickyNotes\Filament\Widgets\StickyNotesWidget;
use Epesi\Modules\StickyNotes\Models\StickyNote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\SignsInUsers;
use Tests\TestCase;

class StickyNotesTest extends TestCase
{
    use RefreshDatabase, SignsInUsers;

    public function test_a_note_is_added_with_a_title_a_body_and_a_color(): void
    {
        $user = $this->userWithRole('employee');
        $this->actingAs($user);

        Livewire::test(StickyNotesWidget::class)
            ->callAction('addNote', ['title' => 'Call Ann', 'body' => 'about the **offer**', 'color' => 'blue'])
            ->assertSee('Call Ann')
            ->assertSeeHtml('<strong>offer</strong>');

        $note = StickyNote::query()->sole();

        $this->assertSame($user->id, $note->user_id);
        $this->assertSame('blue', $note->color);
        $this->assertTrue($note->active);
    }

    public function test_a_note_needs_a_title(): void
    {
        $this->actingAs($this->userWithRole('employee'));

        Livewire::test(StickyNotesWidget::class)
            ->callAction('addNote', ['title' => '', 'color' => 'green'])
            ->assertHasActionErrors(['title' => 'required']);

        $this->assertSame(0, StickyNote::count());
    }

    public function test_the_body_cannot_inject_html(): void
    {
        $user = $this->userWithRole('employee');
        $this->actingAs($user);
        StickyNote::create(['user_id' => $user->id, 'title' => 'x', 'body' => "<script>alert(1)</script>\n\n[a](javascript:alert(1))", 'color' => 'red']);

        Livewire::test(StickyNotesWidget::class)
            ->assertDontSeeHtml('<script>alert(1)</script>')
            ->assertDontSeeHtml('javascript:');
    }

    public function test_a_note_is_edited(): void
    {
        $user = $this->userWithRole('employee');
        $this->actingAs($user);
        $note = StickyNote::create(['user_id' => $user->id, 'title' => 'Old', 'body' => 'old', 'color' => 'yellow']);

        Livewire::test(StickyNotesWidget::class)
            ->callAction('editNote', ['title' => 'New', 'body' => 'new', 'color' => 'red'], ['id' => $note->id]);

        $this->assertSame(['New', 'new', 'red'], [$note->fresh()->title, $note->fresh()->body, $note->fresh()->color]);
    }

    public function test_a_note_is_deactivated_listed_as_inactive_and_deleted_for_good(): void
    {
        $user = $this->userWithRole('employee');
        $this->actingAs($user);
        $note = StickyNote::create(['user_id' => $user->id, 'title' => 'Done soon', 'color' => 'green']);

        $widget = Livewire::test(StickyNotesWidget::class)
            ->assertSee('Done soon')
            ->callAction('setActive', arguments: ['id' => $note->id, 'active' => false])
            ->assertDontSee('Done soon')
            ->call('setStatus', 'inactive')
            ->assertSee('Done soon');

        $this->assertFalse($note->fresh()->active);

        $widget->callAction('deleteNote', arguments: ['id' => $note->id]);

        $this->assertNull(StickyNote::find($note->id));
    }

    public function test_an_active_note_cannot_be_deleted(): void
    {
        $user = $this->userWithRole('employee');
        $this->actingAs($user);
        $note = StickyNote::create(['user_id' => $user->id, 'title' => 'Keep', 'color' => 'green']);

        try {
            Livewire::test(StickyNotesWidget::class)->callAction('deleteNote', arguments: ['id' => $note->id]);
        } catch (\Throwable) {
            // The note isn't found among the inactive ones.
        }

        $this->assertNotNull(StickyNote::find($note->id));
    }

    public function test_notes_are_private_to_their_owner(): void
    {
        $alice = $this->userWithRole('employee');
        $bob = $this->userWithRole('employee');
        $note = StickyNote::create(['user_id' => $alice->id, 'title' => 'Alice only', 'color' => 'yellow']);

        $this->actingAs($bob);

        Livewire::test(StickyNotesWidget::class)->assertDontSee('Alice only');

        try {
            Livewire::test(StickyNotesWidget::class)->callAction('editNote', ['title' => 'Mine now', 'color' => 'red'], ['id' => $note->id]);
        } catch (\Throwable) {
            // Not found among Bob's notes.
        }

        $this->assertSame('Alice only', $note->fresh()->title);
    }

    public function test_notes_are_dragged_into_a_new_order_and_a_new_one_goes_first(): void
    {
        $user = $this->userWithRole('employee');
        $this->actingAs($user);
        [$a, $b, $c] = collect(['A', 'B', 'C'])->map(fn (string $title): StickyNote => StickyNote::create(['user_id' => $user->id, 'title' => $title, 'color' => 'grey']))->all();

        $widget = Livewire::test(StickyNotesWidget::class);

        $this->assertSame(['C', 'B', 'A'], $widget->instance()->notes()->pluck('title')->all());

        $widget->call('reorder', $a->id, 0);

        $this->assertSame(['A', 'C', 'B'], $widget->instance()->notes()->pluck('title')->all());

        $widget->callAction('addNote', ['title' => 'New', 'color' => 'green']);

        $this->assertSame(['New', 'A', 'C', 'B'], $widget->instance()->notes()->pluck('title')->all());
    }

    public function test_a_note_is_dragged_under_a_note_in_another_column(): void
    {
        $user = $this->userWithRole('employee');
        $this->actingAs($user);
        $a = StickyNote::create(['user_id' => $user->id, 'title' => 'A', 'color' => 'grey', 'col' => 0]);
        $b = StickyNote::create(['user_id' => $user->id, 'title' => 'B', 'color' => 'grey', 'col' => 1]);

        $widget = Livewire::test(StickyNotesWidget::class);

        // B goes into A's column, under it.
        $widget->call('reorder', $b->id, 1, '0');

        $columns = $widget->instance()->columns();

        $this->assertSame(['A', 'B'], $columns[0]->pluck('title')->all());
        $this->assertSame([], $columns[1]->pluck('title')->all());

        // And back out to the last column.
        $widget->call('reorder', $b->id, 0, '3');

        $this->assertSame(['B'], $widget->instance()->columns()[3]->pluck('title')->all());
    }

    public function test_a_note_cannot_be_dragged_by_someone_else(): void
    {
        $alice = $this->userWithRole('employee');
        $bob = $this->userWithRole('employee');
        $mine = StickyNote::create(['user_id' => $alice->id, 'title' => 'One', 'color' => 'grey']);
        StickyNote::create(['user_id' => $alice->id, 'title' => 'Two', 'color' => 'grey']);

        $this->actingAs($bob);
        Livewire::test(StickyNotesWidget::class)->call('reorder', $mine->id, 5);

        $this->assertSame(0, $mine->fresh()->position);
    }
}
