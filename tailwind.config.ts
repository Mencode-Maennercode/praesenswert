import type { Config } from 'tailwindcss'

/**
 * Die Palette ist aus den beiden Markenfarben hergeleitet, nicht dazuerfunden.
 *
 *   Navy #1A2745 liegt bei Farbton 222 Grad
 *   Cyan #4DA6B8 liegt bei Farbton 190 Grad
 *
 * Beide liegen nebeneinander auf dem Farbkreis - die Marke ist also bereits
 * analog. Dieser 32-Grad-Bogen traegt die gesamte Seite. Der einzige Ton
 * ausserhalb davon ist Ember: der Komplementaerwert zu Cyan (190 - 180 = 10
 * Grad). Ember wird der Marke gegenuebergestellt, nie beigemischt, und
 * bedeutet ausschliesslich "hier kann gehandelt werden".
 */
const config: Config = {
  content: [
    './pages/**/*.{js,ts,jsx,tsx,mdx}',
    './components/**/*.{js,ts,jsx,tsx,mdx}',
    './app/**/*.{js,ts,jsx,tsx,mdx}',
  ],
  theme: {
    extend: {
      colors: {
        // Die analoge Achse - Anfang bis Ende des Farbgusses
        ink: '#0E1626',
        navy: { DEFAULT: '#1A2745', 600: '#24365E' },
        steel: '#2F5375',
        cyan: { DEFAULT: '#4DA6B8', 400: '#6FBFCE', 700: '#2E7C8D' },
        mist: '#E8F4F6',

        // Der eine Gegenton - nur Handlung
        ember: { DEFAULT: '#E8734C', 600: '#C85A35' },

        // Funktionaler Status, bewusst ausserhalb des Schemas
        wip: '#F0B429',

        // Echte Fremdmarken, nur innerhalb der jeweiligen Produktkarte
        mencode: '#D9B25F',
        womencode: '#C89B8C',

        // Altnamen, damit nichts ausserhalb des Rebuilds bricht
        'brand-navy': '#1A2745',
        'brand-cyan': '#4DA6B8',
        'brand-light': '#E8F4F6',
      },
      fontSize: {
        // Fliessende Groessen fuer die grossen Motion-Ueberschriften
        // Nicht groesser: bei 7vw fuellte die Hero-Ueberschrift auf 1600px
        // fuenf Zeilen und schob die Knoepfe unter die Falz.
        display: ['clamp(2.25rem, 4.6vw, 4.5rem)', { lineHeight: '1.02', letterSpacing: '-0.025em' }],
        headline: ['clamp(2rem, 4.5vw, 3.75rem)', { lineHeight: '1.05', letterSpacing: '-0.02em' }],
      },
      transitionTimingFunction: {
        // Ein einziges Easing fuer die ganze Seite - Bewegung soll wie aus
        // einem Guss wirken, genau wie die Farbe.
        brand: 'cubic-bezier(0.22, 1, 0.36, 1)',
      },
      keyframes: {
        drift: {
          '0%, 100%': { transform: 'translate3d(0,0,0)' },
          '50%': { transform: 'translate3d(0,-14px,0)' },
        },
      },
      animation: {
        drift: 'drift 7s cubic-bezier(0.45,0,0.55,1) infinite',
      },
    },
  },
  plugins: [],
}
export default config
