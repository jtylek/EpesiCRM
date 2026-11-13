<?php

namespace Tests\Feature\Modules;

use Epesi\Modules\CRM\Companies\Models\Company;
use Epesi\Modules\CRM\Contacts\Models\Contact;
use Epesi\Modules\Mail\Models\MailAccount;
use Epesi\Modules\Mail\Services\UnknownSenders;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SignsInUsers;
use Tests\TestCase;

/**
 * Who the Archive button asks about: AI-shared/features/mail/archiving.md,
 * Unknown sender.
 */
class UnknownSendersTest extends TestCase
{
    use RefreshDatabase, SignsInUsers;

    /** @return array{from: list<array{email: string, name: string}>, to: list<array{email: string, name: string}>} */
    protected function message(string $from, string $to, string $fromName = ''): array
    {
        return ['from' => [['email' => $from, 'name' => $fromName]], 'to' => [['email' => $to, 'name' => '']]];
    }

    public function test_only_the_other_party_without_a_live_record_is_unknown(): void
    {
        $user = $this->userWithRole('employee', ['email' => 'eli@ourcompany.test']);
        $this->actingAs($user);
        $own = Contact::create(['first_name' => 'Eli', 'last_name' => 'Employee']);
        $own->forceFill(['user_id' => $user->id])->save();
        $own->syncCollection('emails', [['kind' => 'other', 'value' => 'eli@home.test']]);
        $user->refresh();
        $account = MailAccount::create(['user_id' => $user->id, 'name' => 'Orders', 'email' => 'orders@ourcompany.test', 'imap_host' => 'imap.test', 'imap_password' => 'x', 'smtp_host' => 'smtp.test']);

        $ann = Contact::create(['first_name' => 'Ann', 'last_name' => 'Buyer']);
        $ann->syncCollection('emails', [['kind' => 'work', 'value' => 'ann@customer.test']]);
        $company = Company::create(['company_name' => 'Customer Ltd']);
        $company->syncCollection('emails', [['kind' => 'work', 'value' => 'sales@customer.test']]);
        $secret = Contact::create(['first_name' => 'Sam', 'last_name' => 'Secret', 'permission' => 2]);
        $secret->forceFill(['created_by' => $this->userWithRole('employee')->id])->save();
        $secret->syncCollection('emails', [['kind' => 'work', 'value' => 'sam@elsewhere.test']]);
        $gone = Contact::create(['first_name' => 'Gone', 'last_name' => 'Away']);
        $gone->syncCollection('emails', [['kind' => 'work', 'value' => 'gone@customer.test']]);
        $gone->delete();

        $find = fn (array ...$messages): array => array_column(app(UnknownSenders::class)->find($messages, $user, $account), 'email');

        $this->assertSame([], $find($this->message('Ann@Customer.test', 'orders@ourcompany.test')), 'a contact, whatever the case');
        $this->assertSame([], $find($this->message('sales@customer.test', 'orders@ourcompany.test')), 'a company');
        $this->assertSame([], $find($this->message('sam@elsewhere.test', 'orders@ourcompany.test')), "someone else's private contact still counts");
        $this->assertSame(['gone@customer.test'], $find($this->message('gone@customer.test', 'orders@ourcompany.test')), 'a deleted contact released its address');
        $this->assertSame(['stranger@nowhere.test'], $find($this->message('stranger@nowhere.test', 'orders@ourcompany.test')),
            'a shared mailbox: the company or your own contact on To never makes the sender known');
        $this->assertSame(['new@client.test'], $find($this->message('orders@ourcompany.test', 'new@client.test')), 'your own message: its recipients');
        $this->assertSame([], $find($this->message('eli@home.test', 'ann@customer.test')), "your contact's own extra address makes it yours");
        $this->assertSame(['x@y.test'], $find($this->message('x@y.test', 'a@b.test'), $this->message('X@Y.test', 'a@b.test')), 'once each');
        $this->assertSame([], $find(['from' => [['email' => 'not an address', 'name' => '']], 'to' => []]), 'only valid addresses');

        $withCc = ['from' => [['email' => 'ann@customer.test', 'name' => '']], 'to' => [['email' => 'orders@ourcompany.test', 'name' => '']], 'cc' => [['email' => 'cc@nowhere.test', 'name' => '']]];
        $this->assertSame([], $find($withCc), 'Cc is never checked');
    }

    public function test_names_are_split_from_the_header_or_guessed_from_the_address(): void
    {
        $split = fn (string $name, string $email): array => array_values(UnknownSenders::splitName($name, $email));

        $this->assertSame(['Maria', 'Dahl'], $split('Dahl, Maria', 'maria@siluett.se'));
        $this->assertSame(['Maria', 'Dahl'], $split('Maria Dahl', 'maria@siluett.se'));
        $this->assertSame(['Christiane', 'VanAllen-Daly'], $split('"Christiane VanAllen-Daly"', 'c@x.test'));
        $this->assertSame(['Maria', 'Dahl'], $split('', 'maria.dahl@siluett.se'));
        $this->assertSame(['', ''], $split('', 'info@siluett.se'));
        $this->assertSame(['', 'Siluett'], $split('Siluett', 'info@siluett.se'));
    }

    public function test_suggestions_are_the_same_name_then_people_at_the_domain(): void
    {
        $this->actingAs($this->userWithRole('employee'));
        $company = Company::create(['company_name' => 'Siluett']);
        $company->syncCollection('online_accounts', [['kind' => 'website', 'value' => 'https://www.siluett.se']]);
        $maria = Contact::create(['first_name' => 'Maria', 'last_name' => 'Dahl']);
        $maria->syncCollection('emails', [['kind' => 'work', 'value' => 'maria.dahl@other.test']]);
        $asa = Contact::create(['first_name' => 'Åsa', 'last_name' => 'Boman', 'company_id' => $company->id]);
        $colleague = Contact::create(['first_name' => 'Per', 'last_name' => 'Olsson']);
        $colleague->syncCollection('emails', [['kind' => 'work', 'value' => 'per@siluett.se']]);
        Contact::create(['first_name' => 'Joe', 'last_name' => 'Doe']);

        $senders = app(UnknownSenders::class);

        $this->assertSame([$maria->id, $asa->id, $colleague->id], $senders->suggestions('maria@siluett.se', 'Maria', 'Dahl')->modelKeys());
        $this->assertSame([$maria->id], $senders->suggestions('maria@gmail.com', 'dahl', 'maria')->modelKeys(), 'either order, any case; a free-mail domain suggests no one else');
        $this->assertTrue($senders->companyFor('anyone@siluett.se')?->is($company), 'the company by its website');
        $this->assertNull($senders->companyFor('anyone@gmail.com'));
        $this->assertSame('Siluett', UnknownSenders::companyName('info@siluett.se'));
    }

    public function test_suggestions_work_for_a_sender_with_no_name(): void
    {
        $this->actingAs($this->userWithRole('employee'));
        $colleague = Contact::create(['first_name' => 'Per', 'last_name' => 'Olsson']);
        $colleague->syncCollection('emails', [['kind' => 'work', 'value' => 'per@siluett.se']]);

        $found = app(UnknownSenders::class)->suggestions('info@siluett.se', '', '');

        $this->assertSame([$colleague->id], $found->map(fn ($contact) => $contact->getKey())->all());
    }
}
