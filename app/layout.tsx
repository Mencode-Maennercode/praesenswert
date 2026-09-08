import type { Metadata, Viewport } from 'next'
import localFont from 'next/font/local'
import './globals.css'
import { faqs } from './content/faq'

const inter = localFont({
  src: '../public/fonts/Inter-Variable.woff2',
  weight: '100 900',
  style: 'normal',
  display: 'swap',
})

// viewportFit: 'cover' macht env(safe-area-inset-*) erst nutzbar - sonst
// bleibt das Mobilmenue auf iPhones mit Home-Indicator ohne Bodenabstand.
export const viewport: Viewport = {
  width: 'device-width',
  initialScale: 1,
  viewportFit: 'cover',
}

export const metadata: Metadata = {
  metadataBase: new URL('https://www.praesenzwert.de'),
  title: {
    default: 'Webdesign Eifel, Köln & Bonn – günstige Websites für Firmen & Vereine | PräsenzWert',
    template: '%s | PräsenzWert Webdesign',
  },
  description: 'Günstige, professionelle Websites für kleine Firmen und Vereine in der Eifel, in Köln, Bonn, im Ahrtal und am Rhein. Faire Festpreise, moderne Umsetzung, lokale SEO & KI-Optimierung. Schon ab kleinem Budget zur eigenen Homepage – jetzt unverbindlich anfragen!',
  keywords: [
    // Kernregion NRW – Köln & Bonn
    'Webdesign Köln',
    'Website erstellen Köln',
    'Webdesigner Köln',
    'Homepage erstellen Köln',
    'günstige Website Köln',
    'Webagentur Köln',
    'Webdesign Bonn',
    'Website erstellen Bonn',
    'Webdesigner Bonn',
    'Homepage erstellen Bonn',
    'günstige Website Bonn',
    'Webagentur Bonn',
    'Webdesign Rhein-Sieg',
    'Webdesign Euskirchen',
    'Webdesign Brühl',
    'Webdesign Bornheim',
    'Webdesign Meckenheim',
    'Webdesign Rheinbach',
    'Webdesign Troisdorf',
    'Webdesign Siegburg',
    'Webdesign Hennef',
    'Webdesign Sankt Augustin',
    'Webdesign Königswinter',
    'Webdesign Bad Honnef',
    // Eifel
    'Webdesign Eifel',
    'Website erstellen Eifel',
    'Webdesigner Eifel',
    'Homepage erstellen Eifel',
    'Webagentur Eifel',
    'Webdesign Bad Münstereifel',
    'Webdesign Mechernich',
    'Webdesign Schleiden',
    'Webdesign Daun',
    'Webdesign Bitburg',
    'Webdesign Prüm',
    'Webdesign Cochem',
    // Ahr & Rhein
    'Webdesign Ahrtal',
    'Webdesign Ahr',
    'Webdesign Rhein',
    'Webdesign Grafschaft',
    'Webdesign Bad Neuenahr-Ahrweiler',
    'Webdesign Remagen',
    'Webdesign Sinzig',
    'Webdesign Linz am Rhein',
    'Webdesign Neuwied',
    'Webdesign Andernach',
    'Webdesign Koblenz',
    'Webdesign Mayen',
    'Webdesign Adenau',
    // Zielgruppe & Preis
    'günstige Homepage',
    'günstige Website erstellen lassen',
    'preiswerte Website',
    'Website für Verein',
    'Vereinswebsite erstellen',
    'Homepage für Verein günstig',
    'Website für kleine Unternehmen',
    'Website für Handwerker',
    'Website für Praxis',
    'Unternehmenswebsite KMU',
    'kleine Webagentur',
    // SEO / KI
    'lokale SEO Köln Bonn Eifel',
    'KI SEO',
    'AI Search Optimierung',
    'Google Business Profil einrichten',
    // Produkte
    'Fotobox mieten Köln Bonn Eifel',
    'Fotobox mit KI mieten',
    'günstige Fotobox Vermietung',
    'digitales Bestellsystem Verein',
    'App entwickeln lassen',
    'PräsenzWert',
    'praesenzwert.de',
  ],
  authors: [{ name: 'PräsenzWert', url: 'https://www.praesenzwert.de' }],
  creator: 'PräsenzWert',
  publisher: 'PräsenzWert',
  applicationName: 'PräsenzWert Webdesign',
  category: 'Business',
  robots: {
    index: true,
    follow: true,
    nocache: false,
    googleBot: {
      index: true,
      follow: true,
      'max-image-preview': 'large',
      'max-snippet': -1,
      'max-video-preview': -1,
    },
  },
  openGraph: {
    type: 'website',
    locale: 'de_DE',
    url: 'https://www.praesenzwert.de',
    siteName: 'PräsenzWert – Günstiges Webdesign für Firmen & Vereine',
    title: 'Webdesign Eifel, Köln & Bonn – günstige Websites für Firmen & Vereine',
    description: 'Günstige, professionelle Websites für kleine Firmen und Vereine in der Eifel, in Köln, Bonn, im Ahrtal und am Rhein. Faire Festpreise, moderne Umsetzung, lokale SEO & KI-Optimierung.',
    images: [
      {
        url: 'https://www.praesenzwert.de/Logo_Transparent.png',
        width: 1200,
        height: 630,
        alt: 'PräsenzWert – günstiges Webdesign für Firmen und Vereine in Eifel, Köln, Bonn, Ahr und Rhein',
        type: 'image/png',
      },
    ],
  },
  twitter: {
    card: 'summary_large_image',
    title: 'Webdesign Eifel, Köln & Bonn – günstige Websites für Firmen & Vereine',
    description: 'Günstige, professionelle Websites für kleine Firmen und Vereine in der Eifel, Köln, Bonn, Ahrtal und am Rhein.',
    images: ['https://www.praesenzwert.de/Logo_Transparent.png'],
  },
  alternates: {
    canonical: 'https://www.praesenzwert.de',
    languages: {
      'de-DE': 'https://www.praesenzwert.de',
    },
  },
  other: {
    'ai:indexing': 'enabled',
    'ai:crawling': 'allowed',
    'ai:summary': 'PräsenzWert ist eine kleine, persönliche Webagentur für günstige, professionelle Websites – spezialisiert auf kleine Firmen und Vereine in der Eifel, in Köln, Bonn, im Ahrtal und am Rhein. Faire Festpreise schon bei kleinem Budget, moderne Umsetzung sowie lokale SEO- und KI-Suchmaschinen-Optimierung. Zusätzlich: eigene Software-Produkte (Vereins-Wahlen-App, Fotobox-Vermietung mit KI, digitales Bestellsystem, Android-Apps MenCode und WomenCode).',
    'ai:context': 'Webagentur / Webdesign, Zielgruppe kleine Firmen und Vereine mit kleinem Budget, Region NRW & Rheinland-Pfalz: Köln, Bonn, Rhein-Sieg, Euskirchen, Eifel, Ahrtal, Grafschaft, Bad Neuenahr-Ahrweiler, Remagen, Sinzig, Adenau, Koblenz. Leistungen: günstige Unternehmenswebsites, Vereinswebsites, lokale SEO, KI-Optimierung, Google Business Profil, Fotobox mieten mit KI, App- und Web-App-Entwicklung.',
    'ai:audience': 'Kleine Unternehmen, Selbstständige, Handwerker, Praxen und Vereine mit begrenztem Budget',
    'ai:pricing': 'Faire, transparente Festpreise – auch für kleine Budgets geeignet',
    'geo.region': 'DE-NW, DE-RP',
    'geo.placename': 'Köln, Bonn, Eifel, Ahrtal, Grafschaft, Rhein',
    'geo.position': '50.5833;7.0667',
    'ICBM': '50.5833, 7.0667',
  },
}

