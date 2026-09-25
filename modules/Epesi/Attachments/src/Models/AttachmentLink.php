<?php

namespace Epesi\Modules\Attachments\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * One row of Epesi's "Attached to" multiselect: this note, on that record.
 */
class AttachmentLink extends Model
{
    protected $table = 'epesi_attachment_links';

    protected $fillable = [
        'attachment_id',
        'attachable_type',
        'attachable_id',
    ];

    /**
     * @return BelongsTo<Attachment, $this>
     */
    public function attachment(): BelongsTo
    {
        return $this->belongsTo(Attachment::class);
    }

    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }
}
