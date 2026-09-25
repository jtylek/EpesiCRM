<?php

namespace Tests\Feature\Modules;

use Epesi\Modules\Shoutbox\Filament\Pages\Shoutbox;
use Epesi\Modules\Shoutbox\Filament\Widgets\ShoutboxWidget;
use Epesi\Modules\Shoutbox\Models\Message;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\SignsInUsers;
use Tests\TestCase;

class ShoutboxTest extends TestCase
{
    use RefreshDatabase, SignsInUsers;

    public function test_messages_are_posted_and_private_ones_stay_private(): void
    {
        $alice = $this->userWithRole('employee', ['name' => 'Alice']);
        $bob = $this->userWithRole('employee', ['name' => 'Bob']);
        $carol = $this->userWithRole('employee', ['name' => 'Carol']);

        $this->actingAs($alice);
        Livewire::test(ShoutboxWidget::class)
            ->set('message', 'Hello all')
            ->call('send')
            ->set('message', 'Psst Bob')
            ->set('to', $bob->id)
            ->call('send')
            ->assertSee('Hello all')
            ->assertSee('Psst Bob');

        $this->actingAs($bob);
        Livewire::test(ShoutboxWidget::class)->assertSee('Hello all')->assertSee('Psst Bob');

        $this->actingAs($carol);
        Livewire::test(ShoutboxWidget::class)->assertSee('Hello all')->assertDontSee('Psst Bob');
    }

    public function test_deactivated_users_are_not_offered_as_recipients(): void
    {
        $alice = $this->userWithRole('employee', ['name' => 'Alice']);
        $bob = $this->userWithRole('employee', ['name' => 'Bob']);
        $gone = $this->userWithRole('employee', ['name' => 'Gone', 'active' => false]);

        $this->actingAs($alice);
        Livewire::test(ShoutboxWidget::class)
            ->assertSee('To: Bob')
            ->assertDontSee('To: Gone')
            ->call('replyTo', $gone->id)
            ->assertSet('to', null)
            ->call('replyTo', $bob->id)
            ->assertSet('to', $bob->id)
            ->set('message', 'Psst')
            ->set('to', $gone->id)
            ->call('send')
            ->assertHasErrors('to');

        $this->assertSame(0, Message::count());
    }

    public function test_authors_can_delete_only_recent_messages(): void
    {
        $alice = $this->userWithRole('employee');
        $this->actingAs($alice);

        $recent = Message::create(['user_id' => $alice->id, 'message' => 'oops']);
        $old = Message::create(['user_id' => $alice->id, 'message' => 'history']);
        $old->forceFill(['created_at' => now()->subHour()])->save();

        Livewire::test(ShoutboxWidget::class)->call('delete', $recent->id);
        $this->assertTrue($recent->fresh()->deleted);

        Livewire::test(ShoutboxWidget::class)->call('delete', $old->id)->assertForbidden();
        $this->assertFalse($old->fresh()->deleted);
    }

    public function test_polling_redraws_only_when_the_shown_messages_or_their_times_change(): void
    {
        $alice = $this->userWithRole('employee', ['name' => 'Alice']);
        $bob = $this->userWithRole('employee', ['name' => 'Bob']);
        $this->actingAs($alice);
        $this->freezeSecond();

        $widget = Livewire::test(ShoutboxWidget::class);

        // A rename isn't a new message: the poll skips the redraw, so the old name stays.
        $bob->update(['name' => 'Robert']);
        $widget->call('poll')->assertDontSee('Robert');

        $shout = Message::create(['user_id' => $bob->id, 'message' => 'Hi all']);
        $widget->call('poll')->assertSee('Hi all')->assertSee('Robert');

        $shout->update(['deleted' => true]);
        $widget->call('poll')->assertDontSee('Hi all');

        // Sending draws your message; the next poll has nothing to add.
        $widget->set('message', 'Morning')->call('send')->assertSee('Morning');
        $bob->update(['name' => 'Bobby']);
        $widget->call('poll')->assertDontSee('Bobby');

        // Time passing redraws only when an "… ago" label moves on.
        $this->travel(2)->minutes();
        $widget->call('poll')->assertSee('2 minutes ago')->assertSee('Bobby');
        $bob->update(['name' => 'Bert']);
        $this->travel(20)->seconds();
        $widget->call('poll')->assertDontSee('Bert');
        $this->travel(40)->seconds();
        $widget->call('poll')->assertSee('3 minutes ago')->assertSee('Bert');
    }

    public function test_the_send_button_does_not_react_to_polling(): void
    {
        $this->actingAs($this->userWithRole('employee'));

        // Filament disables a button during every request of its component unless
        // it's given a target, so the poll would make it blink.
        Livewire::test(ShoutboxWidget::class)
            ->assertSeeHtml('wire:poll.10s="poll"')
            ->assertSeeHtml('wire:target="send"');
    }

