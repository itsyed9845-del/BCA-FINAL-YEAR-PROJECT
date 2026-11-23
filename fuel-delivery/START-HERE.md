# SmartFuel — Clean Final Dashboard

This package contains the complete PHP project with the agreed additions and a
design polish. It is intended for PHP 8.0+ and MySQL/MariaDB in XAMPP.

## Upgrading your working project

1. In phpMyAdmin, select your current fuel_delivery_db database and use
   Export → Quick → SQL. Save the backup outside htdocs.
2. Stop Apache while replacing the project files.
3. Move your current project folder to a backup location outside htdocs.
   Keep it until you have checked the upgrade.
4. Extract this ZIP. Copy the included fuel-delivery folder into the SAME
   active htdocs folder where your current project works.
5. If your old config/db.php has different database settings, copy those
   values into config/local.php using config/local.php.example as a template.
   Keep the NEW config/db.php; it loads the upgrade.
   If you already have config/local.php, carry it over instead.
6. If your current project has a storage folder with receipt photos, copy its
   contents into the new storage folder. Keep the supplied .htaccess protection.
7. Start Apache and MySQL. Open http://localhost/fuel-delivery/ and refresh
   with Ctrl+F5. Sign in with your EXISTING accounts.
8. Double-click CHECK-PHP.bat to run PHP syntax checks on your computer.
   Then check the short cleanup checklist below.

**Do not import an SQL file for an upgrade. Do not delete your database.**

The first database connection adds the new tables automatically. Existing
accounts, order history, inventory and earnings are retained. Existing delivery
partners keep approval; newly registered partners need an admin review.
Old saved GPS fixes are cleared once so seeded/stale coordinates cannot appear
as live. Partners should go online and select Share GPS again.

If you named your project folder differently, use that exact folder name in
the URL. The project detects the base path under Apache's DocumentRoot.
If using an Apache alias, set base_url in config/local.php.
Do not put one fuel-delivery folder inside another.

## Cleanup in this version

- Compact cards and spacing; the sidebar menu scrolls independently.
- Log out is a labelled button at the bottom of the menu on all dashboards.
- Installation Check, Support and Password & Security have been removed from
  navigation. Their previous URLs redirect to the signed-in user's dashboard.
  Existing ticket records remain in the database; login security is unchanged.
- No visible latitude/longitude inputs. Type an address and pincode, or click
  Use current location, allow location access and review the filled address.
- Location lookup is optional. If permission, internet or the address provider
  is unavailable, enter your address manually. Add house number in Landmark.
- Editing the filled street address/pincode clears the stored destination GPS
  to avoid showing an ETA to an old location. Landmark edits retain GPS.
- One notification popup at a time; it closes after five seconds or with ×.
  Opening Notifications moves the displayed unread items to read history after
  five visible seconds. Read history has a Clear read history button.
- This cleanup does not require a database reset or SQL import.

## Cleanup checklist

1. Sign in as each role. Confirm the reduced menu and visible Log out button.
2. Place an order with a manually typed address and six-digit pincode.
3. Try Use current location with browser permission. Check the address and
   pincode before confirming; fill the pincode if the service cannot find one.
4. Try typing while a lookup is running. Your typed address must be preserved.
5. Trigger a new-order notification in a second browser signed in as an online
   delivery partner. Confirm the popup closes after five seconds.
6. Open Notifications: visible unread items clear after five seconds and can
   be found under Read history. Clearing history affects only your account.
7. Check an active order's map while the delivery partner shares fresh GPS.

Address lookup uses the public Photon demo endpoint for low-volume local use;
service availability and street/house-number coverage are not guaranteed.
Only clicking Use current location requests location and sends it for lookup.
You can configure a different Photon endpoint in config/geocoding.php.
Reference: https://github.com/komoot/photon

## What is included

