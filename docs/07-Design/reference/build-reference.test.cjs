const { test } = require('node:test');
const assert = require('node:assert/strict');
const { resolver, cssValue } = require('./build-reference.cjs');

test('aliases fail closed for missing targets and cycles', () => {
  assert.throws(() => resolver({ a: { $type: 'color', $value: '{absent}' } })('a'), /Missing token/);
  assert.throws(() => resolver({ a: { $type: 'color', $value: '{b}' }, b: { $type: 'color', $value: '{a}' } })('a'), /Cyclic token alias/);
  assert.throws(() => resolver({ a: { $type: 'color', $value: '{b}' }, b: { $type: 'dimension', $value: { value: 1, unit: 'px' } } })('a'), /type mismatch/);
});

test('DTCG alpha and rem tracking retain their units', () => {
  assert.equal(cssValue({ type: 'color', value: { colorSpace: 'srgb', hex: '#050812', alpha: 0.8 } }), 'rgb(5 8 18 / 0.8)');
  assert.equal(cssValue({ type: 'dimension', value: { value: 0.02, unit: 'rem' } }), '0.02rem');
  assert.throws(() => cssValue({ type: 'dimension', value: { value: 4, unit: 'em' } }), /Unsupported dimension/);
});
