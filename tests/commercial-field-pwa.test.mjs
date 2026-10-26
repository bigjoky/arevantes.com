import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';

const source = readFileSync(new URL('../public/commercial-field-sw.js', import.meta.url), 'utf8');
const origin = 'https://arevantes.com';
function worker({ offline = false } = {}) {
    const handlers = {};
    const writes = [];
    const deleted = [];
    const cache = {
        addAll: async (urls) => writes.push(...urls),
        match: async (url) => new Response(url === '/pwa/offline.html' ? 'No hay conexión' : 'public icon'),
    };
    vm.runInNewContext(source, {
        URL, Response,
        self: { location: { origin }, addEventListener: (name, handler) => {
 handlers[name] = handler; 
}, skipWaiting() {}, clients: { claim: async () => {} } },
        caches: { open: async () => cache, keys: async () => ['arevantes-field-public-v0', 'other-app'], delete: async (key) => deleted.push(key) },
        fetch: async () => {
 if (offline) {
throw new Error('offline');
}

 return new Response('private response'); 
},
    });
    const dispatch = async (name, request) => {
        let result;
        handlers[name]({ request, waitUntil: (promise) => {
 result = promise; 
}, respondWith: (promise) => {
 result = promise; 
} });

        return result;
    };

    return { dispatch, writes, deleted };
}

test('install caches only public notice and canonical icons; activation preserves unrelated caches', async () => {
    const w = worker();
    await w.dispatch('install');
    assert.deepEqual(w.writes, ['/pwa/offline.html', '/pwa/icon-192.png', '/pwa/icon-512.png', '/pwa/icon-maskable-512.png']);
    await w.dispatch('activate');
    assert.deepEqual(w.deleted, ['arevantes-field-public-v0']);
});

test('authenticated navigations always go to network and offline fallback contains no saved visit', async () => {
    const request = { method: 'GET', url: `${origin}/commercial-field`, mode: 'navigate' };
    const online = worker();
    assert.equal(await (await online.dispatch('fetch', request)).text(), 'private response');
    assert.deepEqual(online.writes, []);
    assert.equal(await (await worker({ offline: true }).dispatch('fetch', request)).text(), 'No hay conexión');
});

test('worker never intercepts writes, API reads or cross-origin traffic', async () => {
    const w = worker({ offline: true });

    for (const request of [
        { method: 'POST', url: `${origin}/commercial-field/visits`, mode: 'navigate' },
        { method: 'GET', url: `${origin}/api/mobile/v1/commercial/field`, mode: 'cors' },
        { method: 'GET', url: 'https://another.example/pwa/icon-192.png', mode: 'navigate' },
    ]) {
assert.equal(await w.dispatch('fetch', request), undefined);
}

    assert.deepEqual(w.writes, []);
});

test('manifest launches the protected field page with standalone identity and valid raster icons', () => {
    const manifest = JSON.parse(readFileSync(new URL('../public/commercial-field.webmanifest', import.meta.url), 'utf8'));
    assert.equal(manifest.start_url, '/commercial-field?source=pwa');
    assert.equal(manifest.display, 'standalone');
    assert.equal(manifest.scope, '/');

    for (const icon of manifest.icons) {
        const data = readFileSync(new URL(`../public${icon.src}`, import.meta.url));
        assert.equal(data.subarray(1, 4).toString(), 'PNG');
        assert.equal(`${data.readUInt32BE(16)}x${data.readUInt32BE(20)}`, icon.sizes);
    }
});
