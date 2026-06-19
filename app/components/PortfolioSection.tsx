'use client'

import { motion } from 'framer-motion'
import { ExternalLink, CheckCircle2, Clock } from 'lucide-react'

type ProjectStatus = 'live' | 'progress'

interface Project {
  name: string
  url: string | null
  description: string
  tags: string[]
  status?: ProjectStatus
  statusLabel?: string
  isPlaceholder?: boolean
}

const projects: Project[] = [
  {
    name: 'AG Solar GmbH',
    url: 'https://www.ag-solar.net/',
    description:
      'Regional verankerte Unternehmenswebsite für einen Photovoltaik-, Batteriespeicher- und Wallbox-Spezialisten aus der Grafschaft (Ahr & Rhein). Klare Leistungsdarstellung, lokaler Fokus und starke Call-to-Actions.',
    tags: ['Unternehmenswebsite', 'Photovoltaik & Erneuerbare Energien', 'Regional Ahr · Rhein'],
    status: 'live',
    statusLabel: 'Fertiggestellt · Live',
  },
  {
    name: 'SR Automation',
    url: 'https://www.srautomation.de/',
    description:
      'Professionelle Unternehmenswebsite für einen Automatisierungstechnik-Spezialisten. Moderne Präsentation, klare Struktur und ansprechendes Design.',
    tags: ['Unternehmenswebsite', 'Automatisierungstechnik', 'Webdesign'],
    status: 'live',
    statusLabel: 'Fertiggestellt · Live',
  },
  {
    name: 'Manuela Rosenkranz – Praxis',
    url: 'https://manuela-rosenkranz.de/',
    description:
      'Vertrauensvolle Praxis-Website für Individualpsychologische Beratung & therapeutische Seelsorge in Bad Neuenahr-Ahrweiler. Einfühlsames Design und klare Angebotsstruktur.',
    tags: ['Praxis-Website', 'Beratung & Seelsorge', 'Bad Neuenahr-Ahrweiler'],
    status: 'live',
    statusLabel: 'Fertiggestellt · Live',
  },
  {
    name: 'Städtische Realschule Am Heimbach',
    url: null,
    description:
      'Moderne, übersichtliche Schulwebsite für die Städtische Realschule Am Heimbach in Bonn. Klare Informationsstruktur für Eltern, Schülerinnen und Schüler.',
    tags: ['Schulwebsite', 'Bildung', 'Bonn'],
    status: 'progress',
    statusLabel: 'In Arbeit · geplant Juni 2026',
  },
  {
    name: 'Hebammen am Marienhospital Bonn',
    url: null,
    description:
      'Informative Website für die Hebammen am Marienhospital Bonn. Vertrauensvoller Auftritt mit klaren Angeboten und einfacher Kontaktaufnahme für werdende Eltern.',
    tags: ['Praxis-Website', 'Gesundheit & Geburt', 'Bonn'],
    status: 'progress',
    statusLabel: 'In Arbeit · geplant Juni 2026',
  },
  {
    name: 'Möhnenverein Nierendorf e.V.',
    url: null,
    description:
      'Lebendige, animierte Vereinswebsite für den Möhnenverein Nierendorf in der Grafschaft. Mit Veranstaltungen, Galerie und liebevollem Auftritt für Möhnen, Dorfgarde und Frösche.',
    tags: ['Vereinswebsite', 'Karneval & Brauchtum', 'Grafschaft · Nierendorf'],
    status: 'progress',
    statusLabel: 'In Arbeit · geplant Juni 2026',
  },
  {
    name: 'Weitere Projekte in Auftrag',
    url: null,
    description:
      'Aktuell befinden sich weitere Websites für Unternehmen in der Region in der Umsetzung. Interesse? Jetzt unverbindlich anfragen!',
    tags: ['In Arbeit', 'Region Eifel · Ahr · Rhein'],
    isPlaceholder: true,
  },
]

function StatusBadge({ status, label }: { status: ProjectStatus; label: string }) {
  const isLive = status === 'live'
  return (
    <span
      className={`inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold tracking-wide ${
        isLive
          ? 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200'
          : 'bg-amber-50 text-amber-700 ring-1 ring-amber-200'
      }`}
    >
      {isLive ? (
        <CheckCircle2 className="w-3.5 h-3.5" />
      ) : (
        <Clock className="w-3.5 h-3.5" />
      )}
      {label}
    </span>
  )
}

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
              className={`flex flex-col rounded-2xl border-2 p-8 transition-all duration-300 ${
                project.isPlaceholder
                  ? 'border-dashed border-brand-cyan/40 bg-gradient-to-br from-brand-light to-white'
                  : 'border-brand-cyan/20 bg-gradient-to-br from-brand-light to-white shadow-lg hover:shadow-2xl hover:-translate-y-1'
              }`}
            >
              {project.status && project.statusLabel && (
                <div className="mb-4">
                  <StatusBadge status={project.status} label={project.statusLabel} />
                </div>
              )}

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

              <div className="flex flex-wrap gap-2 mt-auto">
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
