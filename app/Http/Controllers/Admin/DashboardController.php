<?php

// PIC: Chew Zhexuan

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AdminManagementService;
use Illuminate\View\View;

final class DashboardController extends Controller
{
    public function __construct(private readonly AdminManagementService $management) {}

    public function __invoke(): View
    {
        $overview = $this->management->dashboardOverview();

        return view('admin.dashboard', [
            'counts' => $overview['counts'],
            'recentElections' => $overview['recentElections'],
            'pendingElectionApprovals' => $overview['pendingElectionApprovals'],
        ]);
    }
}
