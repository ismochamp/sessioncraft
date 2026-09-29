# SessionCraft — Workshop Booking & Capacity Management

**Category:** Web Development  
**Role:** Design, theme development, plugin development, database workflow, integration testing and documentation  
**Project type:** Independent working project, September 2026  
**Technology:** WordPress 7.1.2, PHP 8.3, MariaDB/InnoDB, HTML, CSS, Docker

## The operational problem

Small workshop organisers need to accept reservations without overselling seats, maintain a fair waiting list, and avoid managing cancellations in scattered messages.

## What I built

A custom WordPress theme and booking plugin connect a responsive workshop catalogue to transactional capacity checks, ordered waiting lists, private cancellation links and staff schedule management.

- Concurrent reservations with a locked capacity check
- Ordered waiting list with automatic promotion
- Private token-protected status and cancellation
- WordPress staff schedule and reservation management

## Workflow

Choose workshop → Reserve → Confirm or waitlist → Cancel → Promote next participant

## What was verified

15 live WordPress integration checks passed, including five concurrent booking attempts with exactly two confirmed seats at capacity two, FIFO promotion, duplicate rejection and cancellation-token validation. Tests exercised the actual WordPress routes and database-backed actions, rather than a static design or a separate preview implementation. Temporary test records were removed. The provided screenshots show the running local WordPress application.

## Design decisions

Seat assignment must remain correct when requests arrive together. A locked workshop row serializes reservation changes; the waiting list uses insertion order. The participant receives one private link for status and cancellation, while staff use native WordPress administration. The editorial theme keeps session purpose, date, availability and next action visible without extra screens.

## Evidence and practical scope

- Source theme and plugin are editable and packaged for installation.
- The local Docker setup starts a real WordPress instance with persistent storage.
- `TEST_RESULTS.md` records the live checks; `test_wordpress.py` can reproduce them.
- `screenshots/` contains actual application captures; no videos are included.

Locally verified independent project with an original fictional workshop programme. No payments, email notifications, public hosting or real client outcome claims. Schedule times are UTC; reservation details are stored locally.

No measured client business improvement is claimed. The evidence is the implemented workflow and the recorded behavior under the checks above.