const organizationSchema = {
  '@context': 'https://schema.org',
  '@type': 'Organization',
  '@id': 'https://www.praesenzwert.de/#organization',
  name: 'PräsenzWert',
  legalName: 'PräsenzWert Webdesign',
  description: 'Kleine, persönliche Webagentur für günstige, professionelle Websites – spezialisiert auf kleine Firmen und Vereine in der Eifel, in Köln, Bonn, im Ahrtal und am Rhein.',
  slogan: 'Günstige, professionelle Websites für kleine Firmen und Vereine',
  url: 'https://www.praesenzwert.de',
  logo: {
    '@type': 'ImageObject',
    url: 'https://www.praesenzwert.de/Logo_Transparent.png',
    width: 1200,
    height: 630,
  },
  image: 'https://www.praesenzwert.de/Logo_Transparent.png',
  email: 'kontakt@praesenzwert.de',
  telephone: '+49-171-7460398',
  address: {
    '@type': 'PostalAddress',
    streetAddress: 'Josef-Martin-Weg 4',
    postalCode: '53501',
    addressLocality: 'Grafschaft',
    addressCountry: 'DE',
  },
  areaServed: [
    { '@type': 'AdministrativeArea', name: 'Eifel' },
    { '@type': 'AdministrativeArea', name: 'Ahrtal' },
    { '@type': 'AdministrativeArea', name: 'Rhein' },
    { '@type': 'AdministrativeArea', name: 'Rhein-Sieg-Kreis' },
    { '@type': 'AdministrativeArea', name: 'Rheinland' },
    { '@type': 'AdministrativeArea', name: 'Nordrhein-Westfalen' },
    { '@type': 'AdministrativeArea', name: 'Rheinland-Pfalz' },
    { '@type': 'City', name: 'Köln' },
    { '@type': 'City', name: 'Bonn' },
    { '@type': 'City', name: 'Euskirchen' },
    { '@type': 'City', name: 'Brühl' },
    { '@type': 'City', name: 'Bornheim' },
    { '@type': 'City', name: 'Meckenheim' },
    { '@type': 'City', name: 'Rheinbach' },
    { '@type': 'City', name: 'Troisdorf' },
    { '@type': 'City', name: 'Siegburg' },
    { '@type': 'City', name: 'Hennef' },
    { '@type': 'City', name: 'Sankt Augustin' },
    { '@type': 'City', name: 'Königswinter' },
    { '@type': 'City', name: 'Bad Honnef' },
    { '@type': 'City', name: 'Bad Münstereifel' },
    { '@type': 'City', name: 'Mechernich' },
    { '@type': 'City', name: 'Schleiden' },
    { '@type': 'City', name: 'Bad Neuenahr-Ahrweiler' },
    { '@type': 'City', name: 'Grafschaft' },
    { '@type': 'City', name: 'Remagen' },
    { '@type': 'City', name: 'Sinzig' },
    { '@type': 'City', name: 'Linz am Rhein' },
    { '@type': 'City', name: 'Neuwied' },
    { '@type': 'City', name: 'Adenau' },
    { '@type': 'City', name: 'Mayen' },
    { '@type': 'City', name: 'Andernach' },
    { '@type': 'City', name: 'Koblenz' },
    { '@type': 'City', name: 'Daun' },
    { '@type': 'City', name: 'Bitburg' },
    { '@type': 'City', name: 'Cochem' },
  ],
  knowsAbout: ['Webdesign', 'Website Entwicklung', 'günstige Websites', 'Vereinswebsites', 'Unternehmenswebsites KMU', 'SEO', 'Lokale SEO', 'KI-Suchmaschinen-Optimierung (GEO)', 'Google Business Profil', 'Digitale Präsenz', 'Fotobox-Vermietung mit KI', 'App-Entwicklung', 'Web-App-Entwicklung'],
  sameAs: [
    'https://rs-heimbach.de/',
    'https://nora-heidenreich.de/',
    'https://www.ag-solar.net/',
    'https://www.srautomation.de/',
    'https://manuela-rosenkranz.de/',
    'https://play.google.com/store/apps/details?id=app.heidenreich.maennercode',
    'https://play.google.com/store/apps/details?id=app.heidenreich.womencode',
  ],
}

