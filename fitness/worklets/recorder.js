/**
 * Nimmt Rohdaten vom Mikrofon entgegen und schickt sie an den Hauptthread.
 *
 * Warum eine eigene Datei und kein Blob-URL: Die Seite läuft unter der CSP
 * der Hauptseite, und die erlaubt Skripte nur von 'self'. Ein aus einer
 * Zeichenkette gebauter Blob-URL würde blockiert - lautlos, mitten in der
 * Aufnahme.
 *
 * Warum überhaupt ein Worklet und kein ScriptProcessorNode: Das Worklet
 * läuft im Audio-Thread. Rendert die Oberfläche gerade eine Animation,
 * fehlen sonst Bruchstücke der Aufnahme - und ein zerhacktes "dreihundert
 * Gramm" versteht auch das beste Modell nicht.
 */
class RecorderProcessor extends AudioWorkletProcessor {
  process(inputs) {
    const kanal = inputs[0]?.[0];
    if (!kanal || kanal.length === 0) {
      // Kein Eingang heisst nicht Ende - das Mikrofon kann kurz stumm sein.
      return true;
    }

    // Kopieren ist Pflicht: Der Puffer wird vom Audio-System sofort
    // wiederverwendet, ein Verweis darauf enthielte gleich fremde Daten.
    const kopie = new Float32Array(kanal.length);
    kopie.set(kanal);

    let spitze = 0;
    for (let i = 0; i < kopie.length; i++) {
      const wert = kopie[i] < 0 ? -kopie[i] : kopie[i];
      if (wert > spitze) spitze = wert;
    }

    this.port.postMessage({ daten: kopie, pegel: spitze }, [kopie.buffer]);
    return true;
  }
}

registerProcessor('aura-recorder', RecorderProcessor);
