import { test } from 'node:test';
import assert from 'node:assert/strict';

globalThis.window = {};
globalThis.CSS = { escape: value => value.replaceAll(':', '\\:') };
await import('../../modules/Epesi/RecordBrowser/resources/js/click-to-fill.js');

test('splits pasted text and preserves selection order, including duplicate words', () => {
    const state = window.epesiClickToFill();
    state.source = 'Jan, Kowalski\r\nJan\t<hello>';
    state.scan();
    assert.deepEqual(state.words, ['Jan', 'Kowalski', 'Jan', '<hello>']);
    state.select(2);
    state.select(1);
    state.select(2);
    state.select(0);
    assert.deepEqual(state.selected.map(index => state.words[index]), ['Kowalski', 'Jan']);
    state.scan();
    assert.deepEqual(state.selected, []);
});

test('fills only editable model fields in the same form and emits Livewire input events', () => {
    const form = {};
    const component = {};
    globalThis.HTMLInputElement = class {
        type = 'text';
        value = 'Old';
        attributes = [{ name: 'wire:model' }];
        events = [];
        closest(selector) {
            if (selector === 'form') return this.form ?? form;
            if (selector === '[wire\\:id]') return component;
            return null;
        }
        dispatchEvent(event) { this.events.push(event.type); }
    };
    globalThis.HTMLTextAreaElement = class extends HTMLInputElement {};
    const state = window.epesiClickToFill();
    state.$el = { contains: () => false, closest: selector => selector === 'form' ? form : component };
    state.open = true;
    state.source = 'New value';
    state.scan();
    state.select(0);
    state.select(1);
    for (const overrides of [{ disabled: true }, { readOnly: true }, { type: 'password' }, { form: {} }, { attributes: [] }]) {
        const field = Object.assign(new HTMLInputElement(), overrides);
        state.fill({ target: field });
        assert.equal(field.value, 'Old');
        assert.equal(state.selected.length, 2);
    }
    const field = new HTMLTextAreaElement();
    state.fill({ target: field });
    assert.equal(field.value, 'New value');
    assert.deepEqual(field.events, ['input', 'change']);
    assert.deepEqual(state.selected, []);
});
