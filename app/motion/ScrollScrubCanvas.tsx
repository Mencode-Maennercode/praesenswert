'use client'

import { useEffect, useRef, useState } from 'react'

/**
 * Das Leitmotiv: ein Bild, das sich beim Scrollen veraendert.
 *
 * Der Clip wird nicht als Video abgespielt, sondern vorher mit ffmpeg in eine
 * WebP-Bildsequenz zerlegt, die hier Bild fuer Bild auf ein Canvas gezeichnet
 * wird. Der Scroll-Fortschritt waehlt den Frame.
 *
 * Warum nicht einfach video.currentTime setzen: Browser koennen in einem
 * komprimierten Video nur zum naechsten Keyframe springen, nicht frei zu jedem
 * Bild. Beim Scrubben ruckelt das sichtbar, in Safari und auf iOS bleibt es
 * teilweise ganz stehen. Eine Bildsequenz hat dieses Problem nicht - jedes
 * Bild ist einzeln adressierbar.
 *
 * Preis dafuer ist Ladevolumen, deshalb laedt die mobile Variante eine deutlich
 * kuerzere und kleinere Sequenz.
 */

type Props = {
  /** Ordner unter /public, ohne Schraegstrich am Ende. */
  ordner: string
  /** Anzahl Frames im Ordner, benannt f_001.webp aufwaerts. */
  anzahl: number
  ordnerMobil: string
  anzahlMobil: number
  /** Standbild fuer reduzierte Bewegung und als erster Anblick. */
  poster: string
  beschreibung: string
  /**
   * Hoehe der Scroll-Buehne. Je hoeher, desto langsamer laeuft die Sequenz
   * durch - das ist der Regler fuer das Tempo des Leitmotivs.
   */
  buehnenHoehe?: string
  /** Liegt fest ueber dem Bild, im klebenden Bereich. */
  children?: React.ReactNode
}

const MOBIL_BIS = 768

function bildPfad(ordner: string, nummer: number) {
  return `${ordner}/f_${String(nummer).padStart(3, '0')}.webp`
}

