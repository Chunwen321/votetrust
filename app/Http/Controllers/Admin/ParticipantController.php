<?php

// PIC: Chew Zhexuan

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AdminManagementService;
use Illuminate\View\View;

final class ParticipantController extends Controller
{
    public function __construct(private readonly AdminManagementService $management) {}

    public function show(int $election): View
    {
        return view('admin.participants.show', [
            'election' => $this->management->election($election),
            'voters' => $this->management->voters($election),
            'candidates' => $this->management->candidates($election),
            'counts' => $this->management->participantCounts($election),
        ]);
    }
}
