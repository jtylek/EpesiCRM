<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Mirrors Epesi's CRM/Priority CommonData list (see CRM_Common's CommonInstall.php),
 * shared by PhoneCall/Task/Meeting.
 */
enum RecordPriority: int implements HasColor, HasLabel
{
    case Low = 0;
    case Medium = 1;
    case High = 2;

    public function getLabel(): string
    {
        return match ($this) {
            self::Low => __('Low'),
            self::Medium => __('Medium'),
            self::High => __('High'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Low => 'gray',
            self::Medium => 'warning',
            self::High => 'danger',
        };
    }
}
