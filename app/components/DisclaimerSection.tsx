'use client'

import { AlertCircle, FileText, Shield, Users, Layers } from 'lucide-react'
import Section from './Section'
import Reveal from '../motion/Reveal'

/**
 * Rechtliche Hinweise.
 *
 * Bewusst der leiseste Abschnitt der Seite: kein Ember, kein Effekt, gedeckte
 * Textfarben. Ember bedeutet auf dieser Seite "hier kann gehandelt werden" -
 * hier soll niemand handeln, hier soll jemand nachlesen. Ein aufgeregt
 * gestalteter Haftungshinweis wirkt ausserdem defensiv; ein ruhiger wirkt
 * souveraen.
 */

const punkte = [
  {
    icon: FileText,
    titel: 'Rechtliche Texte',
    text: 'Impressum, Datenschutzerklärung und alle rechtlich relevanten Texte stellt der Auftraggeber bereit. Ich übernehme dafür weder Rechtsberatung noch Haftung.',
  },
  {
    icon: Shield,
    titel: 'Keine Garantien',
    text: 'Es gibt keine Sicherheitsaudits, keine DSGVO-Garantie und keine Zusicherung eines fehlerfreien Betriebs. Gearbeitet wird nach bestem Wissen, aber ohne rechtliche oder technische Zusicherung.',
  },
  {
    icon: Users,
    titel: 'Verantwortung',
    text: 'Für die Richtigkeit aller gelieferten Inhalte, Bilder und Angaben ist der Auftraggeber verantwortlich. Auch die rechtliche Konformität der fertigen Website liegt bei ihm.',
  },
  {
    icon: Layers,
    titel: 'Leistungsumfang',
    text: 'Die Leistung ist die technische Umsetzung informativer Websites. Keine Shops, keine Benutzer-Logins, keine komplexen Webanwendungen.',
  },
]

export default function DisclaimerSection() {
  return (
    <Section
      id="hinweise"
      nummer="06"
      augenbraue="Hinweise"
      titel="Wo meine Leistung aufhört."
      einleitung="Lieber vorher gelesen als hinterher geklärt. Diese Abgrenzung ist Teil des Angebots, nicht das Kleingedruckte dazu."
    >
      <Reveal>
        <div className="max-w-3xl rounded-2xl border border-cyan/20 bg-cyan/[0.04] p-7 sm:p-9">
          <div className="flex gap-5">
            <AlertCircle className="mt-0.5 h-6 w-6 flex-shrink-0 text-cyan-400" />
            <div>
              <h3 className="mb-4 text-lg font-semibold text-mist">
                Was ich anbiete — und was das nicht ist
              </h3>
              <p className="mb-4 leading-relaxed text-mist/65">
                Ich erstelle informative Websites für Unternehmen und Vereine als
                technische Dienstleistung, auf Basis moderner Frameworks,
                Templates und CMS-Systeme.
              </p>
              <p className="font-medium leading-relaxed text-mist/80">
                Das ist keine Individualsoftware-Entwicklung und keine
                IT-Beratung im klassischen Sinn. Maßgeschneiderte Programmierung
                gehört ausdrücklich nicht zum Angebot.
              </p>
            </div>
          </div>
        </div>
      </Reveal>

      <div className="mt-6 grid max-w-3xl gap-x-10 gap-y-8 sm:grid-cols-2">
        {punkte.map((punkt, i) => (
          <Reveal key={punkt.titel} verzoegerung={i * 0.05}>
            <div className="flex gap-4">
              <punkt.icon className="mt-1 h-4 w-4 flex-shrink-0 text-mist/30" />
              <div>
                <h4 className="mb-2 font-medium text-mist/80">{punkt.titel}</h4>
                <p className="text-sm leading-relaxed text-mist/45">{punkt.text}</p>
              </div>
            </div>
          </Reveal>
        ))}
      </div>
    </Section>
  )
}
