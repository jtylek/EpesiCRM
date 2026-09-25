<?php

namespace App\Filament\Setup\Pages;

use App\Filament\Concerns\TranslatesPageLabels;
use App\Support\Setup\SetupState;
use App\Support\Setup\SetupStep;
use App\Support\Setup\SetupSteps;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\SimplePage;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;

/**
 * The pages installed modules add to the end of setup — FirstRun's
 * post_install loop, which showed each installed module's own form before
 * letting the administrator in. Runs on the request after installation, so
 * the modules' providers (which register the steps) are loaded.
 */
class FinishSetup extends SimplePage
{
    use TranslatesPageLabels;

    protected Width|string|null $maxWidth = Width::ThreeExtraLarge;

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public function mount(): void
    {
        if (! SetupState::isInstalled()) {
            $this->redirect(route('filament.setup.install'));

            return;
        }

        if (! Auth::user()?->hasRole('super_admin')) {
            $this->redirect(filament()->getPanel('main')->getLoginUrl());

            return;
        }

        if (! SetupState::finishPending() || $this->steps() === []) {
            $this->finish();

            return;
        }

        $this->form->fill(collect($this->steps())->map(fn (SetupStep $step): array => $step->defaults())->all());
    }

    public function getTitle(): string|Htmlable
    {
        return __('Finish setup');
    }

    public function getHeading(): string|Htmlable|null
    {
        return __('Almost there');
    }

    public function getSubheading(): string|Htmlable|null
    {
        return __('epesi is installed. A few details for the modules you chose — you can also skip this and fill them in later.');
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('save'),
            Actions::make([$this->skipAction()])->alignCenter(),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Wizard::make(collect($this->steps())
                    ->map(fn (SetupStep $step, string $key): Step => Step::make($step->label())
                        ->description($step->description())
                        ->statePath($key)
                        ->schema($step->schema()))
                    ->values()
                    ->all())
                    ->submitAction(new HtmlString(Blade::render(
                        '<x-filament::button type="submit" size="sm" wire:loading.attr="disabled" wire:target="save">{{ __(\'Finish\') }}</x-filament::button>',
                    ))),
            ]);
    }

    public function skipAction(): Action
    {
        return Action::make('skip')
            ->label('Skip for now')
            ->link()
            ->color('gray')
            ->action(fn () => $this->finish());
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $admin = Auth::user();

        DB::transaction(function () use ($data, $admin): void {
            foreach ($this->steps() as $key => $step) {
                $step->handle($data[$key] ?? [], $admin);
            }
        });

        Notification::make()->title(__('Setup finished'))->body(__('Welcome to epesi.'))->success()->send();

        $this->finish();
    }

    protected function finish(): void
    {
        SetupState::markFinished();

        $this->redirect(filament()->getPanel('main')->getUrl());
    }

    /**
     * @return array<string, SetupStep>
     */
    protected function steps(): array
    {
        return SetupSteps::all();
    }
}
