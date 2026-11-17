import test from 'node:test';
import assert from 'node:assert/strict';
import { profileCardPosition } from '../resources/js/profile-cards.js';

const viewport = { width: 1000, height: 700 };
const card = { width: 288, height: 180 };

test('opens below when there is room', () => {
    assert.deepEqual(profileCardPosition({ left: 50, top: 100, bottom: 120 }, card, viewport), { left: 50, top: 128 });
});

test('opens above names at the bottom of the screen', () => {
    assert.deepEqual(profileCardPosition({ left: 50, top: 650, bottom: 670 }, card, viewport), { left: 50, top: 462 });
});

test('keeps cards inside the right and left edges', () => {
    assert.equal(profileCardPosition({ left: 950, top: 100, bottom: 120 }, card, viewport).left, 704);
    assert.equal(profileCardPosition({ left: -20, top: 100, bottom: 120 }, card, viewport).left, 8);
});

test('keeps a tall profile within the viewport margin', () => {
    const result = profileCardPosition({ left: 30, top: 300, bottom: 320 }, { width: 288, height: 684 }, viewport);
    assert.equal(result.top, 8);
});
