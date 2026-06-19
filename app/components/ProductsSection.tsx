'use client'

import { motion } from 'framer-motion'
import { Check, ExternalLink, Vote, Camera, Beer, Smartphone } from 'lucide-react'
import type { ReactNode } from 'react'

/* ----------------------------------------------------------------------------
   Eigene Software-Produkte – über die klassische Website hinaus.
   Jede Karte zeigt eine On-Brand-Vorschau (SVG-Mockup), kurze Beschreibung
   und konkrete Vorteile gegenüber gängigen Konkurrenzlösungen.
---------------------------------------------------------------------------- */

/* --- Mockups ---------------------------------------------------------------- */

function BrowserFrame({ title, children }: { title: string; children: ReactNode }) {
  return (
    <div className="w-full rounded-2xl overflow-hidden shadow-2xl ring-1 ring-brand-navy/10 bg-white">
      <div className="flex items-center gap-2 px-4 py-3 bg-brand-navy">
        <span className="w-3 h-3 rounded-full bg-red-400" />
        <span className="w-3 h-3 rounded-full bg-amber-400" />
        <span className="w-3 h-3 rounded-full bg-emerald-400" />
        <span className="ml-3 text-xs text-white/70 font-medium truncate">{title}</span>
      </div>
      <div className="p-5 bg-gradient-to-br from-brand-light to-white">{children}</div>
    </div>
  )
}

function VereinsWahlenMockup() {
  return (
    <BrowserFrame title="vereins-wahlen · Abstimmung">
      <p className="text-sm font-bold text-brand-navy mb-1">Vorstandswahl 2026</p>
      <p className="text-[11px] text-gray-500 mb-4">Bitte wähle eine Option · anonym</p>
      <div className="space-y-2.5">
        <div className="flex items-center justify-between rounded-lg border-2 border-brand-cyan bg-brand-cyan/10 px-3 py-2.5">
          <span className="text-sm font-semibold text-brand-navy">Kandidat A</span>
          <span className="flex items-center justify-center w-5 h-5 rounded-full bg-brand-cyan text-white">
            <Check className="w-3 h-3" strokeWidth={3} />
          </span>
        </div>
        <div className="flex items-center justify-between rounded-lg border-2 border-gray-200 bg-white px-3 py-2.5">
          <span className="text-sm text-gray-600">Kandidat B</span>
          <span className="w-5 h-5 rounded-full border-2 border-gray-300" />
        </div>
        <div className="flex items-center justify-between rounded-lg border-2 border-gray-200 bg-white px-3 py-2.5">
          <span className="text-sm text-gray-600">Enthaltung</span>
          <span className="w-5 h-5 rounded-full border-2 border-gray-300" />
        </div>
      </div>
      <div className="mt-4 flex items-center justify-between">
        <span className="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-1 text-[10px] font-semibold text-emerald-700 ring-1 ring-emerald-200">
          🔒 100% anonym
        </span>
        <span className="rounded-lg bg-brand-cyan px-4 py-2 text-xs font-bold text-white">Stimme abgeben</span>
      </div>
    </BrowserFrame>
  )
}

