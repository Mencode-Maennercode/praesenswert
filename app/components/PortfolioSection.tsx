'use client'

import { useEffect, useRef, useState } from 'react'
import { motion, useScroll, useTransform } from 'framer-motion'
import { ArrowUpRight, Check, Clock, Plus } from 'lucide-react'
import RevealText from '../motion/RevealText'
import Reveal from '../motion/Reveal'

/**
 * Referenzen als liegende Bahn.
 *
 * Auf grossen Bildschirmen klebt der Abschnitt und die Karten laufen seitlich
 * durch, waehrend man weiter nach unten scrollt. Unterhalb von lg gibt es das
 * nicht: dort ist die Bahn ein normaler Stapel. Erzwungenes Querscrollen auf
 * einem Telefon kaempft gegen die Wischgeste des Nutzers und fuehlt sich wie
 * ein Fehler an.
 */

type Status = 'live' | 'arbeit' | 'offen'

type Projekt = {
  name: string
  url?: string
  status: Status
  statusText: string
  text: string
  tags: string[]
  /** Zusatzabsatz, wenn an dem Projekt etwas Besonderes erklaerenswert ist. */
  notiz?: string
}

const projekte: Projekt[] = [
  {
    name: 'AG Solar GmbH',
    url: 'https://www.ag-solar.net/',
    status: 'live',
    statusText: 'Live',
    text: 'Unternehmenswebsite für einen Spezialisten für Photovoltaik, Batteriespeicher und Wallboxen aus der Grafschaft. Leistungen klar getrennt, regionaler Bezug sichtbar, Anfragewege kurz gehalten.',
    tags: ['Unternehmenswebsite', 'Erneuerbare Energien', 'Ahr · Rhein'],
  },
  {
    name: 'SR Automation',
    url: 'https://www.srautomation.de/',
    status: 'live',
    statusText: 'Live',
    text: 'Auftritt für einen Betrieb aus der Automatisierungstechnik. Technisches Thema, aber so aufgebaut, dass auch Einkäufer ohne Fachhintergrund schnell finden, was sie suchen.',
    tags: ['Unternehmenswebsite', 'Automatisierungstechnik'],
  },
  {
    name: 'Manuela Rosenkranz',
    url: 'https://manuela-rosenkranz.de/',
    status: 'live',
    statusText: 'Live',
    text: 'Praxis-Website für Individualpsychologische Beratung und therapeutische Seelsorge in Bad Neuenahr-Ahrweiler. Ruhiger Aufbau, klare Angebotsstruktur, niedrigschwellige Kontaktaufnahme.',
    // Kurz halten: die Karte hat eine feste Hoehe. Die ausfuehrliche Fassung
    // dieses Gedankens steht im FAQ ("Kann ich mein eigenes Konzept umsetzen
    // lassen?").
    notiz: 'Konzept und Gestaltung kamen von der Auftraggeberin — und wurden genau so umgesetzt. Wer eine klare Vorstellung mitbringt, bekommt sie eins zu eins.',
    tags: ['Praxis-Website', 'Beratung & Seelsorge', 'Kundenkonzept 1:1'],
  },
  {
    name: 'Realschule Am Heimbach',
    status: 'arbeit',
    statusText: 'In Arbeit',
    text: 'Schulwebsite für die Städtische Realschule Am Heimbach in Bonn. Der Schwerpunkt liegt darauf, dass Eltern und Schülerinnen und Schüler Termine, Formulare und Ansprechpartner ohne Umweg finden.',
    tags: ['Schulwebsite', 'Bildung', 'Bonn'],
  },
  {
    name: 'Hebammen am Marienhospital',
    status: 'arbeit',
    statusText: 'In Arbeit',
    text: 'Informationsseite für die Hebammen am Marienhospital Bonn. Vertrauensvoller Auftritt für werdende Eltern, mit klaren Angeboten und einem einfachen Weg zur Kontaktaufnahme.',
    tags: ['Praxis-Website', 'Gesundheit & Geburt', 'Bonn'],
  },
  {
    name: 'Nora Heidenreich',
    status: 'arbeit',
    statusText: 'In Arbeit',
    text: 'Auftritt für eine mobile Physiotherapie im Kreis Ahrweiler: Hausbesuche für Privatpatienten und Selbstzahler, Schwerpunkt Neurologie. Die Seite muss vor allem eines können — verständlich sein, auch für Angehörige.',
    tags: ['Praxis-Website', 'Physiotherapie', 'Kreis Ahrweiler'],
  },
  {
    name: 'Möhnenverein Nierendorf',
    status: 'arbeit',
    statusText: 'In Arbeit',
    text: 'Vereinswebsite für den Möhnenverein Nierendorf in der Grafschaft. Lebendig und bewegt, mit Veranstaltungen und Galerie für Möhnen, Dorfgarde und Frösche.',
    tags: ['Vereinswebsite', 'Karneval & Brauchtum', 'Grafschaft'],
  },
  {
    name: 'Ihr Projekt',
    status: 'offen',
    statusText: 'Frei',
    text: 'Hier ist Platz. Wenn ein Betrieb oder ein Verein aus der Region eine Website braucht, ist die Anfrage unverbindlich und das Erstgespräch kostenlos.',
    tags: ['Eifel · Ahr · Rhein'],
  },
]

function StatusMarke({ status, text }: { status: Status; text: string }) {
  const stil =
    status === 'live'
      ? 'text-cyan-400 ring-cyan/30'
      : status === 'arbeit'
        ? 'text-wip ring-wip/30'
        : 'text-ember ring-ember/30'

  const Symbol = status === 'live' ? Check : status === 'arbeit' ? Clock : Plus

  return (
    <span
      className={`inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-[11px] font-semibold uppercase tracking-wider ring-1 ${stil}`}
    >
      <Symbol className="h-3 w-3" />
      {text}
    </span>
  )
}

