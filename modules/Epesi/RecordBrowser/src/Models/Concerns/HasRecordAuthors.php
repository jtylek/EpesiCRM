<?php

namespace Epesi\Modules\RecordBrowser\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

/** Requires created_by and LogsActivity; authorship does not imply visibility rules. */
trait HasRecordAuthors
{
    protected static function bootHasRecordAuthors(): void
    {
        static::creating(function ($model): void {
            $model->created_by ??= Auth::id();
        });
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function lastUpdater(): ?User
    {
        return $this->activities()->latest('created_at')->first()?->causer;
    }
}
