import type { ReactNode } from 'react'
import RevealText from '../motion/RevealText'
import Reveal from '../motion/Reveal'

/**
 * Der gemeinsame Rahmen aller Abschnitte.
 *
 * Wichtig ist, was hier NICHT steht: kein Hintergrund. Die Abschnitte sind
 * durchsichtig, damit der Farbguss ununterbrochen durchlaeuft. Ein Abschnitt
 * wird nur an Typografie, Abstand und Bewegung erkennbar, nie an einer Kante.
 *
 * Die Nummer ist die einzige Orientierungshilfe, die es dafuer braucht.
 */

type Props = {
  id: string
  /** Zweistellig, z.B. "01" - laeuft ueber die Seite durch. */
  nummer: string
  augenbraue: string
  titel: string
  einleitung?: string
  children: ReactNode
  className?: string
}

export default function Section({
  id,
  nummer,
  augenbraue,
  titel,
  einleitung,
  children,
  className = '',
}: Props) {
  return (
    <section id={id} className={`relative py-24 sm:py-32 ${className}`}>
      <div className="container mx-auto px-5 sm:px-6 lg:px-8">
        <div className="mb-14 max-w-3xl sm:mb-20">
          <Reveal className="mb-5 flex items-center gap-4">
            <span className="font-mono text-xs tabular-nums text-ember">{nummer}</span>
            <span className="h-px w-10 bg-mist/20" />
            <span className="text-xs uppercase tracking-[0.25em] text-mist/50">
              {augenbraue}
            </span>
          </Reveal>

          <RevealText
            text={titel}
            className="text-headline font-bold text-balance text-mist"
          />

          {einleitung && (
            <Reveal verzoegerung={0.1}>
              <p className="mt-6 max-w-2xl text-base leading-relaxed text-mist/65 sm:text-lg">
                {einleitung}
              </p>
            </Reveal>
          )}
        </div>

        {children}
      </div>
    </section>
  )
}
