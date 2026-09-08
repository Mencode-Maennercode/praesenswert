import type { NextConfig } from 'next'

const nextConfig: NextConfig = {
  // Die Seite wird als statische Dateien auf Apache ausgeliefert (siehe
  // .htaccess und contact.php) - der Export darf nicht wegfallen.
  output: 'export',
  // WICHTIG: trailingSlash NICHT aktivieren. Das wandelt bestehende Routen
  // wie bestell-bar.html in bestell-bar/index.html um (Datei -> Verzeichnis).
  // Am 08.09.2026 hat genau dieser Typwechsel den netcup-Git-Deploy fuer
  // /bestell-bar/ und die neue /leistungen/-Seite zum Stillstand gebracht
  // (.htaccess wurde uebernommen, die neuen Verzeichnisse aber nicht -- die
  // Seiten lieferten 301 statt 200). Neue Next-Routen muessen ohne
  // trailingSlash auskommen, siehe /leistungen fuer das Muster.
  images: {
    // Ohne Node-Server gibt es keine Bildoptimierung zur Laufzeit.
    unoptimized: true,
    // Kein remotePatterns mehr: das fremde Unsplash-Hero ist raus, alle Bilder
    // liegen jetzt im eigenen Verzeichnis.
  },
}

export default nextConfig
