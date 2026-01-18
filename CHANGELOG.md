# Changelog

## update 0.4.2 - 01.18.2026
fixed Invitation Key toggle requiring multiple page refreshes to reflect state changes by implementing comprehensive cache-busting strategies.
added HTTP cache control headers to login.php to prevent HTML page caching.
added data attributes directly to body tag for immediate availability of configuration values to client-side scripts.
removed data attribute setting from page-loader.php to prevent overwriting server-side values.
changed cache-busting parameter for page-loader to use timestamp for fresh load on every request.
added PHP opcode cache invalidation in settings.php when config file is updated.
fixed Timezone Settings persistence issue by prioritizing session timezone over database timezone values.
removed problematic session update logic that was overwriting session timezone with database value on page load.
added error logging for database update failures in timezone settings.

Bugs and Fixes:
- fix an issue with Invitation Key toggle requiring multiple page refreshes to reflect state changes.
- fix an issue with Timezone Settings not persisting selected timezone and resetting to default values.
- fix an issue with page-loader script overwriting server-side data attributes with cached values.

## update 0.4.1 - 01.16.2026
redesigned System Information section with compact layout and shadcn/ui design principles for better space utilization and visual appeal.
implemented server time polling mechanism replacing WebSocket for simpler architecture and better reliability.
added GitHub update system with clickable application version container that checks for updates from private repository.
integrated pulsing blur animation during update checks with visual feedback (blur on/off switching every 300ms).
added refresh/refresh icon next to application version with hover effects and spin animation during updates.
implemented visual status feedback by temporarily replacing version text with update/error information.
created API endpoints for version checking, update downloading, server time polling, and update progress tracking.
added comprehensive error handling with shake animations for failed update checks.
removed toast notifications for update checks in favor of inline visual feedback.

Bugs and Fixes:
- fix an issue with WebSocket connection failures causing repeated console errors by switching to polling mechanism.
- fix an issue with 500 Internal Server Error on /api/server-time endpoint by removing unnecessary authentication checks.
- fix an issue with API route paths for get-notifications and get-sessions returning 404 errors by adding /api/ prefix.
- fix an issue with System Information alignment, width, height, and spacing by implementing consistent flexbox layout with min-height.
- fix an issue with update check feedback not being visible by replacing version text temporarily with status messages.
- fix an issue with entire version container not being clickable by adding cursor pointer and click handler to container.
- fix an issue with blur animation not pulsing by implementing interval-based blur on/off switching.

## update 0.3.7 - 01.15.2026
implementation of containerized docker image
implementation of api endpoints for version check and update if the user is using an outdated app with proper VersionService for version management.

Bugs and Fixes:
- fix an issue with /bin/sh packages modules
- fix an issue with buttons position on left instead of right.

## update 0.3.3 - 11.09.2025
added an api for theming, making sure it use it, instead of making its own with every single pages. IN PROGRESS
added mobile support for settings
added some reworks, restyle sidebar, reduce breadcrumb, typography, etc.
did some validation improvements for outline results.

Bugs and Fixes:
- fix an issue with $_SESSION['theme'] never submitted a form update causing the api to choose system by default.
- readjusting/redesigning the layout to make it look better (inprogress 1)

note: viewing this on mobile now is currently in development but you read and see content much better now compared to before.

## update 0.3.2 - 11.08.2025
implement previous trend comparison instead of using hardcoded rate to calculate actual trend percentages in dashboard

Bugs and Fixes:
- fix an issue with financial snapshot section in total revenue showing collection rate as "↑+12.5% vs last mo" instead of the actual computation rate as well as performance section revenue performance showing as ↑+15.3% instead of the actual computation rate
- fix an issue with null function causing some page to not function.

## update 0.3.1 - 11.07.2025
implement mobile support with navigation bar layouts that currently under development
added remote update for auto push and pull resources for version updates and stuff making our life easier by keeping the files synchronized to the server.

Bugs and Fixes:
Minor issue with slide in animation, touch gestures, overlay backdrop, body scroll lock, active page, user profile, organize section, accessibility, auto close, etc.
Remove safe area inset support that causes gap to some android browsers.

