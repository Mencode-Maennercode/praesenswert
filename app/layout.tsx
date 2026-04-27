import type { Metadata } from 'next'
import { Inter } from 'next/font/google'
import './globals.css'

const inter = Inter({ subsets: ['latin'] })

export const metadata: Metadata = {
  metadataBase: new URL('https://www.praesenzwert.de'),
  title: {
    default: 'PräsenzWert – Webdesign Eifel, Ahr & Rhein | Websites für KMU',
    template: '%s | PräsenzWert Webdesign',
  },
  description: 'Professionelle Unternehmenswebsites für kleine und mittelständische Betriebe in der Eifel, Ahrtal und am Rhein. Faire Preise, moderne Umsetzung, klare Struktur. Jetzt unverbindlich anfragen!',
  keywords: [
    'Webdesign Eifel',
    'Website erstellen Eifel',
    'Webdesign Ahrtal',
    'Webdesign Ahr',
    'Website Ahr',
    'Webdesign Rhein',
    'Website erstellen Rhein',
    'Webdesign Grafschaft',
    'Webdesign Remagen',
    'Webdesign Sinzig',
    'Webdesign Linz am Rhein',
    'Webdesign Neuwied',
    'Webdesign Bonn',
    'Webdesign Euskirchen',
    'Webdesign Adenau',
    'Webdesign Bad Neuenahr',
    'Webdesign Bad Neuenahr-Ahrweiler',
    'Webdesign Ahrweiler',
    'Webdesign Mayen',
    'Webdesign Andernach',
    'Webdesign Koblenz',
    'Webdesign Cochem',
    'Webdesign Daun',
    'Webdesign Bitburg',
    'Webdesign Prüm',
    'Webagentur Eifel',
    'Webagentur Ahrtal',
    'Webagentur Rhein',
    'Homepage erstellen Eifel',
    'Homepage erstellen Ahrtal',
    'Homepage erstellen Rhein',
    'Unternehmenswebsite KMU',
    'Firmenwebsite Rheinland-Pfalz',
    'Webdesigner Eifel',
    'Webdesigner Ahrtal',
    'Webentwicklung Ahr',
    'SEO Eifel',
    'SEO Ahrtal',
    'SEO Rhein',
    'lokale SEO Rheinland-Pfalz',
    'Google Ranking Ahrtal',
    'KI SEO Eifel',
    'AI Search Optimierung',
    'Google Business Profil einrichten',
    'Website für Handwerker Eifel',
    'Website für Praxis Ahrtal',
    'Website für Solarbetriebe',
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
    siteName: 'PräsenzWert – Professionelles Webdesign',
    title: 'PräsenzWert – Webdesign Eifel, Ahr & Rhein | Websites für KMU',
    description: 'Professionelle Unternehmenswebsites für kleine und mittelständische Betriebe in der Eifel, Ahrtal und am Rhein. Faire Preise, moderne Umsetzung, klare Struktur. Jetzt unverbindlich anfragen!',
    images: [
      {
        url: 'https://www.praesenzwert.de/Logo_Transparent.png',
        width: 1200,
        height: 630,
        alt: 'PräsenzWert – Webdesign für die Region Eifel, Ahr und Rhein',
        type: 'image/png',
      },
    ],
  },
  twitter: {
    card: 'summary_large_image',
    title: 'PräsenzWert – Webdesign Eifel, Ahr & Rhein',
    description: 'Professionelle Unternehmenswebsites für KMU in der Eifel, Ahrtal und am Rhein.',
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
    'ai:summary': 'PräsenzWert bietet professionelles Webdesign für KMU in der Region Eifel, Ahrtal, Grafschaft und Rhein. Spezialisiert auf Unternehmenswebsites mit fairen Preisen, moderner Umsetzung und lokaler SEO-Optimierung für Google und KI-Suchmaschinen.',
    'ai:context': 'Webdesign-Agentur, Region Rheinland-Pfalz, Eifel, Ahrtal, Grafschaft, Rhein, Bad Neuenahr-Ahrweiler, Remagen, Sinzig, Adenau, Koblenz',
    'geo.region': 'DE-RP',
    'geo.placename': 'Grafschaft, Ahrtal, Eifel, Rhein',
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
  description: 'Professionelle Unternehmenswebsites für kleine und mittelständische Betriebe in der Region Eifel, Ahr und Rhein.',
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
    { '@type': 'AdministrativeArea', name: 'Rheinland-Pfalz' },
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
    { '@type': 'City', name: 'Bonn' },
    { '@type': 'City', name: 'Euskirchen' },
  ],
  knowsAbout: ['Webdesign', 'Website Entwicklung', 'SEO', 'Lokale SEO', 'KI-Suchmaschinen-Optimierung', 'Google Business Profil', 'Digitale Präsenz', 'Unternehmenswebsites KMU'],
  sameAs: [
    'https://www.srautomation.de/',
    'https://www.ag-solar.net/',
    'https://manuela-rosenkranz.de/',
  ],
}