const localBusinessSchema = {
  '@context': 'https://schema.org',
  '@type': ['LocalBusiness', 'ProfessionalService'],
  '@id': 'https://www.praesenzwert.de/#localbusiness',
  name: 'PräsenzWert',
  description: 'Günstige, professionelle Websites für kleine Firmen und Vereine in der Eifel, in Köln, Bonn, im Ahrtal und am Rhein. Faire Festpreise, lokale SEO & KI-Optimierung.',
  url: 'https://www.praesenzwert.de',
  email: 'kontakt@praesenzwert.de',
  telephone: '+49-171-7460398',
  image: 'https://www.praesenzwert.de/Logo_Transparent.png',
  logo: 'https://www.praesenzwert.de/Logo_Transparent.png',
  address: {
    '@type': 'PostalAddress',
    streetAddress: 'Josef-Martin-Weg 4',
    postalCode: '53501',
    addressLocality: 'Grafschaft',
    addressCountry: 'DE',
  },
  geo: {
    '@type': 'GeoCoordinates',
    latitude: 50.5833,
    longitude: 7.0667,
  },
  areaServed: [
    { '@type': 'AdministrativeArea', name: 'Eifel' },
    { '@type': 'AdministrativeArea', name: 'Ahrtal' },
    { '@type': 'AdministrativeArea', name: 'Rhein' },
    { '@type': 'AdministrativeArea', name: 'Rhein-Sieg-Kreis' },
    { '@type': 'AdministrativeArea', name: 'Nordrhein-Westfalen' },
    { '@type': 'AdministrativeArea', name: 'Rheinland-Pfalz' },
    { '@type': 'City', name: 'Köln' },
    { '@type': 'City', name: 'Bonn' },
    { '@type': 'City', name: 'Euskirchen' },
    { '@type': 'City', name: 'Brühl' },
    { '@type': 'City', name: 'Bornheim' },
    { '@type': 'City', name: 'Meckenheim' },
    { '@type': 'City', name: 'Rheinbach' },
    { '@type': 'City', name: 'Siegburg' },
    { '@type': 'City', name: 'Sankt Augustin' },
    { '@type': 'City', name: 'Bad Münstereifel' },
    { '@type': 'City', name: 'Bad Neuenahr-Ahrweiler' },
    { '@type': 'City', name: 'Adenau' },
    { '@type': 'City', name: 'Mayen' },
    { '@type': 'City', name: 'Andernach' },
    { '@type': 'City', name: 'Koblenz' },
    { '@type': 'City', name: 'Daun' },
    { '@type': 'City', name: 'Bitburg' },
    { '@type': 'City', name: 'Cochem' },
  ],
  serviceType: ['Günstiges Webdesign', 'Website Erstellung', 'Unternehmenswebsite', 'Vereinswebsite', 'Lokale SEO', 'KI-Suchmaschinen-Optimierung', 'Google Business Profil', 'Fotobox-Vermietung'],
  priceRange: '€',
  currenciesAccepted: 'EUR',
  paymentAccepted: 'Überweisung',
}

