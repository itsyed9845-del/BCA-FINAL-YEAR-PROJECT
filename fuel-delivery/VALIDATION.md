# Validation record — clean final dashboard

Executed for this package:

- JavaScript syntax checks for main.js, upgrade.js and address.js.
- Mocked-DOM behavior tests: manual address without GPS, successful address
  autofill, pending lookup submission guard, stale response protection,
  clearing stale destination coordinates after manual edits, unavailable GPS,
  permission denial, single popup limit, five-second timeout and close action.
- Inbox behavior tests: five visible seconds, CSRF-protected read requests for
  displayed IDs only, preservation of unseen unread count.
- PHP string/comment/delimiter structural checks across all PHP files.
- Static required-include and internal-link existence checks.
- CSS brace checks and package ZIP member/CRC integrity checks.

The PHP checks are structural checks, not native PHP lint. This environment
has no PHP/MySQL runtime or runnable browser, so PHP execution, database
transactions, rendered layouts, physical-device GPS and the external address
lookup have not been tested end to end here.

Run CHECK-PHP.bat in your XAMPP installation, then follow the cleanup and
complete-workflow checklists in START-HERE.md. GPS/autofill require browser
permission and localhost or HTTPS; address lookup additionally needs internet.
Manual addresses work without the external address lookup.

No user database or accounts were accessed or reset during this cleanup.
