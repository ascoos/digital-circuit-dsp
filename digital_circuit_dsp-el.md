# Digital Signal Processing Pipeline – DSP Case Study

Αυτή η μελέτη περίπτωσης παρουσιάζει πώς το **Ascoos OS** και η κλάση  
**`TDigitalCircuitHandler`** υλοποιούν έναν πλήρη αγωγό ψηφιακής επεξεργασίας σήματος (DSP) σε καθαρό PHP, χωρίς εξωτερικές βιβλιοθήκες.

Το case study περιλαμβάνει:

- δημιουργία καθαρού και θορυβώδους σήματος  
- Gaussian ή Uniform noise injection  
- FIR & IIR low‑pass filtering  
- Hann‑windowed FFT  
- SNR, THD, SINAD, ENOB estimation  
- latency estimation  
- JSON & CSV export  
- logging και debugging  

Το αποτέλεσμα είναι μια πλήρης, εκπαιδευτική και παραγωγική υλοποίηση DSP, κατάλληλη για IoT, embedded simulation, audio pipelines και εκπαιδευτική χρήση.

---

## Σκοπός

- Δημιουργία καθαρού ημιτονοειδούς σήματος 440 Hz  
- Προσθήκη θορύβου (Gaussian ή Uniform)  
- Εφαρμογή FIR & IIR low‑pass φίλτρων  
- Εκτέλεση FFT με παράθυρο Hann  
- Εντοπισμός κορυφής συχνότητας  
- Υπολογισμός:
  - SNR (input, FIR, IIR)
  - SNR improvement (gain)
  - THD (Total Harmonic Distortion)
  - SINAD (Signal‑to‑Noise And Distortion)
  - ENOB (Effective Number Of Bits)
- Εξαγωγή αποτελεσμάτων σε JSON & CSV  
- Καταγραφή (logging) όλων των μετρικών  

---

## Κύριες Κλάσεις του Ascoos OS

### DSP Core
- **`TDigitalCircuitHandler`**  
  Κεντρική κλάση DSP: φίλτρα, FFT, SNR, Gaussian noise, THD/SINAD/ENOB.

### DSP Utilities
- **`gaussianNoise()`**  
  Παραγωγή Gaussian θορύβου με Box–Muller + caching  
- **`findClosestBin()`**  
  Εύρεση πλησιέστερου FFT bin με ανοχή  
- **`estimateThdSinadEnob()`**  
  Υπολογισμός THD, SINAD, ENOB από FFT  

### Export & Logging
- **`toJSONFile()`**  
  Εξαγωγή πλήρους DSP pipeline σε JSON  
- **`toCSVFile()`**  
  Εξαγωγή FFT spectrum & time‑domain signals  
- **Logger**  
  Καταγραφή όλων των μετρικών και paths  

---

## Δομή Αρχείων

Η υλοποίηση βρίσκεται στο αρχείο:

- [`digital_circuit_dsp.php`](digital_circuit_dsp.php)

Το αρχείο περιλαμβάνει:

- δημιουργία σήματος  
- θόρυβος (Gaussian/Uniform)  
- FIR/IIR filtering  
- FFT με Hann  
- SNR, THD, SINAD, ENOB  
- latency  
- JSON/CSV export  
- logging  

---

## Διάγραμμα Αρχιτεκτονικής

```text
┌──────────────────────────────────────────────────────────────────────────────┐
│                              ASCOOS OS KERNEL                                │
│                     (Electronics • DSP • Math Engine)                        │
└──────────────────────────────────────────────────────────────────────────────┘
                                      │
                                      ▼
                    ┌──────────────────────────────────────┐
                    │      TDigitalCircuitHandler          │
                    │  (Filters • FFT • Noise • Metrics)   │
                    └──────────────────────────────────────┘
                                      │
                                      ▼
         ┌──────────────────────────────────────────────────────────────────┐
         │                         DSP Pipeline                             │
         └──────────────────────────────────────────────────────────────────┘
                                      │
                                      ▼
         ┌──────────────────────────────────────────────────────────────────┐
         │ 1. Signal Generation (Clean + Noise)                             │
         │ 2. FIR Filtering                                                 │
         │ 3. IIR Filtering                                                 │
         │ 4. Hann Window                                                   │
         │ 5. FFT (Magnitude Spectrum)                                      │
         │ 6. SNR / THD / SINAD / ENOB                                      │
         │ 7. Latency Estimation                                            │
         │ 8. JSON / CSV Export                                             │
         │ 9. Logging                                                       │
         └──────────────────────────────────────────────────────────────────┘
                                      │
                                      ▼
┌──────────────────────────────────────────────────────────────────────────────┐
│                           Unified DSP Output Renderer                        │
│      (Console Summary • JSON Data • CSV Files • Logs)                        │
└──────────────────────────────────────────────────────────────────────────────┘
```

