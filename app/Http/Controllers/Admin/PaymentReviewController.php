<?php

// PIC: Low Wen Kai, Risky Puah Kaho - Election payment administration

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Exceptions\BusinessRuleException;
use App\Exceptions\ConcurrencyException;
use App\Http\Controllers\Controller;
use App\Models\Election;
use App\Services\ElectionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class PaymentReviewController extends Controller
{
    public function __construct(private readonly ElectionService $elections) {}

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'verification' => ['nullable', Rule::in(['awaiting', 'verified'])],
        ]);
        $search = trim((string) ($filters['search'] ?? ''));
        $verification = $filters['verification'] ?? null;

        $reviewable = Election::query()
            ->where('status', Election::PENDING_ADMIN_APPROVAL)
            ->whereNotNull('payment_reference');

        $awaitingCount = (clone $reviewable)->whereNull('payment_verified_at')->count();
        $verifiedCount = (clone $reviewable)->whereNotNull('payment_verified_at')->count();

        $payments = (clone $reviewable)
            ->with('organizer')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query
                        ->where('title', 'like', "%{$search}%")
                        ->orWhere('payment_reference', 'like', "%{$search}%")
                        ->orWhereHas('organizer', fn ($organizer) => $organizer
                            ->where('full_name', 'like', "%{$search}%")
                            ->orWhere('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%"));
                });
            })
            ->when($verification === 'awaiting', fn ($query) => $query->whereNull('payment_verified_at'))
            ->when($verification === 'verified', fn ($query) => $query->whereNotNull('payment_verified_at'))
            ->orderByRaw('CASE WHEN payment_verified_at IS NULL THEN 0 ELSE 1 END')
            ->latest('updated_at')
            ->paginate(15)
            ->withQueryString();

        return view('admin.payments.index', [
            'payments' => $payments,
            'awaitingCount' => $awaitingCount,
            'verifiedCount' => $verifiedCount,
            'filters' => ['search' => $search, 'verification' => $verification],
        ]);
    }

    public function verify(Request $request, int $election): RedirectResponse
    {
        $input = $request->validate([
            'current_password' => ['required', 'string'],
            'version' => ['required', 'integer', 'min:1'],
        ]);

        try {
            $this->elections->transition(
                $request->user(),
                $election,
                'verify_payment',
                (string) $input['current_password'],
                ['version' => (int) $input['version']],
            );

            return redirect()->route('admin.payments.index')
                ->with('success', 'Hosting payment verified. The election is now ready for final approval.');
        } catch (BusinessRuleException|ConcurrencyException $exception) {
            return back()->with('error', $exception->getMessage());
        }
    }
}