const localBusinessSchema = {
  '@context': 'https://schema.org',
  '@type': 'LocalBusiness',
  '@id': 'https://www.praesenzwert.de/#localbusiness',
  name: 'PräsenzWert',
  description: 'Professionelle Unternehmenswebsites für kleine und mittelständische Betriebe in der Region Eifel, Ahr und Rhein.',
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
  areaServed: [
    { '@type': 'AdministrativeArea', name: 'Eifel' },
    { '@type': 'AdministrativeArea', name: 'Ahrtal' },
    { '@type': 'AdministrativeArea', name: 'Rhein' },
    { '@type': 'AdministrativeArea', name: 'Rheinland-Pfalz' },
    { '@type': 'City', name: 'Bad Neuenahr-Ahrweiler' },
    { '@type': 'City', name: 'Adenau' },
    { '@type': 'City', name: 'Mayen' },
    { '@type': 'City', name: 'Andernach' },
    { '@type': 'City', name: 'Koblenz' },
    { '@type': 'City', name: 'Daun' },
    { '@type': 'City', name: 'Bitburg' },
    { '@type': 'City', name: 'Cochem' },
  ],
  serviceType: ['Webdesign', 'Website Erstellung', 'Unternehmenswebsite', 'Google Business Profil'],
  priceRange: '€€',
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
  serviceType: 'Webdesign und Website Entwicklung',
  provider: {
    '@id': 'https://www.praesenzwert.de/#organization',
  },
  areaServed: [
    { '@type': 'AdministrativeArea', name: 'Eifel' },
    { '@type': 'AdministrativeArea', name: 'Ahrtal' },
    { '@type': 'AdministrativeArea', name: 'Rheinland-Pfalz' },
  ],
  hasOfferCatalog: {
    '@type': 'OfferCatalog',
    name: 'Webdesign Dienstleistungen',
    itemListElement: [
      {
        '@type': 'Offer',
        itemOffered: {
          '@type': 'Service',
          name: 'Unternehmenswebsite Erstellung',
          description: 'Professionelle Websites für kleine und mittelständische Unternehmen',
        },
      },
      {
        '@type': 'Offer',
        itemOffered: {
          '@type': 'Service',
          name: 'Google Business Profil Einrichtung',
          description: 'Optimierung der Online-Präsenz für lokale Unternehmen',
        },
      },
    ],
  },
}

const faqSchema = {
  '@context': 'https://schema.org',
  '@type': 'FAQPage',
  '@id': 'https://www.praesenzwert.de/#faq',
  mainEntity: [
    {
      '@type': 'Question',
      name: 'Welche Regionen bedient PräsenzWert?',
      acceptedAnswer: {
        '@type': 'Answer',
        text: 'PräsenzWert ist auf Unternehmen in der Region Eifel, Ahrtal, Grafschaft und am Rhein spezialisiert – darunter Bad Neuenahr-Ahrweiler, Remagen, Sinzig, Adenau, Mayen, Andernach, Koblenz, Bonn und Umgebung.',
      },
    },
    {
      '@type': 'Question',
      name: 'Was kostet eine professionelle Unternehmenswebsite?',
      acceptedAnswer: {
        '@type': 'Answer',
        text: 'Die Preise richten sich nach Umfang und Anforderungen. PräsenzWert bietet faire, transparente Festpreise für KMU in der Eifel, im Ahrtal und am Rhein. Eine unverbindliche Anfrage ist jederzeit möglich.',
      },
    },
    {
      '@type': 'Question',
      name: 'Werden die Websites auch für Google und KI-Suchmaschinen optimiert?',
      acceptedAnswer: {
        '@type': 'Answer',
        text: 'Ja. Jede Website wird technisch SEO-optimiert ausgeliefert (semantisches HTML, strukturierte Daten, Schema.org, Sitemap, robots.txt) und ist explizit für KI-Crawler wie GPTBot, ChatGPT-User, ClaudeBot, PerplexityBot und Google-Extended freigegeben.',
      },
    },
    {
      '@type': 'Question',
      name: 'Welche Referenzen gibt es?',
      acceptedAnswer: {
        '@type': 'Answer',
        text: 'Aktuelle Referenzen sind u.a. SR Automation (Automatisierungstechnik), AG Solar GmbH (Photovoltaik & Wallboxen, Grafschaft/Ahr/Rhein) und die Praxis Manuela Rosenkranz (Individualpsychologische Beratung, Bad Neuenahr-Ahrweiler).',
      },
    },
  ],
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
        name: 'SR Automation',
        url: 'https://www.srautomation.de/',
        about: 'Unternehmenswebsite für einen Automatisierungstechnik-Spezialisten',
        creator: { '@id': 'https://www.praesenzwert.de/#organization' },
      },
    },
    {
      '@type': 'ListItem',
      position: 2,
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
      position: 3,
      item: {
        '@type': 'CreativeWork',
        name: 'Manuela Rosenkranz – Praxis für Individualpsychologische Beratung',
        url: 'https://manuela-rosenkranz.de/',
        about: 'Praxis-Website für Individualpsychologische Beratung & therapeutische Seelsorge in Bad Neuenahr-Ahrweiler',
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
        <link rel="preconnect" href="https://fonts.googleapis.com" />
        <link rel="preconnect" href="https://fonts.gstatic.com" crossOrigin="anonymous" />
        <link rel="dns-prefetch" href="https://images.unsplash.com" />
        <meta name="theme-color" content="#1e293b" />
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
      </head>
      <body className={inter.className}>{children}</body>
    </html>
  )
}
