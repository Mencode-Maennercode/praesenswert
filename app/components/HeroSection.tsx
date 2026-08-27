'use client'

import { useRef } from 'react'
import { motion, useScroll, useTransform } from 'framer-motion'
import { ArrowDown, ArrowRight } from 'lucide-react'
import ScrollScrubCanvas from '../motion/ScrollScrubCanvas'
import MagneticButton from '../motion/MagneticButton'

/**
 * Das Leitmotiv der Seite.
 *
 * Die Bildsequenz laeuft ueber gut drei Bildschirmhoehen durch: das
 * Platinen-Gehirn zuendet, waechst zu einem Netz, die Kamera faehrt hinein und
 * das Netz loest sich in ein Partikelfeld auf. Das letzte Bild ist fast
 * gleichmaessig dunkel - genau deshalb kann die Seite dort ohne sichtbare Kante
 * in den Farbguss uebergehen.
 *
 * Der Text liegt scharf im DOM darueber, nicht im Video. Ein KI-Modell rendert
 * Formen zuverlaessig, Buchstabenformen nicht - eine "fast richtige" Wortmarke
 * faellt sofort auf. Deshalb traegt das Video das Motiv und das DOM die Marke.
 */
export default function HeroSection() {
  const buehne = useRef<HTMLElement>(null)

  const { scrollYProgress } = useScroll({
    target: buehne,
    offset: ['start start', 'end start'],
  })

  // Der Textblock raeumt frueh das Feld, damit das Bild uebernehmen kann.
  const textDeckung = useTransform(scrollYProgress, [0, 0.28], [1, 0])
  const textHub = useTransform(scrollYProgress, [0, 0.28], [0, -70])
  const hinweisDeckung = useTransform(scrollYProgress, [0, 0.06], [1, 0])

  return (
    <section id="home" ref={buehne} className="relative">
      <ScrollScrubCanvas
        ordner="/motion/hero"
        anzahl={121}
        ordnerMobil="/motion/hero-mobil"
        anzahlMobil={60}
        poster="/motion/hero-poster.webp"
        beschreibung="Ein Gehirn aus leuchtenden Platinenbahnen waechst zu einem Netzwerk und loest sich in ein Partikelfeld auf"
        buehnenHoehe="320vh"
      >
        {/* Das Motiv wird in der Mitte sehr hell - ohne diese Absenkung
            verliert der Text dort seinen Kontrast. */}
        <div
          aria-hidden
          className="absolute inset-0 bg-gradient-to-b from-ink/75 via-ink/45 to-ink/85"
        />

        <motion.div
          style={{ opacity: textDeckung, y: textHub }}
          className="absolute inset-0 flex items-center justify-center px-5 sm:px-8"
        >
          <div className="w-full max-w-4xl text-center">
            <motion.div
              initial={{ opacity: 0, y: 16 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ duration: 0.9, ease: [0.22, 1, 0.36, 1] }}
              className="mb-7 inline-flex items-center"
            >
              <span className="text-sm font-semibold uppercase tracking-[0.3em] text-cyan-400 sm:text-base">
                Präsenz<span className="text-mist">Wert</span>
              </span>
            </motion.div>

            <motion.h1
              initial={{ opacity: 0, y: 22 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ duration: 1, delay: 0.12, ease: [0.22, 1, 0.36, 1] }}
              className="text-display text-balance font-bold text-mist"
            >
              Websites für kleine Firmen und Vereine.
            </motion.h1>

            <motion.p
              initial={{ opacity: 0, y: 18 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ duration: 1, delay: 0.26, ease: [0.22, 1, 0.36, 1] }}
              className="mx-auto mt-6 max-w-2xl text-balance text-base leading-relaxed text-mist/70 sm:text-lg"
            >
              Professionell und trotzdem bezahlbar — zum Festpreis, ohne
              Agentur-Aufschlag. Für Köln, Bonn, die Eifel, das Ahrtal und den
              Rhein.
            </motion.p>

            <motion.div
              initial={{ opacity: 0, y: 18 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ duration: 1, delay: 0.4, ease: [0.22, 1, 0.36, 1] }}
              className="mt-10 flex flex-col items-center justify-center gap-3 sm:flex-row sm:gap-4"
            >
              <MagneticButton href="#kontakt">
                Projekt anfragen
                <ArrowRight className="h-4 w-4 transition-transform duration-300 ease-brand group-hover:translate-x-1" />
              </MagneticButton>
              <a
                href="#leistungen"
                className="rounded-full border border-mist/20 px-7 py-3.5 font-medium text-mist/80 transition-colors duration-300 ease-brand hover:border-mist/40 hover:text-mist"
              >
                Was ich mache
              </a>
            </motion.div>
          </div>
        </motion.div>

        <motion.div
          style={{ opacity: hinweisDeckung }}
          className="pointer-events-none absolute inset-x-0 bottom-8 flex justify-center"
        >
          <span className="flex flex-col items-center gap-2 text-xs uppercase tracking-[0.25em] text-mist/45">
            Scrollen
            <ArrowDown className="h-4 w-4 animate-drift text-ember" />
          </span>
        </motion.div>
      </ScrollScrubCanvas>
    </section>
  )
}
