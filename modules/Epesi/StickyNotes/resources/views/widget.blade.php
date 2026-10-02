{{--
    Inline styles rather than Tailwind utilities: the panel uses Filament's
    precompiled stylesheet, which only contains the classes Filament itself
    uses (see the Messages applet).
--}}
<x-filament-widgets::widget>
    <style>
        .sticky-note-body { font-size: 0.875rem; overflow-wrap: anywhere; }
        .sticky-note-body > :first-child { margin-top: 0; }
        .sticky-note-body > :last-child { margin-bottom: 0; }
        .sticky-note-body p, .sticky-note-body ul, .sticky-note-body ol, .sticky-note-body blockquote, .sticky-note-body pre { margin: 0 0 0.5rem; }
        .sticky-note-body ul { list-style: disc; padding-left: 1.25rem; }
        .sticky-note-body ol { list-style: decimal; padding-left: 1.25rem; }
        .sticky-note-body h1, .sticky-note-body h2, .sticky-note-body h3 { font-size: 0.875rem; font-weight: 600; margin: 0 0 0.25rem; }
        .sticky-note-body a { text-decoration: underline; }
        .sticky-note-body blockquote { border-left: 3px solid color-mix(in srgb, currentColor 30%, transparent); padding: 0.25rem 0.75rem; background: rgb(0 0 0 / 0.06); border-radius: 0.25rem; }
        .sticky-note-body code { font-family: ui-monospace, monospace; font-size: 0.8125rem; }
        .sticky-note-body pre { background: rgb(255 255 255 / 0.5); border-radius: 0.25rem; padding: 0.375rem 0.5rem; max-width: 100%; overflow-x: auto; white-space: pre-wrap; overflow-wrap: anywhere; }
        .dark .sticky-note-body blockquote { background: rgb(0 0 0 / 0.15); }
        .dark .sticky-note-body pre { background: rgb(255 255 255 / 0.08); }
        .sticky-note-body pre code { white-space: inherit; overflow-wrap: inherit; }

        /* A note's small shadow, the same as the other applets'. */
        .sticky-note { min-width: 0; box-shadow: 0 1px 3px rgb(0 0 0 / 0.08), 0 2px 8px rgb(0 0 0 / 0.1); }
        .dark .sticky-note { box-shadow: 0 0 2px rgb(255 255 255 / 0.1), 0 0 8px rgb(255 255 255 / 0.1); }
        .sticky-note-title { cursor: move; }
        .sticky-note.sortable-ghost { opacity: 0.4; }
        .sticky-notes-board { display: grid; gap: 1.5rem; grid-template-columns: minmax(0, 1fr); align-items: start; }
        .sticky-notes-column { display: flex; flex-direction: column; gap: 1.5rem; min-width: 0; min-height: 3rem; }
        @media (max-width: 767px) { .sticky-notes-board:not(:has(.sortable-ghost)) > .sticky-notes-column:not(:has(> *)) { display: none; } }
        @media (min-width: 768px) { .sticky-notes-board { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
        @media (min-width: 1280px) { .sticky-notes-board { grid-template-columns: repeat(var(--sticky-notes-columns), minmax(0, 1fr)); } }

        /* The Background buttons of the note form, each in its own tint. */
        .sticky-note-colors .fi-btn { color: inherit !important; }
        .sticky-note-colors input:checked + .fi-btn { outline: 2px solid currentColor; outline-offset: 1px; font-weight: 600; }
        @foreach (\Epesi\Modules\StickyNotes\Models\StickyNote::COLORS as $color => $base)
            .sticky-note-colors input[value="{{ $color }}"] + .fi-btn { background: color-mix(in srgb, {{ $base }} 28%, transparent) !important; }
        @endforeach
    </style>

    <x-filament::section icon="heroicon-o-pencil-square" compact>
        <x-slot name="heading">
            <span style="display: inline-flex; align-items: center; gap: 0.5rem;">
                {{ __('Notes') }}
                {{ $this->addNoteAction }}
            </span>
        </x-slot>
        <x-slot name="afterHeader">
            <div style="display: flex; align-items: center; gap: 0.25rem;">
                <x-filament::input.wrapper>
                    <x-filament::input.select wire:change="setStatus($event.target.value)" aria-label="{{ __('Status') }}">
                        <option value="active" @selected($status === 'active')>{{ __('Active') }}</option>
                        <option value="inactive" @selected($status === 'inactive')>{{ __('Inactive') }}</option>
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </div>
        </x-slot>

        @php($columns = $this->columns())

        {{-- Dragged by a note's title, as an applet is by its title bar, within a column or into another; wire:sort:group stays the last wire:sort attribute. --}}
        <div class="sticky-notes-board" style="--sticky-notes-columns: {{ $this::COLUMNS }}">
            @foreach ($columns as $col => $notes)
                <div
                    wire:sort.ghost="reorder"
                    wire:sort:config="{ handle: '.sticky-note-title' }"
                    wire:sort:group-id="{{ $col }}"
                    wire:sort:group="sticky-notes"
                    class="sticky-notes-column"
                >
                    @foreach ($notes as $note)
                    <div
                        wire:key="note-{{ $note->id }}"
                        wire:sort:item="{{ $note->id }}"
                        class="sticky-note"
                        style="border-radius: 0.5rem; padding: 0.75rem 1rem; background: {{ $note->background() }};"
                    >
                        <div style="display: flex; align-items: flex-start; justify-content: space-between; gap: 0.5rem;">
                            {{-- The heading of an applet: Filament's text-base, semibold. --}}
                            <div class="sticky-note-title" style="flex: 1; font-size: 1rem; line-height: 1.5rem; font-weight: 600; overflow-wrap: anywhere;">{{ $note->title }}</div>
                            <div style="display: flex; align-items: center; flex-shrink: 0;">
                                {{ ($this->editNoteAction)(['id' => $note->id]) }}
                                {{ ($this->setActiveAction)(['id' => $note->id, 'active' => ! $note->active]) }}
                                @unless ($note->active)
                                    {{ ($this->deleteNoteAction)(['id' => $note->id]) }}
                                @endunless
                            </div>
                        </div>
                        @if (filled($note->body))
                            <div class="sticky-note-body" style="margin-top: 0.25rem;">{!! $note->bodyHtml() !!}</div>
                        @endif
                    </div>
                    @endforeach
                </div>
            @endforeach
        </div>

        @if (collect($columns)->sum(fn ($notes): int => $notes->count()) === 0)
            <p style="opacity: 0.6; margin: 0; font-size: 0.875rem;">{{ $status === 'inactive' ? __('No inactive notes.') : __('No active notes.') }}</p>
        @endif
    </x-filament::section>

    <x-filament-actions::modals />
</x-filament-widgets::widget>
