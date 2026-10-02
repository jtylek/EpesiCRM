<?php

namespace Tests\Feature;

use App\Support\Locale\Locales;
use Illuminate\Http\Request;
use Tests\TestCase;

class LocaleResolutionTest extends TestCase
{
    public function test_it_falls_back_to_browser_locale_when_user_lookup_fails(): void
    {
        config([
            'app.available_locales' => ['en' => 'English', 'pl' => 'Polski'],
            'app.configured_locale' => 'en',
        ]);

        $request = Request::create('/setup/install');
        $request->headers->set('Accept-Language', 'pl-PL,pl;q=0.9');
        $request->setUserResolver(static function () {
            throw new \RuntimeException('The users table does not exist.');
        });

        $this->assertSame('pl', Locales::forRequest($request));
    }
}
