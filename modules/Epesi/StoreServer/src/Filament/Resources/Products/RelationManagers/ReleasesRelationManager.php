<?php

namespace Epesi\Modules\StoreServer\Filament\Resources\Products\RelationManagers;

use App\Filament\Concerns\TranslatesRelationManagerLabels;
use App\Services\Modules\ModuleException;
use Epesi\Modules\StoreServer\Models\Product;
use Epesi\Modules\StoreServer\Services\ReleasePublisher;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * Releases are never typed in by hand — version, core constraint, checksum and
 * size all come out of the uploaded package's own module.json, which is what
 * keeps the catalog honest about what it is serving.
 */
class ReleasesRelationManager extends RelationManager
{
    use TranslatesRelationManagerLabels;

    protected static string $relationship = 'releases';

    protected static ?string $title = 'Releases';

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('version')
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('version')->badge(),
                TextColumn::make('epesi_core')->label('Core'),
                TextColumn::make('size')
                    ->formatStateUsing(fn (int $state): string => round($state / 1024).' KB'),
                TextColumn::make('sha256')
                    ->label('SHA-256')
                    ->limit(12)
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('is_published')->label('Published')->boolean(),
                TextColumn::make('created_at')->label('Published at')->dateTime(),
            ])
            ->filters([])
            ->headerActions([
                $this->publishAction(),
            ])
            ->recordActions([
                DeleteAction::make(),
            ])
            ->toolbarActions([]);
    }

    protected function publishAction(): Action
    {
        return Action::make('publish')
            ->label('Publish release')
            ->icon(Heroicon::OutlinedArrowUpTray)
            ->schema([
                FileUpload::make('package')
                    ->label('Module package (.zip)')
                    ->acceptedFileTypes(['application/zip', 'application/x-zip-compressed', 'application/octet-stream'])
                    ->storeFiles(false)
                    ->required(),
                Textarea::make('changelog')
                    ->rows(4),
            ])
            ->action(function (array $data): void {
                $upload = is_array($data['package']) ? reset($data['package']) : $data['package'];

                if (! $upload instanceof TemporaryUploadedFile) {
                    Notification::make()->danger()->title(__('Upload failed'))->send();

                    return;
                }

                /** @var Product $product */
                $product = $this->getOwnerRecord();

                try {
                    $release = app(ReleasePublisher::class)->publish($product, $upload->getRealPath(), $data['changelog'] ?? null);
                } catch (ModuleException $exception) {
                    Notification::make()
                        ->danger()
                        ->title(__('Release not published'))
                        ->body($exception->getMessage())
                        ->persistent()
                        ->send();

                    return;
                }

                Notification::make()
                    ->success()
                    ->title(__('Published :module :version', ['module' => $product->module_id, 'version' => $release->version]))
                    ->send();
            });
    }

    public function isReadOnly(): bool
    {
        return false;
    }
}
