import test from 'node:test';
import assert from 'node:assert/strict';
import { freshness, relativeAge } from '../../resources/js/monitoring/freshness.js';

const now = Date.parse('2026-01-01T10:00:00Z');
test('missing and future timestamps do not claim live device connectivity', () => {
    for (const timestamp of [null, NaN, now + 1]) assert.equal(freshness(timestamp, now, 300).state, 'unavailable');
});
test('freshness expires at configured boundary and preserves zero age', () => {
    assert.deepEqual(freshness(now, now, 300), { state: 'fresh', label: 'Data terbaru', ageSeconds: 0 });
    assert.equal(freshness(now - 300000, now, 300).state, 'fresh');
    assert.equal(freshness(now - 301000, now, 300).state, 'stale');
    assert.equal(freshness(now - 301000, now, 300).label, 'Data terlambat');
});
test('relative ages show readable seconds minutes hours and days', () => {
    assert.equal(relativeAge(null), 'Belum ada pembacaan');
    assert.equal(relativeAge(0), 'Baru saja');
    assert.equal(relativeAge(30), '30 detik lalu');
    assert.equal(relativeAge(120), '2 menit lalu');
    assert.equal(relativeAge(7200), '2 jam lalu');
    assert.equal(relativeAge(172800), '2 hari lalu');
});