function FotoboxMockup() {
  return (
    <BrowserFrame title="fotobox · Live-Vorschau">
      <div className="flex gap-3">
        <div className="relative flex-1 rounded-xl bg-brand-navy aspect-[4/3] overflow-hidden flex items-center justify-center">
          {/* simple stylised face with sunglasses filter */}
          <svg viewBox="0 0 120 100" className="w-2/3">
            <circle cx="60" cy="55" r="30" fill="#f4d4b8" />
            <rect x="34" y="44" width="52" height="14" rx="7" fill="#1a2745" />
            <circle cx="48" cy="51" r="8" fill="#4da6b8" />
            <circle cx="72" cy="51" r="8" fill="#4da6b8" />
            <path d="M50 70 q10 8 20 0" stroke="#1a2745" strokeWidth="3" fill="none" strokeLinecap="round" />
          </svg>
          <span className="absolute top-2 left-2 rounded-md bg-black/40 px-2 py-0.5 text-[10px] font-semibold text-white">
            Filter: Sonnenbrille
          </span>
          <span className="absolute inset-0 m-auto flex h-12 w-12 items-center justify-center rounded-full bg-white/90 text-2xl font-black text-brand-navy">
            3
          </span>
        </div>
        <div className="flex w-14 flex-col gap-2">
          <div className="rounded-md bg-gradient-to-br from-brand-cyan/40 to-brand-navy/30 aspect-square" />
          <div className="rounded-md bg-gradient-to-br from-amber-200 to-brand-cyan/30 aspect-square" />
          <div className="rounded-md bg-gradient-to-br from-emerald-200 to-brand-cyan/30 aspect-square" />
        </div>
      </div>
      <div className="mt-4 flex items-center justify-between">
        <span className="text-[10px] text-gray-500">KI-Filter & Hintergründe in Echtzeit</span>
        <span className="rounded-lg bg-brand-cyan px-4 py-2 text-xs font-bold text-white">📸 Foto aufnehmen</span>
      </div>
    </BrowserFrame>
  )
}

function KarnevalMockup() {
  const orders = [
    { table: 'Tisch K17A', items: '4× Bier · 2× Wasser', color: 'border-red-400 bg-red-50', dot: 'bg-red-500', time: '0:12' },
    { table: 'Tisch M23B', items: '6× Bier · Kellner gerufen', color: 'border-amber-400 bg-amber-50', dot: 'bg-amber-500', time: '1:47' },
    { table: 'Tisch B05C', items: '2× Kölsch', color: 'border-emerald-400 bg-emerald-50', dot: 'bg-emerald-500', time: '3:21' },
  ]
  return (
    <BrowserFrame title="bestell-system · Theken-Ansicht">
      <p className="text-sm font-bold text-brand-navy mb-3 flex items-center gap-2">
        <Beer className="w-4 h-4 text-brand-cyan" /> Offene Bestellungen
      </p>
      <div className="space-y-2.5">
        {orders.map((o) => (
          <div key={o.table} className={`flex items-center justify-between rounded-lg border-2 ${o.color} px-3 py-2.5`}>
            <div className="flex items-center gap-2.5">
              <span className={`w-2.5 h-2.5 rounded-full ${o.dot}`} />
              <div>
                <p className="text-sm font-semibold text-brand-navy leading-tight">{o.table}</p>
                <p className="text-[11px] text-gray-500 leading-tight">{o.items}</p>
              </div>
            </div>
            <span className="text-xs font-bold text-gray-400 tabular-nums">{o.time}</span>
          </div>
        ))}
      </div>
      <p className="mt-3 text-[10px] text-gray-500">Farbcode nach Wartezeit · Kellner sehen nur ihre Tische</p>
    </BrowserFrame>
  )
}

function MaennerCodeMockup() {
  return (
    <div className="flex justify-center">
      <div className="relative w-44 rounded-[2rem] bg-brand-navy p-2.5 shadow-2xl ring-1 ring-brand-navy/20">
        <div className="absolute left-1/2 top-2 z-10 h-1 w-12 -translate-x-1/2 rounded-full bg-white/30" />
        <div className="overflow-hidden rounded-[1.6rem] bg-gradient-to-b from-brand-light to-white">
          <div className="bg-brand-navy px-4 pb-4 pt-7 text-center">
            <p className="text-sm font-black tracking-wide text-white">MännerCode</p>
            <p className="text-[10px] text-brand-cyan">Android App</p>
          </div>
          <div className="space-y-2 p-4">
            <div className="h-3 w-3/4 rounded-full bg-brand-cyan/30" />
            <div className="h-3 w-full rounded-full bg-gray-200" />
            <div className="h-3 w-5/6 rounded-full bg-gray-200" />
            <div className="mt-3 rounded-xl bg-brand-cyan/10 p-3">
              <div className="mb-2 h-2.5 w-1/2 rounded-full bg-brand-cyan/40" />
              <div className="h-2.5 w-4/5 rounded-full bg-gray-200" />
            </div>
            <div className="mt-2 rounded-lg bg-brand-cyan py-2 text-center text-[11px] font-bold text-white">
              Öffnen
            </div>
          </div>
        </div>
      </div>
    </div>
  )
}

