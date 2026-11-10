/**
 * Run with plain Node (this project has no JS test runner):
 *
 *     node --test resources/js/i18n/text.test.mjs
 */
import assert from 'node:assert/strict';
import { test } from 'node:test';
import { formatMoney, ordinal, splitMarkers, stripNote } from './text.ts';

test('a translator note is never part of the text', () => {
    assert.equal(stripNote('Nombre||nombre completo del atleta'), 'Nombre');
    assert.equal(stripNote('Meta ||línea de meta'), 'Meta');
    assert.equal(stripNote('Sin nota'), 'Sin nota');
    assert.equal(
        stripNote('{count} foto|{count} fotos||en una historia'),
        '{count} foto|{count} fotos',
    );
});

test('a sentence is cut at its markers, in the order of the translation', () => {
    assert.deepEqual(splitMarkers('¿Ya tienes cuenta? {link}', ['link']), [
        { type: 'text', value: '¿Ya tienes cuenta? ' },
        { type: 'slot', name: 'link' },
    ]);
    assert.deepEqual(splitMarkers('{link} to keep going.', ['link']), [
        { type: 'slot', name: 'link' },
        { type: 'text', value: ' to keep going.' },
    ]);
    assert.deepEqual(splitMarkers('A {b} c {d}.'), [
        { type: 'text', value: 'A ' },
        { type: 'slot', name: 'b' },
        { type: 'text', value: ' c ' },
        { type: 'slot', name: 'd' },
        { type: 'text', value: '.' },
    ]);
});

test('markers without a slot stay as written', () => {
    assert.deepEqual(splitMarkers('Hola {name}, {link}', ['link']), [
        { type: 'text', value: 'Hola {name}, ' },
        { type: 'slot', name: 'link' },
    ]);
    assert.deepEqual(splitMarkers('Sin marcadores', ['link']), [
        { type: 'text', value: 'Sin marcadores' },
    ]);
});

test('ordinals follow the language', () => {
    assert.equal(ordinal(1, 'es-MX'), '1.º');
    assert.equal(ordinal(23, 'es'), '23.º');
    assert.deepEqual(
        [1, 2, 3, 4, 11, 12, 13, 21, 22, 23, 101, 111].map((n) =>
            ordinal(n, 'en-US'),
        ),
        [
            '1st',
            '2nd',
            '3rd',
            '4th',
            '11th',
            '12th',
            '13th',
            '21st',
            '22nd',
            '23rd',
            '101st',
            '111th',
        ],
    );
    assert.equal(ordinal(3, 'pt-BR'), '3.');
});

test('money always carries its currency code', () => {
    assert.equal(formatMoney(120000, 'MXN', 'es-MX'), '$1,200.00 MXN');
    assert.equal(formatMoney(120000, 'MXN', 'en-US'), '$1,200.00 MXN');
    assert.equal(formatMoney(120000, 'mxn', 'en-US'), '$1,200.00 MXN');
    assert.equal(formatMoney(-4550, 'MXN', 'en-US'), '-$45.50 MXN');
    assert.equal(formatMoney(999, 'USD', 'en-US'), '$9.99 USD');
    assert.equal(formatMoney(0, 'MXN', 'es-MX'), '$0.00 MXN');
});
