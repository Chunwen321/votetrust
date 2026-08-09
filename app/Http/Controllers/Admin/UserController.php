<?php

// PIC: Chew Zhexuan

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AdminManagementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class UserController extends Controller
{
    public function __construct(private readonly AdminManagementService $management) {}

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(AdminManagementService::ACCOUNT_STATUSES)],
        ]);

        return view('admin.users.index', [
            'users' => $this->management->members(
                (string) ($filters['search'] ?? ''),
                isset($filters['status']) ? (string) $filters['status'] : null,
            ),
            'accountCounts' => $this->management->accountStatusCounts(),
            'accountStatuses' => AdminManagementService::ACCOUNT_STATUSES,
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(AdminManagementService::ACCOUNT_STATUSES)],
        ]);

        $this->management->changeMemberAccountStatus(
            $request->user(),
            $user,
            (string) $validated['status'],
        );

        return redirect()
            ->route('admin.users.index')
            ->with('success', "{$user->display_name}'s account access was updated.");
    }
}
