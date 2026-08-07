<?php

declare(strict_types=1);

namespace VoteTrust\UserAdmin\Controllers;

use VoteTrust\UserAdmin\Core\Auth;
use VoteTrust\UserAdmin\Core\Csrf;
use VoteTrust\UserAdmin\Core\Request;
use VoteTrust\UserAdmin\Core\Response;
use VoteTrust\UserAdmin\Core\Session;
use VoteTrust\UserAdmin\Core\View;
use VoteTrust\UserAdmin\Exceptions\ValidationException;
use VoteTrust\UserAdmin\Services\UserService;

final class AuthController
{
    public function loginForm(Request $request): Response
    {
        $user = Auth::user();
        if ($user !== null) {
            return Response::redirect($this->homeFor($user));
        }

        return Response::html(View::render('auth/login', [
            'user' => null,
            'errors' => Session::consumeFlash('errors', []),
        ]));
    }

    public function login(Request $request): Response
    {
        Csrf::assertValid($request->input('_token'));
        $username = trim((string) $request->input('username', ''));
        $password = (string) $request->input('password', '');
        $errors = [];
        if (preg_match('/^[A-Za-z0-9_.-]{3,50}$/', $username) !== 1) {
            $errors['username'] = 'Enter a valid username.';
        }
        if ($password === '') {
            $errors['password'] = 'Password is required.';
        }

        if ($errors !== [] || !Auth::attempt($username, $password)) {
            Session::flash('errors', $errors !== [] ? $errors : [
                'username' => 'We could not sign you in with those details.',
            ]);
            Session::put('_old_input', ['username' => $username]);

            return Response::redirect(url('/login'));
        }

        $user = Auth::requireUser();
        Session::flash('success', 'Welcome back, ' . (string) ($user['full_name'] ?: $user['username']) . '.');

        return Response::redirect($this->homeFor($user));
    }

    public function registerForm(Request $request): Response
    {
        if (Auth::user() !== null) {
            return Response::redirect($this->homeFor(Auth::requireUser()));
        }

        return Response::html(View::render('auth/register', [
            'user' => null,
            'errors' => Session::consumeFlash('errors', []),
        ]));
    }

    public function register(Request $request): Response
    {
        Csrf::assertValid($request->input('_token'));
        $values = [
            'username' => trim((string) $request->input('username', '')),
            'full_name' => trim((string) $request->input('full_name', '')),
            'email' => trim((string) $request->input('email', '')),
            'ic_number' => trim((string) $request->input('ic_number', '')),
        ];

        try {
            $user = (new UserService())->register(
                $values['username'],
                $values['full_name'],
                $values['email'],
                $values['ic_number'],
                (string) $request->input('password', ''),
                (string) $request->input('password_confirmation', '')
            );
            Auth::attempt((string) $user['username'], (string) $request->input('password', ''));
            Session::flash('success', 'Your user account is ready. An organiser will enrol you as a voter when your name and IC appear on an election list.');

            return Response::redirect(url('/dashboard'));
        } catch (ValidationException $exception) {
            Session::flash('errors', $exception->errors);
            Session::put('_old_input', $values);

            return Response::redirect(url('/register'));
        }
    }

    public function logout(Request $request): Response
    {
        Csrf::assertValid($request->input('_token'));
        Auth::logout();

        return Response::redirect(url('/login'));
    }

    /** @param array<string, mixed> $user */
    private function homeFor(array $user): string
    {
        if (Auth::isAdmin($user)) {
            return url('/admin');
        }
        return url('/dashboard');
    }
}