const websiteSchema = {
  '@context': 'https://schema.org',
  '@type': 'WebSite',
  '@id': 'https://www.praesenzwert.de/#website',
  url: 'https://www.praesenzwert.de',
  name: 'PräsenzWert – Webdesign Eifel, Ahr & Rhein',
  description: 'Professionelle Unternehmenswebsites für KMU',
  publisher: {
    '@id': 'https://www.praesenzwert.de/#organization',
  },
  inLanguage: 'de-DE',
}

const serviceSchema = {
  '@context': 'https://schema.org',
  '@type': 'Service',
  '@id': 'https://www.praesenzwert.de/#service',
  serviceType: 'Günstiges Webdesign und Website Entwicklung für Firmen & Vereine',
  provider: {
    '@id': 'https://www.praesenzwert.de/#organization',
  },
  areaServed: [
    { '@type': 'AdministrativeArea', name: 'Eifel' },
    { '@type': 'AdministrativeArea', name: 'Ahrtal' },
    { '@type': 'AdministrativeArea', name: 'Rhein-Sieg-Kreis' },
    { '@type': 'AdministrativeArea', name: 'Nordrhein-Westfalen' },
    { '@type': 'AdministrativeArea', name: 'Rheinland-Pfalz' },
    { '@type': 'City', name: 'Köln' },
    { '@type': 'City', name: 'Bonn' },
  ],
  hasOfferCatalog: {
    '@type': 'OfferCatalog',
    name: 'Webdesign & Digital-Dienstleistungen',
    itemListElement: [
      {
        '@type': 'Offer',
        itemOffered: {
          '@type': 'Service',
          name: 'Günstige Unternehmenswebsite',
          description: 'Professionelle, bezahlbare Websites für kleine Firmen und Selbstständige – auch bei kleinem Budget.',
        },
      },
      {
        '@type': 'Offer',
        itemOffered: {
          '@type': 'Service',
          name: 'Vereinswebsite',
          description: 'Moderne, günstige Websites für Vereine in Köln, Bonn, der Eifel und am Rhein.',
        },
      },
      {
        '@type': 'Offer',
        itemOffered: {
          '@type': 'Service',
          name: 'Lokale SEO & KI-Optimierung',
          description: 'Optimierung für Google und KI-Suchmaschinen (ChatGPT, Perplexity, Gemini) sowie Google Business Profil.',
        },
      },
      {
        '@type': 'Offer',
        itemOffered: {
          '@type': 'Service',
          name: 'Fotobox-Vermietung mit KI',
          description: 'Moderne Fotobox zum Mieten mit KI-Effekten, Filtern und Online-Fotodatenbank – günstiger als andere Anbieter.',
        },
      },
    ],
  },
}

