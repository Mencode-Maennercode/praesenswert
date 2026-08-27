'use client'

import { useEffect, useRef, useState } from 'react'

/**
 * Sagt, ob ein Element schon aufgedeckt werden darf.
 *
 * Warum nicht einfach whileInView von framer-motion: das haengt allein am
 * IntersectionObserver. Wenn ein Element zwischen zwei Messungen des Observers
 * vollstaendig durchs Bild rauscht - schnelles Wischen auf dem Telefon, ein
 * Sprung per Ankerlink, ein Mausrad mit grosser Schrittweite - meldet der
 * Observer den Eintritt nie. Das Element bleibt dann dauerhaft unsichtbar,
 * und weil der Text hinter overflow:hidden verschwindet, faellt das niemandem
 * auf: die Ueberschrift ist einfach weg.
 *
 * Deshalb hier zwei Wege zum selben Ziel: der Observer fuer den Normalfall,
 * und eine Lageprüfung beim Scroll-Ende als Netz. Wer einmal an einem Element
 * vorbeigescrollt ist, sieht es garantiert.
 *
 * Beide Quellen teilen sich einen Observer und einen Scroll-Listener fuer die
 * ganze Seite - bei rund vierzig Elementen waeren vierzig eigene Listener
 * unnoetiger Ballast.
 */

type Melder = (sichtbar: true) => void

const wartende = new Map<Element, Melder>()

let beobachter: IntersectionObserver | null = null
let scrollGebunden = false
let angefordert = 0

// Etwas frueher als die Unterkante, damit die Bewegung schon laeuft, wenn das
// Element ins Blickfeld kommt, statt erst danach anzufangen.
const SCHWELLE = 0.9

function aufdecken(element: Element) {
  const melder = wartende.get(element)
  if (!melder) return
  wartende.delete(element)
  beobachter?.unobserve(element)
  melder(true)
}

function lagePruefen() {
  angefordert = 0
  for (const element of [...wartende.keys()]) {
    if (element.getBoundingClientRect().top < window.innerHeight * SCHWELLE) {
      aufdecken(element)
    }
  }
  if (!wartende.size) loesen()
}

function beiScroll() {
  if (angefordert) return
  angefordert = requestAnimationFrame(lagePruefen)
}

function loesen() {
  if (!scrollGebunden) return
  window.removeEventListener('scroll', beiScroll)
  window.removeEventListener('resize', beiScroll)
  scrollGebunden = false
}

function anmelden(element: Element, melder: Melder) {
  wartende.set(element, melder)

  if (!beobachter) {
    beobachter = new IntersectionObserver(
      (eintraege) => {
        for (const eintrag of eintraege) {
          if (eintrag.isIntersecting) aufdecken(eintrag.target)
        }
      },
      { rootMargin: `0px 0px -${Math.round((1 - SCHWELLE) * 100)}% 0px` },
    )
  }
  beobachter.observe(element)

  if (!scrollGebunden) {
    window.addEventListener('scroll', beiScroll, { passive: true })
    window.addEventListener('resize', beiScroll, { passive: true })
    scrollGebunden = true
  }

  // Elemente, die beim ersten Bild schon sichtbar sind, sofort aufdecken.
  beiScroll()
}

export default function useReveal<T extends HTMLElement>() {
  const bezug = useRef<T>(null)
  const [sichtbar, setSichtbar] = useState(false)

  useEffect(() => {
    const element = bezug.current
    if (!element) return

    // Bei reduzierter Bewegung gibt es nichts aufzudecken - alles ist da.
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
      setSichtbar(true)
      return
    }

    anmelden(element, () => setSichtbar(true))
    return () => {
      wartende.delete(element)
      beobachter?.unobserve(element)
    }
  }, [])

  return { bezug, sichtbar }
}
