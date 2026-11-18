// Mobile Praxis – kleine Helfer der Seite. Kein Tracking, keine Cookies, nichts von Dritten.
(() => {
  const ruhig = matchMedia('(prefers-reduced-motion: reduce)').matches
  document.getElementById('jahr').textContent = String(new Date().getFullYear())

  // Kopf bekommt beim Scrollen eine Fläche.
  const kopf = document.getElementById('kopf')
  const kopfSetzen = () => kopf.classList.toggle('kopf--fest', scrollY > 12)
  addEventListener('scroll', kopfSetzen, { passive: true }); kopfSetzen()

  // Module: antippen schaltet um, die Zahl zählt mit.
  const module = [...document.querySelectorAll('.modul')]
  const zahl = document.getElementById('modul-zahl')
  const zaehle = () => { if (zahl) zahl.textContent = String(module.filter((m) => m.getAttribute('aria-pressed') === 'true').length) }
  module.forEach((m) => m.addEventListener('click', () => {
    m.setAttribute('aria-pressed', String(m.getAttribute('aria-pressed') !== 'true'))
    zaehle()
  }))
  zaehle()

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

  // Kalender und Unterschrift spielen ab, sobald sie zu sehen sind – und noch einmal, wenn man wiederkommt.
  const abspielen = (el, dauer) => {
    if (!el || ruhig) return
    let laeuft = false
    new IntersectionObserver((e) => e.forEach((x) => {
      if (!x.isIntersecting || laeuft) return
      laeuft = true
      el.classList.remove('laeuft'); void el.offsetWidth; el.classList.add('laeuft')
      setTimeout(() => { laeuft = false }, dauer)
    }), { threshold: 0.55 }).observe(el)
  }
  // Das Kalenderfenster startet klein auf dem linken Bildschirm und landet genau auf der Bildfläche des rechten:
  // beide Rechtecke werden an den Monitoren ausgemessen, nicht geschätzt.
  const tisch = document.getElementById('schreibtisch')
  const flaeche = (sel) => {
    const schirm = tisch.querySelector(sel)
    const t = tisch.getBoundingClientRect(), r = schirm.getBoundingClientRect()
    const p = parseFloat(getComputedStyle(schirm).paddingLeft) || 0
    return { l: r.left - t.left + p, t: r.top - t.top + p, b: r.width - 2 * p, h: r.height - 2 * p }
  }
  const vermesse = () => {
    if (!tisch) return
    const links = flaeche('.monitor--links .monitor__schirm')
    const rechts = flaeche('.monitor--rechts .monitor__schirm')
    const s = tisch.style
    s.setProperty('--s-l', `${links.l + links.b * 0.08}px`); s.setProperty('--s-t', `${links.t + links.h * 0.12}px`)
    s.setProperty('--s-b', `${links.b * 0.46}px`); s.setProperty('--s-h', `${links.h * 0.46}px`)
    s.setProperty('--z-l', `${rechts.l}px`); s.setProperty('--z-t', `${rechts.t}px`)
    s.setProperty('--z-b', `${rechts.b}px`); s.setProperty('--z-h', `${rechts.h}px`)
  }
  vermesse()
  addEventListener('load', vermesse)
  addEventListener('resize', () => { vermesse(); if (tisch?.classList.contains('laeuft')) { tisch.classList.remove('laeuft'); tisch.classList.add('fertig') } })
  if (tisch) tisch.addEventListener('animationend', () => { tisch.classList.remove('laeuft'); tisch.classList.add('fertig') })
  if (tisch) tisch.addEventListener('animationstart', () => tisch.classList.remove('fertig'))
  abspielen(tisch, 9000)
  abspielen(document.getElementById('signieren'), 9000)

  // Layouts: drei Reiter, ein Bild.
  const reiter = [...document.querySelectorAll('.reiter [role=tab]')]
  const awBild = document.querySelector('#aw-bild img')
  const awText = document.getElementById('aw-text')
  reiter.forEach((r, i) => {
    r.addEventListener('click', () => {
      reiter.forEach((x) => x.setAttribute('aria-selected', String(x === r)))
      awBild.src = `img/${r.dataset.bild}.jpg`
      awBild.alt = `Das Layout ${r.textContent}`
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
    const teile = document.querySelectorAll('.abschnitt__kopf, .grundstock, .module, .befund__bild, .anpassen figure, .saeule, .stufe, .demo__karte, details, .kalender__punkte > div')
    teile.forEach((t) => t.classList.add('erscheint'))
    const sicht = new IntersectionObserver((e) => e.forEach((x) => {
      if (x.isIntersecting) { x.target.classList.add('erscheint--da'); sicht.unobserve(x.target) }
    }), { rootMargin: '0px 0px -8% 0px' })
    teile.forEach((t) => sicht.observe(t))
  }

  // Demo: Adresse, Fassung und Größe stehen in demo.json – ein neuer Bau ändert nur diese Datei.
  const link = document.getElementById('demo-link')
  const meta = document.getElementById('demo-meta')
  fetch('demo.json', { cache: 'no-cache' }).then((r) => { if (!r.ok) throw new Error(String(r.status)); return r.json() }).then((d) => {
    if (!d || !d.url) throw new Error('ohne Adresse')
    link.href = d.url
    link.setAttribute('download', '')
    meta.textContent = `Fassung ${d.fassung} · ${d.groesse}`
  }).catch(() => {
    link.setAttribute('aria-disabled', 'true')
    meta.textContent = 'Die Demo wird gerade neu bereitgestellt – bitte später noch einmal.'
  })
})()
