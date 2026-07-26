<?php

namespace App\Services\Soap;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class CalendarService extends BaseSoapClient
{
    protected function getWsdlUrl(): string
    {
        return config('services.soap.calendar_url') . '?WSDL';
    }

    /** Discipline code for Show Jumping, per getDisciplineList(). */
    public const DIVISION_SHOW_JUMPING = '2';

    /**
     * Events for a division, shaped for the entry form.
     *
     * Cached for an hour: the calendar changes rarely but the entry page hits
     * it on every load. RaceID is the EventCode written to ClassEntriesWeb.
     *
     * SearchDivision must be a non-empty array - the service throws a
     * NullReferenceException on an empty one, so there is no "all divisions"
     * call to make.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getEventList(string $divisionCode = self::DIVISION_SHOW_JUMPING): array
    {
        $divisionCode = $divisionCode ?: self::DIVISION_SHOW_JUMPING;

        return Cache::remember('soap_events_' . $divisionCode, 3600, function () use ($divisionCode) {
            $result = $this->call('GetEventList', [
                'SearchDivision' => [$divisionCode],
                'DivisionCode' => $divisionCode,
                'msg' => [],
            ]);

            $events = $result->GetEventListResult->Events ?? [];
            $events = is_array($events) ? $events : [$events];

            return array_values(array_map(fn ($event) => [
                'event_id' => (string) ($event->RaceID ?? ''),
                'name' => (string) ($event->RaceName ?? ''),
                'location' => (string) ($event->RaceLocation ?? ''),
                'club' => (string) ($event->RaceClub ?? ''),
                'division_id' => (string) ($event->DivisionID ?? ''),
                'division' => (string) ($event->DivisionName ?? ''),
                'season_id' => (string) ($event->SeasonID ?? ''),
                'status' => (string) ($event->StatusName ?? ''),
                'opens_at' => $this->date($event->OpeningDate ?? null),
                'closes_at' => $this->date($event->ClosingDate ?? null),
                'starts_at' => $this->date($event->RaceFromDate ?? null),
                'ends_at' => $this->date($event->RaceTOdate ?? null),
            ], $events));
        });
    }

    /**
     * The calendar returns 0001-01-01 for dates that were never set, which
     * would otherwise sort and display as a real date.
     */
    protected function date(mixed $value): ?string
    {
        $date = $value === null ? '' : substr((string) $value, 0, 10);

        return ($date === '' || str_starts_with($date, '0001-01-01')) ? null : $date;
    }

    /**
     * Events accepting entries.
     *
     * Filtered on StatusName, not on dates: every event on this server reports
     * an unset opening and closing date, so the status field is the only
     * reliable signal.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getOpenEvents(string $divisionCode = self::DIVISION_SHOW_JUMPING): array
    {
        $events = array_values(array_filter(
            $this->getEventList($divisionCode),
            fn ($event) => str_contains(mb_strtolower($event['status']), 'open')
        ));

        usort($events, fn ($a, $b) => strcmp((string) $b['starts_at'], (string) $a['starts_at']));

        return $events;
    }

    /**
     * Classes within an event, keyed by event code.
     *
     * ClassCode is only unique within its event - ClassEntriesWeb's key is
     * (EventCode, ClassCode, Code) - so classes have to be offered per event
     * rather than as one global list. There is no web service operation for
     * this, hence the direct read.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getClassesForEvent(int $eventCode): array
    {
        return Cache::remember('soap_event_classes_' . $eventCode, 3600, function () use ($eventCode) {
            return DB::connection('federation')
                ->table('eefsys.ClassesSJ')
                ->select('ClassCode', 'Name', 'EntryFee', 'Classdate')
                ->where('ID', $eventCode)
                ->orderBy('ClassCode')
                ->get()
                ->map(fn ($class) => [
                    'class_id' => (int) $class->ClassCode,
                    'name' => trim((string) $class->Name) ?: 'Class ' . $class->ClassCode,
                    'entry_fee' => $class->EntryFee === null ? null : (float) $class->EntryFee,
                    'date' => $class->Classdate === null ? null : substr((string) $class->Classdate, 0, 10),
                ])
                ->all();
        });
    }

    public function clearCache(string $divisionCode = self::DIVISION_SHOW_JUMPING): void
    {
        Cache::forget('soap_events_' . ($divisionCode ?: self::DIVISION_SHOW_JUMPING));
    }
}
