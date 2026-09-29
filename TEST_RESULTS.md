# SessionCraft verification

Verified 2026-09-28T07:26:58+00:00 against real local WordPress 7.1.2 / PHP 8.3 / MariaDB.

- PASS: Actual WordPress theme and booking forms render
- PASS: Missing booking nonce rejected
- PASS: Anonymous workshop deletion rejected
- PASS: Authenticated staff creates a persistent workshop
- PASS: Invalid email rejected without reservation
- PASS: Five concurrent reservations yield exactly two confirmed seats and three waiting places
- PASS: Duplicate email within a workshop rejected
- PASS: Tampered cancellation token rejected
- PASS: Private link cancellation persists
- PASS: Cancelling one confirmed seat promotes exactly one waiting participant
- PASS: Waiting-list promotion follows reservation order
- PASS: Repeated cancellation is idempotent
- PASS: Staff cannot reduce capacity below confirmed reservations
- PASS: Increasing capacity promotes the waiting list
- PASS: Staff deletes test workshop and its reservation records

All test-created records removed. Public hosting, email delivery and payments are outside this build.