const faqSchema = {
  '@context': 'https://schema.org',
  '@type': 'FAQPage',
  '@id': 'https://www.praesenzwert.de/#faq',
  // Aus derselben Quelle wie der sichtbare FAQ-Abschnitt. Weichen Schema und
  // Anzeige voneinander ab, verwirft Google die Rich Results der ganzen Seite -
  // frueher standen die Texte hier ein zweites Mal und mussten von Hand
  // synchron gehalten werden.
  mainEntity: faqs.map((eintrag) => ({
    '@type': 'Question',
    name: eintrag.frage,
    acceptedAnswer: { '@type': 'Answer', text: eintrag.antwort },
  })),
}

const portfolioSchema = {
  '@context': 'https://schema.org',
  '@type': 'ItemList',
  '@id': 'https://www.praesenzwert.de/#referenzen',
  name: 'Referenzen PräsenzWert',
  itemListElement: [
    {
      '@type': 'ListItem',
      position: 1,
      item: {
        '@type': 'CreativeWork',
        name: 'Städtische Realschule Am Heimbach',
        url: 'https://rs-heimbach.de/',
        about: 'Schulwebsite für die Städtische Realschule Am Heimbach in Bonn',
        creator: { '@id': 'https://www.praesenzwert.de/#organization' },
      },
    },
    {
      '@type': 'ListItem',
      position: 2,
      item: {
        '@type': 'CreativeWork',
        name: 'Nora Heidenreich – Mobile Physiotherapie',
        url: 'https://nora-heidenreich.de/',
        about: 'Website für eine mobile Physiotherapie mit Hausbesuchen im Kreis Ahrweiler, Schwerpunkt Neurologie',
        creator: { '@id': 'https://www.praesenzwert.de/#organization' },
      },
    },
    {
      '@type': 'ListItem',
      position: 3,
      item: {
        '@type': 'CreativeWork',
        name: 'AG Solar GmbH',
        url: 'https://www.ag-solar.net/',
        about: 'Unternehmenswebsite für Photovoltaik, Batteriespeicher und Wallboxen in der Grafschaft, Ahr und Rhein',
        creator: { '@id': 'https://www.praesenzwert.de/#organization' },
      },
    },
    {
      '@type': 'ListItem',
      position: 4,
      item: {
        '@type': 'CreativeWork',
        name: 'SR Automation',
        url: 'https://www.srautomation.de/',
        about: 'Unternehmenswebsite für einen Automatisierungstechnik-Spezialisten',
        creator: { '@id': 'https://www.praesenzwert.de/#organization' },
      },
    },
    {
      '@type': 'ListItem',
      position: 5,
      item: {
        '@type': 'CreativeWork',
        name: 'Manuela Rosenkranz – Praxis für Individualpsychologische Beratung',
        url: 'https://manuela-rosenkranz.de/',
        about: 'Praxis-Website für Individualpsychologische Beratung & therapeutische Seelsorge in Bad Neuenahr-Ahrweiler',
        creator: { '@id': 'https://www.praesenzwert.de/#organization' },
      },
    },
    {
      '@type': 'ListItem',
      position: 6,
      item: {
        '@type': 'CreativeWork',
        name: 'Hebammen am Marienhospital Bonn',
        about: 'Website für die Hebammen am Marienhospital Bonn (in Arbeit, geplant Juni 2026)',
        creator: { '@id': 'https://www.praesenzwert.de/#organization' },
      },
    },
    {
      '@type': 'ListItem',
      position: 7,
      item: {
        '@type': 'CreativeWork',
        name: 'Möhnenverein Nierendorf e.V.',
        about: 'Vereinswebsite für den Möhnenverein Nierendorf in der Grafschaft (in Arbeit, geplant Juni 2026)',
        creator: { '@id': 'https://www.praesenzwert.de/#organization' },
      },
    },
  ],
}

