import GradientGround from './motion/GradientGround'
import LenisProvider from './motion/LenisProvider'
import Header from './components/Header'
import HeroSection from './components/HeroSection'
import ServicesSection from './components/ServicesSection'
import BenefitsSection from './components/BenefitsSection'
import PortfolioSection from './components/PortfolioSection'
import ProductsSection from './components/ProductsSection'
import FaqSection from './components/FaqSection'
import DisclaimerSection from './components/DisclaimerSection'
import ContactSection from './components/ContactSection'
import Footer from './components/Footer'

export default function Home() {
  return (
    <>
      {/* Liegt fixiert hinter allem und wandert beim Scrollen den analogen
          Farbbogen der Marke entlang. Kein Abschnitt setzt einen eigenen
          Hintergrund - deshalb wirkt die Seite wie ein Guss. */}
      <GradientGround />
      <LenisProvider />

      <Header />

      <main className="relative">
        <HeroSection />
        <ServicesSection />
        <BenefitsSection />
        <PortfolioSection />
        <ProductsSection />
        <FaqSection />
        <DisclaimerSection />
        <ContactSection />
      </main>

      <Footer />
    </>
  )
}
