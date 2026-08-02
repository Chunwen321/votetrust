<?php

declare(strict_types=1);

namespace VoteTrust\Election\Controllers;

use VoteTrust\Election\Core\Auth;
use VoteTrust\Election\Core\Csrf;
use VoteTrust\Election\Core\Request;
use VoteTrust\Election\Core\Response;
use VoteTrust\Election\Core\Session;
use VoteTrust\Election\Core\View;
use VoteTrust\Election\Security\SecurityAuditLogger;

final class AuthController
{
    public function loginForm(Request $request): Response
    {
        if (Auth::user() !== null) {
            return Response::redirect(url(Auth::user()?->isAdmin() ? '/admin/elections' : '/elections'));
        }

        return Response::html(View::render('auth/login', [
            'errors' => Session::consumeFlash('errors', []),
        ]));
    }

    public function login(Request $request): Response
    {
        Csrf::assertValid($request->input('_token'));
        $username = trim((string) $request->input('username', ''));
        $password = (string) $request->input('password', '');
        $errors = [];

        if (!preg_match('/^[A-Za-z0-9_.-]{3,50}$/', $username)) {
            $errors['username'] = 'Enter a valid username.';
        }
        if ($password === '') {
            $errors['password'] = 'Password is required.';
        }

        if ($errors !== [] || !Auth::attempt($username, $password)) {
            (new SecurityAuditLogger())->log('login_failed', ['username' => $username]);
            Session::flash('errors', $errors !== [] ? $errors : ['username' => 'The credentials do not match our records.']);
            Session::put('_old_input', ['username' => $username]);

            return Response::redirect(url('/login'));
        }

        (new SecurityAuditLogger())->log('login_succeeded', ['user_id' => Auth::user()?->id()]);
        Session::flash('success', 'Welcome back.');

        return Response::redirect(url(Auth::user()?->isAdmin() ? '/admin/elections' : '/elections'));
    }

    public function logout(Request $request): Response
    {
        Csrf::assertValid($request->input('_token'));
        Auth::logout();

        return Response::redirect(url('/login'));
    }
}
