# Secure coding demo: RUID generation & crypto failure handling

Run from the CLI:

```
php demo/scenario/run_demo.php
```

Or live in a browser, to switch between the two side by side:

- `http://votetrust.test/demo/scenario/run_demo.php?mode=unsafe` — no error handling at all. The crypto failure is left genuinely uncaught, so PHP's own fatal-error output (exception class, message, internal file path, full stack trace) becomes the HTTP response body — visible on the page and in DevTools' Network/Console tabs.
- `http://votetrust.test/demo/scenario/run_demo.php?mode=safe` — same failure, caught and logged in full to `crypto_failures.log`; the page only ever shows the generic sanitized message.

The browser entry point lives at `public/demo/scenario/run_demo.php` — Apache's DocumentRoot for `votetrust.test` is `public/`, so that's the only way this becomes reachable at that URL. It requires the *same* `RuidGenerator.php`/`CryptoFailureLogger.php` classes shown below via a relative path back to this folder; those classes stay outside `public/` (correct practice — implementation code shouldn't sit inside the web root), so `demo/scenario/RuidGenerator.php` itself is not directly fetchable over HTTP, only the thin entry-point script is. The CLI script above (`demo/scenario/run_demo.php`) is unaffected and still the more detailed of the two output formats.

## Module 1 — Cryptographic Practices (`RuidGenerator.php`)

- `RuidGenerator::secureSource()` generates RUIDs from `random_bytes(16)` (128 bits of OS-backed entropy) — PHP's cryptographically secure RNG.
- `RuidGenerator::insecureSource()` is the deliberately weak baseline (`mt_rand()`) used only to demonstrate the failure mode; it is never wrapped in `RuidGenerator` for real use.
- The `RuidGenerator` constructor **rejects insecure sources**: it fixes PHP's PRNG seed (`mt_srand()`/`srand()`) and calls the source twice. A non-cryptographic PRNG becomes reproducible under a fixed seed; a real CSPRNG (`random_bytes()`) ignores that seed entirely and stays unpredictable. If the two calls match, construction throws and the insecure source is refused.

## Module 2 — Error Handling & Logging (`CryptoFailureLogger.php`)

- `CryptoFailureLogger::log()` writes timestamp, severity, context, and the real exception class/message to `crypto_failures.log` — for developers/ops only.
- `SanitizedCryptoException` is the only thing ever returned to a caller after a crypto failure — a fixed, generic message with no internal detail.
- `crypto_failures.log` is git-ignored (`.gitignore` → `/demo/scenario/crypto_failures.log`) since it can contain internal error detail that shouldn't be committed.

## What the demo shows

Running `run_demo.php` prints a side-by-side comparison to the console:

1. **RUID generation** — 3,000 IDs generated both ways. The insecure 6-digit ID space reliably produces real collisions and is fully predictable once its seed is known; the secure 128-bit space produces zero collisions and stays unpredictable even with the PRNG seed fixed. A direct attempt to construct `RuidGenerator` with the insecure source is shown being rejected.
2. **Crypto failure handling** — the same simulated `random_bytes()` failure is shown twice: once bubbling up uncaught (leaking the exception class, message, file path, and stack trace — exactly what an attacker or confused user would see), and once caught, logged in full to `crypto_failures.log`, with only a generic message shown to the user.

Re-running the script clears and rewrites `crypto_failures.log` each time so its printed contents match that run.
