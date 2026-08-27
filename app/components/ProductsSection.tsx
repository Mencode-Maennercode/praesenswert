'use client'

import Image from 'next/image'
import type { ReactNode } from 'react'
import { ArrowUpRight, Check, Vote, Camera, Beer, Smartphone } from 'lucide-react'
import RevealText from '../motion/RevealText'
import Reveal from '../motion/Reveal'

/**
 * Eigene Produkte.
 *
 * Die Karten kleben gestaffelt uebereinander: jede legt sich beim Scrollen auf
 * die vorherige, statt dass alle untereinander weglaufen. Vier Produkte in
 * einer normalen Liste sind ein langer, gleichfoermiger Block - gestapelt
 * bleibt jedes einzeln lesbar.
 *
 * Die Karten sind dichter als die uebrigen Glasflaechen (ink statt mist), weil
 * sich sonst beim Stapeln drei Transparenzen ueberlagern und alles vermatscht.
 * Ink ist dieselbe Farbe wie der Grund, nur dichter - der Guss bleibt heil.
 *
 * MenCode und WomenCode zeigen die echten Play-Store-Aufnahmen und behalten
 * dort ihre eigenen Markenfarben (Gold und Roségold). Das sind fremde Marken,
 * kein Dekor; sie liegen als Objekte auf dem Guss, ohne ihn zu unterbrechen.
 */

/* --- Nachbauten fuer die Produkte ohne Fotomaterial ---------------------- */

function Rahmen({ titel, children }: { titel: string; children: ReactNode }) {
  return (
    <div className="w-full overflow-hidden rounded-2xl border border-mist/12 bg-ink/70">
      <div className="flex items-center gap-2 border-b border-mist/10 px-4 py-3">
        <span className="h-2.5 w-2.5 rounded-full bg-mist/20" />
        <span className="h-2.5 w-2.5 rounded-full bg-mist/20" />
        <span className="h-2.5 w-2.5 rounded-full bg-mist/20" />
        <span className="ml-2 truncate font-mono text-[11px] text-mist/40">{titel}</span>
      </div>
      <div className="p-5">{children}</div>
    </div>
  )
}

function WahlenBild() {
  return (
    <Rahmen titel="vereins-wahlen · Abstimmung">
      <p className="text-sm font-semibold text-mist">Vorstandswahl 2026</p>
      <p className="mb-4 mt-1 text-[11px] text-mist/40">Eine Option wählen · anonym</p>
      <div className="space-y-2">
        <div className="flex items-center justify-between rounded-lg border border-cyan/50 bg-cyan/10 px-3 py-2.5">
          <span className="text-sm font-medium text-mist">Kandidat A</span>
          <span className="flex h-5 w-5 items-center justify-center rounded-full bg-cyan text-ink">
            <Check className="h-3 w-3" strokeWidth={3} />
          </span>
        </div>
        {['Kandidat B', 'Enthaltung'].map((option) => (
          <div
            key={option}
            className="flex items-center justify-between rounded-lg border border-mist/10 px-3 py-2.5"
          >
            <span className="text-sm text-mist/55">{option}</span>
            <span className="h-5 w-5 rounded-full border border-mist/20" />
          </div>
        ))}
      </div>
      <div className="mt-4 flex items-center justify-between">
        <span className="rounded-full px-2.5 py-1 text-[10px] font-semibold uppercase tracking-wider text-cyan-400 ring-1 ring-cyan/25">
          100 % anonym
        </span>
        <span className="rounded-lg bg-ember px-4 py-2 text-xs font-bold text-ink">
          Stimme abgeben
        </span>
      </div>
    </Rahmen>
  )
}

function FotoboxBild() {
  return (
    <Rahmen titel="fotobox · Live-Vorschau">
      <div className="flex gap-3">
        <div className="relative flex aspect-[4/3] flex-1 items-center justify-center overflow-hidden rounded-xl border border-mist/10 bg-navy">
          {/* Bewusst eine abstrakte Silhouette. Die echten Testaufnahmen der
              Fotobox zeigen erkennbare Personen - die gehoeren ohne deren
              Einwilligung auf keine oeffentliche Seite. */}
          <svg viewBox="0 0 120 100" className="w-2/3" aria-hidden>
            <circle cx="60" cy="52" r="26" fill="none" stroke="#4DA6B8" strokeWidth="1.5" opacity="0.5" />
            <rect x="38" y="44" width="44" height="11" rx="5.5" fill="#4DA6B8" opacity="0.85" />
            <path d="M50 68 q10 7 20 0" stroke="#4DA6B8" strokeWidth="2" fill="none" strokeLinecap="round" opacity="0.7" />
          </svg>
          <span className="absolute left-2 top-2 rounded-md bg-ink/70 px-2 py-0.5 text-[10px] text-mist/60">
            Filter: Sonnenbrille
          </span>
          <span className="absolute inset-0 m-auto flex h-11 w-11 items-center justify-center rounded-full bg-mist/90 text-xl font-black text-ink">
            3
          </span>
        </div>
        <div className="flex w-12 flex-col gap-2">
          {['from-cyan/30', 'from-wip/25', 'from-ember/25'].map((ton) => (
            <div
              key={ton}
              className={`aspect-square rounded-md bg-gradient-to-br ${ton} to-navy/40`}
            />
          ))}
        </div>
      </div>
      <div className="mt-4 flex items-center justify-between">
        <span className="text-[10px] text-mist/40">KI-Effekte in Echtzeit</span>
        <span className="rounded-lg bg-ember px-4 py-2 text-xs font-bold text-ink">
          Foto aufnehmen
        </span>
      </div>
    </Rahmen>
  )
}

