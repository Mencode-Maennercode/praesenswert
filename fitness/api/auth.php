<?php
/**
 * Konten: anlegen, anmelden, wiederherstellen.
 *
 *   register  Name + Passwort + bestätigter Haftungsausschluss
 *   login     Name + Passwort
 *   me        Token -> Profil (der Aufruf beim App-Start)
 *   recover   Name + Wiederherstellungscode -> neues Passwort
 *   password  altes + neues Passwort (angemeldet)
 *   abmelden  überall abmelden (erhöht tokenVersion)
 *
 * Es gibt keine E-Mail-Adressen in dieser App. Ein vergessenes Passwort
 * wäre damit das Ende des Kontos - deshalb der Wiederherstellungscode aus
 * sechs Wörtern, der bei der Registrierung genau einmal angezeigt wird.
 */

declare(strict_types=1);
require __DIR__ . '/_lib.php';
require __DIR__ . '/_words.php';

cors();
requireMethod('POST');

$body = jsonBody(8 * 1024);
$action = clean($body['action'] ?? '', 20);

match ($action) {
    'register' => register($body),
    'login' => login($body),
    'me' => me($body),
    'recover' => recover($body),
    'password' => changePassword($body),
    'abmelden' => logoutEverywhere($body),
    'loeschen' => loeschen($body),
    default => fail('Unbekannte Aktion.'),
};

/* ---------------------------------------------------------------- Anlegen */

function register(array $body): never
{
    /*
     * Honeypot. Ein unsichtbares Feld, das kein Mensch ausfüllt, weil er
     * es nicht sieht. Bots füllen alles aus. Die Antwort ist bewusst ein
     * gefälschter Erfolg - wer merkt, dass er erkannt wurde, baut um.
     */
    if (clean($body['website'] ?? '') !== '') {
        send(['ok' => true, 'token' => 'x', 'code' => str_repeat('x ', 6)]);
    }

    $name = clean($body['name'] ?? '', MAX_NAME);
    $pass = is_string($body['pass'] ?? null) ? $body['pass'] : '';

    if (!validName($name)) {
        fail('Name: 3 bis 24 Zeichen, Buchstaben und Zahlen.', 400, 'name-ungueltig');
    }
    if (mb_strlen($pass) < MIN_PASS || strlen($pass) > MAX_PASS) {
        fail('Passwort: mindestens 8 Zeichen.', 400, 'passwort-kurz');
    }
    if (($body['disclaimer'] ?? null) !== true) {
        fail('Bitte den Hinweis bestätigen.', 400, 'disclaimer');
    }

    // Einladungscode ist standardmässig aus - siehe inviteCode() in _lib.php.
    $invite = inviteCode();
    if ($invite !== '' && !hash_equals($invite, clean($body['invite'] ?? '', 60))) {
        fail('Einladungscode stimmt nicht.', 403, 'invite');
    }

    /*
     * Die Bremse greift bewusst erst HIER - nach allen Formprüfungen.
     * Sonst verbraucht ein Tippfehler im Passwort einen der wenigen
     * Versuche, und wer sich dreimal vertippt, kann sich eine Stunde lang
     * nicht mehr anmelden. Ungültige Anfragen kosten den Server nichts und
     * bringen einem Angreifer nichts.
     */
    rateLimit('register', 8, 3600);

    $code = makeRecoveryCode();

    /*
     * Alles, was über die Eindeutigkeit des Namens entscheidet, passiert
     * innerhalb dieser einen Sperre: prüfen, ID vergeben, Profil schreiben,
     * Index ergänzen. Zwei gleichzeitige Anmeldungen mit demselben Namen
     * können sich dadurch nicht überholen.
     */
    $uid = withLock('users-index', static function () use ($name, $pass, $code): string {
        $index = userIndex();
        $key = normalizeName($name);

        if (isset($index[$key])) {
            fail('Der Name ist schon vergeben.', 409, 'name-vergeben');
        }
        if (count($index) >= MAX_USERS) {
            fail('Die App ist voll.', 403, 'voll');
        }

        $uid = 'u_' . bin2hex(random_bytes(8));
        $jetzt = date('c');

        /*
         * Eigentümer wird, wer sich anmeldet, solange es keinen gibt.
         *
         * Nicht schlicht "das erste Konto": Wird das gelöscht, gäbe es
         * sonst nie wieder einen Eigentümer, und niemand könnte je einen
         * Schlüssel hinterlegen. So rückt beim nächsten Konto jemand nach.
         */
        $istErster = !existiertEigentuemer($index);

        saveUser($uid, [
            'id' => $uid,
            'name' => $name,
            'nameLower' => $key,
            'hash' => password_hash($pass, PASSWORD_DEFAULT),
            'recoveryHash' => password_hash($code, PASSWORD_DEFAULT),
            'owner' => $istErster,
            'tokenVersion' => 1,
            'createdAt' => $jetzt,
            'disclaimerAt' => $jetzt,
            // Erst nach dem Onboarding gefüllt - daran erkennt me(),
            // wohin die App den Nutzer schicken muss.
            'profile' => null,
            'derived' => null,
            'prefs' => [
                'dayStartHour' => 4,       // Das Bier um 1 Uhr gehört zu gestern
                'reminders' => true,
                'reminderHours' => [9, 13, 19],
                'weighDay' => 5,           // Freitag
                'feedVisibility' => 'prozent',
            ],
            'push' => [],
            'streak' => ['days' => 0, 'last' => null],
        ]);

        $index[$key] = $uid;
        writeJson(USER_INDEX, $index);
        return $uid;
    });

    $user = loadUser($uid) ?? [];

    send([
        'ok' => true,
        'token' => makeToken($uid, 1),
        // Genau einmal. Danach existiert nur noch der Hash.
        'recoveryCode' => $code,
        'user' => publicUser($uid, $user),
    ]);
}

