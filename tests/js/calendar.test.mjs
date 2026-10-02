import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';
import { runInNewContext } from 'node:vm';

const source = readFileSync(new URL('../../resources/js/calendar.js', import.meta.url), 'utf8');
const start = source.indexOf('function calendarDateParts(');
const end = source.indexOf('function alternateMonthRange(', start);
const constants = source.match(/const UMM_AL_QURA_(?:MIN|MAX)_YEAR = \d+;/g).join('\n');
const helpers = runInNewContext(`${constants}
${source.slice(start, end)}
({ calendarDateInputValue, normalizeCalendarDigits, gregorianDateForCalendarDate, supportsCalendarYear })`);

for (const [type, expected] of [
    ['persian', '1403-01-01'],
    ['islamic-umalqura', '1445-09-10'],
    ['islamic-civil', '1445-09-10'],
]) {
    test(`${type} picker values round-trip across Gregorian year boundaries`, () => {
        for (const gregorianDate of ['2024-03-20', '2024-12-31', '2025-01-01']) {
            const date = new Date(`${gregorianDate}T00:00:00Z`);
            const value = helpers.calendarDateInputValue(date, type);

            assert.match(value, /^\d{4}-\d{2}-\d{2}$/);
            if (gregorianDate === '2024-03-20') {
                assert.equal(value, expected);
            }

            const [year, month, day] = value.split('-').map(Number);
            const restored = helpers.gregorianDateForCalendarDate(year, month, day, type);

            assert.ok(restored);
            assert.equal(restored.toISOString().slice(0, 10), gregorianDate);
        }
    });
}

test('calendar picker accepts Persian and Arabic-Indic digits', () => {
    assert.equal(helpers.normalizeCalendarDigits('۱۴۰۳-۰۱-۰۱'), '1403-01-01');
    assert.equal(helpers.normalizeCalendarDigits('١٤٠٣-٠١-٠١'), '1403-01-01');
});

test('calendar picker preserves leap and month boundaries', () => {
    for (const [type, cases] of [
        ['persian', [['2021-03-20', '1399-12-30'], ['2021-03-21', '1400-01-01']]],
        ['islamic-umalqura', [['2024-03-11', '1445-09-01'], ['2024-04-09', '1445-09-30'], ['2024-04-10', '1445-10-01'], ['2024-06-07', '1445-12-01']]],
        ['islamic-civil', [['2024-06-07', '1445-11-30']]],
    ]) {
        for (const [gregorian, calendarDate] of cases) {
            const value = helpers.calendarDateInputValue(new Date(`${gregorian}T00:00:00Z`), type);
            assert.equal(value, calendarDate);

            const [year, month, day] = value.split('-').map(Number);
            assert.equal(helpers.gregorianDateForCalendarDate(year, month, day, type).toISOString().slice(0, 10), gregorian);
        }
    }
});

test('calendar picker rejects invalid leap and month days', () => {
    assert.equal(helpers.gregorianDateForCalendarDate(1400, 12, 30, 'persian'), null);
    assert.equal(helpers.gregorianDateForCalendarDate(1445, 9, 31, 'islamic-umalqura'), null);
    assert.equal(helpers.gregorianDateForCalendarDate(1445, 9, 31, 'islamic-civil'), null);
});

test('Umm al-Qura picker rejects years outside the ICU table', () => {
    assert.equal(helpers.supportsCalendarYear(1300, 'islamic-umalqura'), true);
    assert.equal(helpers.supportsCalendarYear(1600, 'islamic-umalqura'), true);
    assert.equal(
        helpers.gregorianDateForCalendarDate(1300, 1, 1, 'islamic-umalqura').toISOString().slice(0, 10),
        '1882-11-12',
    );
    assert.equal(
        helpers.gregorianDateForCalendarDate(1600, 12, 30, 'islamic-umalqura').toISOString().slice(0, 10),
        '2174-11-25',
    );
    assert.equal(helpers.supportsCalendarYear(1299, 'islamic-umalqura'), false);
    assert.equal(helpers.supportsCalendarYear(1601, 'islamic-umalqura'), false);
    assert.equal(helpers.gregorianDateForCalendarDate(1601, 1, 1, 'islamic-umalqura'), null);
    assert.equal(helpers.calendarDateInputValue(new Date(Date.UTC(2174, 11, 1)), 'islamic-umalqura'), null);
});

test('Jalali and civil Hijri reverse lookup cover MySQL date endpoints', () => {
    for (const [type, expected] of [
        ['persian', [['1000-01-01', '0378-10-11'], ['9999-12-31', '9378-10-10']]],
        ['islamic-civil', [['1000-01-01', '0390-01-15'], ['9999-12-31', '9666-04-02']]],
    ]) {
        for (const [gregorian, calendarDate] of expected) {
            const date = new Date(`${gregorian}T00:00:00Z`);
            const value = helpers.calendarDateInputValue(date, type);
            assert.equal(value, calendarDate);

            const [year, month, day] = value.split('-').map(Number);
            assert.equal(
                helpers.gregorianDateForCalendarDate(year, month, day, type).toISOString().slice(0, 10),
                gregorian,
            );
        }
    }
});