| Addition | Where to find it |
| --- | --- |
| Customer-only delivery confirmation code | Customer → Track Order |
| Ratings and reviews after completion | Track Order; Partner → My Profile |
| Notification feed, unread count and new-order popups | All dashboards → Notifications |
| Actual rider map marker and destination pin | Customer dashboard and Track Order |
| GPS-based arrival estimate | Tracking map |
| Cancellation before dispatch with stock return | Track Order; Admin → Manage Orders |
| Refund status and external refund reference | Customer tracking; Financial Reports |
| Order again using current prices | Open an order from history → Order again |
| Stock refill ledger and configurable low-stock alerts | Admin → Fuel Inventory |
| Approval for new delivery partners | Admin → Users & Fleet |
| Date-filtered order report and CSV download | Admin → Financial Reports |
| Emergency booking with online partner check | Customer → Emergency Refuel |
| Manual address or current-location address autofill | Place Order and Track Order |
| Monthly and vehicle spending insights | Customer → Fuel Insights |
| Delivered quantity and receipt/meter photo | Partner → Active Deliveries |
| Suggested partner ranked by workload and GPS distance | Admin → Manage Orders |

Also included: automatic dashboard refresh when order/payout data changes,
clear unpaid-payout labels, payout recording, Home links on login/register,
visible Show/Hide password controls, separate About/Services/Contact pages,
responsive dashboard polish, reduced-motion support, and the existing animated
homepage and image assets.

## Run the complete workflow once

Use different browsers or a normal window and private windows for different
roles. Tabs in the same browser profile share a login session.

1. Register a delivery partner. Admin approves that partner in Users & Fleet.
2. Partner signs in, goes online and selects Share GPS → Allow location.
3. Customer saves a vehicle in Profile, then places an order with a typed
   address or selects Use current location to fill it.
   Review the current price, quantity, address and time window.
4. Admin and eligible online partners receive an in-page popup within about
   five seconds while their dashboard is open.
5. Admin assigns the partner, or the online partner accepts the request.
6. Partner selects Start route. Customer tracking shows the partner once a
   fresh GPS fix is available. Send a chat message in each direction.
7. Partner selects Arrived, then Start fueling.
8. Customer checks the fuel and shares the six-digit delivery code.
9. Partner enters the code, confirms the delivered quantity and payment,
   uploads a JPG/PNG/WebP receipt or meter photo up to 5 MB, then completes.
10. Verify Completed in all three portals. Customer can review the delivery.
    Partner earnings appear as unpaid.
11. After paying the partner externally, Admin records the payout reference.
    The partner's pending balance decreases and its payout becomes processed.
12. Place a separate order and cancel before dispatch. Check the stock return.
    A refund is required only when that cancelled order already had a Paid
    payment record. Do not mark unpaid orders Paid merely to create a refund.
13. Check stock refill, report export and the order-again flow.

## Tracking and notification limits

- Real tracking needs the partner's device location permission and the website
  open. This is browser GPS, not background mobile-app tracking.
- localhost can use browser location. For an agent's phone connecting to your
  computer, use a trusted HTTPS setup. Plain HTTP to a LAN IP normally cannot
  access GPS. On a phone, localhost means the phone, not your computer.
- Maps need internet for Leaflet and OpenStreetMap tiles. The written delivery
  address remains available when map tiles cannot load.
- GPS fixes older than 90 seconds are not shown as live.
- ETA uses straight-line distance × 1.35 as a road allowance and 25 km/h.
  It is explicitly an estimate, not a traffic-aware promise or a routed map.
  It pauses when GPS or the destination is unavailable.
- Popups and chat poll while the website is open; they are not push messages
  delivered after the browser is closed.
- UPI/cash payment is collected on delivery. No card gateway, wallet balance,
  SMS delivery, automatic bank payout or automatic refund transfer is connected.
  Payout/refund controls record payments made outside this project.
- Scheduled time slots express the customer's requested window; they do not
  reserve a fleet appointment or guarantee service coverage.

Technical references:
[Browser location requirements](https://developer.mozilla.org/en-US/docs/Web/API/Geolocation/watchPosition)
and [Leaflet 1.9.4](https://leafletjs.com/reference.html).

## Fresh installation only

Skip this section if your project already has a working database.

For a new empty installation, import database/FRESH-INSTALL-ONLY.sql in
phpMyAdmin. Do not use it to update an existing database. The website then adds
the extension tables on its first connection.

Fresh-install demo accounts only:

| Role | Email |
| --- | --- |
| Admin | admin@fuel.test |
| Partner | agent1@fuel.test |
| Customer | customer@fuel.test |

Fresh-install password: SmartFuelDemo!2026

These credentials are not displayed on the login page. They are for local demo
use only. An upgrade does not change your existing passwords.

## Validation status

See VALIDATION.md for the exact checks performed and the checks requiring
your local PHP/MySQL runtime. This package is not a claim of zero bugs.