/* --- Daten ------------------------------------------------------------------ */

interface Product {
  icon: typeof Vote
  badge: string
  name: string
  description: string
  advantages: string[]
  mockup: ReactNode
  cta?: { label: string; href: string }
}

const products: Product[] = [
  {
    icon: Vote,
    badge: 'Web-App · DSGVO',
    name: 'Vereins-Wahlen',
    description:
      'Digitale, rechtssichere Abstimmungen für Vereine und Organisationen. Mitglieder stimmen per QR-Code oder kurzem Code ab – ganz ohne App-Installation. Das Protokoll gibt es auf Knopfdruck als PDF.',
    advantages: [
      '100 % anonym – Stimme lässt sich nicht zurückverfolgen',
      'Konform mit Vereinsrecht & DSGVO, Serverstandort EU',
      'Keine Installation, Teilnahme direkt im Browser',
      'Daten werden nach der Auswertung unwiderruflich gelöscht',
    ],
    mockup: <VereinsWahlenMockup />,
  },
  {
    icon: Camera,
    badge: 'Hardware + Software',
    name: 'Fotobox für den Raspberry Pi',
    description:
      'Eine komplette Fotobox-Lösung auf Basis eines Raspberry Pi – mit Live-Vorschau, KI-Filtern, Hintergrund-Austausch, Sofort-Druck und QR-Download. Einmal aufgebaut, beliebig oft einsetzbar.',
    advantages: [
      'Einmalige Hardware statt teurer Miete pro Event',
      'KI-Filter & Hintergründe (Gesichtserkennung) in Echtzeit',
      'Sofort-Druck und QR-Download für die Gäste',
      'Läuft eigenständig & offline – keine Cloud nötig',
    ],
    mockup: <FotoboxMockup />,
  },
  {
    icon: Beer,
    badge: 'Web-App · Events',
    name: 'Bestell- & Service-System',
    description:
      'Digitales Bestellsystem für Karneval, Feste und Gastronomie. Gäste scannen den QR-Code am Tisch, bestellen oder rufen den Kellner – die Theke und das Service-Team behalten alles in Echtzeit im Blick.',
    advantages: [
      'Gäste bestellen per QR – ganz ohne App',
      'Farbcodierte Dringlichkeit nach Wartezeit',
      'Kellner sehen nur ihre Tische, mit Vibrationsalarm',
      'Keine teure Kassen-Hardware – läuft im Browser',
    ],
    mockup: <KarnevalMockup />,
  },
  {
    icon: Smartphone,
    badge: 'Android App · Play Store',
    name: 'MännerCode',
    description:
      'Eine native Android-App aus eigener Entwicklung – veröffentlicht und installierbar direkt über den Google Play Store. Von der Idee über die Umsetzung bis zur Store-Veröffentlichung alles aus einer Hand.',
    advantages: [
      'Veröffentlicht im Google Play Store',
      'Native Android-App, kein Baukasten',
      'Komplette Umsetzung inkl. Store-Listing',
    ],
    mockup: <MaennerCodeMockup />,
    cta: {
      label: 'Im Play Store ansehen',
      href: 'https://play.google.com/store/apps/details?id=app.heidenreich.maennercode&hl=de',
    },
  },
]

/* --- Section ---------------------------------------------------------------- */

