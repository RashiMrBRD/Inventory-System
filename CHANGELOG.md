# Changelog

## update 0.4.4 - 02.22.2026
implemented real-time password validation on the registration form with strength indicator and confirmation matching. The password field now shows a dynamic strength bar that fills with color based on password complexity while the requirements checklist displays checkmarks when each requirement is met. Added a confirm password field that validates matching passwords with visual indicators.

added email validation on the registration form with real-time feedback. The email input displays a checking animation while validating and shows green for valid emails or red for invalid ones with specific error messages.

fixed the documentation page that was failing to load properly. The issue was behind escaped quotes in the HTML output where `href=\"/docs` appeared instead of `href="/docs"`. These malformed attributes broke all navigation links on the page. The document scanner was working correctly and found 168 markdown files across both the Project Docs and Container Docs directories.

implemented password visibility toggle with an eye icon that switches between showing and hiding the password text. Added comprehensive CSS styling for all password validation UI elements including the strength bar, requirements list, and confirmation indicators.

Bugs and Fixes:
- fix an issue with registration form not showing password strength indicator, requirements checklist, and confirm password field due to missing HTML elements in the base64 encoded form string.
- fix an issue with docs.php navigation links not working because of escaped backslashes before quotes in HTML attributes.
- fix an issue with browser native validation interfering with custom email validation by adding novalidate attribute to the form.
- fix an issue with Docker container caching JavaScript files for 1 month causing stale login-register-link.js not reflecting updates. Added cache-busting timestamp parameters to JS script sources in login.php.

Known Issues:
- The password requirements checkmarks may not clear properly when the password field is emptied after previously meeting requirements. This is because the checkmark spans are added dynamically and need proper cleanup logic.
- The email validation only checks format validity and does not verify if the email domain actually exists or if the email is already registered in the system.

While the updates from 01.19.2026 to 02.20.2026 introduced significant architectural changes including Windows support, Rust and Flutter migration, Android app development, and various performance optimizations, they were reverted to version 0.4.3 because there were underlying complexities that emerged behind the implementation. The idea behind these changes was ambitious but they introduced unexpected behaviors and data inconsistencies that affected overall functionality. Because time constraints limited thorough testing and validation, the decision was made to roll back to a stable state while preserving the documented progress for future reference.

## update 0.4.3 - 02.21.2026
implemented MongoDB aggregation pipelines for dashboard analytics to replace inefficient `getAll()` + `foreach` patterns that caused page unresponsiveness with large datasets (10k+ records).
added `getDashboardAnalytics()` methods to Inventory, Invoice, Quotation, Order, Project, and Shipment models for server-side statistics calculation.
rewrote dashboard API endpoint to consume aggregation methods instead of loading full collections into PHP memory.
cached CurrencyService IP detection result in session to prevent 5-second external API timeout on every settings page load.
fixed theme selection modal interaction where "Continue Anyway" and "Cancel" buttons were non-functional due to missing event listeners.
implemented proper theme modal behavior: "Continue Anyway" closes modal and keeps selection without auto-submitting, "Cancel" reverts to previous theme.
added keyboard (Escape) and click-outside support for theme modal dismissal.

Bugs and Fixes:
- fix an issue with analytics-dashboard.php and dashboard.php loading slowly and becoming unresponsive with large datasets (10k+ records).
- fix an issue with settings.php slow loading due to uncached currency IP detection API call with 5-second timeout.
- fix an issue with theme selection modal buttons (Continue Anyway, Cancel) not responding to clicks due to missing event listeners.
- fix an issue with theme modal auto-submitting form on "Continue Anyway" instead of just closing modal and keeping selection.

===================
Cancelled due to 
time constraint
================
## update 1.3.6 - 02.19.2026
optimized application performance by updating outdated modules and libraries to their latest stable versions.
implemented lazy loading for assets and components to reduce initial load time.
added performance monitoring hooks for identifying bottlenecks in real-time.

Bugs and Fixes:
- fix an issue with application performance degradation due to outdated dependencies.
- fix an issue with memory leaks in long-running sessions.
- fix an issue with slow database queries on complex join operations.

