<?php

// PIC: Chew Zhexuan

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\SecurityPracticeDemoService;
use App\Services\SecurityPracticeSourcePresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class SecurityPracticeDemoController extends Controller
{
    public function __construct(
        private readonly SecurityPracticeDemoService $demo,
        private readonly SecurityPracticeSourcePresenter $source,
    ) {}

    public function show(Request $request): View
    {
        $this->ensureAvailable();

        return view('security.demo', [
            'maximumAttempts' => max(1, (int) config('votetrust-user.auth.max_failed_attempts', 5)),
            'demoEmail' => SecurityPracticeDemoService::DEMO_EMAIL,
            'demoPassword' => SecurityPracticeDemoService::DEMO_PASSWORD,
            'demoState' => $this->demo->state($request),
            'sourceSnippets' => $this->source->snippets(),
        ]);
    }

    public function action(Request $request): JsonResponse
    {
        $this->ensureAvailable();
        $action = is_string($request->input('action')) ? $request->input('action') : '';

        return response()->json($this->demo->perform($request, $action, $request->all()));
    }

    private function ensureAvailable(): void
    {
        abort_unless(
            (bool) config('votetrust-user.security_demo.enabled')
                && app()->environment(['local', 'testing']),
            404,
        );
    }
}
