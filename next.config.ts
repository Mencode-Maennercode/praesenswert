import type { NextConfig } from 'next'

const nextConfig: NextConfig = {
  // Die Seite wird als statische Dateien auf Apache ausgeliefert (siehe
  // .htaccess und contact.php) - der Export darf nicht wegfallen.
  output: 'export',
  images: {
    // Ohne Node-Server gibt es keine Bildoptimierung zur Laufzeit.
    unoptimized: true,
    // Kein remotePatterns mehr: das fremde Unsplash-Hero ist raus, alle Bilder
    // liegen jetzt im eigenen Verzeichnis.
  },
}

export default nextConfig
