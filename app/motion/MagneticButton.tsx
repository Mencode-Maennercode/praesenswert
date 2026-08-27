'use client'

import { motion, useMotionValue, useSpring } from 'framer-motion'
import { useEffect, useRef, useState } from 'react'
import type { ReactNode } from 'react'

/**
 * Ein Knopf, der dem Zeiger leicht entgegenkommt.
 *
 * Ember ist auf dieser Seite die einzige warme Farbe und markiert ausschliesslich
 * Handlung. Der magnetische Zug ist das bewegte Gegenstueck dazu: was reagiert,
 * ist anklickbar.
 *
 * Nur auf echten Zeigegeraeten. Auf Touch gibt es keinen Hover-Zustand - der
 * Effekt wuerde dort erst beim Tippen feuern und wie ein Fehler wirken.
 */

type Props = {
  href: string
  children: ReactNode
  className?: string
  /** Wie weit der Knopf maximal mitgeht, in Pixeln. */
  zug?: number
  extern?: boolean
}

export default function MagneticButton({
  href,
  children,
  className = '',
  zug = 10,
  extern = false,
}: Props) {
  const knopf = useRef<HTMLAnchorElement>(null)
  const [aktiv, setAktiv] = useState(false)

  const x = useMotionValue(0)
  const y = useMotionValue(0)
  const federX = useSpring(x, { stiffness: 200, damping: 18, mass: 0.4 })
  const federY = useSpring(y, { stiffness: 200, damping: 18, mass: 0.4 })

  useEffect(() => {
    const feinerZeiger = window.matchMedia('(hover: hover) and (pointer: fine)')
    const sanft = window.matchMedia('(prefers-reduced-motion: reduce)')
    setAktiv(feinerZeiger.matches && !sanft.matches)
  }, [])

  const beiBewegung = (e: React.MouseEvent<HTMLAnchorElement>) => {
    if (!aktiv || !knopf.current) return
    const kasten = knopf.current.getBoundingClientRect()
    // Abstand vom Mittelpunkt, auf -1 bis 1 normiert, dann auf den Zug skaliert.
    const dx = (e.clientX - (kasten.left + kasten.width / 2)) / (kasten.width / 2)
    const dy = (e.clientY - (kasten.top + kasten.height / 2)) / (kasten.height / 2)
    x.set(Math.max(-1, Math.min(1, dx)) * zug)
    y.set(Math.max(-1, Math.min(1, dy)) * zug)
  }

  const zurueck = () => {
    x.set(0)
    y.set(0)
  }

  return (
    <motion.a
      ref={knopf}
      href={href}
      onMouseMove={beiBewegung}
      onMouseLeave={zurueck}
      // Beim Fokus per Tastatur zurueck in die Mitte, sonst steht der Knopf
      // schief, wenn ihn vorher die Maus gestreift hat.
      onFocus={zurueck}
      style={aktiv ? { x: federX, y: federY } : undefined}
      {...(extern ? { target: '_blank', rel: 'noopener noreferrer' } : {})}
      className={
        'group relative inline-flex items-center gap-2.5 rounded-full bg-ember px-7 py-3.5 ' +
        'font-semibold text-ink transition-colors duration-300 ease-brand hover:bg-ember-600 ' +
        className
      }
    >
      {children}
    </motion.a>
  )
}
