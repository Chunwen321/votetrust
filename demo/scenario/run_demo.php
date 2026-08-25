<?php

declare(strict_types=1);

/**
 * Secure coding demo: RUID generation (Module 1) and crypto failure
 * handling/logging (Module 2), each shown without the secure practice and
 * then with it. Run with: php demo/scenario/run_demo.php
 */

require __DIR__ . '/RuidGenerator.php';
require __DIR__ . '/CryptoFailureLogger.php';

use Demo\Scenario\CryptoFailureLogger;
use Demo\Scenario\RuidGenerator;
use Demo\Scenario\SanitizedCryptoException;

function section(string $title): void
{
    echo "\n" . str_repeat('=', 72) . "\n{$title}\n" . str_repeat('=', 72) . "\n";
}

function sub(string $title): void
{
    echo "\n--- {$title} ---\n";
}

// ---------------------------------------------------------------------
// MODULE 1 — Cryptographic Practices: RUID generation
// ---------------------------------------------------------------------

section('MODULE 1: RUID GENERATION');

const SAMPLE_SIZE = 3000;

sub('❌ WITHOUT secure practice — Math.random()-equivalent (mt_rand)');

$insecureSource = RuidGenerator::insecureSource();

mt_srand(1000);
$firstRun = [];
for ($i = 0; $i < SAMPLE_SIZE; $i++) {
    $firstRun[] = $insecureSource();
}

// Predictability: if an attacker (or a re-deployed process) knows/repeats
// the seed, they can reproduce the exact same "random" sequence.
mt_srand(1000);
$repeatedRun = [];
for ($i = 0; $i < 5; $i++) {
    $repeatedRun[] = $insecureSource();
}
mt_srand(1000);
$predictedRun = [];
for ($i = 0; $i < 5; $i++) {
    $predictedRun[] = $insecureSource();
}

$uniqueInsecure = count(array_unique($firstRun));
$collisions = SAMPLE_SIZE - $uniqueInsecure;

echo "Generated " . SAMPLE_SIZE . " IDs from a 1,000,000-value space (6 digits).\n";
echo 'Sample IDs: ' . implode(', ', array_slice($firstRun, 0, 5)) . "...\n";
echo "Unique IDs: {$uniqueInsecure} / " . SAMPLE_SIZE . "\n";
echo $collisions > 0
    ? "COLLISIONS FOUND: {$collisions} duplicate ID(s) — two different payments/records could end up with the same RUID.\n"
    : "No collisions this run (still possible next run — the ID space is just too small).\n";
echo "\nPredictability check — same fixed seed (mt_srand(1000)), two independent runs:\n";
echo '  Run A: ' . implode(', ', $repeatedRun) . "\n";
echo '  Run B: ' . implode(', ', $predictedRun) . "\n";
echo $repeatedRun === $predictedRun
    ? "  -> IDENTICAL. Anyone who can guess/observe the seed can predict every RUID this code will ever generate.\n"
    : "  -> Different (unexpected for mt_rand under a fixed seed).\n";

sub('✅ WITH secure practice — random_bytes() (CSPRNG)');

$secureGenerator = new RuidGenerator(RuidGenerator::secureSource());

$secureRun = [];
for ($i = 0; $i < SAMPLE_SIZE; $i++) {
    $secureRun[] = $secureGenerator->generate();
}
$uniqueSecure = count(array_unique($secureRun));

echo "Generated " . SAMPLE_SIZE . " IDs from a 128-bit space (random_bytes(16)).\n";
echo 'Sample IDs: ' . implode(', ', array_slice($secureRun, 0, 3)) . "...\n";
echo "Unique IDs: {$uniqueSecure} / " . SAMPLE_SIZE . "\n";
echo $uniqueSecure === SAMPLE_SIZE
    ? "No collisions — with 2^128 possible values, a collision here is not practically possible.\n"
    : "Unexpected collision — investigate immediately.\n";

