<?php

namespace Epesi\Modules\RecordBrowser\Browsing;

use App\Models\User;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\DB;

/**
 * Which records a recordset's list shows — the tabs above it, and Epesi's
 * browse-mode select (`RecordBrowser::switch_view()`). The last one chosen is
 * remembered per user and record type, as Epesi kept `<tab>_default_view` in
 * the user's settings, so it outlives the session.
 */
enum BrowseMode: string
{
    case All = 'all';
    case Favorites = 'favorites';
    case Recent = 'recent';

    public function getLabel(): string
    {
        return match ($this) {
            self::All => __('All'),
            self::Favorites => __('Favorites'),
            self::Recent => __('Recent'),
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::All => Heroicon::OutlinedQueueList,
            self::Favorites => Heroicon::OutlinedStar,
            self::Recent => Heroicon::OutlinedClock,
        };
    }

    public static function rememberedFor(User $user, string $recordType): ?self
    {
        $mode = DB::table('epesi_recordbrowser_browse_modes')
            ->where('user_id', $user->id)
            ->where('recordable_type', $recordType)
            ->value('mode');

        return $mode === null ? null : self::tryFrom($mode);
    }

    public function rememberFor(User $user, string $recordType): void
    {
        DB::table('epesi_recordbrowser_browse_modes')->upsert(
            [[
                'user_id' => $user->id,
                'recordable_type' => $recordType,
                'mode' => $this->value,
                'created_at' => now(),
                'updated_at' => now(),
            ]],
            ['user_id', 'recordable_type'],
            ['mode', 'updated_at'],
        );
    }
}
