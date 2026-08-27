'use client'

import { useEffect } from 'react'
import Lenis from 'lenis'

/**
 * Traegheitsgeglaettetes Scrollen.
 *
 * Das ist der Effekt, der die Referenzseiten ausmacht: der Scroll laeuft dem
 * Rad leicht nach, statt hart zu springen. Alle Scroll-Animationen der Seite
 * haengen daran - ohne Lenis wirken sie stufig, weil das Mausrad in groben
 * Rasterschritten feuert.
 *
 * Lenis scrollt das echte Dokument (kein transformierter Container), deshalb
 * funktionieren window.scrollY, position: sticky und IntersectionObserver
 * unveraendert weiter. Genau darauf bauen GradientGround und
 * ScrollScrubCanvas auf.
 */

// Muss zu scroll-padding-top in globals.css passen, sonst landen Ankerziele
// unter der fixierten Kopfzeile.
const KOPFZEILE_VERSATZ = -96

export default function LenisProvider() {
  useEffect(() => {
    const sanft = window.matchMedia('(prefers-reduced-motion: reduce)')

    // Wer reduzierte Bewegung eingestellt hat, bekommt gar kein Lenis. Ein
    // nachlaufender Scroll ist genau die Art von Bewegung, die abgeschaltet
    // gehoert - der Browser scrollt dann wieder direkt.
    if (sanft.matches) return

    const lenis = new Lenis({
      duration: 1.05,
      // Exponentielles Ausklingen: schneller Antritt, langes ruhiges Auslaufen.
      easing: (t: number) => Math.min(1, 1.001 - Math.pow(2, -10 * t)),
      smoothWheel: true,
      // Auf Touchgeraeten bleibt der native Scroll. Der ist dort bereits
      // traegheitsbehaftet, und ihn zu ueberschreiben fuehlt sich klebrig an
      // und bricht das Aufziehen zum Neuladen.
      syncTouch: false,
    })

    let laeuft = true
    const rahmen = (zeit: number) => {
      if (!laeuft) return
      lenis.raf(zeit)
      requestAnimationFrame(rahmen)
    }
    requestAnimationFrame(rahmen)

    // Ankerlinks muessen durch Lenis laufen. Der native Sprung wuerde die
    // interne Position von Lenis umgehen und die Seite verspringen lassen.
    const beiKlick = (e: MouseEvent) => {
      // Modifiziertes Klicken oeffnet Tabs - da darf nichts abgefangen werden.
      if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) {
        return
      }

      const link = (e.target as HTMLElement | null)?.closest?.('a')
      if (!(link instanceof HTMLAnchorElement)) return

      const ziel = link.getAttribute('href')
      if (!ziel || !ziel.startsWith('#') || ziel === '#') return

      const element = document.querySelector(ziel)
      if (!element) return

      e.preventDefault()
      lenis.scrollTo(element as HTMLElement, {
        offset: KOPFZEILE_VERSATZ,
        duration: 1.4,
      })
      // Die Adresszeile mitfuehren, ohne einen weiteren Sprung auszuloesen.
      history.pushState(null, '', ziel)
    }

    document.addEventListener('click', beiKlick)

    return () => {
      laeuft = false
      document.removeEventListener('click', beiKlick)
      lenis.destroy()
    }
  }, [])

  return null
}
