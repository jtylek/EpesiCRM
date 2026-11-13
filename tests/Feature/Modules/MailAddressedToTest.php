<?php

namespace Tests\Feature\Modules;

use Epesi\Modules\CRM\Companies\Models\Company;
use Epesi\Modules\CRM\Contacts\Filament\Resources\Contacts\Pages\ViewContact;
use Epesi\Modules\CRM\Contacts\Models\Contact;
use Epesi\Modules\Mail\Filament\RelationManagers\MailsRelationManager;
use Epesi\Modules\Mail\Services\MailArchiver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Concerns\SignsInUsers;
use Tests\Fakes\Eml;
use Tests\TestCase;

class MailAddressedToTest extends TestCase
{
    use RefreshDatabase, SignsInUsers;

    public function test_a_contacts_e_mails_tab_lists_only_mail_sent_by_or_addressed_to_it(): void
    {
        Storage::fake('local');
        $user = $this->userWithRole('employee');
        $this->actingAs($user);

        $ann = Contact::create(['first_name' => 'Ann', 'last_name' => 'Buyer']);
        $ann->syncCollection('emails', [['kind' => 'work', 'value' => 'ann@customer.test']]);
        $company = Company::create(['company_name' => 'Customer Ltd']);
        $company->syncCollection('emails', [['kind' => 'work', 'value' => 'sales@customer.test']]);

        $archiver = app(MailArchiver::class);
        $from = $archiver->archive(Eml::make(from: 'ann@customer.test', to: 'me@ourcompany.test', subject: 'From only', messageId: 'a@x'), $user);
        $to = $archiver->archive(Eml::make(from: 'me@ourcompany.test', to: 'ann@customer.test', subject: 'To her', messageId: 'b@x'), $user);
        $cc = $archiver->archive(Eml::make(from: 'me@ourcompany.test', to: 'x@other.test', cc: 'Ann <ann@customer.test>', subject: 'Cc her', messageId: 'c@x'), $user);
        $other = $archiver->archive(Eml::make(from: 'me@ourcompany.test', to: 'joann@customer.test', subject: 'Someone else', messageId: 'd@x'), $user);
        $viaCompany = $archiver->archive(Eml::make(from: 'sales@customer.test', to: 'me@ourcompany.test', subject: 'Company', messageId: 'e@x'), $user);

        $this->assertTrue($ann->mails()->whereKey($from->id)->exists(), 'archived against her, so linked');

        $ids = MailsRelationManager::addressedMailIds($ann);
        $this->assertEqualsCanonicalizing([$from->id, $to->id, $cc->id], $ids);
        $this->assertSame('3', MailsRelationManager::getBadge($ann, ViewContact::class));

        Livewire::test(MailsRelationManager::class, ['ownerRecord' => $ann, 'pageClass' => ViewContact::class])
            ->assertCanSeeTableRecords([$from, $to, $cc])
            ->assertCanNotSeeTableRecords([$other]);

        // A company keeps every linked message.
        $this->assertNull(MailsRelationManager::addressedMailIds($company));
        $this->assertSame((string) $company->mails()->count(), MailsRelationManager::getBadge($company, ViewContact::class));
        $this->assertTrue($company->mails()->whereKey($viaCompany->id)->exists());
    }
}
