<?php

namespace App\Filament\Administration\Pages;

use App\Filament\Concerns\HasPageIconBreadcrumb;
use App\Filament\Concerns\HidesPageHeading;
use App\Filament\Concerns\TranslatesPageLabels;
use App\Services\Modules\ModuleException;
use App\Support\Logo;
use App\Support\Version;
use BackedEnum;
use Epesi\Modules\Store\Models\StoreSetting;
use Epesi\Modules\Store\Services\Diagnostics;
use Epesi\Modules\Store\Services\Registration;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Html;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Schema as SchemaBuilder;
use Illuminate\Support\HtmlString;

/**
 * Port of Epesi's Base/About (Support > About): version, license and where
 * to get help, for whoever opens Administration wanting to know what
 * they're running rather than the login audit. The main panel's
 * "Administration" user menu item (MainPanelProvider) now opens here first.
 */
class About extends Page
{
    use HasPageIconBreadcrumb;
    use HidesPageHeading;
    use TranslatesPageLabels;

    public const LICENSE_URL = 'https://opensource.org/licenses/MIT';

    public const GITHUB_URL = 'https://github.com/jtylek/epesiCRM';

    public const FORUM_URL = 'https://forum.epe.si/';

    public const WEBSITE_URL = 'https://epesicrm.com/';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInformationCircle;

    protected static ?string $navigationLabel = 'About';

    protected static ?string $title = 'About';

    protected static ?string $slug = 'about';

    protected static ?int $navigationSort = -5;

    public function mount(): void
    {
        if (! self::storeAvailable()) {
            return;
        }

        Diagnostics::rememberWebServer();

        // The e-mail may have been confirmed since: show the registration as it is now.
        if (StoreSetting::current()->isPending()) {
            app(Registration::class)->refreshStatus();
        }
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->compact()
                ->extraAttributes(['class' => 'epesi-about-logo'])
                ->schema([
                    Html::make(fn (): HtmlString => $this->header()),
                    ...$this->registration(),
                ]),
            Section::make(__('License'))
                ->compact()
                ->headerActions([
                    Action::make('license')
                        ->label('MIT License')
                        ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                        ->link()
                        ->url(self::LICENSE_URL, shouldOpenInNewTab: true),
                ])
                ->schema([
                    Text::make(__('epesi is released under the MIT License'))
                        ->weight(FontWeight::Bold),
                    Text::make(__('Copyright © :year by Janusz Tylek', ['year' => now()->year])),
                    Section::make()
                        ->compact()
                        ->extraAttributes(['class' => 'epesi-about-terms', 'style' => 'margin-top:1.5rem'])
                        ->schema([
                            Text::make(__('Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the "Software"), to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:'))
                                ->color('gray'),
                            Text::make(__('The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.'))
                                ->weight(FontWeight::Bold)
                                ->color('gray'),
                        ]),
                    Text::make(__('THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.'))
                        ->color('gray')
                        ->extraAttributes(['style' => 'margin-top:1.5rem']),
                    Html::make(fn (): HtmlString => new HtmlString('<hr style="border-top:1px solid var(--gray-200);margin:.5rem 0">')),
                    Text::make(__('Third-party packages, including the optional Roundcube webmail, keep their own licenses.')),
                    Text::make(__('By using this software you automatically agree to this End User License Agreement [EULA].'))
                        ->weight(FontWeight::Medium),
                ]),
            Callout::make(__('Support'))
                ->description(__('Report a bug or ask a question on GitHub, or join the discussion on the forum.'))
                ->info()
                ->actions([
                    Action::make('github')
                        ->label('GitHub')
                        ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                        ->link()
                        ->url(self::GITHUB_URL, shouldOpenInNewTab: true),
                    Action::make('forum')
                        ->label('Forum')
                        ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                        ->link()
                        ->url(self::FORUM_URL, shouldOpenInNewTab: true),
                    Action::make('website')
                        ->label('Website')
                        ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                        ->link()
                        ->url(self::WEBSITE_URL, shouldOpenInNewTab: true),
                ]),
        ]);
    }

    /**
     * The logo, its tagline under it, then "epesi" in bold with the version on the same line.
     */
    private function header(): HtmlString
    {
        // White in dark mode (Filament's `dark` class on <html>), gray otherwise.
        $style = 'font-size:.875rem';

        return new HtmlString(
            // The logo card's padding is the same 2rem all round.
            '<style>.epesi-about-logo .fi-section-content{padding:2rem !important}.epesi-about-terms .fi-section{background:var(--gray-100) !important}.dark .epesi-about-terms .fi-section{background:var(--gray-800) !important}.epesi-about-text{color:var(--gray-500)}.dark .epesi-about-text{color:#fff}</style>'
            // The tagline is as wide as the logo: a bigger font, and the last (only) line justified to fill the rest.
            .'<div><div style="display:inline-block">'
            .Logo::html('3.5rem')
            .'<div class="epesi-about-text" style="font-size:1.1rem;margin-top:.25rem;font-weight:700;text-align-last:justify">'
            .e(__('Business Information Manager')).'</div></div></div>'
            .'<div class="epesi-about-text" style="'.$style.';margin-top:.75rem"><strong style="font-size:1.125rem;font-weight:700;margin-right:.5rem">epesi</strong>'
            .e(__('Version :version', ['version' => Version::current()])).'</div>'
        );
    }

    /**
     * Registered or not, and the way to register: the Epesi Store (a core
     * module) serves updates and its catalog to registered installations.
     *
     * @return array<int, Text|Actions>
     */
    protected function registration(): array
    {
        if (! self::storeAvailable()) {
            return [];
        }

        $setting = StoreSetting::current();

        if ($setting->isRegistered()) {
            return [Html::make(fn (): HtmlString => new HtmlString('<div style="color:var(--success-600)">'.e(__('You are running registered version.')).'</div>'))];
        }

        return [
            Html::make(fn (): HtmlString => new HtmlString('<div style="color:var(--warning-600)">'.e($setting->isPending()
                ? __('Registration pending: confirm the link sent to :email.', ['email' => $setting->registered_email ?? __('your e-mail')])
                : __('You are running unregistered version.')).'</div>')),
            Actions::make([
                Action::make('register')
                    ->label($setting->isPending() ? __('Open the registration form') : __('Register your Epesi'))
                    ->icon(Heroicon::OutlinedCheckBadge)
                    ->link()
                    ->action(function (): void {
                        try {
                            $url = app(Registration::class)->start(auth()->user());
                        } catch (ModuleException $exception) {
                            Notification::make()->danger()->title(__('The Epesi Store could not be reached'))->body($exception->getMessage())->send();

                            return;
                        }

                        $this->redirect($url);
                    }),
            ])->key('registration'),
        ];
    }

    /** The Store module's tables exist (not before its migrations ran). */
    protected static function storeAvailable(): bool
    {
        return class_exists(StoreSetting::class)
            && rescue(fn (): bool => SchemaBuilder::hasColumn('epesi_store_settings', 'registration_status'), false, report: false);
    }
}