    public function test_the_dashboard_shows_the_widget(): void
    {
        $this->actingAs($this->userWithRole('employee'));

        $this->get('/')->assertOk()->assertSeeLivewire(ShoutboxWidget::class);
    }

    public function test_the_page_lists_visible_messages_and_hides_deleted_ones(): void
    {
        $alice = $this->userWithRole('employee', ['name' => 'Alice']);
        $bob = $this->userWithRole('employee', ['name' => 'Bob']);
        $carol = $this->userWithRole('employee', ['name' => 'Carol']);

        $this->actingAs($alice);
        $public = Message::create(['user_id' => $alice->id, 'message' => 'Hello all']);
        $private = Message::create(['user_id' => $alice->id, 'to_user_id' => $bob->id, 'message' => 'Psst Bob']);
        $gone = Message::create(['user_id' => $alice->id, 'message' => 'oops']);
        $gone->update(['deleted' => true]);

        $this->get(Shoutbox::getUrl())->assertOk();

        Livewire::test(Shoutbox::class)
            ->assertCanSeeTableRecords([$public, $private])
            ->assertDontSee('oops');

        $this->actingAs($carol);
        Livewire::test(Shoutbox::class)
            ->assertSee('Hello all')
            ->assertDontSee('Psst Bob');
    }

    public function test_messages_are_posted_from_the_page(): void
    {
        $alice = $this->userWithRole('employee', ['name' => 'Alice']);
        $bob = $this->userWithRole('employee', ['name' => 'Bob']);
        $gone = $this->userWithRole('employee', ['name' => 'Gone', 'active' => false]);

        $this->actingAs($alice);
        $this->get(Shoutbox::getUrl())->assertOk()->assertSee('To: Bob')->assertDontSee('To: Gone');

        $page = Livewire::test(Shoutbox::class)
            ->set('message', 'Hello all')
            ->call('send')
            ->assertSet('message', '')
            ->set('message', 'Psst Bob')
            ->set('to', $bob->id)
            ->call('send')
            ->assertSee('Hello all')
            ->assertSee('Psst Bob')
            ->set('message', '')
            ->call('send')
            ->assertHasErrors('message')
            ->set('message', 'Psst')
            ->set('to', $gone->id)
            ->call('send')
            ->assertHasErrors('to');

        $this->assertSame([null, $bob->id], Message::orderBy('id')->pluck('to_user_id')->all());
        $page->assertCanSeeTableRecords(Message::all());
    }

    public function test_the_page_redraws_only_when_the_messages_change(): void
    {
        $alice = $this->userWithRole('employee', ['name' => 'Alice']);
        $bob = $this->userWithRole('employee', ['name' => 'Bob']);
        $this->actingAs($alice);
        $this->freezeSecond();

        $page = Livewire::test(Shoutbox::class)->assertSeeHtml('wire:poll.10s="poll"');

        // A rename isn't a new message: the poll skips the redraw, so the old name stays.
        $bob->update(['name' => 'Robert']);
        $page->call('poll')->assertDontSee('To: Robert');

        $shout = Message::create(['user_id' => $bob->id, 'message' => 'Hi all']);
        $page->call('poll')->assertSee('Hi all')->assertSee('To: Robert');

        $shout->update(['deleted' => true]);
        $page->call('poll')->assertDontSee('Hi all');

        // Sending draws your message; the next poll has nothing to add.
        $page->set('message', 'Morning')->call('send')->assertSee('Morning');
        $bob->update(['name' => 'Bobby']);
        $page->call('poll')->assertDontSee('To: Bobby');

        // The dates on the page don't move, but your trash icon goes once the delete window closes.
        $this->travel(Message::DELETE_WINDOW_MINUTES - 1)->minutes();
        $page->call('poll')->assertDontSee('To: Bobby');
        $this->travel(2)->minutes();
        $page->call('poll')->assertSee('To: Bobby');
    }

    public function test_the_page_lets_only_the_author_delete_a_recent_message(): void
    {
        $alice = $this->userWithRole('employee');
        $bob = $this->userWithRole('employee');

        $this->actingAs($alice);
        $shout = Message::create(['user_id' => $alice->id, 'message' => 'oops']);

        $this->actingAs($bob);
        Livewire::test(Shoutbox::class)->assertTableActionHidden('delete', $shout);

        $this->actingAs($alice);
        Livewire::test(Shoutbox::class)
            ->callTableAction('delete', $shout)
            ->assertCanNotSeeTableRecords([$shout->fresh()]);
    }
}