## update 0.2.9 - 11.05.2025
added experimental/unfinished features for testing
added a way to interact with toast notifications and pop up modals via console logs
added additional information
added documentation as temporary dumps
added keyboard shortcuts by pressing w or Shift+/

Bugs and Fixes:
- fix an issue with pop up modal use different button classes, causing the keyboard handler to not detect it.
- fix an issue with session storage not functioning properly
- fix an issue with some scripts causing the page slow to loads

## update 0.2.8 - 11.04.2025
added a ui for map tracking to know who users who logs in and logs out with visualization.
implement a database schema for session tracking
adding OpenStreetMap integration for ip geolocation service locally
added leaflet.js for visualization

Bugs and Fixes:
- fix an issue with unknown session not registering the device
- fix a security vulnerability issue CVE-2019-1000005, and CVE-2025-54869 affecting the 2 library packages mpdf and fpdi.

detailed information can be found from https://github.com/advisories/GHSA-3cwc-m7c2-qr86,
https://github.com/advisories/GHSA-jxhh-4648-vpp3

## update 0.2.7 - 11.03.2025
added a font selection options in settings for the user to use.
implement json file as an alternative with listing list if database become suddenly offline
added a profile page for basic information and setups
added navigation shortcuts using ajax to switch between pages.

## update 0.2.6 - 11.02.2025
implements a way to configure smtp on settings for email notifications and alerts.
integrates chartsjs and canvas api to simulate all kinds of graphs simulation throughout the pages.
added sparkline for statistics with different kind of color scheme based on the system color scheme
added Progressive Line With Easing support for lines, graphs, etc with adaptive animation.

## update 0.2.5 - 11.01.2025
added time tracking and expense management
added project templates, audit trail, profitability reports, budget variance tracking that prevents double billing into project management.
added modal integration for duplicate, adding time entry, expense, budget vs actual, generate invoice, save as template, archive, and delete project as part of a project and management.
reorganize workflow then implements a quick access to match the patterns with cleaner interface.

Bugs and Fixes:
- fix validation issues to some pages
- fix an issue where pop up modal window throws of the side due to unmatched z-index.

## update 0.2.4 - 10.31.2025
added n number formatting system to set large currency value as k,m,b,t,tn, etc. if needed.
added payment tracking wit progress bar, batch operations, quick actions per order, advanced actions menu, export excel and pdf, record payment modal to ordering page
implement positioning logic to measure drop down, calculate viewport dimensions, for boundary api auto population
integration of dropdown that intelligently positions itself to stay within the viewport
added api support endpoint for shipping orders and projects.

implement horror type overlay artifacts layout as a part of an easter egg. (normally set for October 28 to November 2, but was extended to November 7 for testing)

Bugs and Fixes:
- fix order number display issues
- fix an issue with multiple files calling session_start() after the AuthController had already started the session, causing php notices prevents header redirects from working.

## update 0.2.3 - 10.30.2025
implement some minor changes in the layout

Bugs and Fixes:
- fix an issue with layout making the page look weird if resize or by using smaller screen using max-width value to always 100% vw
- fix an issue with incorrect headers and alignments.

## update 0.2.2 - 10.29.2025
added showNewQuoteModal empty state
added esc handler as an api across pages.
redesign orders, projects, and shipping page with modal support for entries.
automate some refactoring changes across the page with dynamic list management
added multi package support with dimensions LxWxH with carrier specific options
added automated tracking for expected delivery date
added special delivery instructions with print shipping label and package dimension tracking functionality on place
added total weight calculation across all packages with external link tracking (on beta)
added service type mapping.
implemented some tables side mapping.

Bugs and Fixes:
- fix an issue where pagination shows up if Items per page is less than to the set of numbers.
- fix an issue to the color scheme mismatch to some pages.
- fix overflowing width on small-screen display
- disable sortTable if no entry is found to some of the pages.
- fix insurance value, signature required checkbox

In preparation with implementing mobile wireframe layouts, we develop some changes to the ui side of things.

