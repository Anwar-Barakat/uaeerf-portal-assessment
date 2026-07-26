<?php

namespace App\Repositories;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardRepository
{
    public function statsForUser(int $userId): array
    {
        return [
            'activeRegistrations' => $this->countCompleted('rider_registrations', $userId),
            'competitionEntries' => $this->countCompleted('show_jumping_entries', $userId),
            'renewals' => $this->countCompleted('rider_renewals', $userId),
        ];
    }

    public function monthlyActivityForUser(int $userId, int $months = 6): array
    {
        $activity = [];

        for ($i = $months - 1; $i >= 0; $i--) {
            $start = Carbon::now()->startOfMonth()->subMonths($i);
            $end = (clone $start)->endOfMonth();

            $activity[] = [
                'month' => $start->format('M'),
                'registrations' => $this->countCompletedBetween('rider_registrations', $userId, $start, $end),
                'entries' => $this->countCompletedBetween('show_jumping_entries', $userId, $start, $end),
            ];
        }

        return $activity;
    }

    private function countCompleted(string $table, int $userId): int
    {
        return DB::table($table)
            ->where('user_id', $userId)
            ->where('status', 'completed')
            ->count();
    }

    private function countCompletedBetween(string $table, int $userId, Carbon $start, Carbon $end): int
    {
        return DB::table($table)
            ->where('user_id', $userId)
            ->where('status', 'completed')
            ->whereBetween('created_at', [$start, $end])
            ->count();
    }

    public function paginateRegistrations(int $userId, ?string $search = null, int $perPage = 10)
    {
        $query = DB::table('rider_registrations')
            ->where('user_id', $userId);

        if ($search) {
            $escapedSearch = str_replace(['%', '_'], ['\%', '\_'], $search);
            $query->where(function ($q) use ($escapedSearch) {
                $q->where('rider_name', 'like', "%{$escapedSearch}%")
                  ->orWhere('tran_ref', 'like', "%{$escapedSearch}%");
            });
        }

        return $query->orderBy('created_at', 'desc')
            ->paginate($perPage, ['id', 'rider_name', 'date_of_birth', 'status', 'tran_ref', 'created_at']);
    }

    public function paginateRenewals(int $userId, ?string $search = null, int $perPage = 10)
    {
        $query = DB::table('rider_renewals')
            ->where('user_id', $userId);

        if ($search) {
            $escapedSearch = str_replace(['%', '_'], ['\%', '\_'], $search);
            $query->where(function ($q) use ($escapedSearch) {
                $q->where('rider_id', 'like', "%{$escapedSearch}%")
                  ->orWhere('season_id', 'like', "%{$escapedSearch}%")
                  ->orWhere('tran_ref', 'like', "%{$escapedSearch}%");
            });
        }

        return $query->orderBy('created_at', 'desc')
            ->paginate($perPage, ['id', 'rider_id', 'season_id', 'status', 'tran_ref', 'created_at']);
    }

    public function paginateEntries(int $userId, ?string $search = null, int $perPage = 10)
    {
        $query = DB::table('show_jumping_entries')
            ->where('user_id', $userId);

        if ($search) {
            $escapedSearch = str_replace(['%', '_'], ['\%', '\_'], $search);
            $query->where(function ($q) use ($escapedSearch) {
                $q->where('rider_id', 'like', "%{$escapedSearch}%")
                  ->orWhere('horse_id', 'like', "%{$escapedSearch}%")
                  ->orWhere('event_id', 'like', "%{$escapedSearch}%")
                  ->orWhere('tran_ref', 'like', "%{$escapedSearch}%");
            });
        }

        return $query->orderBy('created_at', 'desc')
            ->paginate($perPage, ['id', 'rider_id', 'horse_id', 'event_id', 'class_id', 'status', 'tran_ref', 'created_at']);
    }
}
