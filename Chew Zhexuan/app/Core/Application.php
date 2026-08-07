<?php

declare(strict_types=1);

namespace VoteTrust\UserAdmin\Core;

use VoteTrust\UserAdmin\Exceptions\AuthenticationException;
use VoteTrust\UserAdmin\Exceptions\HttpException;

final class Application
{
    public function __construct(private readonly Router $router)
    {
    }

    public function run(Request $request): void
    {
        $this->sendSecurityHeaders();

        try {
            $response = $this->router->dispatch($request);
        } catch (AuthenticationException $exception) {
            $response = $request->expectsJson()
                ? Response::json($this->apiFailure($request, $exception->getMessage()), $exception->statusCode)
                : $this->redirectToLogin();
        } catch (HttpException $exception) {
            $response = $request->expectsJson()
                ? Response::json($this->apiFailure($request, $exception->getMessage(), $exception->errors), $exception->statusCode)
                : Response::html(View::render('errors/http', [
                    'statusCode' => $exception->statusCode,
                    'message' => $exception->getMessage(),
                    'details' => $exception->errors,
                ]), $exception->statusCode);
        } catch (\Throwable $exception) {
            error_log((string) $exception);
            $message = Config::bool('APP_DEBUG', false) ? $exception->getMessage() : 'An unexpected error occurred.';
            $response = $request->expectsJson()
                ? Response::json($this->apiFailure($request, $message), 500)
                : Response::html(View::render('errors/http', [
                    'statusCode' => 500,
                    'message' => $message,
                    'details' => [],
                ]), 500);
        }

        $response->send();
    }

    private function redirectToLogin(): Response
    {
        Session::flash('error', 'Please sign in to continue.');

        return Response::redirect(url('/login'));
    }

    /** @param array<string, string> $errors */
    private function apiFailure(Request $request, string $message, array $errors = []): array
    {
        return [
            'status' => 'E',
            'message' => $message,
            'requestID' => (string) $request->input('requestID', 'UNKNOWN'),
            'timestamp' => (new \DateTimeImmutable())->format(DATE_ATOM),
            'errors' => $errors,
        ];
    }

    private function sendSecurityHeaders(): void
    {
        header("Content-Security-Policy: default-src 'self'; style-src 'self'; script-src 'self'; img-src 'self' data:; form-action 'self'; frame-ancestors 'none'; base-uri 'self'");
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    }
}
