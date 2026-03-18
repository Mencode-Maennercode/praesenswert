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
    'Website Ahr',
    'Webdesign Rhein',
    'Website erstellen Rhein',
    'Webdesign Adenau',
    'Webdesign Bad Neuenahr',
    'Webdesign Ahrweiler',
    'Webdesign Mayen',
    'Webdesign Andernach',
    'Webdesign Koblenz',
    'Webdesign Cochem',
    'Webdesign Daun',
    'Webdesign Bitburg',
    'Webdesign Prüm',
    'Homepage erstellen Eifel',
    'Unternehmenswebsite KMU',
    'Firmenwebsite Rheinland-Pfalz',
    'Webdesigner Eifel',
    'Webentwicklung Ahr',
    'Google Business Profil einrichten',
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
    'ai:summary': 'PräsenzWert bietet professionelles Webdesign für KMU in der Region Eifel, Ahrtal und Rhein. Spezialisiert auf Unternehmenswebsites mit fairen Preisen und moderner Umsetzung.',
    'ai:context': 'Webdesign-Agentur, Region Rheinland-Pfalz, Eifel, Ahrtal, Rhein',
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
    { '@type': 'City', name: 'Adenau' },
    { '@type': 'City', name: 'Mayen' },
    { '@type': 'City', name: 'Andernach' },
    { '@type': 'City', name: 'Koblenz' },
    { '@type': 'City', name: 'Daun' },
    { '@type': 'City', name: 'Bitburg' },
    { '@type': 'City', name: 'Cochem' },
  ],
  knowsAbout: ['Webdesign', 'Website Entwicklung', 'SEO', 'Google Business', 'Digitale Präsenz'],
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
      </head>
      <body className={inter.className}>{children}</body>
    </html>
  )
}
