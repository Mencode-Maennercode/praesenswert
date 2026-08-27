'use client'

import { useRef } from 'react'
import { motion, useScroll, useTransform } from 'framer-motion'
import { Layers, Wrench, Sparkles, Gauge } from 'lucide-react'
import Section from './Section'
import Reveal from '../motion/Reveal'

/**
 * Arbeitsweise.
 *
 * Die Punkte haengen an einer senkrechten Linie, die sich beim Scrollen fuellt.
 * Das ist der einzige Ort auf der Seite, an dem Bewegung etwas erklaert statt
 * nur zu schmuecken: die Arbeitsweise ist ein Ablauf, und die Linie zeigt ihn
 * als Ablauf.
 */

const arbeitsweise = [
  {
    icon: Layers,
    titel: 'Bewährte Grundlage',
    text: 'Aktuelle Web-Frameworks und erprobte Templates als technische Basis. Das spart die Zeit, die sonst in Grundlagenarbeit ginge — und genau diese Zeit ist es, die eine Website teuer macht.',
  },
  {
    icon: Wrench,
    titel: 'Standard statt Sonderweg',
    text: 'Umgesetzt wird mit etablierten Systemen und Werkzeugen. Keine Eigenentwicklung, die niemand außer mir warten kann, sondern Lösungen, die auch in fünf Jahren noch verständlich sind.',
  },
  {
    icon: Sparkles,
    titel: 'KI-gestützte Abläufe',
    text: 'Moderne, KI-gestützte Werkzeuge übernehmen die wiederkehrenden Schritte. Der Aufwand sinkt, der Preis sinkt mit — die Entscheidungen trifft weiterhin ein Mensch.',
  },
  {
    icon: Gauge,
    titel: 'Effizienz als Preisargument',
    text: 'Weil mit Standardlösungen gearbeitet wird, geht es schnell und bleibt günstig. Genau das macht eine ordentliche Website für einen kleinen Betrieb überhaupt erst bezahlbar.',
  },
]

export default function BenefitsSection() {
  const liste = useRef<HTMLDivElement>(null)

  const { scrollYProgress } = useScroll({
    target: liste,
    offset: ['start 75%', 'end 65%'],
  })
  const fuellung = useTransform(scrollYProgress, [0, 1], ['0%', '100%'])

  return (
    <Section
      id="arbeitsweise"
      nummer="02"
      augenbraue="Arbeitsweise"
      titel="Warum es günstig ist, ohne billig zu sein."
      einleitung="Der Preis kommt nicht daher, dass weniger Sorgfalt hineingeht, sondern daher, dass keine Zeit in Dinge fließt, die schon gelöst sind."
    >
      <div ref={liste} className="relative max-w-3xl">
        {/* Die Bahn liegt hinter den Punkten; die Fuellung waechst mit dem Scroll. */}
        <div
          aria-hidden
          className="absolute bottom-2 left-[27px] top-2 w-px bg-mist/12 sm:left-[31px]"
        >
          <motion.div style={{ height: fuellung }} className="w-px bg-cyan/60" />
        </div>

        <div className="space-y-12">
          {arbeitsweise.map((schritt, i) => (
            <Reveal key={schritt.titel} verzoegerung={i * 0.05}>
              <div className="flex gap-6 sm:gap-8">
                <div className="relative z-10 flex h-14 w-14 flex-shrink-0 items-center justify-center rounded-full border border-mist/12 bg-ink/80 backdrop-blur sm:h-16 sm:w-16">
                  <schritt.icon className="h-5 w-5 text-cyan-400" />
                </div>
                <div className="pt-3">
                  <h3 className="mb-3 text-xl font-semibold text-mist sm:text-2xl">
                    {schritt.titel}
                  </h3>
                  <p className="leading-relaxed text-mist/60">{schritt.text}</p>
                </div>
              </div>
            </Reveal>
          ))}
        </div>
      </div>

      <Reveal verzoegerung={0.1}>
        <div className="pane mt-16 max-w-3xl rounded-2xl p-7 sm:p-9">
          <p className="leading-relaxed text-mist/70">
            <span className="font-semibold text-cyan-400">Zur Einordnung:</span>{' '}
            Meine Leistung ist die technische Umsetzung der Website. Die
            Verantwortung für die Inhalte, für Impressum und Datenschutz und für
            die rechtliche Konformität bleibt beim Auftraggeber — dafür bekommt
            er von mir vorab gesagt, was dafür nötig ist.
          </p>
        </div>
      </Reveal>
    </Section>
  )
}
