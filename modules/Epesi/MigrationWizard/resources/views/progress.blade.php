@php
    $s = $status ?? [];
    $phases = ['detected' => 'Legacy installation found', 'backed_up' => 'Your backup confirmed', 'tables_namespaced' => 'Old tables renamed', 'epesi_installed' => 'Epesi 3 installed', 'importing' => 'Importing records and files', 'verified' => 'Verified', 'cleaned' => 'Cleaned up', 'complete' => 'Complete', 'core_complete_with_preserved_data' => 'Complete (some data kept)'];
    $labels = array_keys($phases);
    // "frozen" is an internal step that is over in an instant; it is shown as the step after it.
    $current = ($s['phase'] ?? '') === 'frozen' ? 'tables_namespaced' : ($s['phase'] ?? '');
    $position = array_search($current, $labels, true);
    // What Continue does next, once the records are in and the administrator is set: verification (if it has not
    // passed yet) and then the cleanup, in one go.
    $importDone = ! empty($s['import_finished_text']) && ! ($s['awaiting_admin_handoff'] ?? false);
    $nextStep = ($s['phase'] ?? '') === 'verified' || (($s['phase'] ?? '') === 'importing' && $importDone) ? 'Data cleanup' : null;
@endphp

<div class="space-y-4 text-sm" @if ($running) wire:poll.2s.keep-alive="advance" @endif>
    @if (true)
        <h2 class="text-base font-semibold">Where the migration stands</h2>

        @if (! empty($s['failed_message']))
            <div class="rounded-lg border border-danger-500 p-3 text-danger-700" role="alert" style="white-space: pre-line">{{ $s['failed_message'] }}</div>
            <p>This page cannot continue the migration. Restore your backup as described in the Upgrade Guide.</p>
        @else
            <ol class="list-decimal space-y-1 pl-5">
                @foreach ($phases as $key => $text)
                    @continue(in_array($key, ['complete', 'core_complete_with_preserved_data'], true) && $current !== $key)
                    <li @class(['font-semibold' => $current === $key, 'text-gray-500' => $position !== false && array_search($key, $labels, true) > $position])>{{ $text }}@if ($current === $key) &larr; now @endif</li>
                @endforeach
            </ol>

            <ul class="space-y-1">
                @if (! empty($s['import_started_text']))<li>Import started: {{ $s['import_started_text'] }}</li>@endif
                @if (! empty($s['import_finished_text']))<li>Data migration finished: {{ $s['import_finished_text'] }}</li>@endif
                @if (! empty($s['import_duration_text']))<li>Migration completed in: {{ $s['import_duration_text'] }}</li>@endif
                <li @if (! empty($s['import_started_text']) || ! empty($s['import_finished_text'])) style="margin-top: .75rem" @endif>Importers finished: {{ implode(', ', $s['completed_importers'] ?? []) ?: 'none yet' }}</li>
                @if (! empty($s['obsolete_tables_dropped']))<li>Old tables dropped: {{ implode(', ', $s['obsolete_tables_dropped']) }}</li>@endif
            </ul>

            @if (($s['can_continue'] ?? false) && ! ($s['preupgrade_backup_confirmed'] ?? false) && in_array($s['phase'] ?? '', ['detected', 'assessed', 'backed_up'], true))
                <div class="space-y-2">
                    <p><strong>Nothing is changed until you confirm</strong> that you made a full backup of the database and of the whole Epesi folder yourself (Softaculous or another tool) BEFORE this step. The migration does not make a backup. A failed migration is fixed by restoring that backup; there is no other complete way back.</p>
                    <input type="text" wire:model="typed" placeholder='Type: backed up' class="fi-input block w-full rounded-lg border px-3 py-2">
                    <x-filament::button wire:click="confirmBackup" wire:loading.attr="disabled">Confirm my backup</x-filament::button>
                </div>
            @endif

            @if (! empty($administrators) && ($s['awaiting_admin_handoff'] ?? false))
                <div class="space-y-2">
                    <p><strong>The records are imported.</strong> Choose the legacy administrator who will own the installation. Old passwords are not carried over: set a new one.</p>
                    {{-- Explicit colours: the native drop-down list ignores the dark theme and showed white on white. --}}
                    <select wire:model="adminId" aria-label="Legacy administrator" class="fi-input block w-full rounded-lg border px-3 py-2" style="color-scheme: light; background-color: #fff; color: #111827">
                        <option value="" style="background-color: #fff; color: #111827">Choose...</option>
                        @foreach ($administrators as $admin)
                            <option value="{{ $admin['id'] }}" style="background-color: #fff; color: #111827">{{ $admin['login'] }} (#{{ $admin['id'] }})</option>
                        @endforeach
                    </select>
                    <input type="password" wire:model="password" placeholder="New password (8 characters or more)" class="fi-input block w-full rounded-lg border px-3 py-2" autocomplete="new-password">
                    <input type="password" wire:model="passwordAgain" placeholder="Repeat the password" class="fi-input block w-full rounded-lg border px-3 py-2" autocomplete="new-password">
                    @if ($this->adminError)
                        <div class="rounded-lg border border-danger-500 p-3 text-danger-700" role="alert">{{ $this->adminError }}</div>
                    @endif
                    <x-filament::button wire:click="saveAdministratorAndContinue" wire:loading.attr="disabled">Save password and continue</x-filament::button>
                </div>
            @endif


            @if ($message)
                <div @class(['rounded-lg border p-3', 'border-danger-500 text-danger-700' => in_array($outcome, ['stopped', 'failed'], true)]) style="white-space: pre-line">{{ $message }}</div>
            @endif

            {{-- Continue only once the backup is confirmed: can_continue alone is also true in 'detected'/'assessed',
                 before confirmation, which would otherwise show this block next to the backup form above. --}}
            @if (($s['can_continue'] ?? false) && ($s['preupgrade_backup_confirmed'] ?? false))
                @if ($running)
                    {{-- The status lines are refreshed every 2 s from progress.json, apart from Livewire: Livewire does not poll
                         while one of its own long steps (a verification, a cleanup) is still running. --}}
                    <div class="space-y-1"
                        x-data="{
                            p: @js(['line' => $progressLine, 'speed' => $progressSpeed, 'estimate' => $progressEstimate]),
                            timer: null,
                            init() {
                                this.timer = setInterval(() => fetch(@js($progressJsonUrl), { headers: { Accept: 'application/json' }, cache: 'no-store' })
                                    .then((r) => r.ok ? r.json() : null)
                                    .then((d) => { if (d) this.p = d })
                                    .catch(() => {}), 2000)
                            },
                            destroy() { clearInterval(this.timer) },
                        }">
                        <h3 class="flex items-center gap-2 font-semibold">Status <x-filament::loading-indicator class="h-5 w-5" /></h3>
                        <p>Please wait...</p>
                        {{-- wire:ignore: Livewire's own refresh must not overwrite what progress.json just filled in. --}}
                        <div wire:ignore class="space-y-1">
                            <p x-show="p.line" x-text="p.line">{{ $progressLine }}</p>
                            <p x-show="p.speed">Estimated speed: <span x-text="p.speed">{{ $progressSpeed }}</span></p>
                            <p x-show="p.estimate">Estimated time for this step: <span x-text="p.estimate">{{ $progressEstimate }}</span></p>
                        </div>
                        {{-- A background run is announced once, in the message above. --}}
                        @if (empty($background))
                            <p style="margin-top: .75rem"><strong>Please keep this browser window open, and the computer awake, until the migration finishes.</strong> The migration goes on only while this page is open: a hidden or minimised window, a locked screen or a sleeping computer slows it down or pauses it.</p>
                            <p class="text-gray-500" style="margin-top: .75rem">Nothing is lost if it stops: reopen this page and press Continue, and it goes on from where it was.</p>
                        @endif
                    </div>
                @elseif (! ($s['awaiting_admin_handoff'] ?? false))
                    @if (! empty($backgroundOffer))
                        @if ($backgroundOffer['available'])
                            <p>This server can run the migration as a <strong>background process</strong>. After you press Continue it keeps going on the server even if you close this page or the browser stops responding. Come back to this page to see how it is going.</p>
                        @else
                            <p>This server cannot run the migration in the background ({{ $backgroundOffer['reason'] }}). It will run in short steps from this page: <strong>keep this page open</strong> until it finishes. If it stops, reopen the page and press Continue: it goes on where it was. You can also run <code>{{ $backgroundOffer['command'] }}</code> on the server (command line or a cron job).</p>
                        @endif
                    @endif
                    @if ($nextStep)
                        <p><strong>Next step: {{ $nextStep }}</strong></p>
                    @endif
                    <x-filament::button wire:click="start" wire:loading.attr="disabled">Continue</x-filament::button>
                @endif
            @endif

            @if (! empty($s['finished']))
                <p><strong>The migration is finished.</strong></p>
            @endif

            @if (! empty($s['has_report']))
                <p>
                    <a class="fi-link underline" href="{{ $reportUrl }}" target="_blank" rel="noopener">Open the migration report in a new window</a> (modules, tables and record counts).<br>
                    Detailed log with the warnings per module (migration_log.txt):
                    <a class="fi-link underline" href="{{ $logUrl }}" target="_blank" rel="noopener">open in a new window</a> or
                    <a class="fi-link underline" href="{{ $logDownloadUrl }}">download</a>.
                </p>
            @endif
        @endif
    @endif
</div>
