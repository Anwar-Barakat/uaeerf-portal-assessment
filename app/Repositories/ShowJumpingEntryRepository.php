<?php

namespace App\Repositories;

use App\Data\CreateShowJumpingEntryRepositoryData;
use App\Models\ShowJumpingEntry;
use Illuminate\Support\Facades\DB;

class ShowJumpingEntryRepository
{
    public function create(CreateShowJumpingEntryRepositoryData $data): ShowJumpingEntry
    {
        return ShowJumpingEntry::create($data->toArray());
    }

    public function findByCartId(string $cartId): ?ShowJumpingEntry
    {
        return ShowJumpingEntry::where('cart_id', $cartId)->first();
    }

    public function updateStatus(string $cartId, string $status, array $additionalData = []): bool
    {
        return ShowJumpingEntry::where('cart_id', $cartId)
            ->update(array_merge([
                'status' => $status,
            ], $additionalData)) > 0;
    }

    public function markCompleted(string $cartId, string $tranRef): bool
    {
        return $this->updateStatus($cartId, 'completed', [
            'tran_ref' => $tranRef,
            'completed_at' => now(),
            // Clear any rejection from an earlier attempt: a completed record
            // carrying an old error reads as a failure to anyone looking.
            'error_message' => null,
        ]);
    }

    /**
     * The transaction reference is recorded on failure too - the payment was
     * taken, so support needs it to trace or refund.
     */
    public function markFailed(string $cartId, string $errorMessage, ?string $tranRef = null): bool
    {
        return $this->updateStatus($cartId, 'failed', array_filter([
            'error_message' => $errorMessage,
            'tran_ref' => $tranRef,
        ]));
    }

    /**
     * Insert the paid entry into [EEF].[dbo].[ClassEntriesWeb].
     *
     * Column names follow the live schema, not the assessment brief:
     * EventCode/ClassCode (int), RiderID/HorseID (varchar - values look like
     * E0007711 / H000103), Reference (varchar 50, holds the PayTabs tran_ref),
     * dateSubmit, Fee (money). There is no currency or created-at column.
     *
     * dbo.ClassEntriesWeb is the target rather than eefsys.ClassEntriesWeb:
     * Code is an identity column there, whereas the eefsys copy has a composite
     * key with no identity and would require allocating Code by hand.
     * Status uses the portal vocabulary (Accepted/Pending/Rejected/...).
     */
    public function insertToClassEntriesWeb(ShowJumpingEntry $entry, string $tranRef): void
    {
        $table = config('services.federation.class_entries_table', 'dbo.ClassEntriesWeb');

        // NOTE: this writes to the federation database, so it is outside the
        // 'mssql' transaction that processWithTransaction() opens around the
        // staging-table update. The two cannot be made atomic across servers.
        DB::connection('federation')->table($table)->insert([
            'EventCode' => (int) $entry->event_id,
            'ClassCode' => (int) $entry->class_id,
            'UserId' => $entry->user?->federation_user_id,
            'RiderID' => (string) $entry->rider_id,
            'HorseID' => (string) $entry->horse_id,
            'SeasonCode' => $entry->season_code,
            'Status' => 'Pending',
            'Fee' => 150.00,
            'FeeStatus' => '1',
            'Reference' => $tranRef,
            'dateSubmit' => now(),
            'isFetched' => 0,
            'IsLate' => 0,
        ]);
    }

    /**
     * Has an entry already been filed under this payment reference?
     *
     * The reference is the PayTabs transaction ref, unique to one payment, so a
     * match means our own earlier insert landed. Checked before inserting: a
     * retry - after the local write failed, say - must not enter the
     * combination into the class twice on a single payment.
     */
    public function existsForReference(string $reference): bool
    {
        if ($reference === '') {
            return false;
        }

        $table = config('services.federation.class_entries_table', 'dbo.ClassEntriesWeb');

        return DB::connection('federation')->table($table)->where('Reference', $reference)->exists();
    }

    public function findByUserId(int $userId, int $limit = 10): array
    {
        return ShowJumpingEntry::where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    /**
     * @template TReturn
     *
     * @param  \Closure(\Illuminate\Database\Connection): TReturn  $callback
     * @return TReturn
     */
    public function processWithTransaction(\Closure $callback)
    {
        return DB::transaction($callback);
    }
}
