window.epesiClickToFill = () => ({
    open: false,
    scanned: false,
    source: '',
    words: [],
    selected: [],

    scan() {
        this.words = this.source.split(/[,\s]+/u).filter(Boolean);
        this.selected = [];
        this.scanned = true;
    },

    select(index) {
        const position = this.selected.indexOf(index);
        if (position === -1) this.selected.push(index);
        else this.selected.splice(position, 1);
    },

    fill(event) {
        if (!this.open || !this.scanned || !this.selected.length) return;

        const field = event.target;
        if (!(field instanceof HTMLInputElement || field instanceof HTMLTextAreaElement)) return;
        if (field instanceof HTMLInputElement && !['text', 'email', 'tel', 'url'].includes(field.type)) return;
        if (field.disabled || field.readOnly || field.closest('[inert]') || this.$el.contains(field)) return;

        // Keep page forms and action modals separate, including nested Livewire components.
        const scope = this.$el.closest('form') ?? this.$el.closest('.fi-modal');
        if (!scope || (field.closest('form') ?? field.closest('.fi-modal')) !== scope) return;
        const componentSelector = `[${CSS.escape('wire:id')}]`;
        if (field.closest(componentSelector) !== this.$el.closest(componentSelector)) return;
        if (!Array.from(field.attributes).some(attribute => attribute.name.startsWith('wire:model'))) return;

        field.value = this.selected.map(index => this.words[index]).join(' ');
        field.dispatchEvent(new Event('input', { bubbles: true }));
        field.dispatchEvent(new Event('change', { bubbles: true }));
        this.selected = [];
    },
});
