'use client'

import { motion } from 'framer-motion'
import type { ElementType } from 'react'
import useReveal from './useReveal'

/**
 * Ueberschriften steigen wortweise aus einer Maske auf, mit leichtem Versatz.
 *
 * Der Versatz ist bewusst klein (35ms). Grosse Abstaende lassen den Satz
 * buchstabieren statt auftauchen, und bei langen Ueberschriften wartet man
 * dann sichtbar auf das letzte Wort.
 *
 * Die Sichtbarkeit kommt aus useReveal statt aus whileInView. Hier ist das
 * besonders wichtig: die Woerter liegen hinter overflow:hidden, ein nicht
 * ausgeloester Auftritt macht die Ueberschrift also nicht bloss unscheinbar,
 * sondern vollstaendig unsichtbar.
 */

type Props = {
  text: string
  as?: ElementType
  className?: string
  /** Verzoegerung vor dem ersten Wort, um Bloecke gegeneinander zu staffeln. */
  verzoegerung?: number
}

export default function RevealText({
  text,
  as: Tag = 'h2',
  className = '',
  verzoegerung = 0,
}: Props) {
  const { bezug, sichtbar } = useReveal<HTMLSpanElement>()
  const woerter = text.split(' ')

  return (
    <Tag className={className}>
      {/*
        Der sichtbare Text steht als ein Stueck im DOM, nur visuell versteckt.
        Ohne ihn liest ein Screenreader die Ueberschrift Wort fuer Wort als
        einzelne Fragmente vor, und Suchmaschinen sehen zerstueckelte Spans.
      */}
      <span className="sr-only">{text}</span>

      <span ref={bezug} aria-hidden className="inline">
        {woerter.map((wort, i) => (
          /*
            Das Leerzeichen steht als eigener Textknoten zwischen den Masken.
            Innerhalb eines inline-block mit overflow-hidden faellt ein
            angehaengtes Leerzeichen zusammen und die Woerter kleben aneinander.
          */
          <span key={i}>
            <span className="inline-block overflow-hidden align-bottom">
              <motion.span
                className="inline-block"
                initial={{ y: '105%' }}
                animate={sichtbar ? { y: '0%' } : undefined}
                transition={{
                  duration: 0.75,
                  delay: verzoegerung + i * 0.035,
                  ease: [0.22, 1, 0.36, 1],
                }}
              >
                {wort}
              </motion.span>
            </span>
            {i < woerter.length - 1 ? ' ' : ''}
          </span>
        ))}
      </span>
    </Tag>
  )
}