/* ---------------------------------------------------------------- Anmelden */

function login(array $body): never
{
    rateLimit('login', 12, 900);

    $name = clean($body['name'] ?? '', MAX_NAME);
    $pass = is_string($body['pass'] ?? null) ? $body['pass'] : '';

    $index = userIndex();
    $uid = $index[normalizeName($name)] ?? null;
    $user = is_string($uid) ? loadUser($uid) : null;

    /*
     * Auch bei unbekanntem Namen wird ein Hash geprüft. Ohne das wäre die
     * Antwort bei einem existierenden Konto messbar langsamer als bei
     * einem erfundenen - und damit ließe sich die Nutzerliste auslesen.
     * Der Vergleichswert ist ein echter bcrypt-Hash, damit derselbe
     * Rechenaufwand anfällt.
     */
    $hash = is_array($user) && is_string($user['hash'] ?? null)
        ? $user['hash']
        : '$2y$10$usuallyInvalidHashPlaceholderXXXXXXXXXXXXXXXXXXXXXXXXXXX';

    $ok = password_verify($pass, $hash) && is_array($user);

    if (!$ok) {
        // Bremst Rateversuche zusätzlich aus, ohne echte Nutzer zu stören.
        usleep(400_000);
        fail('Name oder Passwort stimmt nicht.', 401, 'falsch');
    }

    /** @var array $user */
    $version = (int) ($user['tokenVersion'] ?? 1);

    // Rechenaufwand von bcrypt kann sich mit PHP-Versionen ändern.
    // Beim nächsten erfolgreichen Login still nachziehen.
    if (password_needs_rehash($user['hash'], PASSWORD_DEFAULT)) {
        $user['hash'] = password_hash($pass, PASSWORD_DEFAULT);
        saveUser((string) $uid, $user);
    }

    send([
        'ok' => true,
        'token' => makeToken((string) $uid, $version),
        'user' => publicUser((string) $uid, $user),
    ]);
}

/* -------------------------------------------------------------- App-Start */

function me(array $body): never
{
    [$uid, $user] = requireUser($body);
    send(withFreshToken(['ok' => true, 'user' => publicUser($uid, $user)], $uid, $user, $body));
}

/* ------------------------------------------------------- Wiederherstellung */

function recover(array $body): never
{
    // Streng gebremst: der Code hat 48 Bit, aber nur, solange nicht
    // beliebig oft geraten werden darf.
    rateLimit('recover', 5, 3600);

    $name = clean($body['name'] ?? '', MAX_NAME);
    $code = normalizeRecoveryCode(is_string($body['code'] ?? null) ? $body['code'] : '');
    $pass = is_string($body['pass'] ?? null) ? $body['pass'] : '';

    if (mb_strlen($pass) < MIN_PASS || strlen($pass) > MAX_PASS) {
        fail('Passwort: mindestens 8 Zeichen.', 400, 'passwort-kurz');
    }

    $index = userIndex();
    $uid = $index[normalizeName($name)] ?? null;
    $user = is_string($uid) ? loadUser($uid) : null;

    $hash = is_array($user) && is_string($user['recoveryHash'] ?? null)
        ? $user['recoveryHash']
        : '$2y$10$usuallyInvalidHashPlaceholderXXXXXXXXXXXXXXXXXXXXXXXXXXX';

    if (!password_verify($code, $hash) || !is_array($user)) {
        usleep(400_000);
        fail('Code stimmt nicht.', 401, 'falsch');
    }

    /** @var array $user */
    $neu = makeRecoveryCode();

    $user['hash'] = password_hash($pass, PASSWORD_DEFAULT);
    // Der alte Code ist jetzt bekannt gewesen - er wird ersetzt.
    $user['recoveryHash'] = password_hash($neu, PASSWORD_DEFAULT);
    // Alle bestehenden Sitzungen entwerten. Wer das Passwort zurücksetzt,
    // will genau das.
    $user['tokenVersion'] = (int) ($user['tokenVersion'] ?? 1) + 1;
    saveUser((string) $uid, $user);

    send([
        'ok' => true,
        'token' => makeToken((string) $uid, (int) $user['tokenVersion']),
        'recoveryCode' => $neu,
        'user' => publicUser((string) $uid, $user),
    ]);
}

/* ----------------------------------------------------------- Passwortwechsel */

