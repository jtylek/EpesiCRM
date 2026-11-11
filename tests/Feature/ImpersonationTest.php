<?php

namespace Tests\Feature;

use App\Filament\Administration\Resources\Users\Pages\ListUsers;
use App\Filament\Administration\Resources\Users\Pages\ViewUser;
use App\Filament\Administration\Resources\Users\UserResource;
use App\Filament\Portal\Pages\MyContact;
use App\Models\LoginAudit;
use App\Models\User;
use App\Support\Impersonation;
use Epesi\Modules\CRM\Contacts\Models\Contact;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;
use Tests\Concerns\SignsInUsers;
use Tests\TestCase;

/**
 * Administration → Users → "Log in as user": a super_admin works as another
 * account without its password, and the bar at the top of the page takes
 * them back.
 */
class ImpersonationTest extends TestCase
{
    use RefreshDatabase, SignsInUsers;

    protected User $admin;

    protected User $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->userWithRole('super_admin', ['name' => 'Admin']);
        // A password of their own: the session must stop checking the
        // administrator's once it belongs to Ann.
        $this->employee = $this->userWithRole('employee', ['name' => 'Ann', 'password' => 'another password']);

        $this->actingAs($this->admin);
        Filament::setCurrentPanel('administration');
    }

    public function test_a_super_admin_logs_in_as_a_user_from_the_view_page(): void
    {
        // A page first, so the session holds the administrator's password
        // hash, as AuthenticateSession keeps it.
        $this->get('/')->assertOk();

        Filament::setCurrentPanel('administration');
        Livewire::test(ViewUser::class, ['record' => $this->employee->getKey()])
            ->callAction('logInAs')
            ->assertRedirect(Filament::getPanel('main')->getUrl());

        $this->assertSame($this->employee->id, Auth::id());

        $this->get('/')
            ->assertOk()
            ->assertSee('You are logged in as Ann.')
            ->assertSee('Back to Admin');
        $this->assertSame($this->employee->id, Auth::id(), 'still Ann on the next page');

        $this->assertSame($this->admin->id, LoginAudit::query()->where('user_id', $this->employee->id)->sole()->impersonated_by);
        $this->assertNull(LoginAudit::query()->where('user_id', $this->admin->id)->sole()->impersonated_by);
    }

    public function test_and_from_the_list(): void
    {
        Livewire::test(ListUsers::class)
            ->callAction(TestAction::make('logInAs')->table($this->employee))
            ->assertRedirect(Filament::getPanel('main')->getUrl());

        $this->assertSame($this->employee->id, Auth::id());
    }

    public function test_not_as_yourself_nor_as_an_account_that_cannot_sign_in(): void
    {
        $inactive = $this->userWithRole('employee', ['active' => false]);
        $noRole = User::factory()->create();

        foreach ([$this->admin, $inactive, $noRole] as $user) {
            Livewire::test(ViewUser::class, ['record' => $user->getKey()])->assertActionHidden('logInAs');
        }

        Livewire::test(ViewUser::class, ['record' => $this->employee->getKey()])->assertActionVisible('logInAs');
    }

    public function test_only_a_super_admin_can(): void
    {
        $this->actingAs($this->userWithRole('manager'));

        $this->assertFalse(Impersonation::allowed($this->employee));
        $this->expectException(AuthorizationException::class);
        Impersonation::start($this->employee);
    }

    public function test_a_super_admin_logs_in_as_a_dealer_and_lands_in_the_dealer_portal(): void
    {
        $dealer = $this->portalUser('dealer', 'Simona');

        Livewire::test(ViewUser::class, ['record' => $dealer->getKey()])
            ->assertActionVisible('logInAs')
            ->callAction('logInAs')
            ->assertRedirect(Filament::getPanel('dealer')->getUrl());

        $this->assertSame($dealer->id, Auth::id());

        $this->get(MyContact::getUrl(panel: 'dealer'))
            ->assertOk()
            ->assertSee('You are logged in as Simona Alfa.')
            ->assertSee('Back to Admin');

        $this->assertSame($this->admin->id, LoginAudit::query()->where('user_id', $dealer->id)->sole()->impersonated_by);
    }

    public function test_and_as_a_customer_in_the_customer_portal(): void
    {
        $customer = $this->portalUser('customer', 'Carl');

        Livewire::test(ListUsers::class)
            ->callAction(TestAction::make('logInAs')->table($customer))
            ->assertRedirect(Filament::getPanel('portal')->getUrl());

        $this->assertSame($customer->id, Auth::id());

        $this->get(MyContact::getUrl(panel: 'portal'))
            ->assertOk()
            ->assertSee('You are logged in as Carl Alfa.')
            ->assertSee('Back to Admin');

        $this->assertSame($this->admin->id, LoginAudit::query()->where('user_id', $customer->id)->sole()->impersonated_by);
    }

    public function test_a_dealer_who_is_also_staff_lands_in_the_main_panel(): void
    {
        $dealer = $this->portalUser('dealer', 'Petr');
        $dealer->assignRole('employee');

        Livewire::test(ViewUser::class, ['record' => $dealer->getKey()])
            ->callAction('logInAs')
            ->assertRedirect(Filament::getPanel('main')->getUrl());
    }

    public function test_the_bar_takes_them_back_from_the_dealer_portal_and_the_customer_portal(): void
    {
        foreach (['dealer' => 'dealer', 'customer' => 'portal'] as $role => $panel) {
            $user = $this->portalUser($role, ucfirst($role));
            Impersonation::start($user);

            $this->get(MyContact::getUrl(panel: $panel))->assertOk()->assertSee('You are logged in as');

            $this->post(route('impersonation.leave'))
                ->assertRedirect(UserResource::getUrl('view', ['record' => $user], panel: 'administration'));

            $this->assertSame($this->admin->id, Auth::id(), $role);
            $this->assertNull(Impersonation::impersonatorId(), $role);
            $this->get(UserResource::getUrl('view', ['record' => $user], panel: 'administration'))
                ->assertOk()
                ->assertDontSee('You are logged in as');
        }
    }

    public function test_not_as_a_deactivated_dealer(): void
    {
        $dealer = $this->portalUser('dealer', 'Simona');
        $dealer->update(['active' => false]);

        Livewire::test(ViewUser::class, ['record' => $dealer->getKey()])->assertActionHidden('logInAs');
        $this->assertFalse(Impersonation::allowed($dealer));
        $this->expectException(AuthorizationException::class);
        Impersonation::start($dealer);
    }

    public function test_only_a_super_admin_can_log_in_as_a_dealer_or_a_customer(): void
    {
        $dealer = $this->portalUser('dealer', 'Simona');
        $customer = $this->portalUser('customer', 'Carl');
        $this->actingAs($this->userWithRole('manager'));

        $this->assertFalse(Impersonation::allowed($dealer));
        $this->assertFalse(Impersonation::allowed($customer));
        $this->expectException(AuthorizationException::class);
        Impersonation::start($dealer);
    }

    public function test_the_bar_takes_them_back(): void
    {
        Impersonation::start($this->employee);

        $this->post(route('impersonation.leave'))
            ->assertRedirect(UserResource::getUrl('view', ['record' => $this->employee], panel: 'administration'));

        $this->assertSame($this->admin->id, Auth::id());
        $this->assertNull(Impersonation::impersonatorId());
        $this->get(UserResource::getUrl('view', ['record' => $this->employee], panel: 'administration'))
            ->assertOk()
            ->assertDontSee('You are logged in as');
    }

    public function test_logging_in_as_someone_else_again_still_goes_back_to_the_first_administrator(): void
    {
        $otherAdmin = $this->userWithRole('super_admin');

        Impersonation::start($otherAdmin);
        Impersonation::start($this->employee);
        $this->assertSame($this->admin->id, Impersonation::impersonatorId());
    }

    public function test_logging_in_as_the_first_administrator_ends_it(): void
    {
        Impersonation::start($this->userWithRole('super_admin'));
        Impersonation::start($this->admin);

        $this->assertSame($this->admin->id, Auth::id());
        $this->assertNull(Impersonation::impersonatorId());
    }

    public function test_an_administrator_who_has_lost_the_role_is_signed_out_instead(): void
    {
        Impersonation::start($this->employee);
        $this->admin->removeRole('super_admin');

        $this->post(route('impersonation.leave'))->assertRedirect(Filament::getPanel('main')->getLoginUrl());

        $this->assertGuest();
    }

    public function test_leaving_when_not_logged_in_as_anyone_changes_nothing(): void
    {
        $this->post(route('impersonation.leave'))->assertRedirect(Filament::getPanel('main')->getUrl());

        $this->assertSame($this->admin->id, Auth::id());
    }

    /**
     * A dealer or customer login with its own contact (My Contact, where a
     * portal opens, needs one) and a password of its own, as Ann has.
     */
    protected function portalUser(string $role, string $firstName): User
    {
        $user = $this->userWithRole($role, ['password' => 'their own password']);
        Contact::create(['first_name' => $firstName, 'last_name' => 'Alfa', 'user_id' => $user->id]);

        return $user;
    }
}