echo "\nPredictability check — same fixed seed (mt_srand(1000)/srand(1000)), two calls:\n";
mt_srand(1000);
srand(1000);
$secA = $secureGenerator->generate();
mt_srand(1000);
srand(1000);
$secB = $secureGenerator->generate();
echo "  Call A: {$secA}\n";
echo "  Call B: {$secB}\n";
echo $secA !== $secB
    ? "  -> Different, even with the PRNG seed fixed. random_bytes() ignores mt_srand()/srand() entirely.\n"
    : "  -> IDENTICAL (unexpected — this source should not be considered secure).\n";

sub('Rejecting an insecure source outright');

try {
    new RuidGenerator(RuidGenerator::insecureSource());
    echo "UNEXPECTED: insecure source was accepted.\n";
} catch (RuntimeException $e) {
    echo "Rejected as expected:\n  \"{$e->getMessage()}\"\n";
}

// ---------------------------------------------------------------------
// MODULE 2 — Error Handling & Logging: cryptographic module failures
// ---------------------------------------------------------------------

section('MODULE 2: CRYPTO FAILURE HANDLING & LOGGING');

$logPath = __DIR__ . '/crypto_failures.log';
if (is_file($logPath)) {
    unlink($logPath); // start each demo run with a clean log for a readable printout at the end
}

$failingSource = RuidGenerator::failingSecureSource();

sub('❌ WITHOUT secure practice — no error handling');

echo "Calling the RUID source with no try/catch, exactly as an unguarded call site would...\n\n";
try {
    // We still have to catch it here so the rest of the demo can run — but
    // what we print below is deliberately the *raw* exception, i.e. exactly
    // what an uncaught exception (or a naive catch-and-echo) would show a user.
    $failingSource();
} catch (Throwable $leaked) {
    echo "*** RAW OUTPUT SHOWN TO THE USER (internals leaked) ***\n";
    echo 'Fatal error: Uncaught ' . $leaked::class . ': ' . $leaked->getMessage() . "\n";
    echo '  in ' . $leaked->getFile() . ':' . $leaked->getLine() . "\n";
    echo "  Stack trace:\n" . $leaked->getTraceAsString() . "\n";
}
echo "\n^ The user now knows this app uses random_bytes(), the exact libc/OS error, and internal file paths.\n";

sub('✅ WITH secure practice — caught, logged, sanitised');

$logger = new CryptoFailureLogger($logPath);

try {
    $failingSource();
} catch (Throwable $internal) {
    $logger->log($internal, context: 'RUID generation for payment reference', severity: 'CRITICAL');
    $userFacing = new SanitizedCryptoException();

    echo "*** MESSAGE SHOWN TO THE USER ***\n";
    echo "  \"{$userFacing->getMessage()}\"\n";
}

echo "\n*** FULL DETAIL WRITTEN TO LOG (never shown to the user) ***\n";
echo "Log file: {$logPath}\n";
echo file_get_contents($logPath);

// ---------------------------------------------------------------------
// Comparison summary
// ---------------------------------------------------------------------

section('COMPARISON SUMMARY');

printf("%-28s | %-22s | %-22s\n", 'Property', 'WITHOUT', 'WITH');
echo str_repeat('-', 78) . "\n";
printf("%-28s | %-22s | %-22s\n", 'RNG source', 'mt_rand() (PRNG)', 'random_bytes() (CSPRNG)');
printf("%-28s | %-22s | %-22s\n", 'Collisions in ' . SAMPLE_SIZE . ' IDs', (string) $collisions, (string) (SAMPLE_SIZE - $uniqueSecure));
printf("%-28s | %-22s | %-22s\n", 'Predictable from seed', 'Yes', 'No');
printf("%-28s | %-22s | %-22s\n", 'Insecure source rejected', 'N/A (not checked)', 'Yes, at construction');
printf("%-28s | %-22s | %-22s\n", 'Crypto failure handling', 'Uncaught, leaks internals', 'Caught + logged');
printf("%-28s | %-22s | %-22s\n", 'Detail shown to user', 'Exception class/message/trace', 'Generic message only');
printf("%-28s | %-22s | %-22s\n", 'Detail available to devs', 'Only if they saw the leak', 'Full detail in log file');
echo "\nDone. See {$logPath} for the logged failure (excluded from git — see .gitignore).\n";
