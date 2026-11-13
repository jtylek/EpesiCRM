<?php

namespace Epesi\Modules\RecordBrowser\Filament\Administration\Pages;

use Closure;
use Epesi\Modules\RecordBrowser\Recordset\ClipboardPattern;
use Epesi\Modules\RecordBrowser\Recordset\RecordsetFeatures;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;

/**
 * The Copy to clipboard pattern and its preview. The feature itself is
 * switched on the Settings tab; while it is off this tab says so and leads
 * there, and the stored pattern is kept for when it is turned back on.
 */
class RecordsetClipboard extends RecordsetPage
{
    protected static ?string $slug = 'recordsets/{alias}/clipboard';

    public static function tabLabel(): string
    {
        return 'Clipboard';
    }

    public function clipboardEnabled(): bool
    {
        return RecordsetFeatures::enabled('clipboard', $this->alias);
    }

    public function hasSave(): bool
    {
        return $this->clipboardEnabled();
    }

    protected function formData(): array
    {
        return ['clipboard_pattern' => ClipboardPattern::patternFor($this->alias) ?? ''];
    }

    protected function formComponents(): array
    {
        return [
            Placeholder::make('clipboard_off')
                ->hiddenLabel()
                ->visible(fn (): bool => ! $this->clipboardEnabled())
                ->content(__('Copy to clipboard is turned off for this recordset. Turn it on in Settings to set a pattern.')),
            Textarea::make('clipboard_pattern')
                ->label(__('Clipboard pattern'))
                ->rows(8)
                ->live(onBlur: true)
                ->visible(fn (): bool => $this->clipboardEnabled())
                ->hintAction($this->keywordsAction())
                ->rules([
                    fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                        if (trim((string) $value) === '') {
                            // An empty override is allowed: it falls back
                            // to the module's default (ClipboardPattern::patternFor()).
                            return;
                        }

                        if ($error = ClipboardPattern::validate($this->alias, (string) $value)) {
                            $fail($error);
                        }
                    },
                ])
                ->helperText(__('{key} substitutes a field; a %{…} block is dropped whole when none of its keys has a value (blocks can\'t nest). Leave blank to use the recordset\'s default pattern.')),
            Placeholder::make('clipboard_preview')
                ->label(__('Preview'))
                ->visible(fn (): bool => $this->clipboardEnabled())
                ->content(function (Get $get): HtmlString {
                    $pattern = trim((string) ($get('clipboard_pattern') ?? ''));
                    $sample = ClipboardPattern::sampleRecordFor($this->alias);

                    if ($sample === null) {
                        return new HtmlString('<span class="text-sm text-gray-500 dark:text-gray-400">'.e(__('No record of this type exists yet to preview against.')).'</span>');
                    }

                    if ($pattern === '') {
                        return new HtmlString('<span class="text-sm text-gray-500 dark:text-gray-400">'.e(__('Nothing to preview.')).'</span>');
                    }

                    return new HtmlString('<pre class="whitespace-pre-wrap text-sm text-gray-950 dark:text-white">'.e(ClipboardPattern::renderPattern($pattern, $sample, $this->alias)).'</pre>');
                }),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('openSettings')
                ->label(__('Settings'))
                ->icon(Heroicon::OutlinedCog6Tooth)
                ->color('gray')
                ->visible(fn (): bool => ! $this->clipboardEnabled())
                ->url(fn (): string => RecordsetSettings::getUrl(['alias' => $this->alias])),
        ];
    }

    protected function keywordsAction(): Action
    {
        return Action::make('keywords')
            ->label(__('Keywords'))
            ->icon(Heroicon::OutlinedQuestionMarkCircle)
            ->modalHeading(__('Keywords'))
            ->modalDescription(__('{key} substitutes a field; a %{…} block is dropped whole when none of its keys has a value.'))
            ->modalContent(fn (): HtmlString => new HtmlString(
                '<pre class="whitespace-pre-wrap text-sm text-gray-950 dark:text-white">'.e(implode("\n", array_map(
                    fn (string $keyword): string => "{{$keyword}}",
                    array_keys(ClipboardPattern::keywords($this->alias)),
                ))).'</pre>'
            ))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel(__('Close'));
    }

    public function save(): void
    {
        $this->authorizeSave();

        $data = $this->form->getState();

        ClipboardPattern::setPattern($this->alias, $data['clipboard_pattern'] ?? null);

        $this->saved();
    }
}
