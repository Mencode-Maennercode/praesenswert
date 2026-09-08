import type { Metadata } from 'next'
import Link from 'next/link'
import { Monitor, Palette, FileText, Mail, MapPin, Minus, ArrowRight } from 'lucide-react'
import GradientGround from '../motion/GradientGround'
import LenisProvider from '../motion/LenisProvider'
import Header from '../components/Header'
import Footer from '../components/Footer'
import Section from '../components/Section'
import Reveal from '../motion/Reveal'

export const metadata: Metadata = {
  title: 'Leistungen',
  description:
    'Was in einer Website von PräsenzWert enthalten ist — und was nicht: technische Umsetzung, Struktur & Design, Inhalte, Kontaktwege, Google Business Profil. Faire Festpreise für kleine Firmen und Vereine in Eifel, Köln, Bonn und am Rhein.',
  alternates: {
    canonical: 'https://www.praesenzwert.de/leistungen/',
  },
}

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

export default function LeistungenPage() {
  return (
    <>
      <GradientGround />
      <LenisProvider />
      <Header />
      <main className="relative">
        <div className="pt-32 sm:pt-40">
          <div className="container mx-auto px-5 sm:px-6 lg:px-8">
            <Link href="/" className="text-xs uppercase tracking-[0.25em] text-mist/50 hover:text-cyan-400 transition-colors">
              ← Zurück zur Startseite
            </Link>
          </div>
        </div>

        <Section
          id="leistungen-detail"
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
                  <h2 className="mb-3 text-lg font-semibold text-mist">{leistung.titel}</h2>
                  <p className="text-sm leading-relaxed text-mist/60">{leistung.text}</p>
                </article>
              </Reveal>
            ))}
          </div>

          <Reveal verzoegerung={0.1}>
            <div className="mt-16 border-t border-mist/10 pt-12">
              <h2 className="mb-8 text-xs uppercase tracking-[0.25em] text-mist/40">
                Nicht enthalten
              </h2>
              <div className="grid gap-x-10 gap-y-7 sm:grid-cols-2">
                {nichtEnthalten.map((punkt) => (
                  <div key={punkt.titel} className="flex gap-4">
                    <Minus className="mt-1 h-4 w-4 flex-shrink-0 text-mist/25" />
                    <div>
                      <h3 className="mb-1.5 font-medium text-mist/70">{punkt.titel}</h3>
                      <p className="text-sm leading-relaxed text-mist/40">{punkt.text}</p>
                    </div>
                  </div>
                ))}
              </div>
            </div>
          </Reveal>
        </Section>

        <Section
          id="preis-einordnung"
          nummer="02"
          augenbraue="Warum es günstig ist"
          titel="Ohne billig zu sein."
          einleitung="Der Preis kommt nicht daher, dass weniger Sorgfalt hineingeht, sondern daher, dass keine Zeit in Dinge fließt, die schon gelöst sind."
        >
          <Reveal>
            <p className="max-w-2xl text-base leading-relaxed text-mist/65 sm:text-lg">
              Aktuelle Web-Frameworks und erprobte Templates als technische Basis, keine Eigenentwicklung, die
              niemand außer mir warten kann. Moderne, KI-gestützte Werkzeuge übernehmen die wiederkehrenden
              Schritte — die Entscheidungen trifft weiterhin ein Mensch. Zur Einordnung: Meine Leistung ist die
              technische Umsetzung der Website. Die Verantwortung für die Inhalte, für Impressum und Datenschutz
              und für die rechtliche Konformität bleibt beim Auftraggeber.
            </p>
          </Reveal>
          <Reveal verzoegerung={0.1}>
            <Link
              href="/#kontakt"
              className="mt-10 inline-flex items-center gap-2 rounded-full bg-cyan-400 px-6 py-3 font-medium text-slate-900 transition-colors hover:bg-cyan-300"
            >
              Unverbindlich anfragen
              <ArrowRight size={18} />
            </Link>
          </Reveal>
        </Section>
      </main>
      <Footer />
    </>
  )
}
