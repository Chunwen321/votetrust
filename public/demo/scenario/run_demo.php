<?php

declare(strict_types=1);

/**
 * Browser-facing entry point for the secure coding demo.
 *
 * This file lives under public/ (Apache's DocumentRoot for votetrust.test)
 * so it's directly web-servable; the reusable classes it demonstrates stay
 * outside the web root at demo/scenario/ (project root), the same files
 * used by the CLI demo (php demo/scenario/run_demo.php). It bypasses
 * Laravel entirely (no bootstrap, no session, no CSRF) — it's a standalone
 * script, same as the CLI version.
 *
 *   http://votetrust.test/demo/scenario/run_demo.php?mode=unsafe
 *   http://votetrust.test/demo/scenario/run_demo.php?mode=safe
 */

require __DIR__ . '/../../../demo/scenario/RuidGenerator.php';
require __DIR__ . '/../../../demo/scenario/CryptoFailureLogger.php';

use Demo\Scenario\CryptoFailureLogger;
use Demo\Scenario\RuidGenerator;
use Demo\Scenario\SanitizedCryptoException;

$mode = $_GET['mode'] ?? null;

$style = <<<CSS
    body { font: 15px/1.5 ui-monospace, SFMono-Regular, Menlo, monospace; max-width: 860px; margin: 2rem auto; padding: 0 1rem; color: #10233f; }
    h1 { font-size: 1.3rem; }
    .mode-links a { display: inline-block; margin-right: 1rem; padding: .4rem .8rem; border-radius: 6px; text-decoration: none; }
    .mode-links a.unsafe { background: #fff0ee; color: #b42318; }
    .mode-links a.safe { background: #eafaf4; color: #067647; }
    pre { background: #f7f9fc; padding: 1rem; border-radius: 8px; overflow-x: auto; white-space: pre-wrap; }
    .leak { background: #2a0a06; color: #ffb4a8; }
    .safe-box { background: #eafaf4; color: #067647; padding: 1rem; border-radius: 8px; }
    CSS;

// This landing page is the only part rendered through the normal
// error-handled path — the two modes below are the actual demo.
if ($mode !== 'unsafe' && $mode !== 'safe') {
    header('Content-Type: text/html; charset=utf-8');
    echo "<!doctype html><html><head><title>Crypto failure demo</title><style>{$style}</style></head><body>";
    echo '<h1>Secure coding demo: RUID generation &amp; crypto failure handling</h1>';
    echo '<p class="mode-links">';
    echo '<a class="unsafe" href="?mode=unsafe">&#10060; Run WITHOUT secure practice</a>';
    echo '<a class="safe" href="?mode=safe">&#9989; Run WITH secure practice</a>';
    echo '</p>';
    echo '</body></html>';
    exit;
}

echo '<!doctype html><html><head><title>Crypto failure demo — ' . htmlspecialchars($mode) . '</title>';
echo "<style>{$style}</style></head><body>";
echo '<p class="mode-links">';
echo '<a class="unsafe" href="?mode=unsafe">&#10060; unsafe</a>';
echo '<a class="safe" href="?mode=safe">&#9989; safe</a>';
echo '</p>';

const SAMPLE_SIZE = 3000;

if ($mode === 'unsafe') {
    // ---------------------------------------------------------------
    // MODE: unsafe — weak RNG, and the crypto failure below is left
    // completely unguarded. Nothing after the failing call executes;
    // PHP's own uncaught-exception output becomes the HTTP response
    // body, so both the rendered page and the DevTools Network/Console
    // tabs show the same raw leak.
    // ---------------------------------------------------------------
    ini_set('display_errors', '1');
    error_reporting(E_ALL);

    echo '<h1>&#10060; WITHOUT secure practice</h1>';

    $insecureSource = RuidGenerator::insecureSource();
    mt_srand(1000);
    $run = [];
    for ($i = 0; $i < SAMPLE_SIZE; $i++) {
        $run[] = $insecureSource();
    }
    $collisions = SAMPLE_SIZE - count(array_unique($run));

    mt_srand(1000);
    $a = [$insecureSource(), $insecureSource(), $insecureSource()];
    mt_srand(1000);
    $b = [$insecureSource(), $insecureSource(), $insecureSource()];

    echo '<h2>RUID generation (mt_rand)</h2><pre>';
    echo 'Sample: ' . implode(', ', array_slice($run, 0, 5)) . "...\n";
    echo "Collisions in " . SAMPLE_SIZE . " IDs: {$collisions}\n";
    echo 'Fixed-seed run A: ' . implode(', ', $a) . "\n";
    echo 'Fixed-seed run B: ' . implode(', ', $b) . "\n";
    echo $a === $b ? "-> IDENTICAL: fully predictable from the seed.\n" : "-> different\n";
    echo '</pre>';

    echo '<h2>Crypto failure — no error handling</h2>';
    echo '<p>Calling the RNG source directly, no try/catch. What follows is PHP\'s real uncaught-exception output:</p>';

    $failingSource = RuidGenerator::failingSecureSource();
    $failingSource(); // deliberately uncaught — this is the point of "unsafe" mode
    echo '<p>You should never see this line.</p>'; // unreachable
} else {
    // ---------------------------------------------------------------
    // MODE: safe — secure RNG, and the crypto failure below is caught,
    // logged in full, and only a sanitized message is rendered.
    // ---------------------------------------------------------------
    echo '<h1>&#9989; WITH secure practice</h1>';

    $secureGenerator = new RuidGenerator(RuidGenerator::secureSource());
    $run = [];
    for ($i = 0; $i < SAMPLE_SIZE; $i++) {
        $run[] = $secureGenerator->generate();
    }
    $unique = count(array_unique($run));

    mt_srand(1000);
    srand(1000);
    $a = $secureGenerator->generate();
    mt_srand(1000);
    srand(1000);
    $b = $secureGenerator->generate();

    echo '<h2>RUID generation (random_bytes)</h2><pre>';
    echo 'Sample: ' . implode(', ', array_slice($run, 0, 3)) . "...\n";
    echo "Unique IDs: {$unique} / " . SAMPLE_SIZE . "\n";
    echo 'Fixed-seed call A: ' . $a . "\n";
    echo 'Fixed-seed call B: ' . $b . "\n";
    echo $a !== $b ? "-> different: random_bytes() ignores mt_srand()/srand() entirely.\n" : "-> IDENTICAL (unexpected)\n";
    echo '</pre>';

    try {
        new RuidGenerator(RuidGenerator::insecureSource());
        echo '<p>UNEXPECTED: insecure source was accepted.</p>';
    } catch (RuntimeException $e) {
        echo '<h2>Rejecting an insecure source</h2><pre>' . htmlspecialchars($e->getMessage()) . '</pre>';
    }

    echo '<h2>Crypto failure — caught, logged, sanitised</h2>';

    $logPath = __DIR__ . '/../../../demo/scenario/crypto_failures.log';
    $logger = new CryptoFailureLogger($logPath);

    try {
        (RuidGenerator::failingSecureSource())();
    } catch (Throwable $internal) {
        $logger->log($internal, context: 'RUID generation for payment reference (web demo)', severity: 'CRITICAL');
        $userFacing = new SanitizedCryptoException();
        echo '<p><strong>Message shown to the user:</strong></p>';
        echo '<div class="safe-box">' . htmlspecialchars($userFacing->getMessage()) . '</div>';
    }

    echo '<p style="margin-top:1rem">Full detail was written to <code>' . htmlspecialchars($logPath) . '</code> (not shown here, not shown to the user).</p>';
}

echo '</body></html>';
