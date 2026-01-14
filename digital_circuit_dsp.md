# Digital Signal Processing Pipeline – DSP Case Study

> Case Study Code: ASCOOS-OS-CASESTUDY-SEC02786

This case study demonstrates how **Ascoos OS** and the  
**`TDigitalCircuitHandler`** class implement a complete digital signal processing (DSP) pipeline in pure PHP, without external libraries.

The pipeline includes:

- generation of clean and noisy signals  
- Gaussian or Uniform noise injection  
- FIR & IIR low‑pass filtering  
- Hann‑windowed FFT  
- SNR, THD, SINAD, ENOB estimation  
- latency estimation  
- JSON & CSV export  
- logging and debugging  

The result is a complete, educational, and production‑ready DSP implementation suitable for IoT, embedded simulation, audio pipelines, and academic use.

---

## Purpose

- Generate a clean 440 Hz sine wave  
- Add noise (Gaussian or Uniform)  
- Apply FIR & IIR low‑pass filters  
- Perform FFT using a Hann window  
- Detect peak frequency  
- Compute:
  - SNR (input, FIR, IIR)
  - SNR improvement (gain)
  - THD (Total Harmonic Distortion)
  - SINAD (Signal‑to‑Noise And Distortion)
  - ENOB (Effective Number Of Bits)
- Export results to JSON & CSV  
- Log all metrics for analysis  

---

## Core Ascoos OS Classes

### DSP Core
- **`TDigitalCircuitHandler`**  
  Main DSP class: filters, FFT, SNR, Gaussian noise, THD/SINAD/ENOB.

### DSP Utilities
- **`gaussianNoise()`**  
  Generates Gaussian noise using Box–Muller with caching  
- **`findClosestBin()`**  
  Finds the nearest FFT bin within tolerance  
- **`estimateThdSinadEnob()`**  
  Computes THD, SINAD, and ENOB from FFT data  

### Export & Logging
- **`toJSONFile()`**  
  Exports the full DSP pipeline to JSON  
- **`toCSVFile()`**  
  Exports FFT spectrum & time‑domain signals  
- **Logger**  
  Records all metrics and file paths  

---

## File Structure

The implementation is located in:

- [`digital_circuit_dsp.php`](digital_circuit_dsp.php)

This file includes:

- signal generation  
- noise injection (Gaussian/Uniform)  
- FIR/IIR filtering  
- Hann‑windowed FFT  
- SNR, THD, SINAD, ENOB  
- latency estimation  
- JSON/CSV export  
- logging  

---

## Architecture Diagram

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

## Requirements

1. PHP ≥ 8.3  
2. Installed **Ascoos OS** or **AWES 26 Pro**  
3. Enabled classes `TDigitalCircuitHandler`, `TCircuitHandler`, and `TElectronicsHandler` via the **Extras Classes Manager**  
4. Access to:
   - `$AOS_TMP_DATA_PATH`
   - `$AOS_LOGS_PATH`

---

## Execution Flow

1. **Initialize the DSP Handler**  
   Loads `TDigitalCircuitHandler` with logging enabled.

2. **Generate clean signal**  
   440 Hz sine wave, 1024 samples.

3. **Inject noise**  
   - Gaussian (Box–Muller)  
   - or Uniform  

4. **Apply FIR filter**  
   3‑tap moving average.

5. **Apply IIR biquad Butterworth filter**  
   2nd‑order, cutoff 1800 Hz.

6. **Apply Hann window**  
   Reduces spectral leakage.

7. **Perform FFT**  
   Computes magnitude spectrum.

8. **Detect peak frequency**  
   Finds the dominant spectral component.

9. **Compute SNR**  
   Input, FIR, IIR + SNR gain.

10. **Compute THD, SINAD, ENOB**  
    Using tolerance to handle leakage.

11. **Export JSON**  
    Full dataset + metadata.

12. **Export CSV**  
    FFT spectrum + time‑domain signals.

13. **Logging**  
    Records all metrics and file paths.

---

## Code Example

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

## Expected Output

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

## Resources

- [Ascoos OS Documentation (Under Construction)](https://docs.ascoos.com/os)  
- [Ascoos OS Website (Under Construction)](https://os.ascoos.com)  
- [Ascoos](https://www.ascoos.com)
- [AWES Studio](https://awes.ascoos.com)  
- [GitHub Repository](https://github.com/ascoos/os)

---

## License

This case study is covered under the Ascoos General License (AGL).  
See [LICENSE.md](https://github.com/ascoos/os/blob/main/LICENSE-GR.md).
