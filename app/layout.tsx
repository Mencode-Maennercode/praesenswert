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
  robots: {
    index: true,
    follow: true,
    googleBot: {
      index: true,
      follow: true,
      'max-image-preview': 'large',
      'max-snippet': -1,
    },
  },
  openGraph: {
    type: 'website',
    locale: 'de_DE',
    url: 'https://www.praesenzwert.de',
    siteName: 'PräsenzWert',
    title: 'PräsenzWert – Webdesign Eifel, Ahr & Rhein',
    description: 'Professionelle Unternehmenswebsites für KMU in der Eifel, Ahrtal und am Rhein. Faire Preise, moderne Umsetzung. Jetzt unverbindlich anfragen!',
    images: [
      {
        url: '/Logo_Transparent.png',
        width: 1200,
        height: 630,
        alt: 'PräsenzWert – Webdesign für die Region Eifel, Ahr und Rhein',
      },
    ],
  },
  twitter: {
    card: 'summary_large_image',
    title: 'PräsenzWert – Webdesign Eifel, Ahr & Rhein',
    description: 'Professionelle Unternehmenswebsites für KMU in der Eifel, Ahrtal und am Rhein.',
    images: ['/Logo_Transparent.png'],
  },
  alternates: {
    canonical: 'https://www.praesenzwert.de',
  },
}

const localBusinessSchema = {
  '@context': 'https://schema.org',
  '@type': 'LocalBusiness',
  name: 'PräsenzWert',
  description: 'Professionelle Unternehmenswebsites für kleine und mittelständische Betriebe in der Region Eifel, Ahr und Rhein.',
  url: 'https://www.praesenzwert.de',
  email: 'info@praesenzwert.de',
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
  sameAs: [],
}

export default function RootLayout({
  children,
}: {
  children: React.ReactNode
}) {
  return (
    <html lang="de">
      <head>
        <script
          type="application/ld+json"
          dangerouslySetInnerHTML={{ __html: JSON.stringify(localBusinessSchema) }}
        />
      </head>
      <body className={inter.className}>{children}</body>
    </html>
  )
}
