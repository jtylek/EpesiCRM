<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Mirrors Epesi's CRM/Access CommonData list (see CommonInstall.php), which
 * every CRM record's "Permission" field draws from and which the original
 * ACL crits key off (e.g. "(!permission" => 2 meaning "not Private").
 */
enum RecordPermission: int implements HasColor, HasLabel
{
    case Public = 0;
    case PublicReadOnly = 1;
    case Private = 2;

    public function getLabel(): string
    {
        return match ($this) {
            self::Public => __('Public'),
            self::PublicReadOnly => __('Public, Read-Only'),
            self::Private => __('Private'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Public => 'success',
            self::PublicReadOnly => 'warning',
            self::Private => 'danger',
        };
    }
}
