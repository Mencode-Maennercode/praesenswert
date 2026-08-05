<?php
/**
 * Alle Admin-Aktionen an einem Endpunkt.
 *
 * Die Prüfung des Passworts passiert ausschließlich hier auf dem Server.
 * Eine Prüfung im Browser wäre wertlos - das Passwort läge dann im JS-Bundle.
 */

declare(strict_types=1);
require __DIR__ . '/_lib.php';

cors();
requireMethod('POST');

$body = jsonBody();

$id = validEventId($body['eventId'] ?? '');
if ($id === '') {
    fail('Kein gültiger Party-Link.');
}

$event = loadEvent($id);

/* ------------------------------------------------------------ Authentifizierung */

$token = is_string($body['token'] ?? null) ? $body['token'] : '';

if ($token !== '') {
    if (!verifyAdminToken($token, $id)) {
        fail('Deine Sitzung ist abgelaufen. Bitte melde dich neu an.', 401);
    }
} else {
    rateLimit('admin', 10, 900);
    $password = is_string($body['password'] ?? null) ? $body['password'] : '';
    if ($password === '' || !password_verify($password, $event['adminHash'])) {
        // Absichtlich verzögert - macht Durchprobieren unattraktiv.
        usleep(400_000);
        fail('Falsches Passwort.', 401);
    }
    $token = makeAdminToken($id);
}

/* -------------------------------------------------------------------- Aktionen */

$action = is_string($body['action'] ?? null) ? $body['action'] : 'login';
$mailReport = null;

switch ($action) {
    case 'login':
        break;

    case 'updateMeta':
        $meta = is_array($body['meta'] ?? null) ? $body['meta'] : [];
        $event = withEventLock($id, function (array $e) use ($meta) {
            $e['meta'] = [
                'title' => clean($meta['title'] ?? '', MAX_TEXT),
                'host' => clean($meta['host'] ?? '', MAX_NAME),
                'datetime' => clean($meta['datetime'] ?? '', 32),
                'hostProvides' => clean($meta['hostProvides'] ?? '', MAX_TEXT),
                'location' => clean($meta['location'] ?? '', MAX_TEXT),
            ];
            return $e;
        });
        break;

    case 'addSuggestion':
        $value = clean($body['value'] ?? '', MAX_ITEM);
        if ($value === '') {
            fail('Der Vorschlag ist leer.');
        }
        $event = withEventLock($id, function (array $e) use ($value) {
            if (count($e['suggestions']) >= MAX_SUGGEST) {
                fail('Mehr als ' . MAX_SUGGEST . ' Vorschläge werden unübersichtlich.', 409);
            }
            foreach ($e['suggestions'] as $s) {
                if (mb_strtolower($s) === mb_strtolower($value)) {
                    return $e; // schon da, stillschweigend ignorieren
                }
            }
            $e['suggestions'][] = $value;
            return $e;
        });
        break;

    case 'removeSuggestion':
        $value = clean($body['value'] ?? '', MAX_ITEM);
        $event = withEventLock($id, function (array $e) use ($value) {
            $e['suggestions'] = array_values(array_filter(
                $e['suggestions'],
                fn($s) => $s !== $value,
            ));
            return $e;
        });
        break;

    case 'updateParticipant':
        // Der Gastgeber korrigiert einen fremden Eintrag - Tippfehler im Namen,
        // "Salat" zu "Kartoffelsalat" präzisieren, falsche E-Mail geraderücken.
        $pid   = clean($body['participantId'] ?? '', 40);
        $name  = clean($body['name'] ?? '', MAX_NAME);
        $email = cleanEmail($body['email'] ?? '');
        $item  = clean($body['item'] ?? '', MAX_ITEM);

        if ($name === '') {
            fail('Ohne Namen geht es nicht.');
        }
        if ($email === '') {
            fail('Das ist keine gültige E-Mail-Adresse.');
        }

        $event = withEventLock($id, function (array $e) use ($pid, $name, $email, $item) {
            $index = null;
            foreach ($e['participants'] as $i => $p) {
                if ($p['id'] === $pid) {
                    $index = $i;
                    break;
                }
            }
            if ($index === null) {
                fail('Diesen Eintrag gibt es nicht mehr.', 404);
            }

            // Zwei Einträge mit derselben Adresse würde join.php beim nächsten
            // Eintragen zu einem verschmelzen - also hier gar nicht erst zulassen.
            foreach ($e['participants'] as $i => $p) {
                if ($i !== $index && strcasecmp($p['email'], $email) === 0) {
                    fail('Diese E-Mail gehört schon zu einem anderen Eintrag.', 409);
                }
            }

            $old = $e['participants'][$index];

            // Nach der Auslosung steckt die Sache in fremden Rädern und in schon
            // verschickten Mails. Der Name lässt sich weiter korrigieren.
            if ($e['status'] === 'drawn' && $item !== ($old['item'] ?? '')) {
                fail('Die Auslosung ist schon gelaufen - die Sachen stecken in den Rädern. Nimm die Auslosung zurück, wenn du daran noch etwas ändern willst.', 409);
            }

            $e['participants'][$index]['name']  = $name;
            $e['participants'][$index]['email'] = $email;
            $e['participants'][$index]['item']  = $item;

            // Nur was öffentlich sichtbar ist, wird auch öffentlich vermerkt -
            // eine stille E-Mail-Korrektur geht die Liste nichts an.
            if ($name !== ($old['name'] ?? '') || $item !== ($old['item'] ?? '')) {
                $e['participants'][$index]['updatedAt'] = date('c');
                $e['participants'][$index]['editedBy'] = 'admin';
            }
            return $e;
        });
        break;

    case 'removeParticipant':
        $pid = clean($body['participantId'] ?? '', 40);
        $event = withEventLock($id, function (array $e) use ($pid) {
            $e['participants'] = array_values(array_filter(
                $e['participants'],
                fn($p) => $p['id'] !== $pid,
            ));
            return $e;
        });
        break;

    case 'removeItem':
        // Nimmt die Sache aus dem Verlosungstopf, der Mensch bleibt aber dabei
        // und bekommt weiterhin etwas zugelost.
        $pid = clean($body['participantId'] ?? '', 40);
        $event = withEventLock($id, function (array $e) use ($pid) {
            foreach ($e['participants'] as $i => $p) {
                if ($p['id'] === $pid) {
                    $e['participants'][$i]['item'] = '';
                }
            }
            return $e;
        });
        break;

    case 'setAiKey':
        // Der Schlüssel liegt serverseitig in _data/ und wird nie
        // zurückgegeben - auch nicht an den angemeldeten Admin.
        $value = trim(is_string($body['value'] ?? null) ? $body['value'] : '');
        // Google vergibt sowohl "AIza..."- als auch "AQ...."-Schlüssel, also
        // wird hier nur die Form geprüft, nicht ein bestimmter Anfang.
        if (!preg_match('/^[A-Za-z0-9_.\-]{20,200}$/', $value)) {
            fail('Das sieht nicht nach einem Google-AI-Schlüssel aus.');
        }
        storeAiKey($value);
        break;

    case 'clearAiKey':
        deleteAiKey();
        break;

    case 'draw':
        $event = withEventLock($id, fn(array $e) => drawAssignments($e));
        break;

    case 'reopen':
        // Neu auslosen: nur solange noch niemand gedreht hat.
        $event = withEventLock($id, function (array $e) {
            foreach ($e['participants'] as $p) {
                if (!empty($p['spun'])) {
                    fail('Es hat schon jemand gedreht - jetzt wäre eine neue Auslosung unfair.', 409);
                }
            }
            foreach ($e['participants'] as $i => $p) {
                $e['participants'][$i]['assignedItem'] = null;
                $e['participants'][$i]['wheelItems'] = null;
            }
            $e['status'] = 'open';
            $e['drawnAt'] = null;
            return $e;
        });
        break;

    case 'sendMails':
        if ($event['status'] !== 'drawn') {
            fail('Erst auslosen, dann verschicken.', 409);
        }
        $mailReport = sendWheelMails($event);
        break;

    default:
        fail('Unbekannte Aktion.');
}