function Karte({ projekt }: { projekt: Projekt }) {
  const inhalt = (
    <>
      <div className="mb-6 flex items-start justify-between gap-4">
        <StatusMarke status={projekt.status} text={projekt.statusText} />
        {projekt.url && (
          <ArrowUpRight className="h-5 w-5 flex-shrink-0 text-mist/35 transition-all duration-300 ease-brand group-hover:-translate-y-0.5 group-hover:translate-x-0.5 group-hover:text-ember" />
        )}
      </div>

      <h3 className="mb-4 text-2xl font-semibold text-mist">{projekt.name}</h3>
      <p className="mb-5 text-sm leading-relaxed text-mist/60">{projekt.text}</p>

      {projekt.notiz && (
        <p className="mb-5 border-l-2 border-cyan/40 pl-4 text-sm leading-relaxed text-mist/50">
          {projekt.notiz}
        </p>
      )}

      <div className="mt-auto flex flex-wrap gap-2 pt-2">
        {projekt.tags.map((tag) => (
          <span
            key={tag}
            className="rounded-full bg-mist/[0.06] px-3 py-1 text-xs text-mist/50"
          >
            {tag}
          </span>
        ))}
      </div>
    </>
  )

  const klassen =
    'pane pane-hover group flex h-full w-full flex-col overflow-hidden rounded-2xl p-7 ' +
    (projekt.status === 'offen' ? 'border-dashed' : '')

  if (projekt.url) {
    return (
      <a href={projekt.url} target="_blank" rel="noopener noreferrer" className={klassen}>
        {inhalt}
      </a>
    )
  }

  if (projekt.status === 'offen') {
    return (
      <a href="#kontakt" className={klassen}>
        {inhalt}
      </a>
    )
  }

  return <article className={klassen}>{inhalt}</article>
}

export default function PortfolioSection() {
  const buehne = useRef<HTMLDivElement>(null)
  const bahn = useRef<HTMLDivElement>(null)
  const [weg, setWeg] = useState(0)

  // Wie weit die Bahn wandern muss, damit die letzte Karte am rechten Rand
  // ankommt. Muss gemessen werden, weil Kartenbreite und Fensterbreite
  // voneinander unabhaengig sind.
  useEffect(() => {
    const messen = () => {
      if (!bahn.current) return
      setWeg(Math.max(0, bahn.current.scrollWidth - window.innerWidth + 64))
    }
    messen()
    window.addEventListener('resize', messen)
    return () => window.removeEventListener('resize', messen)
  }, [])

  const { scrollYProgress } = useScroll({
    target: buehne,
    offset: ['start start', 'end end'],
  })
  const schub = useTransform(scrollYProgress, [0, 1], [0, -weg])

  return (
    <section id="referenzen" className="relative py-24 sm:py-32">
      <div className="container mx-auto px-5 sm:px-6 lg:px-8">
        <div className="mb-14 max-w-3xl sm:mb-20">
          <Reveal className="mb-5 flex items-center gap-4">
            <span className="font-mono text-xs tabular-nums text-ember">03</span>
            <span className="h-px w-10 bg-mist/20" />
            <span className="text-xs uppercase tracking-[0.25em] text-mist/50">
              Referenzen
            </span>
          </Reveal>
          <RevealText
            text="Gebaut, in Arbeit, und einer noch frei."
            className="text-headline text-balance font-bold text-mist"
          />
          <Reveal verzoegerung={0.1}>
            <p className="mt-6 max-w-2xl text-base leading-relaxed text-mist/65 sm:text-lg">
              Betriebe, Praxen, eine Schule und ein Karnevalsverein — die meisten
              davon aus der Region zwischen Eifel, Ahr und Rhein.
            </p>
          </Reveal>
        </div>
      </div>

      {/* Bis lg: normaler Stapel. Querscrollen auf dem Telefon kaempft gegen
          die Wischgeste und wirkt wie ein Fehler. */}
      <div className="container mx-auto grid gap-4 px-5 sm:grid-cols-2 sm:px-6 lg:hidden">
        {projekte.map((projekt, i) => (
          <Reveal key={projekt.name} verzoegerung={i * 0.04}>
            <Karte projekt={projekt} />
          </Reveal>
        ))}
      </div>

      {/* Ab lg: die Buehne klebt, die Bahn laeuft seitlich durch. Die Hoehe der
          Buehne bestimmt, wie lange das dauert. */}
      <div
        ref={buehne}
        className="relative hidden lg:block"
        // Der Ausgangswert von weg ist 0, also rendern Server und Browser beim
        // ersten Bild dasselbe - erst die Messung im Effekt verlaengert die
        // Buehne. Mit einem geratenen Pixelwert gaebe es hier einen
        // Hydrations-Konflikt.
        style={{ height: `calc(100vh + ${Math.round(weg * 0.85)}px)` }}
      >
        <div className="sticky top-0 flex h-screen items-center overflow-hidden">
          <motion.div ref={bahn} style={{ x: schub }} className="flex gap-5 pl-8 pr-16">
            {projekte.map((projekt) => (
              // Feste Kartenhoehe. Ohne sie richtet sich die Reihe nach der
              // laengsten Karte, und die ragt dann oben und unten aus dem
              // klebenden Bildschirm heraus - Ueberschriften werden abgeschnitten.
              <div
                key={projekt.name}
                className="h-[520px] w-[360px] flex-shrink-0 xl:w-[400px]"
              >
                <Karte projekt={projekt} />
              </div>
            ))}
          </motion.div>
        </div>
      </div>
    </section>
  )
}
