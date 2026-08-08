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
        return view('admin.dashboard', [
            'counts' => $this->management->dashboardCounts(),
            'recentElections' => $this->management->recentElections(),
        ]);
    }
}
