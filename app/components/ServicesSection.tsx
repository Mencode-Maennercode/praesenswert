'use client'

import Link from 'next/link'
import { Monitor, Palette, FileText, Mail, MapPin, Minus, ArrowRight } from 'lucide-react'
import Section from './Section'
import Reveal from '../motion/Reveal'

/**
 * Leistungen.
 *
 * Die Gegenueberstellung "enthalten / nicht enthalten" bleibt inhaltlich
 * erhalten, weil sie das ehrlichste Stueck der Seite ist: sie sagt vor dem
 * Erstgespraech, wo die Leistung aufhoert.
 *
 * Farblich ist die Trennung reine Funktion, kein Dekor - was enthalten ist,
 * steht auf einer Glasflaeche mit Cyan-Symbol; was nicht enthalten ist, ist
 * durchgehend abgesenkt. Kein roter Block, keine Warnfarbe: es ist keine
 * Warnung, sondern eine Abgrenzung.
 */

const enthalten = [
  {
    icon: Monitor,
    titel: 'Technische Umsetzung',
    text: 'Die Website entsteht mit aktuellen Web-Frameworks und bewährten Templates. Solide Basis, schnelle Ladezeiten, sauber ausgeliefert.',
  },
  {
    icon: Palette,
    titel: 'Struktur & Design',
    text: 'Klare Seitenstruktur und ein Auftritt, der zum Betrieb passt — abgestimmt auf vorhandene Markenfarben und das Logo.',
  },
  {
    icon: FileText,
    titel: 'Inhalte einpflegen',
    text: 'Texte, Bilder und Unternehmensdaten werden strukturiert eingebaut, statt einfach untereinander gekippt.',
  },
  {
    icon: Mail,
    titel: 'Kontaktwege',
    text: 'Formular, Telefon, WhatsApp — technisch eingebunden und funktionsgeprüft, damit Anfragen auch wirklich ankommen.',
  },
  {
    icon: MapPin,
    titel: 'Google Business Profil',
    text: 'Auf Wunsch Einrichtung und Pflege des Profils, damit der Betrieb auch in der lokalen Suche und in Karten auftaucht.',
  },
]

const nichtEnthalten = [
  {
    titel: 'Online-Shops',
    text: 'Keine E-Commerce-Systeme, keine Zahlungsabwicklung, keine Warenwirtschaft.',
  },
  {
    titel: 'Benutzer-Logins',
    text: 'Keine geschützten Bereiche, keine Mitgliederverwaltung, keine Nutzerdatenbanken.',
  },
  {
    titel: 'Individuelle Software',
    text: 'Keine maßgeschneiderte Programmierung und keine komplexen Webanwendungen auf Zuruf.',
  },
  {
    titel: 'Rechtliche Beratung',
    text: 'Keine Rechtsberatung und keine DSGVO-Garantien. Rechtstexte kommen vom Auftraggeber.',
  },
]

export default function ServicesSection() {
  return (
    <Section
      id="leistungen"
      nummer="01"
      augenbraue="Leistungen"
      titel="Was drin ist — und was nicht."
      einleitung="Informative Websites für Betriebe und Vereine, zum Festpreis. Damit im Erstgespräch niemand aneinander vorbeiredet, steht hier beides: der Leistungsumfang und seine Grenze."
    >
      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        {enthalten.map((leistung, i) => (
          <Reveal key={leistung.titel} verzoegerung={i * 0.06}>
            <article className="pane pane-hover h-full rounded-2xl p-7 hover:-translate-y-1">
              <div className="mb-6 flex h-12 w-12 items-center justify-center rounded-xl bg-cyan/12 ring-1 ring-cyan/25">
                <leistung.icon className="h-5 w-5 text-cyan-400" />
              </div>
              <h3 className="mb-3 text-lg font-semibold text-mist">{leistung.titel}</h3>
              <p className="text-sm leading-relaxed text-mist/60">{leistung.text}</p>
            </article>
          </Reveal>
        ))}
      </div>

      <Reveal verzoegerung={0.1}>
        <div className="mt-16 border-t border-mist/10 pt-12">
          <h3 className="mb-8 text-xs uppercase tracking-[0.25em] text-mist/40">
            Nicht enthalten
          </h3>
          <div className="grid gap-x-10 gap-y-7 sm:grid-cols-2">
            {nichtEnthalten.map((punkt) => (
              <div key={punkt.titel} className="flex gap-4">
                <Minus className="mt-1 h-4 w-4 flex-shrink-0 text-mist/25" />
                <div>
                  <h4 className="mb-1.5 font-medium text-mist/70">{punkt.titel}</h4>
                  <p className="text-sm leading-relaxed text-mist/40">{punkt.text}</p>
                </div>
              </div>
            ))}
          </div>
        </div>
      </Reveal>

      <Reveal verzoegerung={0.15}>
        <Link
          href="/leistungen"
          className="mt-10 inline-flex items-center gap-2 text-sm font-medium text-cyan-400 hover:text-cyan-300 transition-colors"
        >
          Ausführliche Leistungsseite
          <ArrowRight size={16} />
        </Link>
      </Reveal>
    </Section>
  )
}
