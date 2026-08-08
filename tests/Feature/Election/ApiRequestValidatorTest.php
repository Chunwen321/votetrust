<?php

// PIC: Low Wen Kai - Election Management Module

namespace Tests\Feature\Election;

use App\Contracts\Clock;
use App\Security\ApiRequestValidator;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class ApiRequestValidatorTest extends TestCase
{
    public function test_it_accepts_a_traceable_fresh_envelope(): void
    {
        $now = CarbonImmutable::parse('2026-08-08 12:00:00');
        $clock = new class($now) implements Clock
        {
            public function __construct(private readonly CarbonImmutable $now) {}

            public function now(): CarbonImmutable
            {
                return $this->now;
            }
        };

        $result = (new ApiRequestValidator($clock))->validateEnvelope([
            'requestID' => 'CANDIDATE-REQ-001',
            'timestamp' => $now->toAtomString(),
        ]);

        self::assertSame('CANDIDATE-REQ-001', $result['requestID']);
    }

    public function test_it_rejects_a_stale_envelope(): void
    {
        $now = CarbonImmutable::parse('2026-08-08 12:00:00');
        $clock = new class($now) implements Clock
        {
            public function __construct(private readonly CarbonImmutable $now) {}

            public function now(): CarbonImmutable
            {
                return $this->now;
            }
        };

        $this->expectException(ValidationException::class);
        (new ApiRequestValidator($clock))->validateEnvelope([
            'requestID' => 'CANDIDATE-REQ-002',
            'timestamp' => $now->subMinutes(10)->toAtomString(),
        ]);
    }
}