const softwareProductsSchema = {
  '@context': 'https://schema.org',
  '@type': 'ItemList',
  '@id': 'https://www.praesenzwert.de/#produkte',
  name: 'Eigene Software-Produkte von PräsenzWert',
  itemListElement: [
    {
      '@type': 'ListItem',
      position: 1,
      item: {
        '@type': 'WebApplication',
        name: 'Vereins-Wahlen',
        applicationCategory: 'BusinessApplication',
        operatingSystem: 'Web',
        description: 'Digitale, rechtssichere und anonyme Abstimmungen für Vereine und Organisationen – DSGVO-konform, ohne App-Installation, mit PDF-Protokoll.',
        offers: { '@type': 'Offer', priceCurrency: 'EUR', price: '0', availability: 'https://schema.org/InStock' },
        creator: { '@id': 'https://www.praesenzwert.de/#organization' },
      },
    },
    {
      '@type': 'ListItem',
      position: 2,
      item: {
        '@type': 'Service',
        name: 'Fotobox mieten mit KI',
        serviceType: 'Fotobox-Vermietung',
        description: 'Moderne Fotobox zum Mieten mit KI-Effekten, Filtern, Sofort-Druck und Online-Fotodatenbank – für Hochzeit, Geburtstag, Firmenfeier und Vereinsfest. Deutlich günstiger als andere Anbieter.',
        areaServed: [
          { '@type': 'City', name: 'Köln' },
          { '@type': 'City', name: 'Bonn' },
          { '@type': 'AdministrativeArea', name: 'Eifel' },
        ],
        provider: { '@id': 'https://www.praesenzwert.de/#organization' },
      },
    },
    {
      '@type': 'ListItem',
      position: 3,
      item: {
        '@type': 'WebApplication',
        name: 'Bestell- & Service-System',
        applicationCategory: 'BusinessApplication',
        operatingSystem: 'Web',
        description: 'Digitales Bestellsystem für Karneval, Feste, Vereine und Gastronomie: Gäste bestellen per QR-Code, Theke und Kellner sehen alle Bestellungen live.',
        creator: { '@id': 'https://www.praesenzwert.de/#organization' },
      },
    },
    {
      '@type': 'ListItem',
      position: 4,
      item: {
        '@type': 'MobileApplication',
        name: 'MenCode',
        applicationCategory: 'LifestyleApplication',
        operatingSystem: 'Android',
        description: 'App für eine starke Beziehung: Er bekommt jeden Tag eine kleine Mission für seine Partnerin. Mit Punkten, Serien und sichtbarem Fortschritt.',
        // Die Paket-ID ist historisch (die App hiess frueher MaennerCode) und
        // darf nicht geaendert werden - sie ist die Adresse im Play Store.
        installUrl: 'https://play.google.com/store/apps/details?id=app.heidenreich.maennercode',
        url: 'https://play.google.com/store/apps/details?id=app.heidenreich.maennercode',
        offers: { '@type': 'Offer', priceCurrency: 'EUR', price: '0', availability: 'https://schema.org/InStock' },
        creator: { '@id': 'https://www.praesenzwert.de/#organization' },
      },
    },
    {
      '@type': 'ListItem',
      position: 5,
      item: {
        '@type': 'MobileApplication',
        name: 'WomenCode',
        applicationCategory: 'LifestyleApplication',
        operatingSystem: 'Android',
        description: 'Das Gegenstück zu MenCode: Sie bekommt jeden Tag eine kleine Mission für ihren Partner. Gleiche Mechanik, gespiegelte Perspektive, mit Punkten und Serien.',
        installUrl: 'https://play.google.com/store/apps/details?id=app.heidenreich.womencode',
        url: 'https://play.google.com/store/apps/details?id=app.heidenreich.womencode',
        offers: { '@type': 'Offer', priceCurrency: 'EUR', price: '0', availability: 'https://schema.org/InStock' },
        creator: { '@id': 'https://www.praesenzwert.de/#organization' },
      },
    },
  ],
}

