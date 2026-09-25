<?php

namespace Epesi\Modules\Mail\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * An additional address of a contact or company — an `rc_multiple_emails`
 * record. Unique across the system, so an address identifies one record.
 */
class MailAddress extends Model
{
    protected $table = 'epesi_mail_addresses';

    protected $fillable = [
        'addressable_type',
        'addressable_id',
        'email',
    ];

    protected static function booted(): void
    {
        static::saving(function (MailAddress $address): void {
            $address->email = mb_strtolower(trim($address->email));
        });
    }

    public function addressable(): MorphTo
    {
        return $this->morphTo();
    }
}