export default function ProductsSection() {
  return (
    <section id="produkte" className="py-24 bg-gradient-to-b from-white to-brand-light/40">
      <div className="container mx-auto px-4 sm:px-6 lg:px-8">
        <motion.div
          initial={{ opacity: 0, y: 30 }}
          whileInView={{ opacity: 1, y: 0 }}
          viewport={{ once: true }}
          transition={{ duration: 0.6 }}
          className="text-center mb-16"
        >
          <span className="inline-block mb-4 rounded-full bg-brand-cyan/10 px-4 py-1.5 text-sm font-semibold text-brand-cyan">
            Mehr als Websites
          </span>
          <h2 className="text-4xl sm:text-5xl font-bold text-brand-navy mb-4">
            Eigene Software-Produkte
          </h2>
          <p className="text-xl text-gray-600 max-w-3xl mx-auto">
            Neben Websites entwickle ich eigene Web-Apps, Hardware-Lösungen und Apps –
            durchdacht, eigenständig und mit klaren Vorteilen gegenüber Standardlösungen.
          </p>
        </motion.div>

        <div className="space-y-12 max-w-6xl mx-auto">
          {products.map((product, index) => {
            const isReversed = index % 2 === 1
            return (
              <motion.div
                key={product.name}
                initial={{ opacity: 0, y: 40 }}
                whileInView={{ opacity: 1, y: 0 }}
                viewport={{ once: true }}
                transition={{ duration: 0.6 }}
                className="grid grid-cols-1 lg:grid-cols-2 gap-8 lg:gap-12 items-center bg-white rounded-3xl border border-brand-cyan/15 shadow-lg p-6 sm:p-10"
              >
                <div className={isReversed ? 'lg:order-2' : ''}>{product.mockup}</div>
                <div className={isReversed ? 'lg:order-1' : ''}>
                  <div className="flex items-center gap-3 mb-4">
                    <div className="bg-brand-cyan/10 w-12 h-12 rounded-xl flex items-center justify-center flex-shrink-0">
                      <product.icon className="w-6 h-6 text-brand-cyan" />
                    </div>
                    <span className="rounded-full bg-brand-navy/5 px-3 py-1 text-xs font-semibold text-brand-navy">
                      {product.badge}
                    </span>
                  </div>
                  <h3 className="text-2xl sm:text-3xl font-bold text-brand-navy mb-3">
                    {product.name}
                  </h3>
                  <p className="text-gray-600 leading-relaxed mb-6">{product.description}</p>
                  <ul className="space-y-2.5 mb-6">
                    {product.advantages.map((adv) => (
                      <li key={adv} className="flex items-start gap-3">
                        <span className="mt-0.5 flex h-5 w-5 flex-shrink-0 items-center justify-center rounded-full bg-emerald-100 text-emerald-600">
                          <Check className="w-3.5 h-3.5" strokeWidth={3} />
                        </span>
                        <span className="text-gray-700 text-sm leading-relaxed">{adv}</span>
                      </li>
                    ))}
                  </ul>
                  {product.cta && (
                    <a
                      href={product.cta.href}
                      target="_blank"
                      rel="noopener noreferrer"
                      className="inline-flex items-center gap-2 rounded-lg bg-brand-cyan px-5 py-3 font-semibold text-white shadow-lg transition-all duration-300 hover:bg-brand-navy hover:shadow-xl"
                    >
                      {product.cta.label}
                      <ExternalLink className="w-4 h-4" />
                    </a>
                  )}
                </div>
              </motion.div>
            )
          })}
        </div>

        <motion.div
          initial={{ opacity: 0, y: 20 }}
          whileInView={{ opacity: 1, y: 0 }}
          viewport={{ once: true }}
          transition={{ duration: 0.6 }}
          className="text-center mt-14"
        >
          <p className="text-lg text-gray-600 mb-5">
            Eine Idee, die mehr braucht als eine klassische Website?
          </p>
          <a
            href="#kontakt"
            className="inline-flex items-center gap-2 rounded-lg bg-brand-navy px-7 py-3.5 font-semibold text-white shadow-lg transition-all duration-300 hover:bg-brand-cyan hover:shadow-xl"
          >
            Projekt anfragen →
          </a>
        </motion.div>
      </div>
    </section>
  )
}