export default function RootLayout({
  children,
}: {
  children: React.ReactNode
}) {
  return (
    <html lang="de">
      <head>
        <link rel="manifest" href="/manifest.json" />
        {/* Die Seite laedt nichts mehr von fremden Hosts - das Hero-Bild kommt
            aus dem eigenen Verzeichnis, die Schrift liegt lokal. Deshalb sind
            hier keine Preconnects mehr noetig. */}
        <meta name="theme-color" content="#0E1626" />
        <meta name="apple-mobile-web-app-capable" content="yes" />
        <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent" />
        <meta name="apple-mobile-web-app-title" content="PräsenzWert" />
        <script
          type="application/ld+json"
          dangerouslySetInnerHTML={{ __html: JSON.stringify(organizationSchema) }}
        />
        <script
          type="application/ld+json"
          dangerouslySetInnerHTML={{ __html: JSON.stringify(localBusinessSchema) }}
        />
        <script
          type="application/ld+json"
          dangerouslySetInnerHTML={{ __html: JSON.stringify(websiteSchema) }}
        />
        <script
          type="application/ld+json"
          dangerouslySetInnerHTML={{ __html: JSON.stringify(serviceSchema) }}
        />
        <script
          type="application/ld+json"
          dangerouslySetInnerHTML={{ __html: JSON.stringify(faqSchema) }}
        />
        <script
          type="application/ld+json"
          dangerouslySetInnerHTML={{ __html: JSON.stringify(portfolioSchema) }}
        />
        <script
          type="application/ld+json"
          dangerouslySetInnerHTML={{ __html: JSON.stringify(softwareProductsSchema) }}
        />
        {/* Matomo Analytics, selbst gehostet auf matomo.praesenzwert.de.
            disableCookies() macht das Tracking cookie-los (TTDSG §25 greift
            nur bei Zugriff auf Endgeraete-Speicher) - dadurch ist kein
            Consent-Banner noetig. IP-Anonymisierung ist serverseitig aktiv. */}
        <script
          dangerouslySetInnerHTML={{
            __html: `var _paq = window._paq = window._paq || [];
_paq.push(['disableCookies']);
_paq.push(['trackPageView']);
_paq.push(['enableLinkTracking']);
(function() {
  var u="https://matomo.praesenzwert.de/";
  _paq.push(['setTrackerUrl', u+'matomo.php']);
  _paq.push(['setSiteId', '1']);
  var d=document, g=d.createElement('script'), s=d.getElementsByTagName('script')[0];
  g.async=true; g.src=u+'matomo.js'; s.parentNode.insertBefore(g,s);
})();`,
          }}
        />
      </head>
      <body className={inter.className}>{children}</body>
    </html>
  )
}
