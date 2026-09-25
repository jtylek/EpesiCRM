<?php

namespace Tests\Feature;

use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\Select;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Livewire\Component;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The global Select default from AppServiceProvider::offerNoEmptyChoiceOnRequiredSelects():
 * a required select has no empty "Select an option" choice and is never left
 * empty, whether the form starts new or from a record; an optional one keeps
 * both, and a searchable one with no default waits for the user.
 */
class RequiredSelectTest extends TestCase
{
    private const FILLED = ['priority' => 'medium', 'plain' => 'a', 'searchable' => null, 'optional' => null];

    public function test_a_new_form_starts_every_required_select_on_a_value(): void
    {
        Livewire::test(RequiredSelectForm::class)->assertSchemaStateSet(self::FILLED);
    }

    public function test_a_record_left_empty_gets_the_same_values(): void
    {
        Livewire::test(RequiredSelectForm::class, ['record' => array_fill_keys(array_keys(self::FILLED), null)])
            ->assertSchemaStateSet(self::FILLED);
    }

    public function test_only_optional_selects_offer_the_empty_choice(): void
    {
        $test = Livewire::test(RequiredSelectForm::class);

        foreach (['priority' => false, 'plain' => false, 'searchable' => false, 'optional' => true] as $name => $offered) {
            $test->assertSchemaComponentExists($name, checkComponentUsing: fn (Select $select): bool => $select->canSelectPlaceholder() === $offered);
        }
    }
}

class RequiredSelectForm extends Component implements HasActions, HasSchemas
{
    use InteractsWithActions, InteractsWithSchemas;

    /** @var array<string, mixed> */
    public ?array $data = [];

    /**
     * @param  array<string, mixed>|null  $record  the Edit page's case; null is the Create page's
     */
    public function mount(?array $record = null): void
    {
        $this->form->fill($record);
    }

    public function form(Schema $schema): Schema
    {
        $options = ['a' => 'Alpha', 'b' => 'Beta'];

        return $schema->statePath('data')->components([
            Select::make('priority')->options(['low' => 'Low', 'medium' => 'Medium', 'high' => 'High'])->default('medium')->required(),
            Select::make('plain')->options($options)->required(),
            Select::make('searchable')->options($options)->searchable()->required(),
            Select::make('optional')->options($options),
        ]);
    }

    public function render(): string
    {
        return '<div>{{ $this->form }}</div>';
    }
}
