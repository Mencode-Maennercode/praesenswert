'use client'

import { useEffect } from 'react'

/**
 * Der durchlaufende Farbguss.
 *
 * Die Seite ist EINE ununterbrochene Flaeche. Kein Abschnitt hat eine eigene
 * Farbe, kein Abschnittswechsel ist an einem Farbwechsel erkennbar. Stattdessen
 * wandert eine einzige fixierte Verlaufsflaeche beim Scrollen den analogen
 * Bogen der Marke entlang:
 *
 *   Navy #1A2745 = Farbton 222 Grad   ->   Cyan #4DA6B8 = Farbton 190 Grad
 *
 * Ueber acht Bildschirmhoehen sind das rund 30 Grad, also knapp 4 Grad pro
 * Bildschirm. Das liegt unter der Schwelle, ab der man einen Wechsel bemerkt,
 * aber deutlich ueber der, ab der man einen Unterschied bemerkt: unten fuehlt
 * sich die Seite spuerbar anders an als oben, ohne dass man je einen Uebergang
 * gesehen hat.
 *
 * Interpoliert wird bewusst in HSL statt in RGB. RGB-Interpolation zwischen
 * zwei Blautoenen laeuft durch entsaettigtes Grau in der Mitte - der Bogen
 * wuerde in der Seitenmitte ausgrauen statt durchzulaufen.
 *
 * Die Helligkeit bleibt ueber den gesamten Bogen bei hoechstens 22 Prozent.
 * Dadurch haelt Mist #E8F4F6 als Textfarbe an jeder Stelle mindestens 8:1
 * Kontrast - WCAG AA ist auf der ganzen Seite garantiert, nicht nur oben.
 */

// Anfang und Ende des Bogens, je als [Farbton, Saettigung, Helligkeit].
// GUSS_START muss mit --guss-oben / --guss-unten in globals.css uebereinstimmen,
// sonst blitzt vor dem ersten Frame die falsche Farbe auf.
const OBEN_START: [number, number, number] = [222, 45, 8]
const OBEN_ENDE: [number, number, number] = [192, 50, 11]
const UNTEN_START: [number, number, number] = [222, 45, 17]
const UNTEN_ENDE: [number, number, number] = [192, 50, 22]

function mische(
  von: [number, number, number],
  bis: [number, number, number],
  t: number,
): string {
  const h = von[0] + (bis[0] - von[0]) * t
  const s = von[1] + (bis[1] - von[1]) * t
  const l = von[2] + (bis[2] - von[2]) * t
  return `hsl(${h.toFixed(1)} ${s.toFixed(1)}% ${l.toFixed(1)}%)`
}

export default function GradientGround() {
  useEffect(() => {
    const wurzel = document.documentElement
    let angefordert = 0

    const zeichne = () => {
      angefordert = 0
      const scrollbar = wurzel.scrollHeight - window.innerHeight
      // Kurze Seiten (oder der Moment vor dem ersten Layout) haben keinen
      // Scrollweg - ohne diese Klammer entstuende hier eine Division durch 0.
      const t = scrollbar > 0 ? Math.min(1, Math.max(0, window.scrollY / scrollbar)) : 0

      wurzel.style.setProperty('--guss-oben', mische(OBEN_START, OBEN_ENDE, t))
      wurzel.style.setProperty('--guss-unten', mische(UNTEN_START, UNTEN_ENDE, t))
    }

    const beiScroll = () => {
      if (angefordert) return
      angefordert = requestAnimationFrame(zeichne)
    }

    zeichne()
    window.addEventListener('scroll', beiScroll, { passive: true })
    window.addEventListener('resize', beiScroll, { passive: true })

    return () => {
      if (angefordert) cancelAnimationFrame(angefordert)
      window.removeEventListener('scroll', beiScroll)
      window.removeEventListener('resize', beiScroll)
    }
  }, [])

  return (
    <div aria-hidden className="fixed inset-0 -z-10 pointer-events-none">
      <div
        className="absolute inset-0"
        style={{
          background:
            'linear-gradient(to bottom, var(--guss-oben) 0%, var(--guss-unten) 100%)',
        }}
      />
      {/*
        Ein Verlauf ueber die volle Bildschirmhoehe zeigt auf 8-Bit-Displays
        sichtbare Streifen, gerade in dunklen Toenen. Die Koernung bricht die
        Stufenkanten auf; sie ist zu schwach, um selbst als Textur zu wirken.
      */}
      <div
        className="absolute inset-0 opacity-[0.035] mix-blend-overlay"
        style={{
          backgroundImage:
            "url(\"data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='200' height='200'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.85' numOctaves='3'/%3E%3C/filter%3E%3Crect width='200' height='200' filter='url(%23n)'/%3E%3C/svg%3E\")",
        }}
      />
    </div>
  )
}
