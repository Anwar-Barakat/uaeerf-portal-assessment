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
     * Insert the paid entry into [EEF].[eefsys].[ClassEntriesWeb].
     *
     * This is the table the federation's own system reads - it holds ~136k
     * entries. The dbo copy of the same name is a near-empty scratch table, and
     * writing there means the entry exists nowhere the federation can see it.
     *
     * Code is the primary key and is not an identity column, so it has to be
     * allocated here. It is taken as MAX(Code) + 1 inside a transaction holding
     * an update lock, so two concurrent entries cannot be handed the same
     * number.
     *
     * Column names and values follow the live schema and the conventions of the
     * rows already in it: FeeStatus is the word "PAID", not a flag; RiderID and
     * HorseID are varchar (E0007711 / H000103); Reference (varchar 50) holds
     * the PayTabs transaction ref. There is no currency or created-at column.
     */
    public function insertToClassEntriesWeb(ShowJumpingEntry $entry, string $tranRef): void
    {
        $table = config('services.federation.class_entries_table', 'eefsys.ClassEntriesWeb');
        $connection = DB::connection('federation');

        // Writes to the federation database, so it cannot be made atomic with
        // the local staging update - the two live on different servers.
        $connection->transaction(function () use ($connection, $table, $entry, $tranRef) {
            $code = (int) $connection
                ->selectOne("SELECT ISNULL(MAX(Code), 0) + 1 AS NextCode FROM {$table} WITH (UPDLOCK, HOLDLOCK)")
                ->NextCode;

            $connection->table($table)->insert([
                'Code' => $code,
                'EventCode' => (int) $entry->event_id,
                'ClassCode' => (int) $entry->class_id,
                'UserId' => $entry->user?->federation_user_id,
                'RiderID' => (string) $entry->rider_id,
                'HorseID' => (string) $entry->horse_id,
                'SeasonCode' => $entry->season_code,
                // Awaiting the federation's own review; isFetched marks that
                // their system has not pulled it in yet.
                'Status' => 'Pending',
                'isFetched' => 0,
                'Fee' => 150.00,
                'FeeStatus' => 'PAID',
                'Reference' => $tranRef,
                'dateSubmit' => now(),
                'IsLate' => 0,
                'IsPreRideLate' => 0,
                'IsFEIValid' => 0,
                'AcceptTerms' => 1,
                'Review' => 0,
            ]);
        });
    }

    public function existsForReference(string $reference): bool
    {
        if ($reference === '') {
            return false;
        }

        $table = config('services.federation.class_entries_table', 'eefsys.ClassEntriesWeb');

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
