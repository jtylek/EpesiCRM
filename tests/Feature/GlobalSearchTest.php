<?php

namespace Tests\Feature;

use Epesi\Modules\CRM\Companies\Models\Company;
use Epesi\Modules\Mail\Filament\Resources\Mails\MailResource;
use Epesi\Modules\Mail\Models\Mail;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SignsInUsers;
use Tests\TestCase;

class GlobalSearchTest extends TestCase
{
    use RefreshDatabase, SignsInUsers;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('main'));
        $this->actingAs($this->userWithRole('employee'));
    }

    public function test_a_single_letter_searches_nothing(): void
    {
        Company::create(['company_name' => 'Acme']);

        $provider = Filament::getGlobalSearchProvider();

        $this->assertNull($provider->getResults('a'));
        $this->assertNull($provider->getResults(' a '));
        $this->assertTrue($provider->getResults('ac')->getCategories()->has('companies'));
    }

    public function test_a_group_shows_a_handful_of_hits(): void
    {
        foreach (range(1, 15) as $i) {
            Company::create(['company_name' => "Acme {$i}"]);
        }

        $this->assertCount(10, Filament::getGlobalSearchProvider()->getResults('Acme')->getCategories()['companies']);
    }

    public function test_mail_is_not_in_global_search(): void
    {
        Mail::create(['subject' => 'Quarterly invoice question', 'body_text' => 'long body']);

        $this->assertSame([], MailResource::getGloballySearchableAttributes());
        $this->assertCount(0, Filament::getGlobalSearchProvider()->getResults('invoice question')->getCategories());
    }
}