function changePassword(array $body): never
{
    [$uid, $user] = requireUser($body);
    rateLimit('password', 10, 3600, $uid);

    $alt = is_string($body['alt'] ?? null) ? $body['alt'] : '';
    $neu = is_string($body['neu'] ?? null) ? $body['neu'] : '';

    if (!password_verify($alt, (string) $user['hash'])) {
        usleep(400_000);
        fail('Altes Passwort stimmt nicht.', 401, 'falsch');
    }
    if (mb_strlen($neu) < MIN_PASS || strlen($neu) > MAX_PASS) {
        fail('Passwort: mindestens 8 Zeichen.', 400, 'passwort-kurz');
    }

    $user['hash'] = password_hash($neu, PASSWORD_DEFAULT);
    $user['tokenVersion'] = (int) ($user['tokenVersion'] ?? 1) + 1;
    saveUser($uid, $user);

    send(['ok' => true, 'token' => makeToken($uid, (int) $user['tokenVersion'])]);
}

function logoutEverywhere(array $body): never
{
    [$uid, $user] = requireUser($body);
    $user['tokenVersion'] = (int) ($user['tokenVersion'] ?? 1) + 1;
    saveUser($uid, $user);
    send(['ok' => true]);
}

/** Gibt es aktuell überhaupt einen Eigentümer? */
function existiertEigentuemer(array $index): bool
{
    foreach ($index as $uid) {
        if (!is_string($uid)) {
            continue;
        }
        $u = loadUser($uid);
        if (is_array($u) && ($u['owner'] ?? false) === true) {
            return true;
        }
    }
    return false;
}

/* ------------------------------------------------------------------ Löschen */

/**
 * Konto und alle Daten entfernen.
 *
 * Die Datenschutzseite sagt zu, dass auf Wunsch alles verschwindet - und
 * eine Zusage, die kein Knopf einlöst, ist keine. Gelöscht wird wirklich
 * alles: Profil, Tage, Gewicht, Wochenpläne, der Eintrag im Namensindex
 * und der Knoten in den Tagesaggregaten der letzten Wochen.
 *
 * Zur Sicherheit mit Passwort - ein versehentlich offenes Handy soll
 * nicht reichen.
 */
function loeschen(array $body): never
{
    [$uid, $user] = requireUser($body);
    rateLimit('loeschen', 5, 3600, $uid);

    $pass = is_string($body['pass'] ?? null) ? $body['pass'] : '';
    if (!password_verify($pass, (string) $user['hash'])) {
        usleep(400_000);
        fail('Passwort stimmt nicht.', 401, 'falsch');
    }

    withLock('users-index', static function () use ($uid, $user): void {
        $index = userIndex();
        unset($index[(string) ($user['nameLower'] ?? '')]);
        writeJson(USER_INDEX, $index);
    });

    // Tagesdateien und der ganze Ordner des Nutzers.
    $tage = DAYS_DIR . '/' . $uid;
    if (is_dir($tage)) {
        foreach (glob($tage . '/*') ?: [] as $datei) {
            @unlink($datei);
        }
        @rmdir($tage);
    }
    $plaene = PLANS_DIR . '/' . $uid;
    if (is_dir($plaene)) {
        foreach (glob($plaene . '/*') ?: [] as $datei) {
            @unlink($datei);
        }
        @rmdir($plaene);
    }

    @unlink(WEIGHT_DIR . '/' . $uid . '.json');
    @unlink(userPath($uid));

    // Aus den Tagesaggregaten der letzten sechs Wochen streichen.
    for ($i = 0; $i < 42; $i++) {
        $datum = date('Y-m-d', strtotime("-{$i} day"));
        $datei = FEED_DIR . '/' . $datum . '.json';
        if (!is_file($datei)) {
            continue;
        }
        withLock('feed-' . $datum, static function () use ($datei, $uid, $datum): void {
            $feed = readJson($datei, ['date' => $datum, 'users' => []]);
            if (isset($feed['users'][$uid])) {
                unset($feed['users'][$uid]);
                writeJson($datei, $feed);
            }
        });
    }

    send(['ok' => true]);
}

/* ------------------------------------------------------------------ Antwort */

/**
 * Was der Client vom Nutzerdatensatz sehen darf.
 *
 * Passwort-Hash, Wiederherstellungs-Hash und die Push-Abos bleiben hier.
 * Nichts davon wird in der Oberfläche gebraucht, und was nicht gesendet
 * wird, kann auch nicht versehentlich irgendwo landen.
 */
function publicUser(string $uid, array $user): array
{
    return [
        'id' => $uid,
        'name' => (string) ($user['name'] ?? ''),
        'owner' => (bool) ($user['owner'] ?? false),
        // Der Anker für die gesamte Wegführung der App: ohne Profil geht
        // es ins Onboarding, mit Profil direkt aufs Dashboard.
        'onboarded' => is_array($user['profile'] ?? null),
        'profile' => $user['profile'] ?? null,
        'derived' => $user['derived'] ?? null,
        'prefs' => $user['prefs'] ?? new stdClass(),
        'streak' => $user['streak'] ?? ['days' => 0, 'last' => null],
        'pushAktiv' => is_array($user['push'] ?? null) && count($user['push']) > 0,
    ];
}
