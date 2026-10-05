<?php

namespace Tests\Feature\Modules;

use Epesi\Modules\CommonData\Facades\CommonData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * CommonData reads are cached, so each language needs its own cache entry:
 * a Polish user must not be handed the English list an English user read first.
 */
class CommonDataTranslationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_cached_list_is_translated_per_language(): void
    {
        CommonData::seed('Countries/PL', ['MA' => 'Masovian']);

        app()->setLocale('en');
        $this->assertSame('Masovian', CommonData::array('Countries/PL')['MA']);
        $this->assertSame('Masovian', CommonData::value('Countries/PL/MA'));

        app()->setLocale('pl');
        $this->assertSame('Mazowieckie', CommonData::array('Countries/PL')['MA']);
        $this->assertSame('Mazowieckie', CommonData::value('Countries/PL/MA'));
        $this->assertSame('Masovian', CommonData::raw('Countries/PL')['MA']);
    }
}
