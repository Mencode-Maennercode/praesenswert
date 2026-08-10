/**
 * Service Worker.
 *
 * Drei Aufgaben, mehr nicht:
 *   1. Die Hülle der App offline verfügbar halten
 *   2. Push-Nachrichten anzeigen
 *   3. Beim Antippen die richtige Stelle öffnen
 *
 * Ausdrücklich NICHT: API-Antworten zwischenspeichern. Ein zwischen-
 * gespeicherter Tagesstand, der veraltet ist, wäre schlimmer als gar
 * keiner - man würde ihn für aktuell halten und doppelt eintragen.
 */

const BASE = '/fitness';
const CACHE = 'aura-v1';

// Nur die Hülle. Die gehashten Build-Dateien landen von selbst im Cache,
// sobald sie einmal geladen wurden.
const HUELLE = [`${BASE}/`, `${BASE}/manifest.json`, `${BASE}/icons/icon-192.png`];

self.addEventListener('install', (e) => {
  e.waitUntil(
    caches
      .open(CACHE)
      .then((c) => c.addAll(HUELLE))
      // Eine fehlende Datei darf die Installation nicht scheitern lassen -
      // sonst bleibt der alte Worker aktiv und die App hängt auf einem
      // alten Stand fest.
      .catch(() => undefined),
  );
  self.skipWaiting();
});

self.addEventListener('activate', (e) => {
  e.waitUntil(
    caches
      .keys()
      .then((namen) => Promise.all(namen.filter((n) => n !== CACHE).map((n) => caches.delete(n))))
      .then(() => self.clients.claim()),
  );
});

self.addEventListener('fetch', (e) => {
  const url = new URL(e.request.url);

  // Alles Fremde und alle Schreibzugriffe gehen unangetastet durch.
  if (e.request.method !== 'GET' || url.origin !== self.location.origin) return;
  // Die API nie aus dem Cache - siehe oben.
  if (url.pathname.includes('/api/')) return;

  /*
   * Netz zuerst, Cache als Rückfall.
   *
   * Umgekehrt wäre es schneller, aber dann sähe man nach einem Update
   * so lange die alte Fassung, bis der Cache irgendwann erneuert wird.
   * Bei einer App, die sich noch verändert, ist Aktualität wichtiger.
   */
  e.respondWith(
    fetch(e.request)
      .then((antwort) => {
        if (antwort.ok) {
          const kopie = antwort.clone();
          caches.open(CACHE).then((c) => c.put(e.request, kopie));
        }
        return antwort;
      })
      .catch(async () => {
        const treffer = await caches.match(e.request);
        if (treffer) return treffer;
        // Für Seitenaufrufe die Startseite - so öffnet die App auch
        // offline, statt den Dinosaurier zu zeigen.
        if (e.request.mode === 'navigate') {
          const start = await caches.match(`${BASE}/`);
          if (start) return start;
        }
        return new Response('', { status: 504, statusText: 'offline' });
      }),
  );
});

/**
 * Push.
 *
 * Es kommt bewusst KEIN Inhalt mit - der Text wird hier aus Uhrzeit und
 * Wochentag abgeleitet. Damit entfällt die gesamte Nutzlast-
 * Verschlüsselung auf dem Server, und es geht kein einziges Feature
 * verloren: Die App braucht genau diese vier Sätze.
 *
 * Wichtig: Es MUSS immer eine sichtbare Meldung entstehen. Apple entzieht
 * die Erlaubnis dauerhaft, wenn ein Push mal ohne Anzeige bleibt.
 */
self.addEventListener('push', (e) => {
  const jetzt = new Date();
  const h = jetzt.getHours();
  const wiegetag = jetzt.getDay() === 5 && h >= 16;

  const t = wiegetag
    ? { titel: 'Freitag ist Wiegetag', text: 'Kurz auf die Waage — dauert fünf Sekunden.', ziel: '#verlauf' }
    : h < 11
      ? { titel: 'Guten Morgen', text: 'Was gab es zum Frühstück?', ziel: '#home' }
      : h < 16
        ? { titel: 'Mittagszeit', text: 'Kurz eintragen, dann ist es erledigt.', ziel: '#home' }
        : { titel: 'Tag fast rum', text: 'Was steht heute noch auf dem Konto?', ziel: '#home' };

  e.waitUntil(
    self.registration.showNotification(t.titel, {
      body: t.text,
      icon: `${BASE}/icons/icon-192.png`,
      badge: `${BASE}/icons/badge.png`,
      tag: wiegetag ? 'aura-wiegen' : 'aura-eintragen',
      // Nicht erneut vibrieren, wenn dieselbe Erinnerung ersetzt wird.
      renotify: false,
      data: { url: `${BASE}/${t.ziel}` },
    }),
  );
});

self.addEventListener('notificationclick', (e) => {
  e.notification.close();
  const ziel = e.notification.data?.url || `${BASE}/`;

  e.waitUntil(
    self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((fenster) => {
      // Ein schon offenes Fenster nach vorn holen, statt ein zweites zu
      // öffnen - sonst hat man die App irgendwann dreifach laufen.
      for (const f of fenster) {
        if (f.url.includes(BASE) && 'focus' in f) {
          f.navigate?.(ziel);
          return f.focus();
        }
      }
      return self.clients.openWindow(ziel);
    }),
  );
});
