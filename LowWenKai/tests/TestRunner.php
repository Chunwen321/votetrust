<?php

declare(strict_types=1);

namespace VoteTrust\Election\Tests;

final class TestRunner
{
    /** @var list<array{name:string,test:callable}> */
    private array $tests = [];

    public function add(string $name, callable $test): void
    {
        $this->tests[] = compact('name', 'test');
    }

    public function run(): int
    {
        $failures = 0;
        foreach ($this->tests as $test) {
            try {
                $test['test']();
                echo "[PASS] {$test['name']}\n";
            } catch (\Throwable $exception) {
                $failures++;
                echo "[FAIL] {$test['name']}\n       {$exception->getMessage()}\n";
            }
        }

        echo "\n" . (count($this->tests) - $failures) . '/' . count($this->tests) . " tests passed.\n";

        return $failures === 0 ? 0 : 1;
    }

    public static function assertTrue(bool $condition, string $message = 'Expected condition to be true.'): void
    {
        if (!$condition) {
            throw new \RuntimeException($message);
        }
    }

    public static function assertSame(mixed $expected, mixed $actual, string $message = ''): void
    {
        if ($expected !== $actual) {
            $detail = $message !== '' ? $message : 'Values are not identical.';
            throw new \RuntimeException($detail . ' Expected ' . var_export($expected, true) . ', received ' . var_export($actual, true) . '.');
        }
    }

    /** @param class-string<\Throwable> $exceptionClass */
    public static function assertThrows(string $exceptionClass, callable $callback, string $message = ''): void
    {
        try {
            $callback();
        } catch (\Throwable $exception) {
            if ($exception instanceof $exceptionClass) {
                return;
            }
            throw new \RuntimeException('Expected ' . $exceptionClass . ', received ' . $exception::class . '.');
        }

        throw new \RuntimeException($message !== '' ? $message : 'Expected exception ' . $exceptionClass . ' was not thrown.');
    }
}

