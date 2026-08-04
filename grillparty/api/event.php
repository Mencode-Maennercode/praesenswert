<?php
/** Öffentliche Sicht auf eine Party - ohne Namen, ohne E-Mail-Adressen. */

declare(strict_types=1);
require __DIR__ . '/_lib.php';

cors();
requireMethod('GET');

$id = validEventId($_GET['e'] ?? '');
if ($id === '') {
    fail('Kein gültiger Party-Link.');
}

send(publicView(loadEvent($id)));
