'use client'

import { useState } from 'react'
import { Mail, Phone, MapPin, Send, CheckCircle2, AlertTriangle } from 'lucide-react'
import Section from './Section'
import Reveal from '../motion/Reveal'

/**
 * Kontakt.
 *
 * Hier laeuft der Farbguss aus und Ember blueht auf. Auf den vorangegangenen
 * sechs Abschnitten ist Ember nur in winzigen Dosen aufgetaucht (Nummern,
 * Fokusrahmen, ein Knopf) - deshalb wirkt es hier, wo es zum ersten Mal
 * flaechig auftritt.
 *
 * ACHTUNG Telefonnummer: die alte Seite war hier widerspruechlich. Sichtbarer
 * Text und Organization-Schema nannten 0171 / 7460398, der tel:-Link und der
 * WhatsApp-Link aber 0171 / 4760398 (Ziffern gedreht). Hier steht ueberall die
 * Variante, die zweimal belegt war - das gehoert vom Betreiber bestaetigt.
 */

const TELEFON_ANZEIGE = '0171 / 7460398'
const TELEFON_WAEHLEN = '+491717460398'
const WHATSAPP = 'https://wa.me/491717460398'

type Zustand = 'ruhe' | 'sendet' | 'fertig' | 'fehler'

export default function ContactSection() {
  const [daten, setDaten] = useState({
    name: '',
    email: '',
    company: '',
    phone: '',
    message: '',
  })
  const [zustand, setZustand] = useState<Zustand>('ruhe')

  const aendern = (e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement>) => {
    setDaten({ ...daten, [e.target.name]: e.target.value })
    if (zustand === 'fehler') setZustand('ruhe')
  }

  const senden = async (e: React.FormEvent) => {
    e.preventDefault()
    setZustand('sendet')
    try {
      // Feldnamen und Ziel muessen so bleiben - contact.php erwartet genau das.
      const antwort = await fetch('/contact.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(daten),
      })
      if (!antwort.ok) throw new Error(String(antwort.status))
      setZustand('fertig')
      setDaten({ name: '', email: '', company: '', phone: '', message: '' })
    } catch {
      setZustand('fehler')
    }
  }

  const feldStil =
    'w-full rounded-xl border border-mist/15 bg-mist/[0.04] px-4 py-3 text-mist ' +
    'placeholder:text-mist/30 transition-colors duration-300 ease-brand ' +
    'focus:border-ember/60 focus:bg-mist/[0.07] focus:outline-none'

  return (
    <Section
      id="kontakt"
      nummer="07"
      augenbraue="Kontakt"
      titel="Erstgespräch kostet nichts."
      einleitung="Kurz beschreiben, worum es geht — ich melde mich und sage ehrlich, ob und wie ich helfen kann. Daraus wird ein Festpreis oder eine Absage, aber keine Warteschleife."
    >
      <div className="grid gap-12 lg:grid-cols-[1.1fr_1fr] lg:gap-20">
        <Reveal>
          {zustand === 'fertig' ? (
            <div className="pane flex flex-col items-start rounded-2xl p-9">
              <CheckCircle2 className="mb-5 h-10 w-10 text-ember" />
              <h3 className="mb-3 text-xl font-semibold text-mist">Anfrage ist raus.</h3>
              <p className="leading-relaxed text-mist/60">
                Danke — ich melde mich so schnell wie möglich. Wenn es eilt,
                geht es per WhatsApp oder Telefon meistens schneller.
              </p>
            </div>
          ) : (
            <form onSubmit={senden} className="space-y-5" noValidate={false}>
              <div className="grid gap-5 sm:grid-cols-2">
                <div>
                  <label htmlFor="name" className="mb-2 block text-sm text-mist/70">
                    Name <span className="text-ember">*</span>
                  </label>
                  <input
                    id="name"
                    name="name"
                    required
                    value={daten.name}
                    onChange={aendern}
                    autoComplete="name"
                    placeholder="Vorname Nachname"
                    className={feldStil}
                  />
                </div>
                <div>
                  <label htmlFor="email" className="mb-2 block text-sm text-mist/70">
                    E-Mail <span className="text-ember">*</span>
                  </label>
                  <input
                    id="email"
                    name="email"
                    type="email"
                    required
                    value={daten.email}
                    onChange={aendern}
                    autoComplete="email"
                    placeholder="name@beispiel.de"
                    className={feldStil}
                  />
                </div>
                <div>
                  <label htmlFor="company" className="mb-2 block text-sm text-mist/70">
                    Firma oder Verein
                  </label>
                  <input
                    id="company"
                    name="company"
                    value={daten.company}
                    onChange={aendern}
                    autoComplete="organization"
                    placeholder="optional"
                    className={feldStil}
                  />
                </div>
                <div>
                  <label htmlFor="phone" className="mb-2 block text-sm text-mist/70">
                    Telefon
                  </label>
                  <input
                    id="phone"
                    name="phone"
                    type="tel"
                    value={daten.phone}
                    onChange={aendern}
                    autoComplete="tel"
                    placeholder="optional"
                    className={feldStil}
                  />
                </div>
              </div>

              <div>
                <label htmlFor="message" className="mb-2 block text-sm text-mist/70">
                  Worum geht es? <span className="text-ember">*</span>
                </label>
                <textarea
                  id="message"
                  name="message"
                  required
                  rows={5}
                  value={daten.message}
                  onChange={aendern}
                  placeholder="Ein paar Sätze reichen: was für ein Betrieb, was die Seite können soll, bis wann."
                  className={`${feldStil} resize-none`}
                />
              </div>

              {zustand === 'fehler' && (
                <p
                  role="alert"
                  className="flex items-start gap-3 rounded-xl border border-ember/30 bg-ember/[0.07] px-4 py-3 text-sm text-mist/80"
                >
                  <AlertTriangle className="mt-0.5 h-4 w-4 flex-shrink-0 text-ember" />
                  Das Senden hat nicht geklappt. Schreib mir bitte direkt an{' '}
                  <a href="mailto:kontakt@praesenzwert.de" className="underline">
                    kontakt@praesenzwert.de
                  </a>
                  .
                </p>
              )}

              <button
                type="submit"
                disabled={zustand === 'sendet'}
                className="group inline-flex w-full items-center justify-center gap-2.5 rounded-full bg-ember px-8 py-4 font-semibold text-ink transition-colors duration-300 ease-brand hover:bg-ember-600 disabled:cursor-not-allowed disabled:opacity-60 sm:w-auto"
              >
                {zustand === 'sendet' ? 'Wird gesendet …' : 'Unverbindlich anfragen'}
                {zustand !== 'sendet' && (
                  <Send className="h-4 w-4 transition-transform duration-300 ease-brand group-hover:translate-x-0.5" />
                )}
              </button>
            </form>
          )}
        </Reveal>

        <Reveal verzoegerung={0.1}>
          <div className="space-y-8">
            <div>
              <h3 className="mb-6 text-xs uppercase tracking-[0.25em] text-mist/40">
                Direkt erreichen
              </h3>
              <ul className="space-y-5">
                <li className="flex items-start gap-4">
                  <Phone className="mt-1 h-4 w-4 flex-shrink-0 text-cyan-400" />
                  <div>
                    <p className="mb-0.5 text-sm text-mist/45">Telefon</p>
                    <a
                      href={`tel:${TELEFON_WAEHLEN}`}
                      className="text-mist transition-colors duration-300 ease-brand hover:text-ember"
                    >
                      {TELEFON_ANZEIGE}
                    </a>
                  </div>
                </li>
                <li className="flex items-start gap-4">
                  <Mail className="mt-1 h-4 w-4 flex-shrink-0 text-cyan-400" />
                  <div>
                    <p className="mb-0.5 text-sm text-mist/45">E-Mail</p>
                    <a
                      href="mailto:kontakt@praesenzwert.de"
                      className="text-mist transition-colors duration-300 ease-brand hover:text-ember"
                    >
                      kontakt@praesenzwert.de
                    </a>
                  </div>
                </li>
                <li className="flex items-start gap-4">
                  <MapPin className="mt-1 h-4 w-4 flex-shrink-0 text-cyan-400" />
                  <div>
                    <p className="mb-0.5 text-sm text-mist/45">Standort</p>
                    <p className="text-mist">
                      Josef-Martin-Weg 4
                      <br />
                      53501 Grafschaft
                    </p>
                  </div>
                </li>
              </ul>
            </div>

            <a
              href={WHATSAPP}
              target="_blank"
              rel="noopener noreferrer"
              className="flex items-center justify-center gap-3 rounded-full border border-mist/20 px-6 py-3.5 font-medium text-mist/85 transition-colors duration-300 ease-brand hover:border-ember/60 hover:text-ember"
            >
              <svg className="h-5 w-5" fill="currentColor" viewBox="0 0 24 24" aria-hidden>
                <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z" />
              </svg>
              Per WhatsApp schreiben
            </a>

            <div className="pane rounded-2xl p-7">
              <h4 className="mb-4 font-semibold text-mist">Wie es weitergeht</h4>
              <ol className="space-y-3">
                {[
                  'Unverbindliches Gespräch, telefonisch oder vor Ort',
                  'Klare Leistungsbeschreibung, schriftlich',
                  'Ein Festpreis ohne Nachschlag',
                ].map((schritt, i) => (
                  <li key={schritt} className="flex gap-3 text-sm text-mist/60">
                    <span className="font-mono text-xs tabular-nums text-ember">
                      {String(i + 1).padStart(2, '0')}
                    </span>
                    {schritt}
                  </li>
                ))}
              </ol>
            </div>
          </div>
        </Reveal>
      </div>
    </Section>
  )
}
