<?php

declare(strict_types=1);

namespace VoteTrust\Election\Core;

use VoteTrust\Election\Exceptions\AuthenticationException;
use VoteTrust\Election\Exceptions\HttpException;
use VoteTrust\Election\Exceptions\ValidationException;
use VoteTrust\Election\Services\ApiEnvelope;

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
            if ($request->expectsJson()) {
                $response = Response::json(ApiEnvelope::failure(
                    (string) $request->input('requestID', 'UNKNOWN'),
                    $exception->getMessage()
                ), $exception->statusCode);
            } else {
                Session::flash('error', 'Please sign in to continue.');
                $response = Response::redirect(url('/login'));
            }
        } catch (ValidationException $exception) {
            $response = $request->expectsJson()
                ? Response::json(ApiEnvelope::failure(
                    (string) $request->input('requestID', 'UNKNOWN'),
                    $exception->getMessage(),
                    $exception->errors
                ), $exception->statusCode)
                : Response::html(View::render('errors/http', [
                    'statusCode' => $exception->statusCode,
                    'message' => $exception->getMessage(),
                    'details' => $exception->errors,
                ]), $exception->statusCode);
        } catch (HttpException $exception) {
            $response = $request->expectsJson()
                ? Response::json(ApiEnvelope::failure(
                    (string) $request->input('requestID', 'UNKNOWN'),
                    $exception->getMessage()
                ), $exception->statusCode)
                : Response::html(View::render('errors/http', [
                    'statusCode' => $exception->statusCode,
                    'message' => $exception->getMessage(),
                    'details' => [],
                ]), $exception->statusCode);
        } catch (\Throwable $exception) {
            error_log((string) $exception);
            $message = Config::bool('APP_DEBUG', false)
                ? $exception->getMessage()
                : 'An unexpected error occurred.';
            $response = $request->expectsJson()
                ? Response::json(ApiEnvelope::failure('UNKNOWN', $message, [], 'E'), 500)
                : Response::html(View::render('errors/http', [
                    'statusCode' => 500,
                    'message' => $message,
                    'details' => [],
                ]), 500);
        }

        $response->send();
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

