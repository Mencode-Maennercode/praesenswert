/**
 * Die einzige Quelle der FAQ-Texte.
 *
 * Sowohl der sichtbare Abschnitt (FaqSection) als auch das FAQPage-Schema in
 * layout.tsx lesen von hier. Vorher standen die Texte doppelt im Code, mit
 * einem Kommentar, dass sie identisch bleiben muessen - genau die Art von
 * Absprache, die beim naechsten Textdreh gebrochen wird. Weichen sichtbarer
 * Text und Schema voneinander ab, verwirft Google die Rich Results fuer die
 * ganze Seite.
 */

export type FaqEintrag = { frage: string; antwort: string }

export const faqs: FaqEintrag[] = [
  {
    frage: 'Was kostet eine Website bei PräsenzWert?',
    antwort:
      'PräsenzWert ist auf günstige, faire Festpreise spezialisiert – gerade für kleine Firmen und Vereine mit begrenztem Budget. Du bekommst vorab ein transparentes Angebot ohne versteckte Kosten. So ist eine professionelle Homepage schon mit kleinem Budget möglich.',
  },
  {
    frage: 'Macht ihr auch günstige Websites für Vereine?',
    antwort:
      'Ja. Vereine sind eine meiner Kern-Zielgruppen. Ich erstelle moderne, übersichtliche Vereinswebsites zu einem fairen Preis – ideal für Sportvereine, Karnevalsvereine, Fördervereine und kleine Organisationen in Köln, Bonn, der Eifel und am Rhein.',
  },
  {
    frage: 'In welchen Regionen ist PräsenzWert tätig?',
    antwort:
      'Mein Schwerpunkt liegt im Raum Köln, Bonn, Eifel, Ahrtal und Rhein – inklusive Euskirchen, Rhein-Sieg-Kreis, Bad Münstereifel, Bad Neuenahr-Ahrweiler, Remagen, Sinzig und Umgebung. Termine vor Ort sind in der Region problemlos möglich, die Zusammenarbeit funktioniert aber auch komplett digital.',
  },
  {
    frage: 'Bekomme ich auch in Köln oder Bonn eine günstige Website?',
    antwort:
      'Ja. Als kleine, persönliche Webagentur arbeite ich ohne teuren Agentur-Overhead – dadurch sind professionelle Websites in Köln und Bonn deutlich günstiger als bei großen Anbietern, bei gleichbleibender Qualität.',
  },
  {
    frage: 'Werden die Websites für Google und KI-Suchmaschinen optimiert?',
    antwort:
      'Ja. Jede Website wird technisch SEO-optimiert ausgeliefert (semantisches HTML, strukturierte Daten / Schema.org, Sitemap, schnelle Ladezeiten) und ist explizit für KI-Crawler wie GPTBot, ChatGPT, ClaudeBot, PerplexityBot und Google-Extended freigegeben – damit du auch in ChatGPT, Perplexity & Co. gefunden wirst.',
  },
  {
    frage: 'Wie läuft ein Website-Projekt ab?',
    antwort:
      'Zuerst ein unverbindliches Erstgespräch, dann ein transparentes Festpreis-Angebot. Nach deiner Freigabe setze ich die Website mit modernen Tools und KI-gestützten Workflows effizient um – das hält die Kosten niedrig und die Umsetzung schnell.',
  },
  {
    frage: 'Kann ich mein eigenes Konzept umsetzen lassen?',
    antwort:
      'Ja, und das ist ausdrücklich erwünscht. Wenn du eine klare Vorstellung von deiner Website mitbringst, setze ich genau diese um – nicht meine Version davon. Ich sage dir vorher ehrlich, wo ich etwas anders machen würde und warum, aber die Entscheidung liegt bei dir. Die Praxis-Website von Manuela Rosenkranz ist genau so entstanden: Konzept und Gestaltung kamen von der Auftraggeberin und wurden eins zu eins umgesetzt.',
  },
  {
    frage: 'Bietet ihr auch eine Fotobox zum Mieten an?',
    antwort:
      'Ja. Neben Websites vermiete ich eine moderne Fotobox mit KI-Effekten, lustigen Filtern, Sofort-Druck und einer Online-Fotodatenbank – für Hochzeiten, Geburtstage, Firmenfeiern und Vereinsfeste. In der Regel deutlich günstiger als klassische Fotobox-Anbieter.',
  },
  {
    frage: 'Gibt es auch eigene Apps von PräsenzWert?',
    antwort:
      'Ja, zwei: MenCode und WomenCode. Beide gibt es kostenlos im Google Play Store. Sie geben jeden Tag eine kleine Mission für den Menschen an der Seite – MenCode für ihn, WomenCode für sie, gleiche Mechanik in gespiegelter Perspektive, mit Punkten, Serien und sichtbarem Fortschritt.',
  },
  {
    frage: 'Welche Referenzen gibt es?',
    antwort:
      'Aktuelle Referenzen sind u. a. AG Solar GmbH (Photovoltaik & Wallboxen, Grafschaft/Ahr/Rhein), SR Automation (Automatisierungstechnik) und die Praxis Manuela Rosenkranz (Bad Neuenahr-Ahrweiler). In Arbeit sind Websites für die Realschule Am Heimbach (Bonn), die Hebammen am Marienhospital Bonn, die mobile Physiotherapie Nora Heidenreich (Kreis Ahrweiler) und den Möhnenverein Nierendorf.',
  },
]