$response = ['ok' => true, 'token' => $token, 'event' => adminView($event)];
if ($mailReport !== null) {
    $response['mail'] = $mailReport;
}
send($response);

/* ----------------------------------------------------------------- Mailversand */

/**
 * Verschickt die persönlichen Links per PHP mail().
 *
 * Bewusst nur als Zusatz gedacht: netcup-mail() an viele fremde Gmail-Adressen
 * landet erfahrungsgemäß teilweise im Spam. Der verlässliche Weg ist die
 * Link-Liste im Admin-Bereich.
 */
function sendWheelMails(array $event): array
{
    $sent = 0;
    $failed = 0;
    $host = $event['meta']['host'] !== '' ? $event['meta']['host'] : 'Der Gastgeber';
    $title = $event['meta']['title'] !== '' ? $event['meta']['title'] : 'Grillparty';

    // Absenderdomain aus dem tatsächlichen Host ableiten, damit der
    // From-Header zur sendenden Maschine passt (sonst greift SPF nicht).
    $domain = preg_replace('/^www\./', '', strtolower($_SERVER['HTTP_HOST'] ?? 'localhost'));
    $domain = preg_replace('/:\d+$/', '', (string) $domain);
    $from = 'noreply@' . $domain;

    foreach ($event['participants'] as $p) {
        if (empty($p['email']) || empty($p['token'])) {
            $failed++;
            continue;
        }

        $link = wheelLink($event['id'], $p['token']);
        $subject = '=?UTF-8?B?' . base64_encode('Dein Los für ' . $title) . '?=';

        $bodyText = "Hallo " . $p['name'] . ",\n\n";
        $bodyText .= "die Auslosung für \"" . $title . "\" ist gelaufen!\n\n";
        $bodyText .= "Was du mitbringst, entscheidet dein Glücksrad. Dreh es hier:\n";
        $bodyText .= $link . "\n\n";
        if ($event['meta']['datetime'] !== '') {
            $bodyText .= "Wann: " . formatGermanDate($event['meta']['datetime']) . "\n";
        }
        if ($event['meta']['location'] !== '') {
            $bodyText .= "Wo:   " . $event['meta']['location'] . "\n";
        }
        $bodyText .= "\nBis dann!\n" . $host . "\n\n";
        $bodyText .= "-- \nDer Link gilt nur für dich. Bitte nicht weiterleiten,\nsonst dreht jemand anderes dein Rad.\n";

        $headers  = "From: " . $title . " <" . $from . ">\r\n";
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $headers .= "Content-Transfer-Encoding: 8bit\r\n";
        $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";

        if (@mail($p['email'], $subject, $bodyText, $headers)) {
            $sent++;
        } else {
            $failed++;
        }
    }

    return ['sent' => $sent, 'failed' => $failed];
}

function formatGermanDate(string $iso): string
{
    $ts = strtotime($iso);
    if ($ts === false) {
        return $iso;
    }
    $days = ['Sonntag', 'Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag'];
    return $days[(int) date('w', $ts)] . ', ' . date('d.m.Y', $ts) . ' um ' . date('H:i', $ts) . ' Uhr';
}
