'use client'

import { motion } from 'framer-motion'
import { ExternalLink } from 'lucide-react'

const projects = [
  {
    name: 'SR Automation',
    url: 'https://www.srautomation.de/',
    description: 'Professionelle Unternehmenswebsite für einen Automatisierungstechnik-Spezialisten. Moderne Präsentation, klare Struktur und ansprechendes Design.',
    tags: ['Unternehmenswebsite', 'Automatisierungstechnik', 'Webdesign'],
  },
  {
    name: 'Weitere Projekte in Auftrag',
    url: null,
    description: 'Aktuell befinden sich weitere Websites für Unternehmen in der Region in der Umsetzung. Interesse? Jetzt unverbindlich anfragen!',
    tags: ['In Arbeit', 'Region Eifel · Ahr · Rhein'],
    isPlaceholder: true,
  },
]

export default function PortfolioSection() {
  return (
    <section id="referenzen" className="py-24 bg-white">
      <div className="container mx-auto px-4 sm:px-6 lg:px-8">
        <motion.div
          initial={{ opacity: 0, y: 30 }}
          whileInView={{ opacity: 1, y: 0 }}
          viewport={{ once: true }}
          transition={{ duration: 0.6 }}
          className="text-center mb-16"
        >
          <h2 className="text-4xl sm:text-5xl font-bold text-brand-navy mb-4">
            Referenzen
          </h2>
          <p className="text-xl text-gray-600 max-w-3xl mx-auto">
            Beispiele umgesetzter Websites für Unternehmen in der Region
          </p>
        </motion.div>

        <div className="grid grid-cols-1 md:grid-cols-2 gap-8 max-w-5xl mx-auto">
          {projects.map((project, index) => (
            <motion.div
              key={index}
              initial={{ opacity: 0, y: 30 }}
              whileInView={{ opacity: 1, y: 0 }}
              viewport={{ once: true }}
              transition={{ duration: 0.6, delay: index * 0.15 }}
              className={`rounded-2xl border-2 p-8 transition-all duration-300 ${
                project.isPlaceholder
                  ? 'border-dashed border-brand-cyan/40 bg-gradient-to-br from-brand-light to-white'
                  : 'border-brand-cyan/20 bg-gradient-to-br from-brand-light to-white shadow-lg hover:shadow-2xl hover:-translate-y-1'
              }`}
            >
              <div className="flex items-start justify-between mb-4">
                <h3 className="text-2xl font-bold text-brand-navy">
                  {project.name}
                </h3>
                {project.url && (
                  <a
                    href={project.url}
                    target="_blank"
                    rel="noopener noreferrer"
                    aria-label={`${project.name} Website öffnen`}
                    className="bg-brand-cyan/10 p-2 rounded-lg hover:bg-brand-cyan hover:text-white text-brand-cyan transition-all duration-300 flex-shrink-0"
                  >
                    <ExternalLink className="w-5 h-5" />
                  </a>
                )}
              </div>

              <p className="text-gray-600 leading-relaxed mb-6">
                {project.description}
              </p>

              <div className="flex flex-wrap gap-2">
                {project.tags.map((tag, tagIndex) => (
                  <span
                    key={tagIndex}
                    className="px-3 py-1 bg-brand-cyan/10 text-brand-cyan text-sm font-medium rounded-full"
                  >
                    {tag}
                  </span>
                ))}
              </div>

              {project.url && (
                <a
                  href={project.url}
                  target="_blank"
                  rel="noopener noreferrer"
                  className="mt-6 inline-flex items-center gap-2 text-brand-cyan font-semibold hover:text-brand-navy transition-colors duration-200"
                >
                  Website besuchen
                  <ExternalLink className="w-4 h-4" />
                </a>
              )}

              {project.isPlaceholder && (
                <a
                  href="#kontakt"
                  className="mt-6 inline-flex items-center gap-2 text-brand-cyan font-semibold hover:text-brand-navy transition-colors duration-200"
                >
                  Jetzt anfragen →
                </a>
              )}
            </motion.div>
          ))}
        </div>
      </div>
    </section>
  )
}