## update 1.3.5 - 02.18.2026
refactored core modules for better performance and maintainability.
implemented caching layer for frequently accessed data.
optimized asset bundling and minification process.

Bugs and Fixes:
- fix an issue with cache invalidation not triggering properly on data updates.
- fix an issue with asset versioning causing stale files to be served.

## update 1.3.4 - 02.17.2026
updated third-party libraries to address performance issues and security vulnerabilities.
implemented connection pooling for database operations.
added query optimization for common data retrieval patterns.

Bugs and Fixes:
- fix an issue with database connection overhead causing slow response times.
- fix an issue with redundant API calls on page load.

## update 1.3.3 - 02.16.2026
implemented performance patches across multiple modules.
added debouncing and throttling for user input handlers.
optimized rendering pipeline for large data tables.

Bugs and Fixes:
- fix an issue with excessive re-renders causing UI lag.
- fix an issue with event listeners not being properly cleaned up.

## update 1.3.2 - 02.16.2026
began performance optimization initiative by profiling application bottlenecks.
implemented code splitting for faster initial page loads.
added tree shaking for unused code elimination.

Bugs and Fixes:
- fix an issue with large bundle sizes affecting page load performance.
- fix an issue with unused dependencies being included in production builds.

## update 1.3.1 - 02.16.2026
initiated performance audit and optimization planning.
identified outdated modules and libraries requiring updates.
created performance baseline metrics for comparison.

Bugs and Fixes:
- fix an issue with missing performance metrics collection.
- fix an issue with development mode settings affecting production performance.

## update 1.2.8 - 02.13.2026
finalized container optimization and deployment pipeline improvements.
added health checks and monitoring endpoints.
implemented graceful shutdown handling for containerized environments.

Bugs and Fixes:
- fix an issue with container startup time being too slow.
- fix an issue with health check endpoints not responding under load.

## update 1.2.7 - 02.12.2026
optimized container resource allocation and limits.
added environment-based configuration management.
implemented secret management for sensitive configuration values.

Bugs and Fixes:
- fix an issue with environment variables not being properly loaded.
- fix an issue with container memory limits being exceeded.

## update 1.2.6 - 02.11.2026
continued database structure optimization and indexing improvements.
added database migration scripts for schema updates.
implemented connection retry logic for database resilience.

Bugs and Fixes:
- fix an issue with database migration scripts failing on large datasets.
- fix an issue with missing indexes causing slow query performance.

## update 1.2.5 - 02.10.2026
updated database schema for improved data integrity and performance.
added foreign key constraints and cascade rules.
implemented soft delete pattern for data preservation.

Bugs and Fixes:
- fix an issue with orphaned records after parent deletion.
- fix an issue with cascade delete not working as expected.

## update 1.2.4 - 02.08.2026
implemented security patches for identified vulnerabilities.
added input validation and sanitization across all endpoints.
implemented rate limiting for API endpoints.

Bugs and Fixes:
- fix an issue with XSS vulnerabilities in user input fields.
- fix an issue with missing CSRF protection on form submissions.
- fix an issue with rate limiting not being applied to critical endpoints.

## update 1.0.8 - 02.07.2026
finalized layout fixes and visual consistency improvements.
added responsive breakpoints for various screen sizes.
implemented consistent spacing system across all components.

Bugs and Fixes:
- fix an issue with inconsistent spacing between components.
- fix an issue with responsive layouts breaking on certain screen widths.

## update 1.0.7 - 02.06.2026
continued alignment and width issue fixes across pages.
added flexbox and grid layout standardization.
implemented consistent width constraints for content areas.

Bugs and Fixes:
- fix an issue with content overflowing container boundaries.
- fix an issue with alignment inconsistencies in card components.

## update 1.0.6 - 02.05.2026
addressed spacing and padding inconsistencies.
implemented design token system for consistent spacing.
added visual regression tests for layout changes.

Bugs and Fixes:
- fix an issue with inconsistent padding across similar components.
- fix an issue with margin collapsing causing unexpected spacing.

## update 1.0.5 - 02.04.2026
continued layout and design structure improvements.
refactored CSS architecture for better maintainability.
added utility classes for common styling patterns.

Bugs and Fixes:
- fix an issue with CSS specificity conflicts.
- fix an issue with duplicate style definitions.

