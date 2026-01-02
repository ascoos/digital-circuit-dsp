<?php
/**
 * @ASCOOS-NAME        : Ascoos OS
 * @ASCOOS-VERSION     : 26.0.0
 * @ASCOOS-SUPPORT     : support@ascoos.com
 * @ASCOOS-BUGS        : https://issues.ascoos.com
 *
 * @CASE-STUDY         : digital_circuit_dsp.php
 * @fileNo             : ASCOOS-OS-CASESTUDY-SEC02786
 *
 * @desc <English>
 *   Simulates a digital signal processing pipeline: generates a clean 440 Hz sine + noisy version (uniform or Gaussian),
 *   applies FIR & IIR low-pass filters, performs Hann-windowed FFT, estimates SNR improvement, THD, SINAD, ENOB and latency,
 *   exports results to JSON/CSV – for IoT/embedded DSP prototyping.
 * @desc <Greek>
 *   Προσομοιώνει αγωγό ψηφιακής επεξεργασίας σήματος: δημιουργεί καθαρό ημιτονοειδές 440 Hz + θορυβώδες σήμα (ομοιόμορφο ή Gaussian),
 *   εφαρμόζει FIR & IIR low-pass φίλτρα, εκτελεί FFT με παράθυρο Hann, υπολογίζει βελτίωση SNR, THD, SINAD, ENOB και καθυστέρηση,
 *   εξάγει αποτελέσματα σε JSON/CSV – για πρωτότυπα IoT/embedded DSP.
 *
 * @since PHP 8.3.0+
 */
declare(strict_types=1);

use ASCOOS\OS\Kernel\Science\Electronics\TDigitalCircuitHandler;

$properties = [
    'logs' => [
        'useLogger' => true,
        'dir'       => $AOS_LOGS_PATH,
        'file'      => 'dsp_simulation_improved.log'
    ]
];

