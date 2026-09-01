<?php

// PIC: Chew Zhexuan

declare(strict_types=1);

namespace App\Services;

use App\Security\Authentication\DisableAccountAfterFailedAttemptsStrategy;
use App\Security\Session\FullSessionTerminationStrategy;
use ReflectionMethod;
use RuntimeException;

final class SecurityPracticeSourcePresenter
{
    /**
     * @return array<string, array{label:string,file:string,startLine:?int,endLine:?int,code:string,production:bool}>
     */
    public function snippets(): array
    {
        return [
            'productionSession' => $this->method(
                FullSessionTerminationStrategy::class,
                'terminate',
                'Production logout implementation',
            ),
            'productionAuthentication' => $this->method(
                UserService::class,
                'attemptAuthentication',
                'Production authentication flow',
            ),
            'productionLockout' => $this->method(
                DisableAccountAfterFailedAttemptsStrategy::class,
                'recordFailure',
                'Production failed-attempt strategy',
            ),
            'vulnerableSession' => $this->comparison(
                'Vulnerable logout comparison',
                <<<'PHP'
public function terminate(Request $request): void
{
    // logout(), invalidate(), and regenerateToken() are omitted.
    // The controller redirects, but the authenticated session remains usable.
}
PHP,
            ),
            'vulnerableAuthentication' => $this->comparison(
                'Vulnerable authentication comparison',
                <<<'PHP'
public function attemptAuthentication(string $email, string $password, bool $remember = false): LoginAttemptResult
{
    $user = User::query()->where('email', mb_strtolower(trim($email)))->first();
    $passwordMatches = Hash::check($password, $user?->password ?? self::DUMMY_PASSWORD_HASH);

    if (! $user instanceof User || ! $passwordMatches) {
        return LoginAttemptResult::invalidCredentials();
    }

    Auth::login($user, $remember);

    return LoginAttemptResult::success();
}
PHP,
            ),
        ];
    }

    /**
     * @param  class-string  $class
     * @return array{label:string,file:string,startLine:int,endLine:int,code:string,production:bool}
     */
    private function method(string $class, string $method, string $label): array
    {
        $reflection = new ReflectionMethod($class, $method);
        $file = $reflection->getFileName();
        $startLine = $reflection->getStartLine();
        $endLine = $reflection->getEndLine();

        if (! is_string($file) || $startLine === false || $endLine === false) {
            throw new RuntimeException("Unable to read {$class}::{$method} source.");
        }

        $lines = file($file);
        if ($lines === false) {
            throw new RuntimeException("Unable to open {$file}.");
        }

        $relativeFile = str_replace('\\', '/', substr($file, strlen(base_path()) + 1));

        return [
            'label' => $label,
            'file' => $relativeFile,
            'startLine' => $startLine,
            'endLine' => $endLine,
            'code' => implode('', array_slice($lines, $startLine - 1, $endLine - $startLine + 1)),
            'production' => true,
        ];
    }

    /** @return array{label:string,file:string,startLine:null,endLine:null,code:string,production:bool} */
    private function comparison(string $label, string $code): array
    {
        return [
            'label' => $label,
            'file' => 'Demo-only comparison — not present in production',
            'startLine' => null,
            'endLine' => null,
            'code' => $code.PHP_EOL,
            'production' => false,
        ];
    }
}
