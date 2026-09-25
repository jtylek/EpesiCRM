<?php

namespace Tests\Feature;

use App\Enums\RecordPermission;
use App\Models\User;
use App\Support\Locale\Locales;
use Database\Seeders\DemoDataSeeder;
use Epesi\Modules\CRM\Tasks\Models\Task;
use Epesi\Modules\Mail\Models\MailAccount;
use Epesi\Modules\RegionalSettings\Models\RegionalSetting;
use Filament\Facades\Filament;
use Filament\Navigation\NavigationManager;
use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Pages\ViewRecord;
use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Lang;
use Livewire\Livewire;
use Tests\Concerns\SignsInUsers;
use Tests\TestCase;
use Throwable;

/**
 * Languages: who gets which one, and — by opening every page of every panel
 * in Polish — that the interface has no English left in it.
 */
class TranslationsTest extends TestCase
{
    use RefreshDatabase, SignsInUsers;

    public function test_a_user_gets_their_own_language_else_the_system_default(): void
    {
        $user = $this->userWithRole('employee');
        $this->assertSame('en', Locales::forUser($user));

        RegionalSetting::query()->create(['user_id' => null, 'language' => 'pl']);
        $this->assertSame('pl', Locales::forUser($user));
        $this->assertSame('pl', $user->preferredLocale(), 'mail and notifications follow it');

        RegionalSetting::query()->create(['user_id' => $user->id, 'language' => 'en']);
        $this->assertSame('en', Locales::forUser($user));
    }

    public function test_a_language_that_is_not_offered_falls_back(): void
    {
        $user = $this->userWithRole('employee');
        RegionalSetting::query()->create(['user_id' => $user->id, 'language' => 'xx']);

        $this->assertSame('en', Locales::forUser($user));
    }

    public function test_the_login_page_follows_the_browser(): void
    {
        User::factory()->create();

        $this->get(route('filament.main.auth.login'), ['Accept-Language' => 'pl-PL,pl;q=0.9,en;q=0.8'])
            ->assertOk()
            ->assertSee('lang="pl"', false);

        $this->get(route('filament.main.auth.login'), ['Accept-Language' => 'de-DE'])
            ->assertOk()
            ->assertSee('lang="en"', false);
    }

    public function test_the_signed_in_user_gets_their_language(): void
    {
        $user = $this->userWithRole('employee');
        RegionalSetting::query()->create(['user_id' => $user->id, 'language' => 'pl']);

        $this->actingAs($user)
            ->get('/', ['Accept-Language' => 'en'])
            ->assertOk()
            ->assertSee('lang="pl"', false)
            ->assertSee('Panel'); // Filament's own Polish for "Dashboard"
    }

    public function test_notifications_are_written_in_the_recipients_language(): void
    {
        $author = $this->userWithRole('employee');
        $colleague = $this->userWithRole('employee', ['name' => 'Ann']);
        RegionalSetting::query()->create(['user_id' => $author->id, 'language' => 'pl']);

        $this->actingAs($author);
        $task = Task::create(['title' => 'Call the bank', 'permission' => RecordPermission::Public]);

        // The change is made in English; the author reads about it in Polish.
        $this->actingAs($colleague);
        app()->setLocale('en');
        $task->update(['title' => 'Call the bank today']);

        $data = $author->notifications()->sole()->data;
        $this->assertSame('Zadanie: Call the bank today', $data['title']);
        $this->assertSame('Ann zmienił(a): Tytuł.', $data['body'], 'fields by their Polish labels');
        $this->assertSame('en', app()->getLocale(), 'the request keeps its own language');
    }

