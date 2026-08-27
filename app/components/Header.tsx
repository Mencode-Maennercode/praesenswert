'use client'

import { useEffect, useState } from 'react'
import Image from 'next/image'
import { AnimatePresence, motion } from 'framer-motion'
import { Menu, X } from 'lucide-react'

/**
 * Kopfzeile.
 *
 * Ueber dem Leitmotiv bleibt sie durchsichtig - eine Leiste ueber dem
 * Hero-Bild wuerde die Aufnahme zerschneiden. Erst wenn der Hero durchgelaufen
 * ist, legt sich Glas darunter.
 *
 * Die Fortschrittslinie ganz oben ist der einzige Ember-Akzent, der die ganze
 * Seite ueber sichtbar bleibt: sie zeigt Handlung im woertlichen Sinn, naemlich
 * die eigene Bewegung durch die Seite.
 */

const punkte = [
  { href: '#leistungen', text: 'Leistungen' },
  { href: '#arbeitsweise', text: 'Arbeitsweise' },
  { href: '#referenzen', text: 'Referenzen' },
  { href: '#produkte', text: 'Produkte' },
  { href: '#faq', text: 'FAQ' },
  { href: '#hinweise', text: 'Hinweise' },
]

export default function Header() {
  const [gelegt, setGelegt] = useState(false)
  const [fortschritt, setFortschritt] = useState(0)
  const [menueOffen, setMenueOffen] = useState(false)

  useEffect(() => {
    let angefordert = 0

    const messen = () => {
      angefordert = 0
      const wurzel = document.documentElement
      const scrollbar = wurzel.scrollHeight - window.innerHeight
      setGelegt(window.scrollY > window.innerHeight * 0.9)
      setFortschritt(scrollbar > 0 ? (window.scrollY / scrollbar) * 100 : 0)
    }

    const beiScroll = () => {
      if (angefordert) return
      angefordert = requestAnimationFrame(messen)
    }

    messen()
    window.addEventListener('scroll', beiScroll, { passive: true })
    window.addEventListener('resize', beiScroll, { passive: true })
    return () => {
      if (angefordert) cancelAnimationFrame(angefordert)
      window.removeEventListener('scroll', beiScroll)
      window.removeEventListener('resize', beiScroll)
    }
  }, [])

  // Hintergrund festhalten, solange das Mobilmenue offen ist.
  useEffect(() => {
    document.body.style.overflow = menueOffen ? 'hidden' : ''
    return () => {
      document.body.style.overflow = ''
    }
  }, [menueOffen])

  return (
    <header className="fixed inset-x-0 top-0 z-40">
      <div
        aria-hidden
        className={`absolute inset-0 border-b transition-all duration-500 ease-brand ${
          gelegt
            ? 'border-mist/10 bg-ink/70 backdrop-blur-xl'
            : 'border-transparent bg-transparent'
        }`}
      />

      {/* Lesefortschritt */}
      <div
        aria-hidden
        className="absolute inset-x-0 top-0 h-px origin-left bg-ember/70"
        style={{ transform: `scaleX(${fortschritt / 100})` }}
      />

      <div className="container relative mx-auto px-5 sm:px-6 lg:px-8">
        <div className="flex h-20 items-center justify-between">
          <a href="#home" className="flex items-center gap-3" aria-label="PräsenzWert, zum Seitenanfang">
            <AnimatePresence>
              {gelegt && (
                <motion.span
                  initial={{ opacity: 0, scale: 0.7 }}
                  animate={{ opacity: 1, scale: 1 }}
                  exit={{ opacity: 0, scale: 0.7 }}
                  transition={{ duration: 0.4, ease: [0.22, 1, 0.36, 1] }}
                >
                  <Image
                    src="/logo-mark.png"
                    alt=""
                    width={128}
                    height={128}
                    className="h-10 w-10 object-contain sm:h-11 sm:w-11"
                  />
                </motion.span>
              )}
            </AnimatePresence>
            <span className="text-base font-semibold tracking-tight text-mist sm:text-lg">
              Präsenz<span className="text-cyan-400">Wert</span>
            </span>
          </a>

          <nav className="hidden items-center gap-7 md:flex">
            {punkte.map((punkt) => (
              <a
                key={punkt.href}
                href={punkt.href}
                className="text-sm text-mist/65 transition-colors duration-300 ease-brand hover:text-mist"
              >
                {punkt.text}
              </a>
            ))}
          </nav>

          <div className="hidden md:block">
            <a
              href="#kontakt"
              className="rounded-full bg-ember px-5 py-2.5 text-sm font-semibold text-ink transition-colors duration-300 ease-brand hover:bg-ember-600"
            >
              Anfragen
            </a>
          </div>

          <button
            type="button"
            className="text-mist md:hidden"
            onClick={() => setMenueOffen(true)}
            aria-label="Menü öffnen"
            aria-expanded={menueOffen}
          >
            <Menu size={24} />
          </button>
        </div>
      </div>

      <AnimatePresence>
        {menueOffen && (
          <>
            <motion.div
              initial={{ opacity: 0 }}
              animate={{ opacity: 1 }}
              exit={{ opacity: 0 }}
              transition={{ duration: 0.25 }}
              className="fixed inset-0 z-40 bg-ink/80 backdrop-blur-sm md:hidden"
              onClick={() => setMenueOffen(false)}
            />
            <motion.div
              initial={{ x: '100%' }}
              animate={{ x: 0 }}
              exit={{ x: '100%' }}
              transition={{ duration: 0.4, ease: [0.22, 1, 0.36, 1] }}
              className="fixed inset-y-0 right-0 z-50 flex w-[86vw] max-w-sm flex-col border-l border-mist/10 bg-ink px-7 py-6 md:hidden"
            >
              <div className="mb-10 flex justify-end">
                <button
                  type="button"
                  onClick={() => setMenueOffen(false)}
                  aria-label="Menü schließen"
                  className="text-mist/60"
                >
                  <X size={24} />
                </button>
              </div>

              <nav className="flex flex-col gap-1">
                {[{ href: '#home', text: 'Start' }, ...punkte].map((punkt) => (
                  <a
                    key={punkt.href}
                    href={punkt.href}
                    onClick={() => setMenueOffen(false)}
                    className="border-b border-mist/[0.07] py-4 text-lg text-mist/80"
                  >
                    {punkt.text}
                  </a>
                ))}
              </nav>

              <a
                href="#kontakt"
                onClick={() => setMenueOffen(false)}
                className="mt-auto rounded-full bg-ember px-6 py-3.5 text-center font-semibold text-ink"
              >
                Anfragen
              </a>
            </motion.div>
          </>
        )}
      </AnimatePresence>
    </header>
  )
}
