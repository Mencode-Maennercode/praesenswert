'use client'

import { motion } from 'framer-motion'
import type { ReactNode } from 'react'
import useReveal from './useReveal'

/**
 * Der Standard-Auftritt fuer Bloecke: leicht von unten, leicht aus dem Nichts.
 *
 * Bewusst nur 18px Weg. Die alte Seite liess alles 30px weit einfliegen, was
 * bei sechs Karten nebeneinander wie ein Kartenspiel aussieht. Kurze Wege
 * wirken teurer und stoeren den durchlaufenden Eindruck der Seite nicht.
 *
 * Die Sichtbarkeit kommt aus useReveal statt aus whileInView - siehe dort,
 * warum der IntersectionObserver allein nicht genuegt.
 */

type Props = {
  children: ReactNode
  className?: string
  verzoegerung?: number
}

export default function Reveal({ children, className = '', verzoegerung = 0 }: Props) {
  const { bezug, sichtbar } = useReveal<HTMLDivElement>()

  return (
    <motion.div
      ref={bezug}
      className={className}
      initial={{ opacity: 0, y: 18 }}
      animate={sichtbar ? { opacity: 1, y: 0 } : undefined}
      transition={{ duration: 0.7, delay: verzoegerung, ease: [0.22, 1, 0.36, 1] }}
    >
      {children}
    </motion.div>
  )
}
