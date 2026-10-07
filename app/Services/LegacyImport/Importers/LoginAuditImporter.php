<?php

namespace App\Services\LegacyImport\Importers;

use App\Models\LoginAudit;
use App\Models\User;
use App\Services\LegacyImport\ImportSummary;
use App\Services\LegacyImport\LegacyIdMap;
use Illuminate\Support\Facades\DB;

/**
 * The legacy `base_login_audit` (one row per login session), upserted on the
 * legacy id so a re-run updates instead of duplicating. Sessions recorded by
 * this app itself (no legacy_id) are left alone. Needs the `users` tab: a
 * session of a user that wasn't imported is kept with no user, its login name
 * unknown, as the audit table allows.
 */
class LoginAuditImporter
{
    public function run(bool $withHistory = true): ImportSummary
    {
        $summary = new ImportSummary;
        $legacy = DB::connection('legacy');

        if (! $legacy->getSchemaBuilder()->hasTable('base_login_audit')) {
            $summary->warn('No base_login_audit table in the legacy database.');

            return $summary;
        }

        $users = LegacyIdMap::for(User::class);
        $logins = $legacy->table('user_login')->pluck('login', 'id');
        $existing = LoginAudit::query()->whereNotNull('legacy_id')->pluck('id', 'legacy_id');

        foreach ($legacy->table('base_login_audit')->orderBy('id')->cursor() as $row) {
            $audit = isset($existing[$row->id]) ? LoginAudit::query()->find($existing[$row->id]) : new LoginAudit;
            $audit->exists ? $summary->updated++ : $summary->created++;

            $audit->forceFill([
                'legacy_id' => (int) $row->id,
                'user_id' => $users->get($row->user_login_id === null ? null : (int) $row->user_login_id),
                'login' => $logins[$row->user_login_id] ?? null,
                'started_at' => $row->start_time,
                'ended_at' => $row->end_time ?? $row->start_time,
                'ip_address' => $row->ip_address ?? '',
                'host_name' => $row->host_name,
                'device' => $row->device,
            ])->save();
        }

        return $summary;
    }
}