## update 0.2.1 - 10.28.2025
implement quickview support for inventory
replaced submitBtn as a way to switch between tabs on edit mode.
implement barcode to show the actual barcode inside the inventory with click to copy.
implements toggle switch between barcode, sku, and upc.
implements recordPayment api to retrieves existing invoice data.
added DejaVu Sans for exported layouts
added getCount(filter) to invoice model
making get list support integrated from the api

Bugs and Fixes:
- fix an issue with barcode, sku, upc rendering ajax synchronization
- fix an issue with invoice quickview using unknown variable instead of a correct id causing the data to show as empty
- fix an issue with inventory items showing the price value as 0.

## update 0.1.9 - 10.27.2025
added a temporary support for modern browsers to test api request changes across different uri.
added keyboard shortcut support ensuring easy access across endpoint.
improving ajax api implemention across different request.
implement a feature where closing showNewQuoteModal() other than closeQuoteModal() will show a confirmation modal
change some toast notifications to show as console logs.
update container support from php, and apache 8.2 to php, and apache 8.3 to resolve composer dependency conflicts.

Bugs and Fixes:
- fix an issue with data formatting alignment for exported excel data
- fix an issue with pad string color causing an http error 500
- fix an issue with unknown variable causing a false positive in the codebase
- fix an issue where you can't type on showNewQuoteModal() textfield
- fix an issue where using z-index causing a textfield to not function
- fix an issue with the current date showing as 01011970 by default instead of the current date after create quotation was created.
- fix an issue with items=description,unit_price, and customer showing an invalid form where it cause a bug not focusable.
- fix an issue where alert() is blocking the focus event.
- fix an issue with container support using version 8.2 causing dependency conflicts

## update 0.1.8 - 10.26.2025
redesign changes to quotations, invoicing, orders, projects, and shipping along with some tweaks with SWE approved statements.
update toggle behavior with three easy steps.
give quotation date, time, and currency added implementation support from api

Bugs and Fixes:
- fix an issue with click handler from export button and bulk menu
- fix an issue with renderCurrentPage only hiding/showing existing rows in DOM based on pagination.

## update 0.1.7 - 10.25.2025
created an api endpoint for coc, je, and fr to use dynamic ajax filtering
update the adding, editing, removing ui from inventory system.
integration of quotation with following modules now fully implemented
replacing toast notification with console.log to move on with test development and proceed to seeing a logs with the console instead.
added rejection void status on quotation section
added a propagation status with the api request

Bugs and Fixes:
- fix an issue with quotations does not update value
- fix an issue where approve, void, duplicate showing failed
- fix an issue with currency system does not recognize by other pages causing it to show unknown value
- fix an issue with status display show inaacuracy
- fix an issue with the event bubbles up and immediately triggers the "close on outside click" listener, which then prevents the next click from working properly.

## update 0.1.6 - 10.24.2025
redesign dashboards for better visibility and optimizations.
redesign analytics page for visual metrics adaptation of stocks.
added rangeLabels for dashboard and analytics range of days.
added a sorting filter across the page.
added algorithm for inventory mechanism itself as api
added ajax implementation

Bugs and Fixes:
- fix an issue with 7d, 30d, 90d, 1y a sorting filter that does nothing
- fix dashboard only displaying a sort of month, 30 days, quarter, month but only for display.
- fix an issue with pagination causing the number of items revert to default.

## update 0.1.5 - 10.23.2025
integration of phpspreedsheet support setting a new api request using xlsx instead of outdated csv
added a phpoffice library from composer
updates the full integration for chart of accounts
reorganizing full data tables support across all pages.

Bugs and Fixes:
- fix an issue with chart of accounts, journal entries, and financial reports causing an window.location.reload() even when its not supposed to.
- fix format table support crashes at 1000 (now support upto 999,999.00)

## update 0.1.4 - 10.22.2025
implementation of containerized docker image
implementation of api endpoints for version check and update if the user is using an outdated app with proper VersionService for version management.

Bugs and Fixes:
- fix an issue with /bin/sh packages modules
- fix an issue with buttons position on left instead of right.
