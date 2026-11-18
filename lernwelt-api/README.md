# lernwelt-api – KI-Proxy der Lernwelt-App

PHP 8.2 ohne Composer. Live unter `https://praesenzwert.de/lernwelt-api/`.
Verträge: `lernwelt/src/types/api.ts`. KI: **Mistral AI** (Paris), Schlüssel in `_data/mistral.key`
(oder `MISTRAL_API_KEY`), nie im Repo.

| Endpunkt | Zweck | Budget je Nutzer/Tag |
|---|---|---|
| `analyze.php` | Lernprofil-Auswertung | 4 |
| `handwriting.php` | PNG + Sollwort → Erkennung, Fehler, Positionen | 30 |
| `explain.php` | Erklärung zu Fehler, Antwort-Cache per Hash | 20 (Cache-Treffer frei) |
| `tts.php` | Voxtral-TTS (deutsche Stimme „Heidi“), Datei-Cache | 60 (Cache-Treffer frei) |
| `admin-ingest.php` | Buchfotos → Themen/Skills (nur Admin) | 30 |

- Auth: `Authorization: Bearer <Firebase-ID-Token>`, RS256 per `openssl_verify` gegen die
  securetoken-Zertifikate (gecacht nach `max-age`), `aud`/`iss` = `seo-automation-6398`, `exp`.
- CORS nur `https://lernwelt-app.web.app` und `http://localhost:5173`.
- 429 `{error, retryAfter}` bei leerem Nutzer-/Tageskontingent (12/min, global 500/Tag) oder wenn
  alle Modelle der Kette gebremst sind. 5xx → Client-Fallback.
- Handschrift: Das Modell liest **blind** (ohne Sollwort), der Server richtet Soll/Ist per
  Levenshtein aus. Mit Sollwort „erkennen“ kleine Modelle sonst das Sollwort, auch wenn nichts dasteht.
- Admin: UID in Firestore `config/admins.uids` (REST mit dem Token des Aufrufers) **und**
  `X-Admin-Key`. Der erste Admin-Aufruf ohne `_data/admin.hash` legt den Hash an (≥ 12 Zeichen);
  zurücksetzen = Datei löschen.
- `_data/`, `_*.php`, `_test/` sind per `.htaccess` gesperrt.

Tests: `MISTRAL_API_KEY=… bash _test/run.sh` (lokaler `php -S`, Test-Tokens aus `_test/mint.php`,
die nur unter `cli-server` akzeptiert werden).

## Tarif (gemessen 18.11.2026, Gratis-Tarif)

- `mistral-small`/`-medium` und `voxtral-mini-tts-3`: Limit **0 Anfragen/Minute** → sofort 429.
  `voxtral-mini-tts-latest` (v1) nimmt keine Stimmen an. **TTS läuft im Gratis-Tarif also nicht**,
  der Client nutzt `speechSynthesis`.
- Frei nutzbar: `ministral-3b/8b/14b` (14b auch mit Bild), je **2 Anfragen/Minute**.
- Die Modellketten stehen mit small/medium vorn: Nach Wechsel in den bezahlten Tarif werden sie
  ohne Codeänderung genutzt (bessere Handschrift-Erkennung, TTS).

## Rechtliches

- **Gemini scheidet aus** (Bedingungen vom 23.03.2026): nur Bezahl-Tarif für Apps mit EU-Nutzern
  und Verbot für Apps, die sich an unter 18-Jährige richten; gleiche Klausel bei Vertex AI
  (Service Specific Terms §20(d)).
- **Mistral** (Commercial Terms, 25.09.2026, §2.2(c)): keine personenbezogenen Daten von Kindern
  unter 13 bzw. dem Alter der digitalen Einwilligung als Customer Data, und Minderjährige nur mit
  „legally adequate consent from their parent or guardian“. Deshalb: Einwilligung der Eltern bei der
  Registrierung, keine Namen/IDs in Anfragen, nur Aggregate, Aufgabentexte und das Schriftbild
  eines einzelnen Wortes. Training ist im Mistral-Konto abgeschaltet.