---

## Προαπαιτούμενα

1. PHP ≥ 8.3  
2. Εγκατεστημένο το **Ascoos OS** ή το **AWES 26 Pro**  
3. Ενεργοποιημένες οι κλάσεις `TDigitalCircuitHandler`, `TCircuitHandler` και `TElectronicsHandler` μέσω της εφαρμογής `Extras Classes Manager`.  
4. Πρόσβαση στα paths:
   - `$AOS_TMP_DATA_PATH`
   - `$AOS_LOGS_PATH`

---

## Ροή Εκτέλεσης

1. **Αρχικοποίηση του DSP Handler**  
   Φορτώνεται ο `TDigitalCircuitHandler` με logging.

2. **Δημιουργία καθαρού σήματος**  
   Ημιτονοειδές 440 Hz, 1024 samples.

3. **Προσθήκη θορύβου**  
   - Gaussian (Box–Muller)  
   - ή Uniform  

4. **Εφαρμογή FIR φίλτρου**  
   3‑tap moving average.

5. **Εφαρμογή IIR biquad Butterworth**  
   2ης τάξης, cutoff 1800 Hz.

6. **Εφαρμογή Hann window**  
   Μείωση spectral leakage.

7. **FFT ανάλυση**  
   Υπολογισμός magnitude spectrum.

8. **Εντοπισμός κορυφής συχνότητας**  
   Peak detection.

9. **Υπολογισμός SNR**  
   Input, FIR, IIR + SNR gain.

10. **Υπολογισμός THD, SINAD, ENOB**  
    Με χρήση tolerance για leakage.

11. **Εξαγωγή JSON**  
    Πλήρες dataset + metadata.

12. **Εξαγωγή CSV**  
    FFT spectrum + time‑domain signals.

13. **Logging**  
    Καταγραφή όλων των μετρικών.

---

## Παράδειγμα Κώδικα

```php
$dsp = new TDigitalCircuitHandler([], $properties);

$noise = $dsp->gaussianNoise(0.03);
$fir   = $dsp->applyFIRFilter($firCoeffs, $noisySignal);
$iir   = $dsp->applyBiquadFilter($biquadCoeffs, $noisySignal);

$fft   = $dsp->fft($windowed, true);
$mag   = array_map([$dsp, 'complexMagnitude'], $fft);

$metrics = $dsp->estimateThdSinadEnob($fftFreqs, $fftMags, 440.0);
```

---

## Αναμενόμενο Αποτέλεσμα

```
=== DSP Simulation Results ===

Peak frequency (FFT): 440.0 Hz
Max FFT magnitude   : 0.9981

SNR (input noisy)   : 29.52 dB
SNR (after FIR)     : 33.87 dB
SNR (after IIR)     : 35.12 dB

THD (approx)        : -42.1 dB
SINAD (approx)      : 33.5 dB
ENOB (approx)       : 5.27 bits

JSON exported to: /tmp/signal_dsp_demo.json
FFT CSV exported to: /tmp/signal_dsp_fft_spectrum.csv
Time CSV exported to: /tmp/signal_dsp_time_signals.csv
```

---

## Πόροι

- [Ascoos OS Documentation (Υπό κατασκευή)](https://docs.ascoos.com/os)  
- [Ascoos OS Website (Υπο κατασκευή)](https://os.ascoos.com)  
- [Ascoos](https://www.ascoos.com)
- [AWES Studio](https://awes.ascoos.com)  
- [GitHub Repository](https://github.com/ascoos/os)

---

## Άδεια Χρήσης

Αυτή η μελέτη καλύπτεται από την Ascoos General License (AGL).  
Δείτε το [LICENSE.md](https://github.com/ascoos/os/blob/main/LICENSE-GR.md).
