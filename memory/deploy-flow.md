---
name: deploy-flow
description: How the präsenzwert site is built and deployed live
metadata:
  type: project
---

präsenzwert (c:\Users\Heidenreich\CascadeProjects\prasenswert) is a Next.js static export (`output: 'export'`, builds to `out/`). The hoster serves from the **repo root**, not `out/`.

Deploy = build, then sync `out/*` into the repo root (replace root `_next/` cleanly to drop stale chunks, copy all top-level files), then `git commit` + `git push origin main`. Pushing to `main` on github.com/Mencode-Maennercode/praesenswert.git makes it live at https://www.praesenzwert.de/.

Both `out/` and the synced copies in root are committed to git.
