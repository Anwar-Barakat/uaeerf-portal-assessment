<?php

namespace App\Http\Controllers;

use App\Models\RiderRegistration;
use App\Models\RiderRenewal;
use App\Models\ShowJumpingEntry;
use App\Repositories\DashboardRepository;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HistoryController extends Controller
{
    public function __construct(private readonly DashboardRepository $dashboard)
    {
    }

    public function registrations(Request $request): Response
    {
        $userId = $request->user()->id;
        $search = $request->input('search');
        $registrations = $this->dashboard->paginateRegistrations($userId, $search);

        return Inertia::render('history/registrations', [
            'registrations' => $registrations,
            'filters' => ['search' => $search],
        ]);
    }

    public function renewals(Request $request): Response
    {
        $userId = $request->user()->id;
        $search = $request->input('search');
        $renewals = $this->dashboard->paginateRenewals($userId, $search);

        return Inertia::render('history/renewals', [
            'renewals' => $renewals,
            'filters' => ['search' => $search],
        ]);
    }

    public function entries(Request $request): Response
    {
        $userId = $request->user()->id;
        $search = $request->input('search');
        $entries = $this->dashboard->paginateEntries($userId, $search);

        return Inertia::render('history/entries', [
            'entries' => $entries,
            'filters' => ['search' => $search],
        ]);
    }

    /**
     * Every show page is scoped to the signed-in user: these records carry
     * personal data and payment references, so another user's id must 404
     * rather than render.
     */
    public function showRegistration(Request $request, int $id): Response
    {
        $registration = RiderRegistration::where('user_id', $request->user()->id)
            ->findOrFail($id);

        return Inertia::render('history/registration-show', [
            'registration' => $registration,
        ]);
    }

    public function showRenewal(Request $request, int $id): Response
    {
        $renewal = RiderRenewal::where('user_id', $request->user()->id)
            ->findOrFail($id);

        return Inertia::render('history/renewal-show', [
            'renewal' => $renewal,
        ]);
    }

    public function showEntry(Request $request, int $id): Response
    {
        $entry = ShowJumpingEntry::where('user_id', $request->user()->id)
            ->findOrFail($id);

        return Inertia::render('history/entry-show', [
            'entry' => $entry,
        ]);
    }
}