function Telefon({
  quelle,
  alt,
  ton,
}: {
  quelle: string
  alt: string
  ton: string
}) {
  return (
    <div
      className="relative w-full max-w-[190px] rounded-[1.8rem] border p-2"
      style={{ borderColor: `${ton}55`, background: '#0E1626' }}
    >
      <div className="overflow-hidden rounded-[1.4rem]">
        <Image
          src={quelle}
          alt={alt}
          width={540}
          height={960}
          className="h-auto w-full"
          sizes="190px"
        />
      </div>
    </div>
  )
}

/* --- Daten ---------------------------------------------------------------- */

type Aktion = { text: string; href: string; extern?: boolean }

type Produkt = {
  icon: typeof Vote
  marke: string
  name: string
  text: string
  vorteile: string[]
  bild: ReactNode
  aktionen?: Aktion[]
}

const produkte: Produkt[] = [
  {
    icon: Smartphone,
    marke: 'Android Apps · Play Store',
    name: 'MenCode & WomenCode',
    text: 'Zwei Apps, ein Gedanke: jeden Tag eine kleine Mission für den Menschen an der Seite. MenCode für ihn, WomenCode für sie — gleiche Mechanik, gespiegelte Perspektive. Mit Punkten, Serien und sichtbarem Fortschritt.',
    vorteile: [
      'Jeden Tag eine konkrete Mission statt vager Ratschläge',
      'Punkte und Serien halten die Gewohnheit am Laufen',
      '„Warum es wirkt“ erklärt bei jeder Mission den Hintergrund',
      'Beide Apps kostenlos im Google Play Store',
    ],
    bild: (
      <div className="flex items-start justify-center gap-3 sm:gap-4">
        <Telefon
          quelle="/products/mencode.png"
          alt="MenCode: Mission des Tages mit Punktestand und Serie"
          ton="#D9B25F"
        />
        <Telefon
          quelle="/products/womencode.png"
          alt="WomenCode: Mission des Tages mit Punktestand und Serie"
          ton="#C89B8C"
        />
      </div>
    ),
    aktionen: [
      {
        text: 'MenCode',
        href: 'https://play.google.com/store/apps/details?id=app.heidenreich.maennercode&hl=de',
        extern: true,
      },
      {
        text: 'WomenCode',
        href: 'https://play.google.com/store/apps/details?id=app.heidenreich.womencode&hl=de',
        extern: true,
      },
    ],
  },
  {
    icon: Beer,
    marke: 'Web-App · Events & Gastro',
    name: 'Bestell- & Service-System',
    text: 'Digitales Bestellsystem für Karneval, Feste, Vereine und Gastronomie. Gäste scannen den QR-Code am Tisch, bestellen oder rufen den Service. Theke und Kellner sehen jede Bestellung live.',
    vorteile: [
      'Gäste bestellen per QR-Code, ganz ohne App',
      'Theke und Kellner sehen alle Bestellungen in Echtzeit',
      'Farbcodierte Dringlichkeit nach Wartezeit',
      'Keine Kassen-Hardware nötig — läuft im Browser',
    ],
    // Hochformat: ohne Breitenbegrenzung wird das Foto ueber eine halbe
    // Kartenbreite hoch und drueckt die Textspalte aus dem Bild.
    bild: (
      <div className="mx-auto w-full max-w-[280px] overflow-hidden rounded-2xl border border-mist/12">
        <Image
          src="/products/bestellsystem.png"
          alt="Ein Gast bestellt am Tisch über den QR-Code mit dem Smartphone"
          width={317}
          height={621}
          className="h-auto w-full"
          sizes="280px"
        />
      </div>
    ),
    aktionen: [{ text: 'System ansehen', href: '/bestell-bar' }],
  },
  {
    icon: Vote,
    marke: 'Web-App · DSGVO',
    name: 'Vereins-Wahlen',
    text: 'Digitale Abstimmungen für Vereine und Organisationen. Mitglieder stimmen per QR-Code oder Kurzcode ab, ganz ohne App-Installation. Das Protokoll gibt es danach auf Knopfdruck als PDF.',
    vorteile: [
      'Anonym — eine abgegebene Stimme lässt sich niemandem zuordnen',
      'Auf Vereinsrecht und DSGVO ausgelegt, Server in der EU',
      'Keine Installation, Teilnahme direkt im Browser',
      'Nach der Auswertung werden die Daten unwiderruflich gelöscht',
    ],
    bild: <WahlenBild />,
  },
  {
    icon: Camera,
    marke: 'Vermietung · KI-Effekte',
    name: 'Fotobox mieten',
    text: 'Die Fotobox für Hochzeit, Geburtstag, Firmenfeier oder Vereinsfest in der Eifel, in Köln und Bonn. Mit KI-Effekten, Filtern, Sofortdruck und einer Online-Galerie für alle Aufnahmen.',
    vorteile: [
      'Deutlich günstiger als die üblichen Fotobox-Anbieter',
      'KI-Effekte und Filter mit Gesichtserkennung in Echtzeit',
      'Online-Galerie — alle Bilder sicher gespeichert und teilbar',
      'Sofortdruck vor Ort plus QR-Download für die Gäste',
      'Aufbau und Einweisung sind inklusive',
    ],
    bild: <FotoboxBild />,
    aktionen: [{ text: 'Fotobox anfragen', href: '#kontakt' }],
  },
]

