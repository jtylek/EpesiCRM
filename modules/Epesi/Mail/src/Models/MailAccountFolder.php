<?php

namespace Epesi\Modules\Mail\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * How far the fetcher has read one IMAP folder: the highest UID seen, valid
 * only while the folder's UIDVALIDITY is unchanged.
 */
class MailAccountFolder extends Model
{
    protected $table = 'epesi_mail_account_folders';

    protected $fillable = [
        'account_id',
        'folder',
        'uid_validity',
        'last_uid',
    ];

    protected function casts(): array
    {
        return [
            'uid_validity' => 'integer',
            'last_uid' => 'integer',
        ];
    }
}
