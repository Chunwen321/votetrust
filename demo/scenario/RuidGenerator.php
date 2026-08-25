<?php

declare(strict_types=1);

namespace Demo\Scenario;

use RuntimeException;

/**
 * Module 1 — Cryptographic Practices: RUID (Random Unique ID) generation.
 *
 * Wraps an injected random source and refuses to accept one that behaves
 * like a non-cryptographic PRNG. The detection is functional, not a
 * name/type check: PHP's Mersenne Twister PRNG (mt_rand()/rand()) is fully
 * determined by its seed, so fixing that seed with mt_srand()/srand() makes
 * two calls produce an identical result. A real CSPRNG (random_bytes(), and
 * therefore anything built on it) draws from OS-level entropy and is never
 * affected by mt_srand()/srand() at all, so the same test leaves it
 * unpredictable. That difference is what "reject insecure sources" checks.
 */
final class RuidGenerator
{
    /** @var callable(): string */
    private $source;

    public function __construct(callable $source)
    {
        $this->assertSecure($source);
        $this->source = $source;
    }

    public function generate(): string
    {
        return ($this->source)();
    }

    private function assertSecure(callable $source): void
    {
        mt_srand(42);
        srand(42);
        $a = $source();

        mt_srand(42);
        srand(42);
        $b = $source();

        if ($a === $b) {
            throw new RuntimeException(
                'Insecure RNG source rejected: output became reproducible once the PRNG seed was fixed. '
                . 'Use a cryptographically secure source (e.g. random_bytes()) instead of mt_rand()/rand().'
            );
        }
    }

    /**
     * Insecure baseline for the demo: Mersenne Twister, deliberately kept to
     * a small 6-digit ID space (1,000,000 possible values) so collisions are
     * easy to observe within a few thousand generations instead of needing
     * an astronomical sample size.
     */
    public static function insecureSource(): callable
    {
        return static fn (): string => str_pad((string) mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Secure source: 128 bits of OS-backed entropy via random_bytes(),
     * PHP's cryptographically secure RNG (uses /dev/urandom, getrandom(2),
     * or CryptGenRandom depending on platform).
     */
    public static function secureSource(): callable
    {
        return static fn (): string => 'RUID-' . strtoupper(bin2hex(random_bytes(16)));
    }

    /**
     * Stands in for a real CSPRNG outage (e.g. the OS entropy source being
     * unavailable) so Module 2's error handling can be demonstrated without
     * needing to actually break the operating system's RNG.
     */
    public static function failingSecureSource(): callable
    {
        return static function (): string {
            throw new RuntimeException(
                'random_bytes(): unable to gather sufficient random data at /dev/urandom [errno 24: Too many open files]'
            );
        };
    }
}
