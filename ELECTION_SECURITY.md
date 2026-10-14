# Election integrity and ballot secrecy

## Protections added

- Result totals, result pages, CSV/PDF exports, and result emails are restricted to elections whose status is `closed`.
- Ballot positions and candidate management are locked once an election starts or any vote exists.
- Elections with recorded votes cannot be deleted. Admins can close and reopen voting within the scheduled window; closing results are hidden again when voting reopens.
- The election snapshots the count of active student accounts when voting opens. Turnout uses unique voters divided by that snapshot; ballot counts are reported separately.
- Vote insertion locks the election row while validating and recording the ballot; the unique voter/position/election constraint continues to prevent duplicate votes.

## Deployment migration

Back up the database, then run `database_migrations/election_integrity.sql` once after importing the schema and before enabling elections, for both existing and new MySQL/MariaDB installations. It adds the voter-count snapshot column if missing and replaces vote-table cascading foreign keys with restrictive ones so related deletes cannot erase stored ballots. The foreign-key migration is intentionally a one-time operation.

## Important limitation: ballots are not secret

The existing `votes` table stores both `student_id` and `candidate_id` on the same row. This allows someone with database access (and code paths that query those rows) to link a student to their selected candidate. The safeguards in this change do **not** make those ballots anonymous or cryptographically secret. Do not represent this system as providing a secret ballot or as suitable for a high-stakes election.

Existing votes cannot be made secret retroactively by deleting `student_id`: that field is also how the application enforces one vote per student and how current data can be investigated. Preserve existing records and backups.

## Separate migration required for secret ballots

A future secret-ballot design should be reviewed and tested separately before use. It needs to separate voter eligibility/one-vote tracking from ballot choices, prevent application interfaces from joining the records, protect any cryptographic keys outside the database, define retention and audit policies, and address timing/metadata correlation. Merely hashing `student_id` is pseudonymization, not a guarantee of ballot secrecy. A migration must preserve historical election records and clearly mark which elections used the old identifiable format.

## Eligible-voter count migration note

`eligible_voter_count` is nullable for existing databases and is added by the election class when needed. New elections snapshot the active-student count at first activation. Existing active/closed elections without a snapshot receive a best-effort count of active student accounts the first time stats are requested; this is not a historical electorate reconstruction. Back up the database before deployment and review these legacy turnout values before publishing them.
