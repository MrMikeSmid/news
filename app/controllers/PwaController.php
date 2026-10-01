<?php

declare(strict_types=1);

function pwa_manifest(): never
{
    header('Content-Type: application/manifest+json');
    echo json_encode([
        'name' => 'Dagstart',
        'short_name' => 'Dagstart',
        'start_url' => url('/'),
        'scope' => url('/'),
        'display' => 'standalone',
        'background_color' => '#FAF9F6',
        'theme_color' => '#FAF9F6',
        'icons' => [
            ['src' => asset('icons/icon-192.png'), 'sizes' => '192x192', 'type' => 'image/png'],
            ['src' => asset('icons/icon-512.png'), 'sizes' => '512x512', 'type' => 'image/png'],
            ['src' => asset('icons/icon-maskable-512.png'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
        ],
    ], JSON_UNESCAPED_SLASHES);
    exit;
}

function pwa_service_worker(): never
{
    header('Content-Type: application/javascript');
    header('Cache-Control: no-cache');
    $shell = json_encode([asset('css/style.css'), asset('js/app.js'), asset('icons/icon.svg')]);
    echo <<<JS
const CACHE = 'dagstart-v1';
const SHELL = $shell;
self.addEventListener('install', e => { e.waitUntil(caches.open(CACHE).then(c => c.addAll(SHELL))); self.skipWaiting(); });
self.addEventListener('activate', e => { e.waitUntil(caches.keys().then(keys => Promise.all(keys.filter(k => k !== CACHE).map(k => caches.delete(k))))); self.clients.claim(); });
self.addEventListener('fetch', e => {
  if (e.request.method !== 'GET') return;
  const url = new URL(e.request.url);
  if (!SHELL.includes(url.pathname)) return;
  e.respondWith(fetch(e.request).then(r => { const copy = r.clone(); caches.open(CACHE).then(c => c.put(e.request, copy)); return r; }).catch(() => caches.match(e.request)));
});
JS;
    exit;
}