try {
    $dHandler = new TDigitalCircuitHandler([], $properties);

    // ────────────────────────────────────────────────
    // 1. Test signal generation (clean + noisy)
    //    Δημιουργία δοκιμαστικού σήματος (καθαρό + θορυβώδες)
    // ────────────────────────────────────────────────
    $fs = 8000.0;   // Sampling frequency (Hz)
    $N  = 1024;     // Number of samples (power-of-2 for FFT)

    $cleanSignal = [];  // Pure 440 Hz sine
    $noisySignal = [];  // Sine + noise
    $timeVector  = [];  // Time axis

    $noiseScale   = 0.03;            // ~30 dB below main tone (for uniform)
    $noiseStdDev  = 0.03;            // ~30 dB below main tone (for Gaussian)
    $noiseType    = 'gaussian';      // 'uniform' or 'gaussian'

    for ($i = 0; $i < $N; $i++) {
        $t  = $i / $fs;
        $s  = sin(2 * M_PI * 440.0 * $t); // 440 Hz sine

        if ($noiseType === 'gaussian') {
            // Gaussian noise with stdDev = $noiseStdDev
            $noise = $dHandler->gaussianNoise($noiseStdDev);
        } else {
            // Uniform noise in [-1, 1) then scaled
            $noise = $noiseScale * ((mt_rand() / mt_getrandmax() - 0.5) * 2.0);
        }

        $timeVector[]  = $t;
        $cleanSignal[] = $s;
        $noisySignal[] = $s + $noise;
    }

    // ────────────────────────────────────────────────
    // 2. FIR low-pass filtering (3-tap moving average)
    //    FIR low-pass φιλτράρισμα (3-tap κινούμενος μέσος)
    // ────────────────────────────────────────────────
    $firCoeffs   = [0.25, 0.5, 0.25]; // Simple triangular FIR
    $firFiltered = $dHandler->applyFIRFilter($firCoeffs, $noisySignal);

    // Group delay ~ (M-1)/2 samples
    $firDelaySamples = (count($firCoeffs) - 1) / 2.0;
    $firDelaySeconds = $firDelaySamples / $fs;

    // ────────────────────────────────────────────────
    // 3. IIR biquad low-pass Butterworth (2nd order @ 1800 Hz)
    //    IIR biquad low-pass Butterworth (2ης τάξης @ 1800 Hz)
    // ────────────────────────────────────────────────
    $fc = 1800.0;                 // Cutoff frequency
    $Q  = 1 / sqrt(2);            // ≈0.7071 → Butterworth

    $biquadCoeffs = $dHandler->biquadLowpassCoefficients($fc, $Q, $fs);
    $iirFiltered  = $dHandler->applyBiquadFilter($biquadCoeffs, $noisySignal);

    // Approximate latency: 1 sample (implementation-dependent)
    $iirDelaySamples = 1.0;
    $iirDelaySeconds = $iirDelaySamples / $fs;

    // ────────────────────────────────────────────────
    // 4. Hann-windowed FFT analysis (reducing spectral leakage)
    //    Ανάλυση FFT με παράθυρο Hann (μείωση spectral leakage)
    // ────────────────────────────────────────────────
    $hannWindow = [];
    for ($i = 0; $i < $N; $i++) {
        $hannWindow[] = 0.5 * (1.0 - cos(2.0 * M_PI * $i / ($N - 1)));
    }

    $windowedSignal = [];
    for ($i = 0; $i < $N; $i++) {
        $windowedSignal[] = $noisySignal[$i] * $hannWindow[$i];
    }

    $fftComplex   = $dHandler->fft($windowedSignal, true); // Normalized FFT
    $fftMagnitude = array_map([$dHandler, 'complexMagnitude'], $fftComplex);

    // Keep only positive spectrum [0, fs/2]
    $halfLength = (int)($N / 2) + 1;
    $fftMags    = array_slice($fftMagnitude, 0, $halfLength);

    $fftFreqs = [];
    $df = $fs / $N;
    for ($i = 0; $i < $halfLength; $i++) {
        $fftFreqs[] = $i * $df;
    }

    // Peak frequency detection
    $maxMag   = max($fftMags);
    $peakIdx  = array_search($maxMag, $fftMags, true);
    $peakFreq = $fftFreqs[$peakIdx] ?? 0.0;

    // ────────────────────────────────────────────────
    // 5. SNR estimation (input vs FIR vs IIR)
    //    Εκτίμηση SNR (είσοδος vs FIR vs IIR)
    // ────────────────────────────────────────────────
    $snrInput = $dHandler->SNRdB($cleanSignal, $noisySignal); // SNR before filtering
    $snrFIR   = $dHandler->SNRdB($cleanSignal, $firFiltered); // SNR after FIR
    $snrIIR   = $dHandler->SNRdB($cleanSignal, $iirFiltered); // SNR after IIR

    $snrFirGain = $snrFIR - $snrInput;
    $snrIirGain = $snrIIR - $snrInput;

    // ────────────────────────────────────────────────
    // 5b. THD, SINAD, ENOB estimation (from FFT)
    //     Εκτίμηση THD, SINAD, ENOB από FFT
    // ────────────────────────────────────────────────
    $dspMetrics = $dHandler->estimateThdSinadEnob($fftFreqs, $fftMags, 440.0, 5, 1.5);

    $thdDb   = $dspMetrics['thd_db'];
    $sinadDb = $dspMetrics['sinad_db'];
    $enob    = $dspMetrics['enob_bits'];

    // ────────────────────────────────────────────────
    // 6. Console summary of key results
    //    Περίληψη βασικών αποτελεσμάτων στην κονσόλα
    // ────────────────────────────────────────────────
    echo "DSP Simulation Results / Αποτελέσματα DSP\n";
    echo "──────────────────────────────────────────\n";
    echo "Signal length         : $N samples\n";
    echo "Sampling rate         : {$fs} Hz\n";
    echo "Noise type            : {$noiseType}\n";
    echo "Peak frequency (FFT)  : " . round($peakFreq, 1) . " Hz\n";
    echo "Max FFT magnitude     : " . round($maxMag, 4) . "\n\n";

    echo "SNR (input noisy)     : " . ($snrInput === INF ? "INF" : round($snrInput, 2)) . " dB\n";
    echo "SNR (after FIR)       : " . ($snrFIR === INF ? "INF" : round($snrFIR, 2)) . " dB\n";
    echo "SNR (after IIR)       : " . ($snrIIR === INF ? "INF" : round($snrIIR, 2)) . " dB\n\n";

    echo "SNR gain (FIR)        : " . (is_nan($snrFirGain) ? "NaN" : round($snrFirGain, 2)) . " dB\n";
    echo "SNR gain (IIR)        : " . (is_nan($snrIirGain) ? "NaN" : round($snrIirGain, 2)) . " dB\n\n";

    echo "THD (approx)          : " . (is_nan($thdDb)   ? "NaN" : round($thdDb, 2))   . " dB\n";
    echo "SINAD (approx)        : " . (is_nan($sinadDb) ? "NaN" : round($sinadDb, 2)) . " dB\n";
    echo "ENOB (approx)         : " . (is_nan($enob)    ? "NaN" : round($enob, 3))    . " bits\n\n";

    echo "FIR delay             : " . round($firDelaySamples, 2) . " samples (" . round($firDelaySeconds * 1e3, 3) . " ms)\n";
    echo "IIR delay (approx)    : " . round($iirDelaySamples, 2) . " samples (" . round($iirDelaySeconds * 1e3, 3) . " ms)\n\n";

    echo "FIR first 5 samples   : " . json_encode(array_slice($firFiltered, 0, 5), JSON_NUMERIC_CHECK) . "\n";
    echo "IIR biquad coeffs     : " . json_encode($biquadCoeffs, JSON_NUMERIC_CHECK) . "\n\n";

    // ────────────────────────────────────────────────
    // 7. Export full data to JSON
    //    Εξαγωγή πλήρων δεδομένων σε JSON
    // ────────────────────────────────────────────────
    $jsonExportPath = $AOS_TMP_DATA_PATH . '/signal_dsp_demo.json';

    $dHandler->toJSONFile($jsonExportPath, [
        'fs'      => $fs,
        'N'       => $N,
        'time'    => $timeVector,
        'signals' => [
            'clean' => $cleanSignal,
            'noisy' => $noisySignal,
            'fir'   => $firFiltered,
            'iir'   => $iirFiltered,
        ],
        'fft' => [
            'freq'   => $fftFreqs,
            'mag'    => $fftMags,
            'window' => 'Hann',
        ],
        'snr_db' => [
            'input_noisy' => $snrInput,
            'fir'         => $snrFIR,
            'iir'         => $snrIIR,
        ],
        'snr_improvement' => [
            'fir_gain_db' => $snrFirGain,
            'iir_gain_db' => $snrIirGain,
        ],
        'latency' => [
            'fir' => [
                'samples' => $firDelaySamples,
                'seconds' => $firDelaySeconds,
            ],
            'iir' => [
                'samples' => $iirDelaySamples,
                'seconds' => $iirDelaySeconds,
            ],
        ],
        'dsp_metrics' => [
            'thd_db'    => $thdDb,
            'sinad_db'  => $sinadDb,
            'enob_bits' => $enob,
        ],
        'metadata' => [
            'peak_frequency_hz' => $peakFreq,
            'generated_at'      => date('c'),
            'description_en'    => 'DSP demo: 440 Hz sine, noise ('
                . $noiseType . '), FIR/IIR low-pass, Hann FFT, SNR, THD, SINAD, ENOB & latency estimation.',
            'description_gr'    => 'Demo DSP: ημιτονοειδές 440 Hz, θόρυβος ('
                . $noiseType . '), FIR/IIR low-pass, FFT Hann, εκτίμηση SNR, THD, SINAD, ENOB & καθυστέρησης.',
            'filters'           => [
                'fir_coeffs'   => $firCoeffs,
                'iir_cutoff_hz'=> $fc,
                'iir_Q'        => $Q,
                'iir_coeffs'   => $biquadCoeffs,
            ],
            'plot_suggestions'  => [
                'time-domain: clean vs noisy vs FIR vs IIR',
                'frequency-domain: FFT magnitude with 440 Hz peak',
                'SNR evolution: input vs FIR vs IIR',
                'DSP metrics: THD / SINAD / ENOB vs configuration',
            ],
        ],
    ]);

    echo "JSON results exported to: $jsonExportPath\n";

    // ────────────────────────────────────────────────
    // 8. Export selected data to CSV (FFT spectrum & time-domain)
    //    Εξαγωγή επιλεγμένων δεδομένων σε CSV (φάσμα FFT & χρονικό πεδίο)
    // ────────────────────────────────────────────────

    // 8a. FFT spectrum CSV
    $fftCsvPath = $AOS_TMP_DATA_PATH . '/signal_dsp_fft_spectrum.csv';

    $fftHeader = [
        'freq_hz',
        'magnitude',
    ];

    $fftRows = [];
    for ($i = 0; $i < $halfLength; $i++) {
        $fftRows[] = [
            $fftFreqs[$i],
            $fftMags[$i],
        ];
    }

    $dHandler->toCSVFile($fftCsvPath, $fftRows, $fftHeader, ',');

    echo "FFT spectrum exported to: $fftCsvPath\n";

    // 8b. Time-domain signals CSV
    $timeCsvPath = $AOS_TMP_DATA_PATH . '/signal_dsp_time_signals.csv';

    $timeHeader = [
        't_seconds',
        'clean',
        'noisy',
        'fir',
        'iir',
    ];

    $timeRows = [];
    for ($i = 0; $i < $N; $i++) {
        $timeRows[] = [
            $timeVector[$i],
            $cleanSignal[$i],
            $noisySignal[$i],
            $firFiltered[$i] ?? null,
            $iirFiltered[$i] ?? null,
        ];
    }

    $dHandler->toCSVFile($timeCsvPath, $timeRows, $timeHeader, ',');

    echo "Time-domain signals exported to: $timeCsvPath\n";

    // ────────────────────────────────────────────────
    // 9. Logging summary
    //    Σύνοψη στο log
    // ────────────────────────────────────────────────
    $dHandler->logger?->log(
        sprintf(
                "DSP simulation completed | N=%d | noise=%s | peak=%.1f Hz | max_mag=%.4f | ".                "SNR_in=%.2f dB | SNR_FIR=%.2f dB | SNR_IIR=%.2f dB | ".
            "THD=%.2f dB | SINAD=%.2f dB | ENOB=%.3f bits | ".
            "JSON=%s | FFT_CSV=%s | TIME_CSV=%s",
            $N,
            $noiseType,
            $peakFreq,
            $maxMag,
            $snrInput,
            $snrFIR,
            $snrIIR,
            $thdDb,
            $sinadDb,
            $enob,
            $jsonExportPath,
            $fftCsvPath,
            $timeCsvPath
        ),
        $dHandler::DEBUG_LEVEL_INFO
    );

} catch (Throwable $e) {
    echo "Error / Σφάλμα: " . $e->getMessage() . "\n";

    if (isset($dHandler) && $dHandler->logger) {
        $dHandler->logger->log(
            "DSP simulation failed: " . $e->getMessage(),
            $dHandler::DEBUG_LEVEL_ERROR
        );
    }
} finally {
    if (isset($dHandler)) {
        $dHandler->Free();
    }
}