// Mobile Praxis – kleine Helfer der Seite. Kein Tracking, keine Cookies, nichts von Dritten.
(() => {
  const ruhig = matchMedia('(prefers-reduced-motion: reduce)').matches
  document.getElementById('jahr').textContent = String(new Date().getFullYear())

  // Kopf bekommt beim Scrollen eine Fläche.
  const kopf = document.getElementById('kopf')
  const kopfSetzen = () => kopf.classList.toggle('kopf--fest', scrollY > 12)
  addEventListener('scroll', kopfSetzen, { passive: true }); kopfSetzen()

  // Ein Tag: der sichtbare Schritt bestimmt das Bild auf der Bühne.
  const buehne = document.getElementById('buehne')
  const schritte = [...document.querySelectorAll('.schritt')]
  const zeige = (el) => {
    schritte.forEach((s) => s.classList.toggle('schritt--aktiv', s === el))
    const neu = `img/${el.dataset.bild}.jpg`
    if (!buehne || buehne.getAttribute('src') === neu) return
    if (ruhig) { buehne.src = neu; return }
    buehne.classList.add('wechsel')
    setTimeout(() => { buehne.src = neu; buehne.onload = () => buehne.classList.remove('wechsel') }, 200)
  }
  const beobachter = new IntersectionObserver((e) => {
    e.filter((x) => x.isIntersecting).forEach((x) => zeige(x.target))
  }, { rootMargin: '-45% 0px -45% 0px' })
  schritte.forEach((s) => beobachter.observe(s))
  if (schritte[0]) schritte[0].classList.add('schritt--aktiv')
  // Bilder der Bühne vorladen, damit der Wechsel nicht flackert.
  schritte.forEach((s) => { const i = new Image(); i.src = `img/${s.dataset.bild}.jpg` })

  // Arbeitsweisen: drei Reiter, ein Bild.
  const reiter = [...document.querySelectorAll('.reiter [role=tab]')]
  const awBild = document.querySelector('#aw-bild img')
  const awText = document.getElementById('aw-text')
  reiter.forEach((r, i) => {
    r.addEventListener('click', () => {
      reiter.forEach((x) => x.setAttribute('aria-selected', String(x === r)))
      awBild.src = `img/${r.dataset.bild}.jpg`
      awBild.alt = `Die Arbeitsweise ${r.textContent}`
      awText.textContent = r.dataset.text
    })
    r.addEventListener('keydown', (e) => {
      const ziel = e.key === 'ArrowRight' ? reiter[(i + 1) % reiter.length] : e.key === 'ArrowLeft' ? reiter[(i + reiter.length - 1) % reiter.length] : null
      if (ziel) { ziel.focus(); ziel.click() }
    })
  })

  // Band: Inhalt verdoppeln, damit die Laufschrift nahtlos ist.
  const band = document.querySelector('.band ul')
  if (band) band.innerHTML += band.innerHTML.replace(/<li>/g, '<li aria-hidden="true">')

  // Erscheinen beim Scrollen.
  if (!ruhig) {
    const teile = document.querySelectorAll('.abschnitt__kopf, .kachel, .saeule, .stufe, .demo__karte, details')
    teile.forEach((t) => t.classList.add('erscheint'))
    const sicht = new IntersectionObserver((e) => e.forEach((x) => {
      if (x.isIntersecting) { x.target.classList.add('erscheint--da'); sicht.unobserve(x.target) }
    }), { rootMargin: '0px 0px -8% 0px' })
    teile.forEach((t) => sicht.observe(t))
  }

  // Demo: Adresse, Fassung, Größe und Prüfsumme stehen in demo.json – ein neuer Bau ändert nur diese Datei.
  const link = document.getElementById('demo-link')
  const meta = document.getElementById('demo-meta')
  fetch('demo.json', { cache: 'no-cache' }).then((r) => { if (!r.ok) throw new Error(String(r.status)); return r.json() }).then((d) => {
    if (!d || !d.url) throw new Error('ohne Adresse')
    link.href = d.url
    link.setAttribute('download', '')
    meta.innerHTML = `Fassung ${d.fassung} · ${d.groesse}<br>SHA-256 ${d.sha256}`
  }).catch(() => {
    link.setAttribute('aria-disabled', 'true')
    meta.textContent = 'Die Demo wird gerade neu bereitgestellt – bitte später noch einmal.'
  })
})()
