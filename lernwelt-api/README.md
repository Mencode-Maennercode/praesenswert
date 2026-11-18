# lernwelt-api – KI-Proxy der Lernwelt-App

PHP 8.2 ohne Composer. Live unter `https://praesenzwert.de/lernwelt-api/`.
Verträge: `lernwelt/src/types/api.ts`.

| Endpunkt | Zweck | Budget je Nutzer/Tag |
|---|---|---|
| `analyze.php` | Lernprofil-Auswertung | 4 |
| `handwriting.php` | PNG + Sollwort → Erkennung, Fehler, Positionen | 30 |
| `explain.php` | Erklärung zu Fehler, Antwort-Cache per Hash | 20 (Cache-Treffer frei) |
| `tts.php` | Gemini-TTS → `audio/wav`, Datei-Cache | 60 (Cache-Treffer frei) |
| `admin-ingest.php` | Buchfotos → Themen/Skills (nur Admin) | 30 |

- Auth: `Authorization: Bearer <Firebase-ID-Token>`, RS256 per `openssl_verify` gegen die
  securetoken-Zertifikate (gecacht nach `max-age`), `aud`/`iss` = `seo-automation-6398`, `exp`.
- CORS nur `https://lernwelt-app.web.app` und `http://localhost:5173`.
- 429 `{error, retryAfter}` bei leerem Nutzer-/Tageskontingent (12/min, global 500/Tag) oder
  wenn alle Modelle der Kette leer sind. 5xx → Client-Fallback.
- Gemini-Key: `GEMINI_API_KEY` → `_data/ai.key` → Key der Grillparty bzw. Fitness auf dem Webspace.
- TTS-Modell per `models.list` (1×/Tag gecacht), Flash vor Pro, neueste zuerst; Stimme `Leda` als Standard.
- Admin: UID in Firestore `config/admins.uids` (REST mit dem Token des Aufrufers) **und**
  `X-Admin-Key`. Der erste Admin-Aufruf ohne `_data/admin.hash` legt den Hash an (≥ 12 Zeichen);
  zurücksetzen = Datei löschen.
- `_data/`, `_*.php`, `_test/` sind per `.htaccess` gesperrt.

Tests: `GEMINI_API_KEY=… bash _test/run.sh` (lokaler `php -S`, Test-Tokens aus `_test/mint.php`,
die nur unter `cli-server` akzeptiert werden).

## Gemini-API-Bedingungen (geprüft 18.11.2026, Fassung „Effective March 23, 2026")

**Training:** Ja – für Nutzer in EWR, Schweiz und UK gelten die Datenregeln der Bezahlstufe auch
für das Gratis-Kontingent: *„If you're in the European Economic Area, Switzerland, or the United
Kingdom, the terms under 'How Google uses Your Data' in 'Paid Services' apply to all Services,
including … unpaid quota in the Gemini API"*. Prompts und Antworten werden also nicht zum Training
verwendet, nur befristet zur Missbrauchserkennung protokolliert.

**Aber zwei Bedingungen stehen dem Einsatz entgegen:**

1. *„You may use only Paid Services when making API Clients available to users in the European
   Economic Area, Switzerland, or the United Kingdom."* – eine App für Nutzer in Deutschland darf
   laut Bedingungen nicht über das Gratis-Kontingent laufen. → Abrechnung im Cloud-Projekt nötig.
2. *„You must be 18 years of age or older to use the APIs. You also will not use the Services as
   part of a … application … that is directed towards or is likely to be accessed by individuals
   under the age of 18."* – gilt unabhängig von der Kostenstufe und trifft eine Kinder-Lern-App direkt.
   Vor dem Livegang klären (z. B. Vertex AI mit eigenen Bedingungen, oder KI nur im Elternbereich).
