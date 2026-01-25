'use client'

import { useState } from 'react'
import { motion, AnimatePresence } from 'framer-motion'
import { useInView } from 'react-intersection-observer'
import CountUp from 'react-countup'
import { 
  ArrowRight, 
  CheckCircle2, 
  XCircle, 
  QrCode, 
  Smartphone, 
  BarChart3, 
  Users, 
  Clock, 
  TrendingUp,
  Shield,
  Download,
  PartyPopper,
  Music,
  Building,
  Users2,
  Calculator,
  CreditCard,
  Wifi,
  Zap,
  Settings,
  X,
  Play,
  Mail,
  Phone,
  MessageCircle
} from 'lucide-react'

export default function BestellBarPage() {
  const [ref, inView] = useInView({
    triggerOnce: true,
    threshold: 0.1,
  })

  const [modalOpen, setModalOpen] = useState(false)
  const [modalType, setModalType] = useState<'impressum' | 'datenschutz'>('impressum')
  const [imageModal, setImageModal] = useState<string | null>(null)

  const openModal = (type: 'impressum' | 'datenschutz') => {
    setModalType(type)
    setModalOpen(true)
  }

  const openImageModal = (src: string) => {
    setImageModal(src)
  }

  return (
    <div className="min-h-screen bg-gradient-to-br from-slate-900 via-slate-800 to-zinc-900 text-white overflow-hidden">
      {/* Hero Section with Video Background */}
      <section className="relative h-screen flex items-center justify-center overflow-hidden">
        {/* Video Background */}
        <div className="absolute inset-0 z-0">
          <video
            autoPlay
            loop
            muted
            playsInline
            className="w-full h-full object-cover"
          >
            <source src="/5847855-hd_1280_720_30fps.mp4" type="video/mp4" />
          </video>
          <div className="absolute inset-0 bg-gradient-to-b from-slate-900/70 via-slate-900/50 to-slate-900/90"></div>
        </div>

        {/* Hero Content - Animated Text */}
        <div className="relative z-10 text-center px-4 max-w-6xl mx-auto">
          {/* Bestell-Bar - fades in after 1.5s */}
          <motion.h1
            initial={{ opacity: 0, y: 30 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 1, delay: 1.5 }}
            className="text-7xl md:text-9xl font-black mb-6 tracking-tight"
          >
            <span className="bg-clip-text text-transparent bg-gradient-to-r from-amber-400 via-orange-500 to-red-500">
              Bestell-Bar
            </span>
          </motion.h1>
          
          {/* Subtitle - fades in after 2.5s */}
          <motion.p
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.8, delay: 2.5 }}
            className="text-2xl md:text-4xl mb-8 text-white font-bold"
          >
            Event-Bestellungen revolutioniert!
          </motion.p>

          {/* Tagline - fades in after 3.2s */}
          <motion.p
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            transition={{ duration: 0.8, delay: 3.2 }}
            className="text-lg md:text-xl text-slate-300 mb-12 max-w-2xl mx-auto"
          >
            Digitale Getränkebestellung mit Live-Statistik — Kein Kopfrechnen mehr!
          </motion.p>
          
          {/* CTA Button - fades in after 3.8s */}
          <motion.div
            initial={{ opacity: 0, scale: 0.9 }}
            animate={{ opacity: 1, scale: 1 }}
            transition={{ duration: 0.5, delay: 3.8 }}
          >
            <motion.a
              href="#contact"
              whileHover={{ scale: 1.05 }}
              whileTap={{ scale: 0.95 }}
              className="bg-gradient-to-r from-amber-500 to-orange-600 text-white px-10 py-5 rounded-full text-xl font-bold hover:shadow-2xl hover:shadow-orange-500/30 transition-all duration-300 inline-flex items-center gap-3"
            >
              Demo vereinbaren
              <ArrowRight className="w-6 h-6" />
            </motion.a>
          </motion.div>
        </div>

        {/* Back Button */}
        <motion.a
          href="https://praesenzwert.de"
          initial={{ opacity: 0, x: -50 }}
          animate={{ opacity: 1, x: 0 }}
          transition={{ delay: 1 }}
          className="absolute top-8 left-8 z-20 bg-white/10 backdrop-blur-md px-6 py-3 rounded-full hover:bg-white/20 transition-all duration-300 inline-flex items-center gap-2 border border-white/10"
        >
          <ArrowRight className="w-5 h-5 rotate-180" />
          Zurück zu PräsenzWert
        </motion.a>

        {/* Scroll Indicator */}
        <motion.div
          initial={{ opacity: 0 }}
          animate={{ opacity: 1 }}
          transition={{ delay: 4.5 }}
          className="absolute bottom-8 left-1/2 -translate-x-1/2 z-20"
        >
          <motion.div
            animate={{ y: [0, 10, 0] }}
            transition={{ duration: 1.5, repeat: Infinity }}
            className="w-6 h-10 border-2 border-white/30 rounded-full flex justify-center pt-2"
          >
            <div className="w-1.5 h-3 bg-white/50 rounded-full"></div>
          </motion.div>
        </motion.div>
      </section>

      {/* Problem & Solution Section without Video */}
      <section className="py-24 px-4 relative">
        <div className="absolute inset-0 bg-gradient-to-b from-slate-900 via-red-950/20 to-slate-900"></div>
        
        <div className="max-w-6xl mx-auto relative z-10">
          <motion.div
            ref={ref}
            initial={{ opacity: 0, y: 50 }}
            animate={inView ? { opacity: 1, y: 0 } : {}}
            transition={{ duration: 0.6 }}
            className="text-center mb-16"
          >
            <h2 className="text-4xl md:text-6xl font-black mb-4">
              <span className="bg-clip-text text-transparent bg-gradient-to-r from-red-400 to-amber-400">
                Das Problem
              </span>
            </h2>
            <p className="text-slate-400 text-lg">Kennen Sie das?</p>
          </motion.div>

          <div className="grid md:grid-cols-2 gap-8 mb-20">
            {/* Problem */}
            <motion.div
              initial={{ opacity: 0, x: -50 }}
              whileInView={{ opacity: 1, x: 0 }}
              transition={{ duration: 0.6 }}
              className="bg-gradient-to-br from-red-950/50 to-slate-900/50 backdrop-blur-md p-8 rounded-3xl border border-red-500/20"
            >
              <div className="flex items-center gap-3 mb-6">
                <div className="w-12 h-12 bg-red-500/20 rounded-full flex items-center justify-center flex-shrink-0">
                  <XCircle className="w-6 h-6 text-red-400" />
                </div>
                <h3 className="text-2xl font-bold text-red-400">Chaos am Event</h3>
              </div>
              <ul className="space-y-4 text-slate-300">
                <li className="flex items-start gap-3">
                  <span className="text-red-400 mt-1 text-xl" style={{ transform: 'translateY(-8px)' }}>×</span>
                  <span>Gäste halten unlesbare "Durst"-Schilder hoch</span>
                </li>
                <li className="flex items-start gap-3">
                  <span className="text-red-400 mt-1 text-xl" style={{ transform: 'translateY(-8px)' }}>×</span>
                  <span>Gäste warten ewigkeiten bis der Kellner sie wahrnimmt</span>
                </li>
                <li className="flex items-start gap-3">
                  <span className="text-red-400 mt-1 text-xl" style={{ transform: 'translateY(-8px)' }}>×</span>
                  <span>Chaotische, unstrukturierte Bestelleingänge an der Theke</span>
                </li>
              </ul>
            </motion.div>

            {/* Solution */}
            <motion.div
              initial={{ opacity: 0, x: 50 }}
              whileInView={{ opacity: 1, x: 0 }}
              transition={{ duration: 0.6, delay: 0.2 }}
              className="bg-gradient-to-br from-emerald-950/50 to-slate-900/50 backdrop-blur-md p-8 rounded-3xl border border-emerald-500/20"
            >
              <div className="flex items-center gap-3 mb-6">
                <div className="w-12 h-12 bg-emerald-500/20 rounded-full flex items-center justify-center flex-shrink-0">
                  <CheckCircle2 className="w-6 h-6 text-emerald-400" />
                </div>
                <h3 className="text-2xl font-bold text-emerald-400">Die Lösung</h3>
              </div>
              <ul className="space-y-4 text-slate-300">
                <li className="flex items-start gap-3">
                  <span className="text-emerald-400 mt-1 text-xl" style={{ transform: 'translateY(-8px)' }}>✓</span>
                  <span>QR-Code-basiertes Bestellsystem</span>
                </li>
                <li className="flex items-start gap-3">
                  <span className="text-emerald-400 mt-1 text-xl" style={{ transform: 'translateY(-8px)' }}>✓</span>
                  <span>Digitale Bestellungen statt unlesbarer Schilder</span>
                </li>
                <li className="flex items-start gap-3">
                  <span className="text-emerald-400 mt-1 text-xl" style={{ transform: 'translateY(-8px)' }}>✓</span>
                  <span>Strukturierte und effiziente Bedienung</span>
                </li>
              </ul>
            </motion.div>
          </div>
        </div>
      </section>

      {/* How It Works - with Video */}
      <section className="py-24 px-4 relative">
        <div className="absolute inset-0 bg-gradient-to-b from-slate-900 via-amber-950/20 to-slate-900"></div>
        
        <div className="max-w-6xl mx-auto relative z-10">
          <motion.div
            initial={{ opacity: 0, y: 50 }}
            whileInView={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.6 }}
            className="text-center mb-16"
          >
            <h2 className="text-4xl md:text-6xl font-black mb-4">
              <span className="bg-clip-text text-transparent bg-gradient-to-r from-amber-400 to-orange-500">
                So funktioniert's
              </span>
            </h2>
          </motion.div>

          <div className="grid md:grid-cols-2 lg:grid-cols-6 gap-6">
            {[
              {
                icon: QrCode,
                title: "QR-Code scannen",
                description: "Gäste scannen den Code am Tisch",
                step: "1"
              },
              {
                icon: Smartphone,
                title: "Bestellen",
                description: "Getränke auswählen oder Kellner rufen",
                step: "2"
              },
              {
                icon: Users,
                title: "Kellner kann Bestellung aufnehmen",
                description: "Kellner kann Getränke ebenfalls erfassen",
                step: "2.5",
                optional: true
              },
              {
                icon: BarChart3,
                title: "Theke bereitet vor",
                description: "Theke kann Bestellung nach Dringlichkeit (Farbcodierung) direkt vorbereiten",
                step: "3"
              },
              {
                icon: Users,
                title: "Kellner liefert",
                description: "Nur zugewiesene Tische auf dem Handy",
                step: "4"
              },
              {
                icon: CreditCard,
                title: "Abrechnung",
                description: "Kellner sieht was jeder Tisch zahlt",
                step: "5"
              }
            ].map((feature, index) => (
              <motion.div
                key={index}
                initial={{ opacity: 0, y: 50 }}
                whileInView={{ opacity: 1, y: 0 }}
                transition={{ duration: 0.5, delay: index * 0.1 }}
                className={`relative bg-slate-800/50 backdrop-blur-md p-6 rounded-2xl border border-slate-700/50 hover:border-amber-500/30 transition-all duration-300 group ${feature.optional ? 'ml-4 opacity-80' : ''}`}
              >
                <div className={`absolute -top-3 -left-3 w-8 h-8 bg-gradient-to-r from-amber-500 to-orange-500 rounded-full flex items-center justify-center text-sm font-bold ${feature.optional ? 'bg-gradient-to-r from-slate-500 to-slate-600' : ''}`}>
                  {feature.step}
                </div>
                <feature.icon className={`w-10 h-10 text-amber-400 mb-4 group-hover:scale-110 transition-transform ${feature.optional ? 'text-slate-400' : ''}`} />
                <h3 className={`text-lg font-bold mb-2 ${feature.optional ? 'text-slate-400' : ''}`}>{feature.title}</h3>
                <p className="text-slate-400 text-sm">{feature.description}</p>
                {feature.optional && (
                  <div className="mt-2 text-xs text-slate-500 italic">Optional</div>
                )}
              </motion.div>
            ))}
          </div>
        </div>
      </section>

      {/* Advantages Section without Video */}
      <section className="py-24 px-4 relative">
        <div className="absolute inset-0 bg-gradient-to-b from-slate-900 via-emerald-950/20 to-slate-900"></div>
        
        <div className="max-w-6xl mx-auto relative z-10">
          <motion.div
            initial={{ opacity: 0, y: 50 }}
            whileInView={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.6 }}
            className="text-center mb-16"
          >
            <h2 className="text-4xl md:text-6xl font-black mb-4">
              <span className="bg-clip-text text-transparent bg-gradient-to-r from-emerald-400 to-teal-400">
                Ihre Vorteile
              </span>
            </h2>
          </motion.div>

          <div className="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
            {[
              {
                icon: Clock,
                title: "88% schneller",
                description: "Direkte Bestellung ohne Wartezeit"
              },
              {
                icon: Shield,
                title: "Keine verlorenen Bestellungen",
                description: "Alles digital erfasst"
              },
              {
                icon: Zap,
                title: "Sofort startklar",
                description: "Kein Server nötig - nur Internet"
              },
              {
                icon: Wifi,
                title: "Geringer Datenverbrauch",
                description: "Funktioniert mit Mobilem Hotspot"
              },
              {
                icon: Settings,
                title: "Selbst einrichtbar",
                description: "Ohne technische Vorkenntnisse"
              },
              {
                icon: Calculator,
                title: "Flexibel anpassbar",
                description: "Produkte & Preise auch während des laufenden Betriebs verwalten"
              }
            ].map((advantage, index) => (
              <motion.div
                key={index}
                initial={{ opacity: 0, scale: 0.9 }}
                whileInView={{ opacity: 1, scale: 1 }}
                transition={{ duration: 0.5, delay: index * 0.1 }}
                className="bg-slate-800/60 backdrop-blur-md p-6 rounded-2xl border border-emerald-500/20 hover:border-emerald-400/40 transition-all duration-300"
              >
                <advantage.icon className="w-10 h-10 text-emerald-400 mb-4" />
                <h3 className="text-xl font-bold mb-2 text-emerald-400">{advantage.title}</h3>
                <p className="text-slate-400">{advantage.description}</p>
              </motion.div>
            ))}
          </div>
        </div>
      </section>

      {/* Statistics Features */}
      <section className="py-24 px-4 bg-gradient-to-b from-slate-900 via-slate-800 to-slate-900">
        <div className="max-w-6xl mx-auto">
          <motion.div
            initial={{ opacity: 0, y: 50 }}
            whileInView={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.6 }}
            className="text-center mb-16"
          >
            <h2 className="text-4xl md:text-6xl font-black mb-4">
              <span className="bg-clip-text text-transparent bg-gradient-to-r from-blue-400 to-cyan-400">
                Live-Statistiken
              </span>
            </h2>
            <p className="text-slate-400 text-lg">Alles im Blick - in Echtzeit</p>
          </motion.div>

          <div className="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
            {[
              { icon: TrendingUp, title: "Live-Umsatzanzeige", desc: "Während des Events verfolgen" },
              { icon: BarChart3, title: "Bestellstatistik", desc: "Pro Tisch & Zeitraum" },
              { icon: Clock, title: "Spitzenzeiten-Analyse", desc: "Für Personalplanung" },
              { icon: Calculator, title: "Tagesabschluss", desc: "Mit einem Klick" },
              { icon: Download, title: "Export-Funktion", desc: "Für die Buchhaltung" }
            ].map((feature, index) => (
              <motion.div
                key={index}
                initial={{ opacity: 0, y: 30 }}
                whileInView={{ opacity: 1, y: 0 }}
                transition={{ duration: 0.5, delay: index * 0.1 }}
                className="bg-slate-800/50 backdrop-blur-md p-6 rounded-2xl border border-blue-500/20 hover:border-blue-400/40 transition-all"
              >
                <feature.icon className="w-10 h-10 text-blue-400 mb-4" />
                <h3 className="text-lg font-bold mb-2">{feature.title}</h3>
                <p className="text-slate-400 text-sm">{feature.desc}</p>
              </motion.div>
            ))}
          </div>
        </div>
      </section>

      {/* App Preview Section */}
      <section className="py-24 px-4 relative">
        <div className="absolute inset-0 bg-gradient-to-b from-slate-900 via-blue-950/20 to-slate-900"></div>
        
        <div className="max-w-6xl mx-auto relative z-10">
          <motion.div
            initial={{ opacity: 0, y: 50 }}
            whileInView={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.6 }}
            className="text-center mb-16"
          >
            <h2 className="text-4xl md:text-6xl font-black mb-4">
              <span className="bg-clip-text text-transparent bg-gradient-to-r from-blue-400 to-cyan-400">
                Die App in Aktion
              </span>
            </h2>
            <p className="text-slate-400 text-lg">So sieht die Bestell-Bar App wirklich aus</p>
          </motion.div>

          <div className="grid md:grid-cols-2 lg:grid-cols-4 gap-8">
            {/* QR Scan */}
            <motion.div
              initial={{ opacity: 0, scale: 0.8 }}
              whileInView={{ opacity: 1, scale: 1 }}
              transition={{ duration: 0.5, delay: 0.1 }}
              className="relative group"
            >
              <div className="bg-slate-800/60 backdrop-blur-md p-6 rounded-2xl border border-blue-500/20 hover:border-blue-400/40 transition-all duration-300">
                <div className="relative">
                  <img 
                    src="/qrscann.png" 
                    alt="QR-Code Scan" 
                    className="w-full h-64 object-cover rounded-lg border border-slate-600/50 shadow-xl group-hover:scale-105 transition-transform duration-300 cursor-pointer"
                    onClick={() => openImageModal("/qrscann.png")}
                  />
                  <div className="absolute inset-0 bg-gradient-to-t from-slate-900/50 to-transparent rounded-lg"></div>
                </div>
                <h3 className="text-lg font-bold text-blue-400 mt-4 mb-2">QR-Code Scannen</h3>
                <p className="text-slate-400 text-sm">Gäste scannen einfach den Code am Tisch</p>
              </div>
            </motion.div>

            {/* Bestellübersicht */}
            <motion.div
              initial={{ opacity: 0, scale: 0.8 }}
              whileInView={{ opacity: 1, scale: 1 }}
              transition={{ duration: 0.5, delay: 0.2 }}
              className="relative group"
            >
              <div className="bg-slate-800/60 backdrop-blur-md p-6 rounded-2xl border border-blue-500/20 hover:border-blue-400/40 transition-all duration-300">
                <div className="relative">
                  <img 
                    src="/bar.png" 
                    alt="Bestellübersicht" 
                    className="w-full h-64 object-cover rounded-lg border border-slate-600/50 shadow-xl group-hover:scale-105 transition-transform duration-300 cursor-pointer"
                    onClick={() => openImageModal("/bar.png")}
                  />
                  <div className="absolute inset-0 bg-gradient-to-t from-slate-900/50 to-transparent rounded-lg"></div>
                </div>
                <h3 className="text-lg font-bold text-blue-400 mt-4 mb-2">Bestellübersicht</h3>
                <p className="text-slate-400 text-sm">Klare Getränkeauswahl für Gäste</p>
              </div>
            </motion.div>

            {/* Kellner-App */}
            <motion.div
              initial={{ opacity: 0, scale: 0.8 }}
              whileInView={{ opacity: 1, scale: 1 }}
              transition={{ duration: 0.5, delay: 0.3 }}
              className="relative group"
            >
              <div className="bg-slate-800/60 backdrop-blur-md p-6 rounded-2xl border border-blue-500/20 hover:border-blue-400/40 transition-all duration-300">
                <div className="relative">
                  <img 
                    src="/kellner.png" 
                    alt="Kellner-App" 
                    className="w-full h-64 object-cover rounded-lg border border-slate-600/50 shadow-xl group-hover:scale-105 transition-transform duration-300 cursor-pointer"
                    onClick={() => openImageModal("/kellner.png")}
                  />
                  <div className="absolute inset-0 bg-gradient-to-t from-slate-900/50 to-transparent rounded-lg"></div>
                </div>
                <h3 className="text-lg font-bold text-blue-400 mt-4 mb-2">Kellner-Ansicht</h3>
                <p className="text-slate-400 text-sm">Zugewiesene Tische auf einen Blick</p>
              </div>
            </motion.div>

            {/* Theke-Vorbereitung */}
            <motion.div
              initial={{ opacity: 0, scale: 0.8 }}
              whileInView={{ opacity: 1, scale: 1 }}
              transition={{ duration: 0.5, delay: 0.4 }}
              className="relative group"
            >
              <div className="bg-slate-800/60 backdrop-blur-md p-6 rounded-2xl border border-blue-500/20 hover:border-blue-400/40 transition-all duration-300">
                <div className="relative">
                  <img 
                    src="/vorbereitung.png" 
                    alt="Theke-Vorbereitung" 
                    className="w-full h-64 object-cover rounded-lg border border-slate-600/50 shadow-xl group-hover:scale-105 transition-transform duration-300 cursor-pointer"
                    onClick={() => openImageModal("/vorbereitung.png")}
                  />
                  <div className="absolute inset-0 bg-gradient-to-t from-slate-900/50 to-transparent rounded-lg"></div>
                </div>
                <h3 className="text-lg font-bold text-blue-400 mt-4 mb-2">Administration</h3>
                <p className="text-slate-400 text-sm">Selbstverwaltung von Getränken und Preisen</p>
              </div>
            </motion.div>
          </div>
        </div>
      </section>

      {/* What the App Does NOT */}
      <section className="py-24 px-4">
        <div className="max-w-4xl mx-auto">
          <motion.div
            initial={{ opacity: 0, y: 50 }}
            whileInView={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.6 }}
            className="text-center mb-16"
          >
            <h2 className="text-4xl md:text-5xl font-black mb-4">
              <span className="text-slate-400">
                Was wir <span className="text-red-400">NICHT</span> machen
              </span>
            </h2>
          </motion.div>

          <div className="grid md:grid-cols-2 gap-4">
            {[
              "Keine Zahlungsabwicklung",
              "Keine Gästeregistrierung nötig",
              "Nicht für klassische Gastronomie",
              "Keine Tischreservierungen"
            ].map((item, index) => (
              <motion.div
                key={index}
                initial={{ opacity: 0, x: -30 }}
                whileInView={{ opacity: 1, x: 0 }}
                transition={{ duration: 0.4, delay: index * 0.1 }}
                className="bg-slate-800/50 p-5 rounded-xl border border-slate-700/50 flex items-center gap-4"
              >
                <XCircle className="w-6 h-6 text-slate-500 flex-shrink-0" />
                <p className="text-slate-400">{item}</p>
              </motion.div>
            ))}
          </div>
        </div>
      </section>

      {/* Use Cases Section without Video */}
      <section className="py-24 px-4 relative">
        <div className="absolute inset-0 bg-gradient-to-b from-slate-900 via-amber-950/20 to-slate-900"></div>
        
        <div className="max-w-6xl mx-auto relative z-10">
          <motion.div
            initial={{ opacity: 0, y: 50 }}
            whileInView={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.6 }}
            className="text-center mb-16"
          >
            <h2 className="text-4xl md:text-6xl font-black mb-4">
              <span className="bg-clip-text text-transparent bg-gradient-to-r from-amber-400 to-red-400">
                Perfekt für
              </span>
            </h2>
          </motion.div>

          <div className="grid md:grid-cols-2 lg:grid-cols-4 gap-6">
            {[
              { icon: PartyPopper, title: "Karneval", color: "from-amber-500 to-orange-500" },
              { icon: Music, title: "Festivals", color: "from-purple-500 to-pink-500" },
              { icon: Building, title: "Firmenfeiern", color: "from-blue-500 to-cyan-500" },
              { icon: Users2, title: "Vereinsfeste", color: "from-emerald-500 to-teal-500" }
            ].map((useCase, index) => (
              <motion.div
                key={index}
                initial={{ opacity: 0, scale: 0.8 }}
                whileInView={{ opacity: 1, scale: 1 }}
                transition={{ duration: 0.5, delay: index * 0.1 }}
                className="bg-slate-800/60 backdrop-blur-md p-8 rounded-2xl border border-slate-700/50 hover:border-white/20 transition-all text-center group"
              >
                <div className={`w-16 h-16 bg-gradient-to-r ${useCase.color} rounded-2xl flex items-center justify-center mx-auto mb-4 group-hover:scale-110 transition-transform`}>
                  <useCase.icon className="w-8 h-8 text-white" />
                </div>
                <h3 className="text-xl font-bold">{useCase.title}</h3>
              </motion.div>
            ))}
          </div>
        </div>
      </section>

      {/* Pricing */}
      <section className="py-24 px-4">
        <div className="max-w-4xl mx-auto">
          <motion.div
            initial={{ opacity: 0, y: 50 }}
            whileInView={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.6 }}
            className="text-center mb-16"
          >
            <h2 className="text-4xl md:text-6xl font-black mb-4">
              <span className="bg-clip-text text-transparent bg-gradient-to-r from-emerald-400 to-teal-400">
                Faire Preise
              </span>
            </h2>
          </motion.div>

          <motion.div
            initial={{ opacity: 0, scale: 0.9 }}
            whileInView={{ opacity: 1, scale: 1 }}
            transition={{ duration: 0.6 }}
            className="bg-gradient-to-br from-slate-800 to-slate-900 backdrop-blur-md p-12 rounded-3xl border border-emerald-500/20 text-center"
          >
            <div className="mb-8">
              <p className="text-emerald-400 font-bold text-xl mb-2">Ab</p>
              <div className="text-6xl font-black text-white mb-2">
                1.5<span className="text-emerald-400">%</span>
              </div>
              <p className="text-slate-400">des durch die App generierten Umsatzes</p>
            </div>

            <div className="text-5xl font-black text-amber-400 mb-2">
              <CountUp end={100} suffix="€" duration={2} start={350} />
            </div>
            <p className="text-slate-400 mb-10">Mindestgebühr pro Event</p>

            <div className="flex flex-wrap justify-center gap-6 text-slate-300">
              {["Keine Fixkosten", "Nur bei Erfolg zahlen", "Transparent"].map((item, i) => (
                <div key={i} className="flex items-center gap-2">
                  <CheckCircle2 className="w-5 h-5 text-emerald-400" />
                  <span>{item}</span>
                </div>
              ))}
            </div>
          </motion.div>
        </div>
      </section>

      {/* Contact Form Section - replaces CTA */}
      <section id="contact" className="py-24 px-4 relative overflow-hidden">
        <div className="absolute inset-0 bg-gradient-to-r from-amber-900/30 to-orange-900/30"></div>
        
        <div className="max-w-6xl mx-auto relative z-10">
          <motion.div
            initial={{ opacity: 0, y: 50 }}
            whileInView={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.6 }}
            className="text-center mb-16"
          >
            <h2 className="text-4xl md:text-6xl font-black mb-6">
              <span className="bg-clip-text text-transparent bg-gradient-to-r from-amber-400 to-orange-500">
                Bereit für die nächste Eventsaison?
              </span>
            </h2>
            <p className="text-xl mb-12 text-slate-300">
              Vereinbaren Sie jetzt eine kostenlose Demo
            </p>
          </motion.div>

          <div className="grid md:grid-cols-2 gap-12 max-w-6xl mx-auto">
            {/* Contact Form */}
            <motion.div
              initial={{ opacity: 0, x: -30 }}
              whileInView={{ opacity: 1, x: 0 }}
              transition={{ duration: 0.6 }}
            >
              <h3 className="text-2xl font-bold text-white mb-6">
                Demo-Anfrage
              </h3>
              <form 
                onSubmit={async (e) => {
                  e.preventDefault()
                  const formData = new FormData(e.currentTarget)
                  const name = formData.get('name') as string
                  const email = formData.get('email') as string
                  const company = formData.get('company') as string
                  const phone = formData.get('phone') as string
                  const message = formData.get('message') as string
                  
                  if (!name || !email || !message) {
                    alert('Bitte füllen Sie alle Pflichtfelder aus.')
                    return
                  }
                  
                  try {
                    const response = await fetch('/api/contact', {
                      method: 'POST',
                      headers: { 'Content-Type': 'application/json' },
                      body: JSON.stringify({ name, email, company, phone, message })
                    })
                    
                    if (response.ok) {
                      alert('Anfrage erfolgreich gesendet! Ich melde mich bald bei Ihnen.')
                      e.currentTarget.reset()
                    } else {
                      alert('Fehler beim Senden. Bitte versuchen Sie es später.')
                    }
                  } catch (error) {
                    alert('Fehler beim Senden. Bitte versuchen Sie es später.')
                  }
                }}
                className="space-y-6"
              >
                <div>
                  <label htmlFor="name" className="block text-sm font-semibold text-white mb-2">
                    Name *
                  </label>
                  <input
                    type="text"
                    id="name"
                    name="name"
                    required
                    className="w-full px-4 py-3 bg-slate-800/50 border border-slate-700 rounded-lg focus:border-amber-500 focus:outline-none transition-colors text-white placeholder-slate-500"
                    placeholder="Vorname Nachname"
                  />
                </div>

                <div>
                  <label htmlFor="email" className="block text-sm font-semibold text-white mb-2">
                    E-Mail *
                  </label>
                  <input
                    type="email"
                    id="email"
                    name="email"
                    required
                    className="w-full px-4 py-3 bg-slate-800/50 border border-slate-700 rounded-lg focus:border-amber-500 focus:outline-none transition-colors text-white placeholder-slate-500"
                    placeholder="beispiel@email.de"
                  />
                </div>

                <div>
                  <label htmlFor="company" className="block text-sm font-semibold text-white mb-2">
                    Unternehmen/Event
                  </label>
                  <input
                    type="text"
                    id="company"
                    name="company"
                    className="w-full px-4 py-3 bg-slate-800/50 border border-slate-700 rounded-lg focus:border-amber-500 focus:outline-none transition-colors text-white placeholder-slate-500"
                    placeholder="Name des Unternehmens oder Events"
                  />
                </div>

                <div>
                  <label htmlFor="phone" className="block text-sm font-semibold text-white mb-2">
                    Telefon
                  </label>
                  <input
                    type="tel"
                    id="phone"
                    name="phone"
                    className="w-full px-4 py-3 bg-slate-800/50 border border-slate-700 rounded-lg focus:border-amber-500 focus:outline-none transition-colors text-white placeholder-slate-500"
                    placeholder="Telefonnummer"
                  />
                </div>

                <div>
                  <label htmlFor="message" className="block text-sm font-semibold text-white mb-2">
                    Nachricht *
                  </label>
                  <textarea
                    id="message"
                    name="message"
                    required
                    rows={5}
                    className="w-full px-4 py-3 bg-slate-800/50 border border-slate-700 rounded-lg focus:border-amber-500 focus:outline-none transition-colors resize-none text-white placeholder-slate-500"
                    placeholder="Interesse an Demo, Event-Typ, Anzahl Gäste, gewünschter Termin..."
                  />
                </div>

                <button
                  type="submit"
                  className="w-full bg-gradient-to-r from-amber-500 to-orange-600 text-white px-8 py-4 rounded-lg font-semibold text-lg hover:shadow-2xl hover:shadow-amber-500/30 transition-all duration-300 flex items-center justify-center gap-2"
                >
                  Demo anfordern
                  <ArrowRight className="w-5 h-5" />
                </button>
              </form>
            </motion.div>

            {/* Contact Info */}
            <motion.div
              initial={{ opacity: 0, x: 30 }}
              whileInView={{ opacity: 1, x: 0 }}
              transition={{ duration: 0.6 }}
              className="space-y-8"
            >
              <div>
                <h3 className="text-2xl font-bold text-white mb-6">
                  Direktkontakt
                </h3>
                <div className="space-y-6">
                  <div className="flex items-start gap-4">
                    <div className="bg-amber-500/20 p-3 rounded-lg">
                      <Mail className="w-6 h-6 text-amber-400" />
                    </div>
                    <div>
                      <h4 className="font-semibold text-white mb-1">E-Mail</h4>
                      <a 
                        href="mailto:kontakt@praesenzwert.de" 
                        className="text-slate-400 hover:text-amber-400 transition-colors underline"
                      >
                        kontakt@praesenzwert.de
                      </a>
                    </div>
                  </div>

                  <div className="flex items-start gap-4">
                    <div className="bg-amber-500/20 p-3 rounded-lg">
                      <Phone className="w-6 h-6 text-amber-400" />
                    </div>
                    <div>
                      <h4 className="font-semibold text-white mb-1">Telefon</h4>
                      <a 
                        href="tel:+491717460398" 
                        className="text-slate-400 hover:text-amber-400 transition-colors underline"
                      >
                        0171 746 03 98
                      </a>
                    </div>
                  </div>

                  <div className="flex items-start gap-4">
                    <div className="bg-green-500/20 p-3 rounded-lg">
                      <MessageCircle className="w-6 h-6 text-green-400" />
                    </div>
                    <div>
                      <h4 className="font-semibold text-white mb-1">WhatsApp</h4>
                      <a 
                        href="https://wa.me/491717460398" 
                        target="_blank" 
                        rel="noopener noreferrer"
                        className="text-slate-400 hover:text-green-400 transition-colors underline"
                      >
                        Direkt auf WhatsApp schreiben
                      </a>
                    </div>
                  </div>
                </div>
              </div>

              <div className="bg-gradient-to-br from-amber-950/30 to-slate-800/50 p-8 rounded-2xl border border-amber-500/20">
                <h4 className="font-bold text-white mb-4 text-xl">
                  Kostenlose Demo
                </h4>
                <p className="text-slate-400 leading-relaxed mb-4">
                  Ich zeige Ihnen live, wie das Bestell-Bar System funktioniert. 
                  Keine Verpflichtung, unverbindliche und persönliche Beratung.
                </p>
                <ul className="space-y-2 text-slate-400">
                  <li className="flex items-center gap-2">
                    <div className="w-2 h-2 bg-amber-400 rounded-full"></div>
                    Live-Demonstration
                  </li>
                  <li className="flex items-center gap-2">
                    <div className="w-2 h-2 bg-amber-400 rounded-full"></div>
                    Individuelle Beratung
                  </li>
                  <li className="flex items-center gap-2">
                    <div className="w-2 h-2 bg-amber-400 rounded-full"></div>
                    Transparente Preisinformation
                  </li>
                </ul>
              </div>
            </motion.div>
          </div>
        </div>
      </section>

      {/* Technical Highlights */}
      <section className="py-16 px-4 bg-slate-900/50">
        <div className="max-w-6xl mx-auto">
          <div className="flex flex-wrap justify-center gap-4">
            {[
              "Next.js & Firebase", "Mobil-optimiert", "Echtzeit-Sync", "44+ Tische", "Verschlüsselt", "Kein Server nötig"
            ].map((item, index) => (
              <motion.div
                key={index}
                initial={{ opacity: 0 }}
                whileInView={{ opacity: 1 }}
                transition={{ delay: index * 0.05 }}
                className="bg-slate-800/50 px-4 py-2 rounded-full text-sm text-slate-400 border border-slate-700/50"
              >
                {item}
              </motion.div>
            ))}
          </div>
        </div>
      </section>

      {/* Footer */}
      <footer className="py-12 px-4 bg-slate-950 text-center border-t border-slate-800">
        <div className="max-w-4xl mx-auto">
          <p className="text-slate-500 mb-4">
            © 2026 PräsenzWert — Bestell-Bar
          </p>
          <div className="flex justify-center gap-6 text-sm text-slate-600 mb-4">
            <button
              onClick={() => openModal('impressum')}
              className="hover:text-white transition-colors"
            >
              Impressum
            </button>
            <button
              onClick={() => openModal('datenschutz')}
              className="hover:text-white transition-colors"
            >
              Datenschutz
            </button>
          </div>
          <p className="text-slate-600 text-xs mb-2">
            Video: <a href="https://www.pexels.com/de-de/video/hande-bar-getranke-freunde-5847855/" target="_blank" rel="noopener noreferrer" className="hover:text-amber-400 transition-colors underline">Pexels</a> | Lizenz: Pexels License
          </p>
        </div>
      </footer>

      {/* Image Modal */}
      <AnimatePresence>
        {imageModal && (
          <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
            <motion.div
              initial={{ opacity: 0 }}
              animate={{ opacity: 1 }}
              exit={{ opacity: 0 }}
              className="absolute inset-0 bg-black/90 backdrop-blur-sm"
              onClick={() => setImageModal(null)}
            />
            
            <motion.div
              initial={{ opacity: 0, scale: 0.8, y: 50 }}
              animate={{ opacity: 1, scale: 1, y: 0 }}
              exit={{ opacity: 0, scale: 0.8, y: 50 }}
              transition={{ type: "spring", stiffness: 300, damping: 30 }}
              className="relative bg-slate-900 rounded-2xl p-8 max-w-6xl max-h-[90vh] w-full shadow-2xl border border-slate-700"
            >
              <button
                onClick={() => setImageModal(null)}
                className="absolute top-4 right-4 bg-slate-800 hover:bg-slate-700 text-white rounded-full p-2 transition-colors z-10"
              >
                <X size={20} />
              </button>
              
              <div className="relative w-full h-full flex items-center justify-center">
                <img 
                  src={imageModal} 
                  alt="App Screenshot" 
                  className="max-w-full max-h-[70vh] object-contain rounded-lg"
                />
              </div>
            </motion.div>
          </div>
        )}
      </AnimatePresence>

      {/* Legal Modal */}
      <AnimatePresence>
        {modalOpen && (
          <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
            <motion.div
              initial={{ opacity: 0 }}
              animate={{ opacity: 1 }}
              exit={{ opacity: 0 }}
              className="absolute inset-0 bg-black/70 backdrop-blur-sm"
              onClick={() => setModalOpen(false)}
            />
            
            <motion.div
              initial={{ opacity: 0, scale: 0.95, y: 20 }}
              animate={{ opacity: 1, scale: 1, y: 0 }}
              exit={{ opacity: 0, scale: 0.95, y: 20 }}
              className="relative bg-slate-900 rounded-2xl shadow-2xl max-w-3xl w-full max-h-[85vh] overflow-hidden border border-slate-700"
            >
              <div className="bg-gradient-to-r from-amber-600 to-orange-600 text-white px-6 py-4 flex items-center justify-between">
                <h2 className="text-2xl font-bold">
                  {modalType === 'impressum' ? 'Impressum' : 'Datenschutzerklärung'}
                </h2>
                <button
                  onClick={() => setModalOpen(false)}
                  className="p-2 hover:bg-white/20 rounded-lg transition-colors"
                >
                  <X size={24} />
                </button>
              </div>
              
              <div className="p-6 overflow-y-auto max-h-[calc(85vh-80px)] text-slate-300">
                {modalType === 'impressum' ? (
                  <div className="space-y-6">
                    <section>
                      <h3 className="text-xl font-bold text-amber-400 mb-3">Angaben gemäß § 5 TMG</h3>
                      <div className="bg-slate-800 p-4 rounded-lg">
                        <p className="mb-1"><strong>PräsenzWert – Dennis Heidenreich</strong></p>
                        <p className="mb-1">Dennis Heidenreich</p>
                        <p className="mb-1">Josef-Martin-Weg 4</p>
                        <p>53501 Grafschaft, Deutschland</p>
                      </div>
                    </section>
                    <section>
                      <h3 className="text-xl font-bold text-amber-400 mb-3">Kontakt</h3>
                      <div className="bg-slate-800 p-4 rounded-lg">
                        <p className="mb-1"><strong>Telefon:</strong> <a href="tel:+491717460398" className="text-amber-400 hover:underline">+49 171 7460398</a></p>
                        <p><strong>E-Mail:</strong> <a href="mailto:kontakt@praesenzwert.de" className="text-amber-400 hover:underline">kontakt@praesenzwert.de</a></p>
                      </div>
                    </section>
                    <section>
                      <h3 className="text-xl font-bold text-amber-400 mb-3">Rechtsform</h3>
                      <div className="bg-slate-800 p-4 rounded-lg">
                        <p>Einzelunternehmen</p>
                      </div>
                    </section>
                    <section>
                      <h3 className="text-xl font-bold text-amber-400 mb-3">Umsatzsteuer</h3>
                      <div className="bg-slate-800 p-4 rounded-lg">
                        <p>Gemäß § 19 UStG wird keine Umsatzsteuer berechnet (Kleinunternehmerregelung).</p>
                      </div>
                    </section>
                  </div>
                ) : (
                  <div className="space-y-6">
                    <section>
                      <h3 className="text-xl font-bold text-amber-400 mb-3">Datenschutz auf einen Blick</h3>
                      <p className="mb-4">Die folgenden Hinweise geben einen Überblick darüber, was mit Ihren personenbezogenen Daten passiert.</p>
                      <h4 className="text-lg font-semibold text-amber-400 mb-2">Verantwortliche Stelle</h4>
                      <div className="bg-slate-800 p-4 rounded-lg">
                        <p><strong>PräsenzWert – Dennis Heidenreich</strong><br />
                        Josef-Martin-Weg 4, 53501 Grafschaft<br />
                        E-Mail: <a href="mailto:kontakt@praesenzwert.de" className="text-amber-400 hover:underline">kontakt@praesenzwert.de</a></p>
                      </div>
                    </section>
                    <section>
                      <h3 className="text-xl font-bold text-amber-400 mb-3">Hosting</h3>
                      <div className="bg-slate-800 p-4 rounded-lg">
                        <p>Diese Website wird DSGVO-konform in der EU gehostet.</p>
                      </div>
                    </section>
                    <section>
                      <h3 className="text-xl font-bold text-amber-400 mb-3">SSL-Verschlüsselung</h3>
                      <p>Diese Seite nutzt SSL/TLS-Verschlüsselung für sichere Datenübertragung.</p>
                    </section>
                  </div>
                )}
                <div className="mt-8 pt-6 border-t border-slate-700">
                  <p className="text-slate-500 text-xs">Stand: Januar 2026</p>
                </div>
              </div>
            </motion.div>
          </div>
        )}
      </AnimatePresence>
    </div>
  )
}
