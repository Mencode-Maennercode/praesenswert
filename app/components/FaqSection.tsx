'use client'

import { useState } from 'react'
import { AnimatePresence, motion } from 'framer-motion'
import { Plus } from 'lucide-react'
import Section from './Section'
import Reveal from '../motion/Reveal'
import { faqs } from '../content/faq'

/**
 * Haeufige Fragen.
 *
 * Die Texte kommen aus app/content/faq.ts, aus derselben Quelle wie das
 * FAQPage-Schema in layout.tsx. Sie koennen deshalb nicht auseinanderlaufen.
 *
 * Ein eigener Zustand statt <details>: nur so laesst sich das Aufklappen
 * animieren, und es kann immer nur eine Frage offen sein - bei zehn Eintraegen
 * ist eine halb aufgeklappte Liste sonst schnell unuebersichtlich.
 */
export default function FaqSection() {
  const [offen, setOffen] = useState<number | null>(0)

  return (
    <Section
      id="faq"
      nummer="05"
      augenbraue="Häufige Fragen"
      titel="Das, was sonst im Erstgespräch gefragt wird."
    >
      <div className="max-w-3xl">
        {faqs.map((eintrag, i) => {
          const istOffen = offen === i
          return (
            <Reveal key={eintrag.frage} verzoegerung={Math.min(i, 5) * 0.04}>
              <div className="border-b border-mist/10">
                <h3>
                  <button
                    type="button"
                    onClick={() => setOffen(istOffen ? null : i)}
                    aria-expanded={istOffen}
                    aria-controls={`faq-antwort-${i}`}
                    className="flex w-full items-center justify-between gap-6 py-6 text-left"
                  >
                    <span
                      className={`text-lg font-medium transition-colors duration-300 ease-brand ${
                        istOffen ? 'text-mist' : 'text-mist/75 hover:text-mist'
                      }`}
                    >
                      {eintrag.frage}
                    </span>
                    <span
                      className={`flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full ring-1 transition-all duration-400 ease-brand ${
                        istOffen
                          ? 'rotate-45 text-ember ring-ember/40'
                          : 'text-mist/50 ring-mist/15'
                      }`}
                    >
                      <Plus className="h-4 w-4" />
                    </span>
                  </button>
                </h3>

                <AnimatePresence initial={false}>
                  {istOffen && (
                    <motion.div
                      id={`faq-antwort-${i}`}
                      initial={{ height: 0, opacity: 0 }}
                      animate={{ height: 'auto', opacity: 1 }}
                      exit={{ height: 0, opacity: 0 }}
                      transition={{ duration: 0.45, ease: [0.22, 1, 0.36, 1] }}
                      className="overflow-hidden"
                    >
                      <p className="max-w-2xl pb-7 pr-12 leading-relaxed text-mist/60">
                        {eintrag.antwort}
                      </p>
                    </motion.div>
                  )}
                </AnimatePresence>
              </div>
            </Reveal>
          )
        })}
      </div>
    </Section>
  )
}
