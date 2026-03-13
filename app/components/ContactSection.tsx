'use client'

import { motion } from 'framer-motion'
import { Mail, Phone, MapPin, Send, CheckCircle } from 'lucide-react'
import { useState } from 'react'

export default function ContactSection() {
  const [formData, setFormData] = useState({
    name: '',
    email: '',
    company: '',
    phone: '',
    message: '',
  })
  const [submitted, setSubmitted] = useState(false)
  const [submitting, setSubmitting] = useState(false)

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    
    if (!formData.name.trim() || !formData.email.trim() || !formData.message.trim()) {
      alert('Bitte füllen Sie alle Pflichtfelder aus.')
      return
    }
    
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/
    if (!emailRegex.test(formData.email)) {
      alert('Bitte geben Sie eine gültige E-Mail-Adresse ein.')
      return
    }

    setSubmitting(true)

    try {
      const response = await fetch('/contact.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          name: formData.name,
          email: formData.email,
          company: formData.company,
          phone: formData.phone,
          message: formData.message,
        }),
      })

      if (response.ok) {
        setSubmitted(true)
        setFormData({ name: '', email: '', company: '', phone: '', message: '' })
      } else {
        throw new Error('Fehler beim Senden')
      }
    } catch {
      alert('Fehler beim Senden. Bitte schreiben Sie direkt an: kontakt@praesenzwert.de')
    } finally {
      setSubmitting(false)
    }
  }

  const handleChange = (e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement>) => {
    setFormData({
      ...formData,
      [e.target.name]: e.target.value,
    })
  }

  return (
    <section id="kontakt" className="py-24 bg-white">
      <div className="container mx-auto px-4 sm:px-6 lg:px-8">
        <motion.div
          initial={{ opacity: 0, y: 30 }}
          whileInView={{ opacity: 1, y: 0 }}
          viewport={{ once: true }}
          transition={{ duration: 0.6 }}
          className="text-center mb-16"
        >
          <h2 className="text-4xl sm:text-5xl font-bold text-brand-navy mb-4">
            Jetzt Kontakt aufnehmen
          </h2>
          <p className="text-xl text-gray-600 max-w-3xl mx-auto">
            Vereinbarung eines unverbindlichen Beratungsgesprächs für das Website-Projekt
          </p>
        </motion.div>

        <div className="grid grid-cols-1 lg:grid-cols-2 gap-12 max-w-6xl mx-auto">
          <motion.div
            initial={{ opacity: 0, x: -30 }}
            whileInView={{ opacity: 1, x: 0 }}
            viewport={{ once: true }}
            transition={{ duration: 0.6 }}
          >
            <h3 className="text-2xl font-bold text-brand-navy mb-6">
              Anfrage
            </h3>

            {submitted ? (
              <div className="flex flex-col items-center justify-center py-16 text-center">
                <CheckCircle className="w-16 h-16 text-brand-cyan mb-4" />
                <h4 className="text-2xl font-bold text-brand-navy mb-2">Anfrage gesendet!</h4>
                <p className="text-gray-600">Vielen Dank! Ich melde mich so schnell wie möglich bei Ihnen.</p>
              </div>
            ) : (
              <form onSubmit={handleSubmit} className="space-y-6">
                <div>
                  <label htmlFor="name" className="block text-sm font-semibold text-brand-navy mb-2">
                    Name *
                  </label>
                  <input
                    type="text"
                    id="name"
                    name="name"
                    required
                    value={formData.name}
                    onChange={handleChange}
                    className="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:border-brand-cyan focus:outline-none transition-colors"
                    placeholder="Vorname Nachname"
                  />
                </div>

                <div>
                  <label htmlFor="email" className="block text-sm font-semibold text-brand-navy mb-2">
                    E-Mail *
                  </label>
                  <input
                    type="email"
                    id="email"
                    name="email"
                    required
                    value={formData.email}
                    onChange={handleChange}
                    className="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:border-brand-cyan focus:outline-none transition-colors"
                    placeholder="beispiel@email.de"
                  />
                </div>

                <div>
                  <label htmlFor="company" className="block text-sm font-semibold text-brand-navy mb-2">
                    Unternehmen
                  </label>
                  <input
                    type="text"
                    id="company"
                    name="company"
                    value={formData.company}
                    onChange={handleChange}
                    className="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:border-brand-cyan focus:outline-none transition-colors"
                    placeholder="Name des Unternehmens"
                  />
                </div>

                <div>
                  <label htmlFor="phone" className="block text-sm font-semibold text-brand-navy mb-2">
                    Telefon
                  </label>
                  <input
                    type="tel"
                    id="phone"
                    name="phone"
                    value={formData.phone}
                    onChange={handleChange}
                    className="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:border-brand-cyan focus:outline-none transition-colors"
                    placeholder="Telefonnummer"
                  />
                </div>

                <div>
                  <label htmlFor="message" className="block text-sm font-semibold text-brand-navy mb-2">
                    Nachricht *
                  </label>
                  <textarea
                    id="message"
                    name="message"
                    required
                    value={formData.message}
                    onChange={handleChange}
                    rows={5}
                    className="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:border-brand-cyan focus:outline-none transition-colors resize-none"
                    placeholder="Kurze Projektbeschreibung und Vorstellungen..."
                  />
                </div>

                <button
                  type="submit"
                  disabled={submitting}
                  className="w-full bg-brand-cyan text-white px-8 py-4 rounded-lg font-semibold text-lg hover:bg-brand-navy transition-all duration-300 shadow-lg hover:shadow-xl flex items-center justify-center gap-2 disabled:opacity-60 disabled:cursor-not-allowed"
                >
                  {submitting ? 'Wird gesendet...' : 'Unverbindliche Anfrage senden'}
                  {!submitting && <Send size={20} />}
                </button>
              </form>
            )}
          </motion.div>

          <motion.div
            initial={{ opacity: 0, x: 30 }}
            whileInView={{ opacity: 1, x: 0 }}
            viewport={{ once: true }}
            transition={{ duration: 0.6 }}
            className="space-y-8"
          >
            <div>
              <h3 className="text-2xl font-bold text-brand-navy mb-6">
                Ihre Region Rhein · Ahr · Eifel
              </h3>
              <div className="space-y-6">
                <div className="flex items-start gap-4">
                  <div className="bg-brand-cyan/10 p-3 rounded-lg">
                    <Phone className="w-6 h-6 text-brand-cyan" />
                  </div>
                  <div>
                    <h4 className="font-semibold text-brand-navy mb-1">Telefon</h4>
                    <a 
                      href="tel:+491714760398" 
                      className="text-gray-600 hover:text-brand-cyan transition-colors inline-flex items-center gap-2 group"
                    >
                      <span className="group-hover:scale-110 transition-transform">📞</span>
                      0171 / 7460398
                    </a>
                  </div>
                </div>

                <div className="flex items-start gap-4">
                  <div className="bg-brand-cyan/10 p-3 rounded-lg">
                    <Mail className="w-6 h-6 text-brand-cyan" />
                  </div>
                  <div>
                    <h4 className="font-semibold text-brand-navy mb-1">E-Mail</h4>
                    <a href="mailto:kontakt@praesenzwert.de" className="text-gray-600 hover:text-brand-cyan transition-colors">kontakt@praesenzwert.de</a>
                  </div>
                </div>

                <div className="flex items-start gap-4">
                  <div className="bg-brand-cyan/10 p-3 rounded-lg">
                    <MapPin className="w-6 h-6 text-brand-cyan" />
                  </div>
                  <div>
                    <h4 className="font-semibold text-brand-navy mb-1">Standort</h4>
                    <p className="text-gray-600">Josef-Martin-Weg 4<br />53501 Grafschaft</p>
                  </div>
                </div>

                <a
                  href="https://wa.me/491714760398"
                  target="_blank"
                  rel="noopener noreferrer"
                  className="flex items-center gap-3 bg-[#25D366] text-white px-6 py-4 rounded-lg font-semibold hover:bg-[#20BA5A] transition-all duration-300 shadow-lg hover:shadow-xl"
                >
                  <svg className="w-6 h-6" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/>
                  </svg>
                  WhatsApp Nachricht senden
                </a>
              </div>
            </div>

            <div className="bg-gradient-to-br from-brand-light to-white p-8 rounded-2xl border border-brand-cyan/20">
              <h4 className="font-bold text-brand-navy mb-4 text-xl">
                Unverbindliches Erstgespräch
              </h4>
              <p className="text-gray-600 leading-relaxed mb-4">
                Wir besprechen die Anforderungen und ich erstelle ein transparentes Angebot. 
                Keine versteckten Kosten, faire Preise für regionale Betriebe & Organisationen.
              </p>
              <ul className="space-y-2 text-gray-600">
                <li className="flex items-center gap-2">
                  <div className="w-2 h-2 bg-brand-cyan rounded-full"></div>
                  Unverbindliche Beratung
                </li>
                <li className="flex items-center gap-2">
                  <div className="w-2 h-2 bg-brand-cyan rounded-full"></div>
                  Klare Leistungsbeschreibung
                </li>
                <li className="flex items-center gap-2">
                  <div className="w-2 h-2 bg-brand-cyan rounded-full"></div>
                  Faire, transparente Preise
                </li>
              </ul>
            </div>
          </motion.div>
        </div>
      </div>
    </section>
  )
}
