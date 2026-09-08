import type { NextConfig } from 'next'

const nextConfig: NextConfig = {
  // Die Seite wird als statische Dateien auf Apache ausgeliefert (siehe
  // .htaccess und contact.php) - der Export darf nicht wegfallen.
  output: 'export',
  // Erzeugt fuer jede Route ein echtes Verzeichnis mit index.html statt einer
  // <route>.html-Datei. Voraussetzung dafuer, dass die .htaccess-Regel
  // "existierende Datei/Verzeichnis direkt ausliefern" neue Unterseiten wie
  // /leistungen/ erkennt, statt sie faelschlich ueber den SPA-Fallback laufen
  // zu lassen.
  trailingSlash: true,
  images: {
    // Ohne Node-Server gibt es keine Bildoptimierung zur Laufzeit.
    unoptimized: true,
    // Kein remotePatterns mehr: das fremde Unsplash-Hero ist raus, alle Bilder
    // liegen jetzt im eigenen Verzeichnis.
  },
}

export default nextConfig
