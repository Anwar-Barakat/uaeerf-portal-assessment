# Federation Integration Notes

Findings from Task 0 reconnaissance against the live UAEERF development
environment. Several contradict the assessment brief; where they do, the brief
is noted and the observed behaviour is what the code implements.

## Database

| | Brief | Actual |
|---|---|---|
| Database | `[WEBSQL].[EEFRegistration]` | **`EEF`**, on the host in `FEDERATION_DB_HOST` (named instance, static port) |
| User table | `dbo.UserProfile` | **`eefsys.WebUserProfile`** |
| Entries table | `dbo.ClassEntriesWeb` | **`eefsys.ClassEntriesWeb`** - the table the federation reads (~136k rows). The `dbo` copy of the same name holds a dozen rows and is invisible to their system. |

Connecting with database `EEFRegistration` fails outright:

```
Msg 4060: Cannot open database "EEFRegistration" requested by the login.
```

The assessment login holds `db_owner`, `db_datareader` and `db_datawriter`,
with default schema `dbo`.

### `eefsys.WebUserProfile`

```
UserId int NOT NULL (PK, NOT identity - inserts must allocate it)
Email nvarchar(4000)      Password nvarchar(250)     Fname nvarchar(50)
Lname nvarchar(50)        MobileNo nvarchar(max)     Dob datetime
Stable_ID nvarchar(max)   Discipline nvarchar(50)    OLD_ID int
IsStableAuthorized bit    IsSynced bit               UserGender int
DateRegistered datetime   AccountsCustomerID varchar(500)
```

There is no `dbo.UserProfile` and no synonym for one. The columns named in the
brief (`FullName`, `MobileNumber`, `City`, `Country`, `Address`, `Status`) do
not exist.

**Passwords are stored in plain text.** The length distribution proves it —
3,380 rows are empty, the rest cluster between 3 and 20 characters. The only
60-character values are bcrypt hashes written by assessment candidates'
applications.

This application never writes plain text. `UserProfile::verifyPassword()`
accepts bcrypt *and* legacy plain text on read so existing portal accounts can
sign in, but registration always hashes. Flagged rather than replicated.

### `eefsys.ClassEntriesWeb`

```
EventCode int NOT NULL    ClassCode int NOT NULL     Code int NOT NULL (primary key, no identity)
UserId int                RiderID varchar(50)        RiderStableID varchar(50)
HorseID varchar(50)       StableID nchar(10)         TrainerID nchar(10)
OwnerID nchar(10)         isFetched tinyint          Status nvarchar(50)
Remarks nvarchar(max)     dateSubmit datetime        IsLate bit
IsPreRideLate bit         FEIRemarks nvarchar(max)   IsFEIValid bit
SeasonCode int            Fee money                  FeeStatus nvarchar(max)
Reference varchar(50)
```

The table exists in two schemas, and **the schema matters**: `eefsys` holds
136,580 entries and is what the federation's own system reads; `dbo` holds a
dozen and is a scratch copy. An entry written to `dbo` is invisible to the
federation, so this application targets `eefsys`.

`PK_ClassEntriesWeb` is `Code` alone - unique, not an identity column, so it
must be allocated on insert. `insertToClassEntriesWeb()` takes `MAX(Code) + 1`
inside a transaction holding `UPDLOCK, HOLDLOCK`, so two concurrent entries
cannot be given the same number.

`FeeStatus` is the word `PAID`, not a flag - matching the rows already there.

`RiderID` and `HorseID` are **varchar**, holding values like `E0007711` and
`H000103` — never coerce them to integers. `Reference` carries the PayTabs
`tran_ref`. There is no currency or created-at column.

Status vocabulary in use: `Accepted` (96,410), `Pending` (15,712), `Rejected`
(11,353), `Substituted` (6,672), `Withdrawn` (6,255), `Validated` (169).
`FeeStatus` is numerically coded (`1`, `3`, `4`). The handful of `APPROVED` /
`PAID` rows are other candidates' test data, not the portal's convention.

### Other tables used

- `eefsys.HorseMaster` — horse directory (32,933 rows), keyed by `HorseID`
  varchar with `StableID`, `Name`, `CurrentName`, `Active`, `ActiveSeason`.
