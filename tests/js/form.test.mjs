import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { JSDOM } from 'jsdom';
const source = readFileSync(new URL('../../resources/views/form/script.blade.php', import.meta.url), 'utf8').replace(/<\/?script[^>]*>/g, '');
function setup(fetch) {
  const form = (id) => `<form data-newsletter-form id="${id}" action="/newsletter/subscribe" data-error-message="Please retry"><input name="context" value="encrypted"><label for="${id}-email">Email</label><input id="${id}-email" name="email" value="a@example.org" aria-describedby="${id}-email-error"><div id="${id}-email-error" data-error="email"></div><input name="consent" type="checkbox"><button type="submit">Subscribe</button><div data-newsletter-status aria-live="polite" tabindex="-1"></div></form>`;
  const dom = new JSDOM(form('first') + form('second') + '<form id="other"></form>', { url: 'http://localhost', runScripts: 'outside-only' });
  dom.window.fetch = fetch; dom.window.eval(source);
  const submit = (id = 'first') => { const node = dom.window.document.getElementById(id); const event = new dom.window.Event('submit', { bubbles: true, cancelable: true }); node.dispatchEvent(event); return { node, event }; };
  return { dom, submit, document: dom.window.document };
}
const settle = () => new Promise(resolve => setTimeout(resolve, 0));
test('success posts form data, announces status and moves focus', async () => {
  let request;
  const { document, submit } = setup(async (url, options) => { request = { url, options }; return { ok: true, json: async () => ({ status: 'success', message: 'Check inbox' }) }; });
  const { node, event } = submit(); await settle();
  assert.equal(event.defaultPrevented, true); assert.equal(request.options.method, 'POST');
  assert.equal(request.options.headers.Accept, 'application/json'); assert.equal(request.options.body.get('context'), 'encrypted');
  assert.equal(node.querySelector('[data-newsletter-status]').textContent, 'Check inbox');
  assert.equal(document.activeElement, node.querySelector('[data-newsletter-status]'));
  assert.equal(node.querySelector('button').disabled, false);
});
test('422 errors are linked, escaped and focus the first invalid field', async () => {
  const { document, submit } = setup(async () => ({ ok: false, status: 422, json: async () => ({ message: 'Invalid', errors: { email: ['<bad email>'] } }) }));
  const { node } = submit(); await settle();
  assert.equal(node.querySelector('[name=email]').getAttribute('aria-invalid'), 'true');
  assert.equal(document.activeElement, node.querySelector('[name=email]'));
  assert.equal(node.querySelector('[data-error=email]').textContent, '<bad email>');
  assert.equal(node.querySelector('[data-error=email]').children.length, 0);
});
test('network failure preserves input and announces a retry message', async () => {
  const { document, submit } = setup(async () => { throw Error('network'); });
  const { node } = submit(); await settle();
  assert.equal(node.querySelector('[name=email]').value, 'a@example.org');
  assert.equal(node.querySelector('[data-newsletter-status]').textContent, 'Please retry');
  assert.equal(document.activeElement, node.querySelector('[data-newsletter-status]'));
});
test('delegated enhancement handles later forms without touching another instance', async () => {
  const { document, submit } = setup(async () => ({ ok: true, json: async () => ({ message: 'Second only' }) }));
  submit('second'); await settle();
  assert.equal(document.querySelector('#first [data-newsletter-status]').textContent, '');
  assert.equal(document.querySelector('#second [data-newsletter-status]').textContent, 'Second only');
  assert.equal(submit('other').event.defaultPrevented, false);
});
test('inflight submit is not duplicated and old errors are cleared on retry', async () => {
  let resolve, calls = 0;
  const { submit } = setup(() => { calls++; return new Promise(r => { resolve = r; }); });
  const { node } = submit(); submit();
  assert.equal(calls, 1); assert.equal(node.getAttribute('aria-busy'), 'true');
  resolve({ ok: false, status: 422, json: async () => ({ errors: { email: ['Invalid'] } }) }); await settle();
  submit(); assert.equal(node.querySelector('[name=email]').hasAttribute('aria-invalid'), false);
  resolve({ ok: true, json: async () => ({ message: 'OK' }) }); await settle();
  assert.equal(node.hasAttribute('aria-busy'), false);
});
test('confirmed notice receives focus when the source page loads', () => {
  const dom = new JSDOM('<div data-newsletter-confirmed role="status" tabindex="-1" autofocus>Confirmed</div>', { url: 'http://localhost', runScripts: 'outside-only' });
  dom.window.fetch = async () => {};
  dom.window.eval(source);
  assert.equal(dom.window.document.activeElement, dom.window.document.querySelector('[data-newsletter-confirmed]'));
});