## update 1.0.4 - 02.03.2026
implemented design system improvements and component standardization.
added consistent border radius and shadow styles.
implemented color palette standardization.

Bugs and Fixes:
- fix an issue with inconsistent border radius values.
- fix an issue with shadow styles not matching design specifications.

## update 1.0.3 - 02.02.2026
continued layout fixes and alignment corrections.
added consistent typography scale and line heights.
implemented proper z-index hierarchy for overlays and modals.

Bugs and Fixes:
- fix an issue with typography not scaling properly.
- fix an issue with z-index conflicts between overlapping elements.

## update 1.0.2 - 01.28.2026
began design structure improvements and layout refactoring.
identified layout issues and alignment inconsistencies.
started implementing consistent design patterns.

Bugs and Fixes:
- fix an issue with layout breaking on window resize.
- fix an issue with alignment inconsistencies in header and footer.

## update 0.9.4 - 01.27.2026
finalized Android app support with native features.
implemented push notification support for Android.
added background sync capabilities for offline functionality.

Bugs and Fixes:
- fix an issue with push notifications not being received on certain devices.
- fix an issue with background sync failing silently.

## update 0.9.3 - 01.26.2026
continued Android app development and optimization.
implemented native sharing capabilities.
added deep linking support for seamless navigation.

Bugs and Fixes:
- fix an issue with deep links not opening the correct page.
- fix an issue with share functionality not working on older Android versions.

## update 0.9.2 - 01.26.2026
added Android-specific UI components and interactions.
implemented material design patterns for Android.
added haptic feedback for touch interactions.

Bugs and Fixes:
- fix an issue with touch targets being too small for Android guidelines.
- fix an issue with haptic feedback not working on certain devices.

## update 0.9.1 - 01.25.2026
continued Android app support implementation.
optimized performance for mobile devices.
added gesture navigation support.

Bugs and Fixes:
- fix an issue with gesture navigation conflicting with app gestures.
- fix an issue with performance drops on lower-end Android devices.

## update 0.8.2 - 01.25.2026
initiated Android app support development.
created Android project structure and configuration.
implemented basic Android app shell with navigation.

Bugs and Fixes:
- fix an issue with Android build configuration.
- fix an issue with app not launching on certain Android versions.

## update 0.8.1 - 01.24.2026
finalized Flutter integration and cross-platform architecture.
implemented unified codebase for web and mobile platforms.
added platform-specific optimizations.

Bugs and Fixes:
- fix an issue with platform-specific code not being properly abstracted.
- fix an issue with Flutter hot reload not working correctly.

## update 0.8.0 - 01.23.2026
continued Flutter migration and component development.
implemented state management solution for Flutter.
added navigation and routing system for Flutter app.

Bugs and Fixes:
- fix an issue with state not persisting across navigation.
- fix an issue with routing not handling deep links properly.

## update 0.7.0 - 01.22.2026
expanded Flutter UI components and screens.
implemented responsive layouts for Flutter.
added form handling and validation for Flutter.

Bugs and Fixes:
- fix an issue with form validation not triggering correctly.
- fix an issue with responsive layouts not adapting to screen sizes.

## update 0.6.0 - 01.21.2026
continued Rust backend development and Flutter integration.
implemented API client for Flutter to communicate with Rust backend.
added authentication flow for Flutter app.

Bugs and Fixes:
- fix an issue with API client not handling errors properly.
- fix an issue with authentication tokens not being stored securely.

## update 0.5.0 - 01.20.2026
implemented Flutter framework setup and initial UI components.
began migrating frontend components to Flutter.
added Rust backend API endpoints for Flutter consumption.

Bugs and Fixes:
- fix an issue with Flutter dependencies not resolving correctly.
- fix an issue with Rust API endpoints not returning proper CORS headers.

## update 0.4.2 - 01.19.2026
initiated Windows support implementation.
began rewriting overall structures to Rust and Flutter.
created initial Rust backend architecture.
added Windows-specific build configurations.

Bugs and Fixes:
- fix an issue with Rust compilation errors on Windows.
- fix an issue with cross-platform build scripts not working correctly.
- fix an issue with Windows-specific paths not being handled properly.

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
