<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Mirrors Epesi's CRM/Status CommonData list (see CRM_Common's CommonInstall.php),
 * shared by PhoneCall/Task/Meeting the way RecordPermission is. Order is
 * load-bearing: CRM_CommonCommon::STATUS_CLOSED = 3 in the source app.
 */
enum RecordStatus: int implements HasColor, HasLabel
{
    case Open = 0;
    case InProgress = 1;
    case OnHold = 2;
    case Closed = 3;
    case Canceled = 4;

    /**
     * Done with, one way or the other — what a list leaves out by default.
     *
     * @return array<int, self>
     */
    public static function finished(): array
    {
        return [self::Closed, self::Canceled];
    }

    /**
     * From lang/<locale>/record_labels.php rather than the JSON files: "Open"
     * the status and "Open" the button are different words in Polish.
     */
    public function getLabel(): string
    {
        return match ($this) {
            self::Open => __('record_labels.status.open'),
            self::InProgress => __('record_labels.status.in_progress'),
            self::OnHold => __('record_labels.status.on_hold'),
            self::Closed => __('record_labels.status.closed'),
            self::Canceled => __('record_labels.status.canceled'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Open => 'info',
            self::InProgress => 'warning',
            self::OnHold => 'gray',
            self::Closed => 'success',
            self::Canceled => 'danger',
        };
    }
}
