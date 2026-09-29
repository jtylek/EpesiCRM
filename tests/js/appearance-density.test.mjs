import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';
import { runInNewContext } from 'node:vm';

const source = readFileSync(new URL('../../app/Support/Appearance/CurrentTheme.php', import.meta.url), 'utf8');
const script = source.match(/<script>([\s\S]*?)<\/script>/)[1];

test('density and font size survive HTML attribute replacement and follow each destination', () => {
    const classes = new Set(['fi', 'dark']);
    const listeners = new Map();
    let density = 'compact';
    let fontSize = 'default';
    const document = {
        querySelector: (selector) => {
            if (selector === 'meta[name="epesi-density"]') {
                return density === null ? null : { content: density };
            }

            if (selector === 'meta[name="epesi-font-size"]') {
                return fontSize === null ? null : { content: fontSize };
            }

            return null;
        },
        documentElement: {
            classList: {
                toggle: (name, enabled) => enabled ? classes.add(name) : classes.delete(name),
            },
        },
        addEventListener: (name, listener) => listeners.set(name, listener),
    };

    runInNewContext(script, { document });
    assert.ok(classes.has('epesi-compact'), 'initial load is compact');
    assert.ok(!classes.has('epesi-font-sm') && !classes.has('epesi-font-lg'), 'initial load is default font size');

    // Livewire replaces html attributes and head metadata without rerunning
    // an identical head script. Back/forward visits use the same event.
    for (const destination of ['compact', 'compact', 'comfortable', 'compact', null]) {
        classes.delete('epesi-compact');
        density = destination;
        listeners.get('livewire:navigated')();
        assert.equal(classes.has('epesi-compact'), destination === 'compact');
        assert.ok(classes.has('dark'), 'density must preserve dark mode');
    }

    density = 'comfortable';
    classes.add('epesi-compact');
    listeners.get('livewire:navigated')();
    assert.equal(classes.has('epesi-compact'), false, 'comfortable clears stale compact state');

    for (const destination of ['small', 'small', 'large', 'default', null]) {
        fontSize = destination;
        listeners.get('livewire:navigated')();
        assert.equal(classes.has('epesi-font-sm'), destination === 'small');
        assert.equal(classes.has('epesi-font-lg'), destination === 'large');
    }

    fontSize = 'large';
    classes.add('epesi-font-sm');
    listeners.get('livewire:navigated')();
    assert.equal(classes.has('epesi-font-sm'), false, 'large clears stale small state');
    assert.ok(classes.has('epesi-font-lg'));
});
