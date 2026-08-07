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

final class ProfileController
{
    public function show(Request $request): Response
    {
        return Response::html(View::render('profile/show', [
            'pageTitle' => 'Profile',
            'user' => Auth::requireUser(),
            'errors' => Session::consumeFlash('errors', []),
        ]));
    }

    public function update(Request $request): Response
    {
        Csrf::assertValid($request->input('_token'));
        $user = Auth::requireUser();
        $values = [
            'full_name' => trim((string) $request->input('full_name', '')),
            'email' => trim((string) $request->input('email', '')),
        ];
        try {
            (new UserService())->updateProfile($user, $values['full_name'], $values['email']);
            Session::flash('success', 'Your profile has been updated.');
        } catch (ValidationException $exception) {
            Session::flash('errors', $exception->errors);
            Session::put('_old_input', $values);
        }

        return Response::redirect(url('/profile'));
    }

    public function updatePassword(Request $request): Response
    {
        Csrf::assertValid($request->input('_token'));
        try {
            (new UserService())->changePassword(
                Auth::requireUser(),
                (string) $request->input('current_password', ''),
                (string) $request->input('new_password', ''),
                (string) $request->input('password_confirmation', '')
            );
            Session::flash('success', 'Your password has been updated.');
        } catch (ValidationException $exception) {
            Session::flash('errors', $exception->errors);
        }

        return Response::redirect(url('/profile'));
    }
}
