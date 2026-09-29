# Malt portfolio title

SessionCraft — Workshop Booking & Capacity Management

# Malt description

Small workshop organisers need to accept reservations without overselling seats, maintain a fair waiting list, and avoid managing cancellations in scattered messages.

I designed and built SessionCraft, a working WordPress application that addresses this with a clear workshop catalogue, persistent reservations and capacity management. The plugin assigns a confirmed seat or a waiting-list place, prevents duplicate reservations, and promotes the next participant when a confirmed seat is cancelled. Participants manage their reservations through private links. Staff maintain workshops, capacity and booking lists in WordPress.

The implementation uses database transactions and row locking to handle simultaneous requests. In a live check, five concurrent booking attempts for a two-seat workshop produced exactly two confirmed reservations and three waiting-list entries. I also verified cancellation security, queue order, repeated cancellation behavior and staff permissions.

My role covered the custom theme, application plugin, database workflow, responsive interface and live integration tests. Source code, installation packages, actual screenshots and verification notes are included.

Independent project with original fictional sample content, verified locally on real WordPress. No payments or email notifications are included. Public deployment and client business results are not claimed.

# Suggested skills

WordPress · PHP · Custom Plugin Development · Responsive Web Design · Database Integration · Workflow Automation · Booking Systems
