<?php

namespace App\Services\Soap;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class RegistrationsService extends BaseSoapClient
{
    protected function getWsdlUrl(): string
    {
        return config('services.soap.registrations_url') . '?WSDL';
    }

    /**
     * Federation user ids the service will accept a person registration for.
     *
     * Submit_PersonNewRegistration answers "Invalid User ID" for anything not
     * in this list, whatever the rest of the payload looks like. Checking it up
     * front is what stops us charging someone for a submission that is certain
     * to be refused.
     *
     * @return array<int, int>
     */
    public function acceptedUserIds(): array
    {
        return Cache::remember('soap_dec_user_ids', 3600, function () {
            $result = $this->call('Get_DEC_UserList', ['msg' => []]);

            $users = $result->Get_DEC_UserListResult->WebUsers ?? [];
            $users = is_array($users) ? $users : [$users];

            return array_values(array_map(static fn ($user) => (int) $user->UserId, $users));
        });
    }

    public function acceptsUserId(?int $userId): bool
    {
        return $userId !== null && in_array($userId, $this->acceptedUserIds(), true);
    }

    /*
     * Get_PersonRenewal is deliberately not used to decide whether a rider can
     * be renewed. It only reports applications belonging to the user id passed
     * to it, while the service refuses a rider who has an application from
     * *any* portal account - so a rider whose application belonged to another
     * account looked renewable and was turned away at the payment step. See
     * RidersService::ridersWithRenewalInFlight(), which reads them all.
     */

    /**
     * Why this rider cannot be renewed for this season, or null if they can.
     *
     * There is no dry-run operation and the federation database does not record
     * the condition in a form we can query, so this asks the service itself.
     * The probe deliberately omits Weight, which the service always rejects
     * ("Please enter a valid weight in kilogram") - so it can never create an
     * application, while still reporting the blocking conditions we care about,
     * such as "Athelete already register for the season".
     *
     * Without this, a rider who cannot be renewed is only discovered after the
     * money has been taken.
     */
    public function renewalBlockedReason(
        string $riderId,
        int $seasonCode,
        int $divisionId,
        int $userId,
        ?int $jumpingDivisionLevel = null,
        ?string $competitionLevel = null,
    ): ?string {
        // Every field except Weight must be valid. An incomplete payload draws
        // a deserialisation error that aborts the service's validation before
        // it reaches the "already registered" check, so the probe would report
        // a blocked rider as renewable.
        $result = $this->submitPersonRenewal([
            'PayType' => 'ONLINE',
            'UserId' => $userId,
            'DivisionID' => $divisionId,
            'PersonID' => $riderId,
            'SeasonCode' => $seasonCode,
            'RegisterSeason' => 1,
            'ReferenceNumber' => 'ELIGIBILITY-CHECK',
            'DateSubmit' => date('c'),
            'JumpingDivisionLevel' => (int) ($jumpingDivisionLevel ?? 0),
            'Complevel' => (string) ($competitionLevel ?? ''),
            // Weight intentionally absent - see above.
        ]);

        if (!empty($result->Submit_PersonRenewalResult)) {
            // The service accepted a payload we expected it to reject, so it
            // has just created an application we did not intend.
            Log::critical('Renewal eligibility probe unexpectedly succeeded', [
                'rider_id' => $riderId,
                'season_code' => $seasonCode,
            ]);

            return null;
        }

        foreach ((array) ($result->msg->string ?? []) as $message) {
            $text = (string) $message;

            // "Athelete already register for the season ..." (sic)
            if (str_contains(mb_strtolower($text), 'already')) {
                return $text;
            }
        }

        return null;
    }

