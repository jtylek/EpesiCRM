<?php

namespace Epesi\Modules\Followup;

use App\Enums\RecordPermission;
use App\Enums\RecordPriority;
use App\Enums\RecordStatus;
use Carbon\CarbonInterface;
use Epesi\Modules\Attachments\Models\Attachment;
use Epesi\Modules\CRM\Meetings\Models\Meeting;
use Epesi\Modules\CRM\PhoneCalls\Models\PhoneCall;
use Epesi\Modules\CRM\Tasks\Models\Task;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Closing an activity and scheduling the next — the port of
 * CRM_FollowupCommon and the "Follow-up" leightbox Tasks, Meetings and Phone
 * Calls show when their status is changed.
 *
 * The follow-up inherits who and what the original was about: its title,
 * permission, priority, employees and customers (contacts and companies).
 * With the Attachments module present, both records get a tracing note
 * pointing at the other, as add_tracing_notes() did.
 */
class Followup
{
    /** @var array<string, class-string<Model>> */
    public const TYPES = [
        'task' => Task::class,
        'meeting' => Meeting::class,
        'phone_call' => PhoneCall::class,
    ];

    public static function isOpen(Model $record): bool
    {
        return ! in_array($record->status, [RecordStatus::Closed, RecordStatus::Canceled], true);
    }

    /**
     * @return Model|null the follow-up created, if one was asked for
     */
    public static function close(
        Model $record,
        RecordStatus $status = RecordStatus::Closed,
        ?string $note = null,
        ?string $followupType = null,
        ?string $followupTitle = null,
        ?CarbonInterface $when = null,
    ): ?Model {
        return DB::transaction(function () use ($record, $status, $note, $followupType, $followupTitle, $when): ?Model {
            $record->update(['status' => $status]);

            if (filled($note) && static::attachmentsAvailable()) {
                Attachment::addTo($record, e($note));
            }

            if ($followupType === null || ! isset(static::TYPES[$followupType])) {
                return null;
            }

            $followup = static::createFollowup($record, $followupType, $followupTitle ?: static::titleOf($record), $when ?? now()->addDay());

            if (static::attachmentsAvailable()) {
                Attachment::addTo($followup, __('Follow-up after: :record', ['record' => static::describe($record)]));
                Attachment::addTo($record, __('Follow-up: :record', ['record' => static::describe($followup)]));
            }

            return $followup;
        });
    }

    public static function titleOf(Model $record): string
    {
        return (string) ($record instanceof PhoneCall ? $record->subject : $record->title);
    }

    protected static function describe(Model $record): string
    {
        return __(Str::headline($record->getMorphClass())).' "'.e(static::titleOf($record)).'"';
    }

    protected static function createFollowup(Model $source, string $type, string $title, CarbonInterface $when): Model
    {
        $common = [
            // An in-memory source created without these carries nulls
            // rather than the column defaults, so fall back to them here.
            'permission' => $source->permission ?? RecordPermission::Public,
            'priority' => $source->priority ?? RecordPriority::Medium,
            'status' => RecordStatus::Open,
        ];

        $employees = $source->employees()->pluck('contacts.id')->all();
        $customers = static::customerContactIds($source);
        $companies = static::customerCompanyIds($source);

        $followup = match ($type) {
            'task' => Task::create([...$common, 'title' => $title, 'deadline' => $when, 'timeless' => false]),
            'meeting' => Meeting::create([...$common, 'title' => $title, 'date' => $when->toDateString(), 'time' => $when->format('H:i:s')]),
            'phone_call' => PhoneCall::create([
                ...$common,
                'subject' => $title,
                'called_at' => $when,
                'contact_id' => $customers[0] ?? null,
                'company_id' => $companies[0] ?? null,
            ]),
        };

        $followup->employees()->sync($employees);

        if (! $followup instanceof PhoneCall) {
            $followup->customers()->sync($customers);
            $followup->customerCompanies()->sync($companies);
        }

        return $followup;
    }

    /**
     * @return array<int, int>
     */
    protected static function customerContactIds(Model $source): array
    {
        return $source instanceof PhoneCall
            ? array_values(array_filter([$source->contact_id]))
            : $source->customers()->pluck('contacts.id')->all();
    }

    /**
     * @return array<int, int>
     */
    protected static function customerCompanyIds(Model $source): array
    {
        return $source instanceof PhoneCall
            ? array_values(array_filter([$source->company_id]))
            : $source->customerCompanies()->pluck('companies.id')->all();
    }

    /**
     * The Attachments module is optional: its classes only autoload while it
     * is enabled (ModuleServiceProvider registers PSR-4 per enabled module).
     */
    protected static function attachmentsAvailable(): bool
    {
        return class_exists(Attachment::class);
    }
}