- `eefsys.VIEW_Registrations_WithUserID` — riders by stable, filtered on
  `Registrant = 'Rider'`.
- `eefsys.Registration` — `WebUserID` is the federation user id on submitted
  registrations.

## SOAP web services

Authentication is a handshake against `WSAuthentication.Login` with the system
credentials, cached for an hour, performed before every other call
(`BaseSoapClient::ensureAuthenticated`). `Login` sets an `EEFWSAuthentication`
cookie.

### `Submit_PersonNewRegistration` rejects almost every user

The critical constraint for Task 3. `UserId` must belong to a whitelist of
**53 accounts** returned by `Get_DEC_UserList`:

```
150, 165, 175, 180, 190, 194, 415, 423, 427, 433, 441, 442, 445, 452, 454,
472, 507, 515, 538, 777, 877, 907, 941, 1079, 1163, 1300, 1354, 1403, 1407,
1409, 1744, 1768, 1791, 1833, 1847, 2139, 2252, 2263, 2324, 2368, 2397, 2475,
4889, 4890, 4936, 6104, 6187, 6198, 6332, 6336, 6409, 6415, 6429
```

Any other value returns `Invalid User ID`, including ids that exist in
`eefsys.WebUserProfile` and even ids with real rows in `eefsys.Registration`
(6422). Tested and ruled out:

- Passing the local `users.id` — wrong, and fixed, but not the cause.
- Creating the profile in `eefsys.WebUserProfile` — still rejected.
- Setting `Stable_ID` + `IsStableAuthorized = 1` to match accepted accounts —
  still rejected. (1,070 profiles carry that flag; only 53 are accepted.)
- Propagating the `EEFWSAuthentication` cookie to the registrations client —
  still rejected.

The web service therefore validates against a user store this login cannot
see — consistent with the brief's reference to `WEBSQL.EEFRegistration`, a
different server from the `EEF` database above.

**Consequence: a self-registered account cannot complete a rider registration.**
Demonstrating the flow end-to-end requires one of the 53 whitelisted ids.

Note that `Invalid User ID` arrives as one item in a list of validation
messages, so a partially-filled probe is enough to test id acceptance without
creating a record:

```php
$result = $registrationsService->submitPersonNewRegistration(['UserId' => 150]);
// "Invalid User ID" absent => the id is accepted.
```

### `WSHorses` is unusable on this server

`SearchHorseListV5` did not respond within 120 s for any filter combination
tested (by stable, by name, by discipline), and once returned a non-XML body
after 235 s. Horse lookups therefore read `eefsys.HorseMaster` directly, which
returns in under a second. The web service path is retained behind
`SOAP_HORSES_VIA_WEBSERVICE=true`.

### `WSCalendar.GetEventList`

`SearchDivision` must be a **non-empty** array — an empty one throws
`NullReferenceException` server-side, so there is no "all divisions" call.
Show Jumping is division `2` (622 events).

Every event reports `OpeningDate` and `ClosingDate` as `0001-01-01`, so open
entries are determined by `StatusName` ("Open For Entries"), not by date.

### `WSRiders.SearchRiderList`

`SearchStable` returns zero results for any value tried, whether a stable id
(`E0000014`) or a stable name. Riders-by-stable is read from
`eefsys.VIEW_Registrations_WithUserID` instead; the name and EEF-id searches
work through the web service and still do.

## Connections

Two MSSQL connections, deliberately separate:

- **`federation`** — the live `EEF` database. Read/written only through the
  federation's own tables. This application owns no schema here.
- **`mssql`** — this application's staging tables (`payment_transactions`,
  `rider_registrations`, `rider_renewals`, `show_jumping_entries`).

Requires the `sqlsrv` PHP extension, present in the app container but not on a
default host PHP install. Run artisan commands that touch `federation` through
`docker exec uaeerf_app`.

One `.env` caveat: a password containing `#` **must be quoted**. Unquoted, the
`#` starts a comment and the value is silently truncated at that point, which
surfaces as `Login failed for user '<user>'` even though the credential is
correct.

```dotenv
FEDERATION_DB_PASSWORD="pa55word!@#"   # quoted - correct
FEDERATION_DB_PASSWORD=pa55word!@#     # truncated to "pa55word!@"
```