    public function test_every_page_is_translated_to_polish(): void
    {
        $this->withoutVite();
        $admin = $this->userWithRole('super_admin');
        (new DemoDataSeeder)->run($admin);
        RegionalSetting::query()->create(['user_id' => $admin->id, 'language' => 'pl']);
        // The compose page only opens for someone who can send.
        MailAccount::create(['user_id' => $admin->id, 'name' => 'Work', 'email' => 'admin@example.test', 'smtp_host' => 'smtp.test']);
        $this->actingAs($admin);

        // A label that arrives already in Polish (Filament's own, or one
        // this app translated first) is looked up once more by
        // translateLabel(); that second lookup is not a gap.
        $translated = $this->translatedValues('pl');

        $missing = [];
        Lang::handleMissingKeysUsing(function (string $key, array $replace, ?string $locale) use (&$missing, $translated): string {
            // Not interface text: the user menu is labelled with the user's
            // name, and Shield catches this exception of Spatie's.
            $notText = $key === auth()->user()?->name || str_starts_with($key, 'There is no permission named');

            if ($locale === 'pl' && ! $notText && ! str_contains($key, '::') && ! str_contains($key, '\\') && ! isset($translated[$key]) && preg_match('/\p{L}/u', $key)) {
                $missing[$key] = true;
            }

            return $key;
        });

        foreach (['main', 'administration', 'user-settings'] as $panelId) {
            $panel = Filament::getPanel($panelId);
            Filament::setCurrentPanel($panel);

            // The navigation is a scoped singleton, which a real request
            // starts without; within one test it would keep the first panel's.
            app()->forgetInstance(NavigationManager::class);

            foreach ($panel->getPages() as $page) {
                if ($page::canAccess()) {
                    $this->get($page::getUrl(panel: $panelId))->assertOk();
                }
            }

            foreach ($panel->getResources() as $resource) {
                $this->crawlResource($resource, $panelId);
            }

            foreach ($panel->getWidgets() as $widget) {
                app()->setLocale('pl');
                try {
                    Livewire::test($widget);
                } catch (Throwable) {
                    // A widget that needs a page to sit on; its text is
                    // covered where that page renders it.
                }
            }
        }

        $missing = array_keys($missing);
        sort($missing);

        // TRANSLATIONS_MISSING=path/to/file.json writes the list out, as a
        // starting point for lang/pl.json.
        if ($dump = env('TRANSLATIONS_MISSING')) {
            file_put_contents($dump, json_encode(array_fill_keys($missing, ''), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }

        $this->assertSame([], $missing, "Not translated to Polish:\n".implode("\n", $missing));
    }

    /**
     * What the crawl can't open — modals, notifications, the setup wizard:
     * every string the code hands to __() or labels a component with.
     */
    public function test_every_string_in_the_code_has_polish(): void
    {
        $polish = [];
        foreach ([lang_path('pl.json'), ...glob(base_path('modules/*/*/lang/pl.json')) ?: [], ...glob(base_path('modules/*/*/*/lang/pl.json')) ?: []] as $file) {
            $polish += (array) json_decode(file_get_contents($file), true);
        }

        $patterns = [
            '/(?:__|trans_choice)\(\s*\'((?:[^\'\\\\]|\\\\.)+)\'/',
            '/->(?:label|modalHeading|modalSubmitActionLabel)\(\s*\'((?:[^\'\\\\]|\\\\.)+)\'\s*\)/',
            '/(?:Step|Tab|Fieldset)::make\(\s*\'((?:[^\'\\\\]|\\\\.)+)\'/',
        ];

        $missing = [];
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(base_path(), \FilesystemIterator::SKIP_DOTS));

        foreach ($files as $file) {
            $path = str_replace('\\', '/', $file->getPathname());

            if (! preg_match('#/(app|modules|resources/views)/.*\.php$#', $path) || preg_match('#/(vendor|node_modules|storage|Console)/#', $path)) {
                continue;
            }

            foreach ($patterns as $pattern) {
                preg_match_all($pattern, file_get_contents($path), $matches);

                foreach ($matches[1] as $key) {
                    $key = stripslashes($key);

                    if (preg_match('/\p{L}/u', $key) && ! str_contains($key, '::') && ! preg_match('/^[a-z_]+(\.[a-z_]+)+$/', $key) && ! isset($polish[$key])) {
                        $missing[$key] = str_replace(base_path().'/', '', $path);
                    }
                }
            }
        }

        foreach (config('setup.profiles') as $profile) {
            foreach (array_filter([$profile['label'] ?? null, $profile['description'] ?? null]) as $key) {
                if (! isset($polish[$key])) {
                    $missing[$key] = 'config/setup.php';
                }
            }
        }

        ksort($missing);

        $this->assertSame([], $missing, 'No Polish for these (string => where):');
    }

    /**
     * @return array<string, true>
     */
    protected function translatedValues(string $locale): array
    {
        $lines = [];

        foreach ([
            ...glob(base_path("vendor/*/*/resources/lang/{$locale}/*.php")) ?: [],
            ...glob(base_path("vendor/*/*/resources/lang/{$locale}/*/*.php")) ?: [],
            ...glob(lang_path("{$locale}/*.php")) ?: [],
        ] as $file) {
            $lines[] = require $file;
        }

        foreach ([lang_path("{$locale}.json"), ...glob(base_path("modules/*/*/lang/{$locale}.json")) ?: [], ...glob(base_path("modules/*/*/*/lang/{$locale}.json")) ?: []] as $file) {
            if (is_file($file)) {
                $lines[] = json_decode(file_get_contents($file), true);
            }
        }

        $values = [];
        array_walk_recursive($lines, function ($value) use (&$values): void {
            if (is_string($value)) {
                $values[$value] = true;
            }
        });

        return $values;
    }

    /**
     * @param  class-string<resource>  $resource
     */
    protected function crawlResource(string $resource, string $panelId): void
    {
        $record = $resource::getModel()::query()->withoutGlobalScopes()->first();

        foreach ($resource::getPages() as $name => $registration) {
            $page = $registration->getPage();
            $needsRecord = is_subclass_of($page, EditRecord::class)
                || is_subclass_of($page, ViewRecord::class);

            if ($needsRecord && $record === null) {
                continue;
            }

            $url = $needsRecord
                ? $resource::getUrl($name, ['record' => $record], panel: $panelId)
                : $resource::getUrl($name, panel: $panelId);

            $this->get($url)->assertOk();
        }

        if ($record === null) {
            return;
        }

        $viewPage = collect($resource::getPages())
            ->map(fn ($registration): string => $registration->getPage())
            ->first(fn (string $page): bool => is_subclass_of($page, ViewRecord::class) || is_subclass_of($page, EditRecord::class));

        foreach ($resource::getRelations() as $manager) {
            $manager = is_string($manager) ? $manager : null;

            if ($manager === null || ! $viewPage) {
                continue;
            }

            app()->setLocale('pl');
            $this->renderRelationManager($manager, $record, $viewPage);
        }
    }

    protected function renderRelationManager(string $manager, Model $record, string $page): void
    {
        if (! $manager::canViewForRecord($record, $page)) {
            return;
        }

        Livewire::test($manager, ['ownerRecord' => $record, 'pageClass' => $page])->assertOk();
    }
}
