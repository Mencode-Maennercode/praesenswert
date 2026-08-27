import Image from 'next/image'

/**
 * Fusszeile.
 *
 * Kein eigener Hintergrund - der Farbguss laeuft bis zur letzten Zeile durch
 * und endet dort, wo er angefangen hat. Nur eine Haarlinie trennt sie ab.
 */

const seite = [
  { href: '#leistungen', text: 'Leistungen' },
  { href: '#arbeitsweise', text: 'Arbeitsweise' },
  { href: '#referenzen', text: 'Referenzen' },
  { href: '#produkte', text: 'Produkte' },
  { href: '#faq', text: 'FAQ' },
  { href: '#kontakt', text: 'Kontakt' },
]

const produkte = [
  { href: '/bestell-bar', text: 'Bestell-System' },
  { href: '/mencode', text: 'MenCode' },
  { href: '/womencode', text: 'WomenCode' },
]

const rechtliches = [
  { href: '/impressum', text: 'Impressum' },
  { href: '/datenschutz', text: 'Datenschutz' },
]

export default function Footer() {
  return (
    <footer className="relative border-t border-mist/10 pb-12 pt-20">
      <div className="container mx-auto px-5 sm:px-6 lg:px-8">
        <div className="grid gap-12 sm:grid-cols-2 lg:grid-cols-4">
          <div className="lg:col-span-1">
            <div className="mb-5 flex items-center gap-2.5">
              <Image
                src="/logo-mark.png"
                alt=""
                width={96}
                height={96}
                className="h-10 w-10 object-contain"
              />
              <span className="font-semibold tracking-tight text-mist">
                Präsenz<span className="text-cyan-400">Wert</span>
              </span>
            </div>
            <p className="max-w-xs text-sm leading-relaxed text-mist/45">
              Günstige, professionelle Websites für kleine Firmen und Vereine
              zwischen Eifel, Ahr, Rhein, Köln und Bonn.
            </p>
          </div>

          {[
            { titel: 'Seite', punkte: seite },
            { titel: 'Produkte', punkte: produkte },
            { titel: 'Rechtliches', punkte: rechtliches },
          ].map((spalte) => (
            <div key={spalte.titel}>
              <h3 className="mb-5 text-xs uppercase tracking-[0.25em] text-mist/35">
                {spalte.titel}
              </h3>
              <ul className="space-y-3">
                {spalte.punkte.map((punkt) => (
                  <li key={punkt.href}>
                    <a
                      href={punkt.href}
                      className="text-sm text-mist/55 transition-colors duration-300 ease-brand hover:text-ember"
                    >
                      {punkt.text}
                    </a>
                  </li>
                ))}
              </ul>
            </div>
          ))}
        </div>

        <div className="mt-16 flex flex-col gap-3 border-t border-mist/10 pt-8 text-sm text-mist/35 sm:flex-row sm:items-center sm:justify-between">
          <p>&copy; {new Date().getFullYear()} PräsenzWert</p>
          <p>Josef-Martin-Weg 4 · 53501 Grafschaft</p>
        </div>
      </div>
    </footer>
  )
}
