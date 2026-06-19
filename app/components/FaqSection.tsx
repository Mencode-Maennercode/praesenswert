'use client'

import { motion } from 'framer-motion'
import { Plus } from 'lucide-react'

/* Sichtbare FAQ – inhaltlich identisch zum FAQPage-Schema in layout.tsx,
   damit Google-Rich-Results & KI-Antwortmaschinen (ChatGPT, Perplexity)
   die Fragen sauber übernehmen können. */
export const faqs = [
  {
    q: 'Was kostet eine Website bei PräsenzWert?',
    a: 'PräsenzWert ist auf günstige, faire Festpreise spezialisiert – gerade für kleine Firmen und Vereine mit begrenztem Budget. Du bekommst vorab ein transparentes Angebot ohne versteckte Kosten. So ist eine professionelle Homepage schon mit kleinem Budget möglich.',
  },
  {
    q: 'Macht ihr auch günstige Websites für Vereine?',
    a: 'Ja. Vereine sind eine meiner Kern-Zielgruppen. Ich erstelle moderne, übersichtliche Vereinswebsites zu einem fairen Preis – ideal für Sportvereine, Karnevalsvereine, Fördervereine und kleine Organisationen in Köln, Bonn, der Eifel und am Rhein.',
  },
  {
    q: 'In welchen Regionen ist PräsenzWert tätig?',
    a: 'Mein Schwerpunkt liegt im Raum Köln, Bonn, Eifel, Ahrtal und Rhein – inklusive Euskirchen, Rhein-Sieg-Kreis, Bad Münstereifel, Bad Neuenahr-Ahrweiler, Remagen, Sinzig und Umgebung. Termine vor Ort sind in der Region problemlos möglich, die Zusammenarbeit funktioniert aber auch komplett digital.',
  },
  {
    q: 'Bekomme ich auch in Köln oder Bonn eine günstige Website?',
    a: 'Ja. Als kleine, persönliche Webagentur arbeite ich ohne teuren Agentur-Overhead – dadurch sind professionelle Websites in Köln und Bonn deutlich günstiger als bei großen Anbietern, bei gleichbleibender Qualität.',
  },
  {
    q: 'Werden die Websites für Google und KI-Suchmaschinen optimiert?',
    a: 'Ja. Jede Website wird technisch SEO-optimiert ausgeliefert (semantisches HTML, strukturierte Daten / Schema.org, Sitemap, schnelle Ladezeiten) und ist explizit für KI-Crawler wie GPTBot, ChatGPT, ClaudeBot, PerplexityBot und Google-Extended freigegeben – damit du auch in ChatGPT, Perplexity & Co. gefunden wirst.',
  },
  {
    q: 'Wie läuft ein Website-Projekt ab?',
    a: 'Zuerst ein unverbindliches Erstgespräch, dann ein transparentes Festpreis-Angebot. Nach deiner Freigabe setze ich die Website mit modernen Tools und KI-gestützten Workflows effizient um – das hält die Kosten niedrig und die Umsetzung schnell.',
  },
  {
    q: 'Bietet ihr auch eine Fotobox zum Mieten an?',
    a: 'Ja. Neben Websites vermiete ich eine moderne Fotobox mit KI-Effekten, lustigen Filtern, Sofort-Druck und einer Online-Fotodatenbank – für Hochzeiten, Geburtstage, Firmenfeiern und Vereinsfeste. In der Regel deutlich günstiger als klassische Fotobox-Anbieter.',
  },
  {
    q: 'Welche Referenzen gibt es?',
    a: 'Aktuelle Referenzen sind u. a. AG Solar GmbH (Photovoltaik & Wallboxen, Grafschaft/Ahr/Rhein), SR Automation (Automatisierungstechnik) und die Praxis Manuela Rosenkranz (Bad Neuenahr-Ahrweiler). In Arbeit sind Websites für die Realschule Am Heimbach (Bonn), die Hebammen am Marienhospital Bonn und den Möhnenverein Nierendorf.',
  },
]

export default function FaqSection() {
  return (
    <section id="faq" className="py-24 bg-white">
      <div className="container mx-auto px-4 sm:px-6 lg:px-8">
        <motion.div
          initial={{ opacity: 0, y: 30 }}
          whileInView={{ opacity: 1, y: 0 }}
          viewport={{ once: true }}
          transition={{ duration: 0.6 }}
          className="text-center mb-14"
        >
          <h2 className="text-4xl sm:text-5xl font-bold text-brand-navy mb-4">
            Häufige Fragen
          </h2>
          <p className="text-xl text-gray-600 max-w-3xl mx-auto">
            Günstige Websites für Firmen & Vereine in Köln, Bonn, der Eifel und am Rhein
          </p>
        </motion.div>

        <div className="max-w-3xl mx-auto space-y-4">
          {faqs.map((item, index) => (
            <motion.details
              key={index}
              initial={{ opacity: 0, y: 20 }}
              whileInView={{ opacity: 1, y: 0 }}
              viewport={{ once: true }}
              transition={{ duration: 0.4, delay: index * 0.05 }}
              className="group rounded-2xl border-2 border-brand-cyan/15 bg-gradient-to-br from-brand-light to-white p-6 shadow-sm open:shadow-lg transition-shadow"
            >
              <summary className="flex cursor-pointer items-center justify-between gap-4 list-none">
                <h3 className="text-lg font-bold text-brand-navy">{item.q}</h3>
                <span className="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full bg-brand-cyan/10 text-brand-cyan transition-transform duration-300 group-open:rotate-45">
                  <Plus className="h-5 w-5" />
                </span>
              </summary>
              <p className="mt-4 text-gray-600 leading-relaxed">{item.a}</p>
            </motion.details>
          ))}
        </div>
      </div>
    </section>
  )
}
