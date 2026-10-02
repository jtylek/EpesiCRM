// Adds a clock face to Filament's date-time picker, in a column of its own beside
// the calendar. The picker's hour/minute number inputs stay (they are what Alpine
// reads); the clock just writes to them and mirrors them. Click or drag the face:
// the hour first (outer ring 1–12, inner ring 13–00), then the minute.

const NS = 'http://www.w3.org/2000/svg';
const SIZE = 176;
const C = SIZE / 2;
const R_OUTER = 68;
const R_INNER = 42;
const SPLIT = 55;

const pad = (n) => String(n).padStart(2, '0');
const el = (name, attrs = {}, text) => {
    const node = document.createElementNS(NS, name);
    Object.entries(attrs).forEach(([key, value]) => node.setAttribute(key, value));
    if (text !== undefined) {
        node.textContent = text;
    }

    return node;
};
const at = (deg, r) => [C + r * Math.sin((deg * Math.PI) / 180), C - r * Math.cos((deg * Math.PI) / 180)];

const clocks = new Set();

function enhance(box) {
    box.dataset.clock = '1';

    const [hourInput, minuteInput] = box.querySelectorAll('input[type="number"]');

    if (!hourInput || !minuteInput) {
        return;
    }

    let mode = 'hour';

    const svg = el('svg', { viewBox: `0 0 ${SIZE} ${SIZE}`, class: 'epesi-clock', role: 'group' });
    const face = el('circle', { cx: C, cy: C, r: C - 2, class: 'epesi-clock-face' });
    const hand = el('line', { x1: C, y1: C, class: 'epesi-clock-hand' });
    const knob = el('circle', { r: 14, class: 'epesi-clock-knob' });
    const numbers = el('g');
    svg.append(face, hand, knob, el('circle', { cx: C, cy: C, r: 2.5, class: 'epesi-clock-hub' }), numbers);
    box.append(svg);

    const hour = () => Math.min(23, Math.max(0, parseInt(hourInput.value, 10) || 0));
    const minute = () => Math.min(59, Math.max(0, parseInt(minuteInput.value, 10) || 0));

    const draw = () => {
        const h = hour();
        const m = minute();
        let angle;
        let radius;

        numbers.replaceChildren();

        if (mode === 'hour') {
            const inner = h === 0 || h > 12;
            angle = (h % 12) * 30;
            radius = inner ? R_INNER : R_OUTER;

            for (let i = 1; i <= 12; i++) {
                const [ox, oy] = at(i * 30, R_OUTER);
                const [ix, iy] = at(i * 30, R_INNER);
                numbers.append(
                    el('text', { x: ox, y: oy, class: 'epesi-clock-number' + (!inner && i % 12 === h % 12 ? ' is-selected' : '') }, String(i)),
                    el('text', { x: ix, y: iy, class: 'epesi-clock-number is-inner' + (inner && i % 12 === h % 12 ? ' is-selected' : '') }, pad(i === 12 ? 0 : i + 12)),
                );
            }
        } else {
            angle = m * 6;
            radius = R_OUTER;

            for (let i = 0; i < 12; i++) {
                const [x, y] = at(i * 30, R_OUTER);
                numbers.append(el('text', { x, y, class: 'epesi-clock-number' + (m === i * 5 ? ' is-selected' : '') }, pad(i * 5)));
            }
        }

        const [x, y] = at(angle, radius);
        hand.setAttribute('x2', x);
        hand.setAttribute('y2', y);
        knob.setAttribute('cx', x);
        knob.setAttribute('cy', y);
        box.dataset.mode = mode;
    };

    const write = (input, value) => {
        input.value = String(value);
        input.dispatchEvent(new Event('input', { bubbles: true }));
    };

    const pick = (event) => {
        const rect = svg.getBoundingClientRect();
        const dx = ((event.clientX - rect.left) / rect.width) * SIZE - C;
        const dy = ((event.clientY - rect.top) / rect.height) * SIZE - C;
        const angle = ((Math.atan2(dx, -dy) * 180) / Math.PI + 360) % 360;

        if (mode === 'hour') {
            const index = Math.round(angle / 30) % 12;
            const inner = Math.hypot(dx, dy) < SPLIT;
            write(hourInput, inner ? (index === 0 ? 0 : index + 12) : (index === 0 ? 12 : index));
        } else {
            const step = parseInt(minuteInput.step, 10) || 1;
            write(minuteInput, (Math.round(Math.round(angle / 6) / step) * step) % 60);
        }

        draw();
    };

    let dragging = false;
    svg.addEventListener('pointerdown', (event) => {
        dragging = true;
        svg.setPointerCapture(event.pointerId);
        pick(event);
    });
    svg.addEventListener('pointermove', (event) => dragging && pick(event));
    svg.addEventListener('pointerup', () => {
        if (dragging && mode === 'hour') {
            mode = 'minute';
            draw();
        }
        dragging = false;
    });

    hourInput.addEventListener('focus', () => { mode = 'hour'; draw(); });
    minuteInput.addEventListener('focus', () => { mode = 'minute'; draw(); });
    [hourInput, minuteInput].forEach((input) => input.addEventListener('input', draw));

    let last = '';
    clocks.add(() => {
        if (!box.isConnected) {
            return false;
        }

        // Alpine sets the inputs' values without an event: mirror them while shown.
        const now = hourInput.value + ':' + minuteInput.value;
        if (box.offsetParent !== null && now !== last) {
            last = now;
            draw();
        }

        return true;
    });
    draw();
}

// A close button on the corner of every picker panel (date-only ones too).
function addClose(panel) {
    panel.dataset.close = '1';

    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'epesi-picker-close';
    button.setAttribute('aria-label', 'Close');
    const icon = document.createElementNS(NS, 'svg');
    icon.setAttribute('viewBox', '0 0 24 24');
    icon.setAttribute('fill', 'none');
    icon.setAttribute('stroke', 'currentColor');
    icon.setAttribute('stroke-width', '2');
    const path = document.createElementNS(NS, 'path');
    path.setAttribute('d', 'M6 18 18 6M6 6l12 12');
    path.setAttribute('stroke-linecap', 'round');
    icon.append(path);
    button.append(icon);

    button.addEventListener('click', () => {
        const root = panel.closest('[x-data]');
        const data = root && window.Alpine ? window.Alpine.$data(root) : null;

        if (data?.isOpen?.()) {
            data.togglePanelVisibility();
        }
    });

    panel.append(button);
}

function scan() {
    document.querySelectorAll('.fi-fo-date-time-picker-panel:not([data-close])').forEach(addClose);
    document.querySelectorAll('.fi-fo-date-time-picker-time-inputs:not([data-clock])').forEach(enhance);
}

let queued = false;
new MutationObserver(() => {
    if (!queued) {
        queued = true;
        requestAnimationFrame(() => { queued = false; scan(); });
    }
}).observe(document.documentElement, { childList: true, subtree: true });

setInterval(() => clocks.forEach((tick) => tick() || clocks.delete(tick)), 250);
scan();