export default function ScrollScrubCanvas({
  ordner,
  anzahl,
  ordnerMobil,
  anzahlMobil,
  poster,
  beschreibung,
  buehnenHoehe = '320vh',
  children,
}: Props) {
  const buehne = useRef<HTMLDivElement>(null)
  const leinwand = useRef<HTMLCanvasElement>(null)
  const bilder = useRef<HTMLImageElement[]>([])
  const [fortschritt, setFortschritt] = useState(0)
  const [bereit, setBereit] = useState(false)
  const [ohneBewegung, setOhneBewegung] = useState(false)

  useEffect(() => {
    const sanft = window.matchMedia('(prefers-reduced-motion: reduce)')
    if (sanft.matches) {
      setOhneBewegung(true)
      return
    }

    const mobil = window.innerWidth < MOBIL_BIS
    const quelle = mobil ? ordnerMobil : ordner
    const menge = mobil ? anzahlMobil : anzahl

    let abgebrochen = false
    let geladen = 0

    const liste: HTMLImageElement[] = new Array(menge)
    bilder.current = liste

    // Erst nach dem Laden der Seite anfangen. Die Sequenz ist gross und wuerde
    // sonst mit dem sichtbaren Text um Bandbreite konkurrieren.
    const starteLaden = () => {
      for (let i = 0; i < menge; i++) {
        const bild = new Image()
        bild.decoding = 'async'
        bild.src = bildPfad(quelle, i + 1)
        bild.onload = () => {
          if (abgebrochen) return
          geladen++
          // Sobald das erste Bild da ist, kann gezeichnet werden - der Rest
          // fuellt sich waehrend des Scrollens auf.
          if (geladen === 1 || geladen === menge) setBereit(true)
          setFortschritt(geladen / menge)
        }
        liste[i] = bild
      }
    }

    if (document.readyState === 'complete') {
      starteLaden()
    } else {
      window.addEventListener('load', starteLaden, { once: true })
    }

    return () => {
      abgebrochen = true
      window.removeEventListener('load', starteLaden)
      // Laufende Anfragen abbrechen, damit ein schneller Seitenwechsel nicht
      // hunderte Bilder weiterlaedt.
      for (const bild of liste) if (bild) bild.src = ''
      bilder.current = []
    }
  }, [ordner, anzahl, ordnerMobil, anzahlMobil])

  useEffect(() => {
    if (ohneBewegung) return

    const flaeche = leinwand.current
    const rahmen = buehne.current
    if (!flaeche || !rahmen) return

    const stift = flaeche.getContext('2d', { alpha: false })
    if (!stift) return

    let angefordert = 0

    const passeGroesseAn = () => {
      // Auf Bildschirmen mit hoher Pixeldichte bei 2 deckeln - darueber kostet
      // jede Zeichnung spuerbar mehr, ohne sichtbar besser zu werden.
      const dichte = Math.min(2, window.devicePixelRatio || 1)
      flaeche.width = Math.round(flaeche.clientWidth * dichte)
      flaeche.height = Math.round(flaeche.clientHeight * dichte)
    }

    const zeichne = () => {
      angefordert = 0

      const kasten = rahmen.getBoundingClientRect()
      // Wie weit ist der Scroll durch die hohe Buehne gelaufen: 0 sobald ihre
      // Oberkante den oberen Rand erreicht, 1 wenn ihre Unterkante ihn erreicht.
      const weg = kasten.height - window.innerHeight
      const t = weg > 0 ? Math.min(1, Math.max(0, -kasten.top / weg)) : 0

      const liste = bilder.current
      if (!liste.length) return

      const index = Math.min(liste.length - 1, Math.max(0, Math.round(t * (liste.length - 1))))

      // Noch nicht geladene Frames ueberspringen und das naechste fertige Bild
      // zeigen, statt schwarz zu blitzen.
      let bild = liste[index]
      if (!bild?.complete || !bild.naturalWidth) {
        for (let abstand = 1; abstand < liste.length; abstand++) {
          const davor = liste[index - abstand]
          const danach = liste[index + abstand]
          if (davor?.complete && davor.naturalWidth) {
            bild = davor
            break
          }
          if (danach?.complete && danach.naturalWidth) {
            bild = danach
            break
          }
        }
      }
      if (!bild?.complete || !bild.naturalWidth) return

      // Formatfuellend zeichnen (wie object-fit: cover), damit das Motiv bei
      // jedem Seitenverhaeltnis den Rahmen ausfuellt.
      const zielB = flaeche.width
      const zielH = flaeche.height
      const massstab = Math.max(zielB / bild.naturalWidth, zielH / bild.naturalHeight)
      const b = bild.naturalWidth * massstab
      const h = bild.naturalHeight * massstab
      stift.drawImage(bild, (zielB - b) / 2, (zielH - h) / 2, b, h)
    }

    const beiScroll = () => {
      if (angefordert) return
      angefordert = requestAnimationFrame(zeichne)
    }

    const beiGroesse = () => {
      passeGroesseAn()
      zeichne()
    }

    passeGroesseAn()
    zeichne()
    window.addEventListener('scroll', beiScroll, { passive: true })
    window.addEventListener('resize', beiGroesse)

    return () => {
      if (angefordert) cancelAnimationFrame(angefordert)
      window.removeEventListener('scroll', beiScroll)
      window.removeEventListener('resize', beiGroesse)
    }
  }, [ohneBewegung, bereit])

  // Reduzierte Bewegung: ein Standbild statt der Sequenz, und die Buehne
  // schrumpft auf eine Bildschirmhoehe - ohne Animation waeren die zusaetzlichen
  // Bildschirme reines Leerscrollen.
  if (ohneBewegung) {
    return (
      <div className="relative h-screen overflow-hidden">
        {/* eslint-disable-next-line @next/next/no-img-element */}
        <img
          src={poster}
          alt={beschreibung}
          className="absolute inset-0 h-full w-full object-cover"
        />
        {children}
      </div>
    )
  }

  return (
    <div ref={buehne} className="relative" style={{ height: buehnenHoehe }}>
      <div className="sticky top-0 h-screen overflow-hidden">
        <canvas
          ref={leinwand}
          className="h-full w-full"
          role="img"
          aria-label={beschreibung}
        />
        {children}
      </div>
      {/* Ladefortschritt als Haarlinie - Ember, weil es der einzige warme Ton ist */}
      {fortschritt < 1 && (
        <div
          aria-hidden
          className="pointer-events-none fixed inset-x-0 top-0 z-50 h-px bg-ember/70 transition-[width] duration-300 ease-brand"
          style={{ width: `${Math.round(fortschritt * 100)}%` }}
        />
      )}
    </div>
  )
}
