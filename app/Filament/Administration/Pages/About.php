<?php

namespace App\Filament\Administration\Pages;

use App\Filament\Concerns\HasPageIconBreadcrumb;
use App\Filament\Concerns\HidesPageHeading;
use App\Filament\Concerns\TranslatesPageLabels;
use App\Support\Version;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;
use Filament\Support\Icons\Heroicon;

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

    protected static ?int $navigationSort = -1;

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->compact()
                ->schema([
                    Text::make(Version::label())
                        ->size(TextSize::Large)
                        ->weight(FontWeight::Bold),
                    Text::make(__('Version :version', ['version' => Version::current()])),
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
                    Text::make(__('epesi is released under the MIT License')),
                    Text::make(__('Copyright © :year by Janusz Tylek and Karina Tylek', ['year' => now()->year])),
                    Text::make(__('Third-party packages, including the optional Roundcube webmail, keep their own licenses.')),
                    Text::make(__('By using this software you automatically agree to this End User License Agreement [EULA].'))
                        ->weight(FontWeight::Medium),
                    Text::make(__('Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the "Software"), to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:'))
                        ->color('gray'),
                    Text::make(__('The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.'))
                        ->weight(FontWeight::Bold)
                        ->color('gray'),
                    Text::make(__('THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.'))
                        ->color('gray'),
                ]),
        ]);
    }
}