    public function submitPersonNewRegistration(array $person, int $personType = 1): object
    {
        $defaults = [
            'PayType' => '',
            'FormId' => 0,
            'UserId' => 0,
            'RefNum' => '',
            'RefKey' => '',
            'Name' => '',
            'FatherName' => '',
            'Nationality' => '',
            'NationalityID' => '',
            'Dob' => '1900-01-01T00:00:00',
            'Sex' => '',
            'SexID' => '',
            'BloodGroup' => '',
            'Address' => '',
            'PoBox' => '',
            'City' => '',
            'CityID' => '',
            'Country' => '',
            'CountryID' => '',
            'Telephone' => '',
            'Fax' => '',
            'Mobile' => '',
            'Email' => '',
            'StableID' => '',
            'Club' => '',
            'OtherClub' => '',
            'Kin' => '',
            'AppRelationship' => '',
            'ContactTel' => '',
            'FEIActive' => 0,
            'FEIRegistration' => '',
            'FEIRegistrationDate' => '1900-01-01T00:00:00',
            'LicenseNumber' => '',
            'LicenseNumberDate' => '1900-01-01T00:00:00',
            'Division' => '',
            'DivisionId' => 0,
            'HomeAddress' => '',
            'HomeCity' => '',
            'HomeCityId' => 0,
            'HomeCountry' => '',
            'HomeCountryId' => 0,
            'HomePhone' => '',
            'HomeFax' => '',
            'HomeMobile' => '',
            'Justification' => '',
            'Remarks' => '',
            'Category' => '',
            'JumpingDivisionLevel' => '',
            'RegistrationType' => '',
            'BBMPIN' => '',
            'IPHONEPIN' => '',
            'DateSubmit' => date('c'),
            'Status' => '',
            'StatusId' => 0,
            'IsCompleted' => 0,
            'IsFetched' => 0,
            'RegisterSeason' => 1,
            'RegisterFEI' => 0,
            'isRegisterLongines' => 0,
            'SeasonCode' => 0,
            'DocumentCheck' => 0,
            'Weight' => '',
            'EID' => '',
            'Browser' => '',
            'ReferenceNumber' => '',
            'SourceID' => 0,
            'VisaCategory' => 0,
            'UAENationalCategory' => '',
            'Education' => '',
        ];

        return $this->call('Submit_PersonNewRegistration', [
            'PersonType' => $personType,
            'person' => array_merge($defaults, $person),
            'msg' => [],
        ]);
    }

    public function submitPersonRenewal(array $person, int $personType = 1): object
    {
        $defaults = [
            'PayType' => '',
            'Weight' => '',
            'EID' => '',
            'MobileNo' => '',
            'Email' => '',
            'StableID' => '',
            'JumpingDivisionLevel' => 0,
            'FormID' => 0,
            'UserId' => 0,
            'PersonID' => '',
            'DivisionID' => 0,
            'Complevel' => '',
            'DateSubmit' => date('c'),
            'ApprovalDate' => '1900-01-01T00:00:00',
            'ApprovedBy' => '',
            'Status' => '',
            'StatusID' => '',
            'isFetched' => 0,
            'RegisterSeason' => 1,
            'RegisterFEI' => 0,
            'RegisterLongines' => 0,
            'Remarks' => '',
            'SeasonCode' => 0,
            'DocumentCheck' => 0,
            'ReferenceNumber' => '',
            // Declared as a string in the WSDL, but the service casts it to an
            // Integer server-side: an empty value fails deserialisation with
            // "Conversion from string "" to type 'Integer' is not valid".
            'SourceID' => '0',
            'VisaCategory' => 0,
            'UAENationalCategory' => '',
            'Education' => '',
        ];

        return $this->call('Submit_PersonRenewal', [
            'PersonType' => $personType,
            'Person' => array_merge($defaults, $person),
            'msg' => [],
        ]);
    }

    public function submitHorseNewRegistration(array $data): object
    {
        return $this->call('Submit_HorseNewRegistration', $data);
    }

    public function submitHorseRenewal(array $data): object
    {
        return $this->call('Submit_HorseRenewal', $data);
    }

    public function getHorseNewRegistration(string $registrationId): object
    {
        return $this->call('Get_HorseNewRegistration', [
            'registrationId' => $registrationId,
        ]);
    }

    public function getHorseRenewal(string $renewalId): object
    {
        return $this->call('Get_HorseRenewal', [
            'renewalId' => $renewalId,
        ]);
    }

    public function getHorseOwner(string $ownerId): object
    {
        return $this->call('Get_HorseOwner', [
            'ownerId' => $ownerId,
        ]);
    }

    public function getHorseTrainer(string $trainerId): object
    {
        return $this->call('Get_HorseTrainer', [
            'trainerId' => $trainerId,
        ]);
    }

    public function getDECStableList(): array
    {
        $result = $this->call('getDECStableList');
        return $result->getDECStableListResult ?? [];
    }

    public function getDECUserList(): array
    {
        $result = $this->call('Get_DEC_UserList');
        return $result->Get_DEC_UserListResult ?? [];
    }
}