/* --- Abschnitt ------------------------------------------------------------ */

export default function ProductsSection() {
  return (
    <section id="produkte" className="relative py-24 sm:py-32">
      <div className="container mx-auto px-5 sm:px-6 lg:px-8">
        <div className="mb-14 max-w-3xl sm:mb-20">
          <Reveal className="mb-5 flex items-center gap-4">
            <span className="font-mono text-xs tabular-nums text-ember">04</span>
            <span className="h-px w-10 bg-mist/20" />
            <span className="text-xs uppercase tracking-[0.25em] text-mist/50">
              Eigene Produkte
            </span>
          </Reveal>
          <RevealText
            text="Was ich baue, wenn eine Website nicht reicht."
            className="text-headline text-balance font-bold text-mist"
          />
          <Reveal verzoegerung={0.1}>
            <p className="mt-6 max-w-2xl text-base leading-relaxed text-mist/65 sm:text-lg">
              Neben Websites entstehen hier eigene Web-Apps, eine vermietbare
              Fotobox und zwei Apps im Play Store — alles aus konkreten
              Anlässen in der Region heraus gebaut.
            </p>
          </Reveal>
        </div>

        <div className="space-y-6">
          {produkte.map((produkt, i) => (
            <div
              key={produkt.name}
              className="sticky"
              // Jede Karte kommt 14px tiefer zum Stehen als die davor, sodass
              // die Kante der vorherigen sichtbar bleibt und der Stapel
              // als Stapel lesbar ist.
              style={{ top: `${96 + i * 14}px` }}
            >
              <Reveal>
                <article className="overflow-hidden rounded-3xl border border-mist/12 bg-ink/85 backdrop-blur-xl">
                  <div className="grid items-center gap-8 p-6 sm:p-10 lg:grid-cols-2 lg:gap-14">
                    <div className={i % 2 === 1 ? 'lg:order-2' : ''}>{produkt.bild}</div>

                    <div className={i % 2 === 1 ? 'lg:order-1' : ''}>
                      <div className="mb-5 flex items-center gap-3">
                        <span className="flex h-10 w-10 items-center justify-center rounded-xl bg-cyan/12 ring-1 ring-cyan/25">
                          <produkt.icon className="h-4 w-4 text-cyan-400" />
                        </span>
                        <span className="text-[11px] uppercase tracking-[0.2em] text-mist/45">
                          {produkt.marke}
                        </span>
                      </div>

                      <h3 className="mb-4 text-2xl font-semibold text-mist sm:text-3xl">
                        {produkt.name}
                      </h3>
                      <p className="mb-7 leading-relaxed text-mist/60">{produkt.text}</p>

                      <ul className="mb-8 space-y-3">
                        {produkt.vorteile.map((vorteil) => (
                          <li key={vorteil} className="flex items-start gap-3">
                            <Check
                              className="mt-1 h-3.5 w-3.5 flex-shrink-0 text-cyan-400"
                              strokeWidth={3}
                            />
                            <span className="text-sm leading-relaxed text-mist/65">
                              {vorteil}
                            </span>
                          </li>
                        ))}
                      </ul>

                      {produkt.aktionen && (
                        <div className="flex flex-wrap gap-3">
                          {produkt.aktionen.map((aktion) => (
                            <a
                              key={aktion.text}
                              href={aktion.href}
                              {...(aktion.extern
                                ? { target: '_blank', rel: 'noopener noreferrer' }
                                : {})}
                              className="group inline-flex items-center gap-2 rounded-full border border-mist/20 px-5 py-2.5 text-sm font-medium text-mist/85 transition-colors duration-300 ease-brand hover:border-ember/60 hover:text-ember"
                            >
                              {aktion.text}
                              <ArrowUpRight className="h-3.5 w-3.5 transition-transform duration-300 ease-brand group-hover:-translate-y-0.5 group-hover:translate-x-0.5" />
                            </a>
                          ))}
                        </div>
                      )}
                    </div>
                  </div>
                </article>
              </Reveal>
            </div>
          ))}
        </div>
      </div>
    </section>
  )
}
