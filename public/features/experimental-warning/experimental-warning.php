<?php
/**
 * Experimental Feature Warning System
 * 
 * This file provides helper functions to easily inject the experimental warning modal
 * into pages that are not production-ready.
 * 
 * EASY REMOVAL: To remove this feature system entirely, simply:
 * 1. Delete the entire /public/features/experimental-warning/ folder
 * 2. Remove the include statements from the target pages
 * 
 * @version 1.0.0
 */

/**
 * Inject experimental warning assets and modal
 * 
 * @param string $pageName Display name of the experimental page or page filename
 * @param array $options Additional configuration options
 * @return void
 */
function renderExperimentalWarning($pageName, $options = []) {
    // Detect current page
    $currentPage = basename($_SERVER['PHP_SELF']);
    $experimentalPages = getExperimentalPages();
    
    // Check if we have predefined config for this page
    $pageConfig = null;
    if (isset($experimentalPages[$currentPage])) {
        $pageConfig = $experimentalPages[$currentPage];
    } else {
        // Try to find by pageName match
        foreach ($experimentalPages as $config) {
            if ($config['pageName'] === $pageName) {
                $pageConfig = $config;
                break;
            }
        }
    }
    
    // Check if this is a welcome page
    $isWelcome = isset($pageConfig['isWelcome']) && $pageConfig['isWelcome'] === true;
    
    // Default options with more natural language
    $defaults = [
        'title' => $isWelcome ? 'Inventory & Project Management Demo' : '⚠️ Experimental Feature Detected',
        'description' => 'This feature is currently in experimental status and is not yet ready for production use.',
        'details' => 'Because this feature is still under development, you may encounter unexpected behavior, incomplete functionality, or bugs that could affect your experience.',
        'recommendation' => 'If you have any suggestions or ideas on how we can improve this feature, please contact the administrator for more information.',
        'autoInit' => true
    ];
    
    // Merge with page-specific config if available
    if ($pageConfig) {
        $defaults = array_merge($defaults, [
            'description' => $pageConfig['description'] ?? $defaults['description'],
            'details' => $pageConfig['details'] ?? $defaults['details'],
            'recommendation' => $pageConfig['recommendation'] ?? $defaults['recommendation']
        ]);
        $pageName = $pageConfig['pageName'];
    }
    
    $config = array_merge($defaults, $options);
    
    // Get app info if this is a welcome page
    $appInfo = isset($pageConfig['appInfo']) ? $pageConfig['appInfo'] : null;
    
    // Convert to JSON for JavaScript
    $jsConfig = json_encode([
        'pageName' => $pageName,
        'pageFile' => $currentPage,
        'title' => $config['title'],
        'description' => $config['description'],
        'details' => $config['details'],
        'recommendation' => $config['recommendation'],
        'isWelcome' => $isWelcome,
        'appInfo' => $appInfo
    ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    
    ?>
    <!-- Experimental Warning System -->
    <?php
        if (!function_exists('asset_proxy_url')) {
            function asset_proxy_url(string $rel): string {
                $configPath = __DIR__ . '/../../../config/app.php';
                $cfg = file_exists($configPath) ? require $configPath : [];
                $key = $cfg['assets']['signing_key'] ?? (getenv('ASSET_SIGNING_KEY') ?: 'change-me-dev-key');
                $exp = time() + 3600;
                try { $nonce = bin2hex(random_bytes(12)); } catch (\Throwable $e) { $nonce = bin2hex((string)mt_rand()); }
                $b64 = rtrim(strtr(base64_encode($rel), '+/', '-_'), '=');
                $sig = hash_hmac('sha256', $rel . '|' . $exp . '|' . $nonce, $key);
                $dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
                if ($dir === '/' || $dir === '\\' || $dir === '.') { $dir = ''; }
                $prefix = $dir !== '' ? ('/' . trim($dir, '/') . '/') : '/';
                return $prefix . 'asset.php?d=' . rawurlencode($b64) . '&e=' . $exp . '&n=' . rawurlencode($nonce) . '&s=' . $sig;
            }
        }
    ?>
    <?php static $debugLoggerInjected = false; ?>
    <?php if (!$debugLoggerInjected): ?>
    <script src="<?php echo asset_proxy_url('assets/js/debug-logger.js'); ?>" defer></script>
    <?php $debugLoggerInjected = true; ?>
    <?php endif; ?>
    <link rel="stylesheet" href="<?php echo asset_proxy_url('features/experimental-warning/experimental-warning.css'); ?>">
    <script src="<?php echo asset_proxy_url('features/experimental-warning/experimental-warning.js'); ?>" defer></script>
    <?php if ($config['autoInit']): ?>
    <script src="<?php echo asset_proxy_url('features/experimental-warning/experimental-warning-init.js'); ?>&config=<?php echo urlencode(base64_encode($jsConfig)); ?>" defer></script>
    <?php endif; ?>
    <!-- End Experimental Warning System -->
    <?php
}

/**
 * Check if a page should show the experimental warning
 * 
 * This function can be used to conditionally show warnings based on
 * environment, user role, or other criteria.
 * 
 * @param string $pageName Name of the page
 * @return bool Whether to show the warning
 */
function shouldShowExperimentalWarning($pageName) {
    // Always show in development/staging
    // You can add logic here to hide warnings in production
    // or for specific user roles (e.g., admins)
    
    return true; // Currently always shows
}

/**
 * Get list of experimental pages
 * 
 * This centralized list makes it easy to see which pages have warnings
 * and manage them all in one place.
 * 
 * @return array List of experimental page configurations
 */
function getExperimentalPages() {
    return [
        'profile.php' => [
            'pageName' => 'User Profile Management',
            'pageFile' => 'profile.php',
            'description' => 'You are attempting to access the User Profile page, which is currently in active development. This means that while you can explore the features, some functionality may not work as expected because we are still testing and refining this system.',
            'details' => 'The profile management system includes features like photo uploads, password changes, and personal information editing. However, these features are experimental, so you might encounter bugs or incomplete functionality that could affect your experience.',
            'recommendation' => 'If you notice any issues or have suggestions on how we can improve this feature, please contact the administrator. Your feedback is valuable because it helps us build a better system for everyone.'
        ],
        'conversations.php' => [
            'pageName' => 'Conversations & Messaging',
            'pageFile' => 'conversations.php',
            'description' => 'You are about to enter the Conversations page, which is currently in beta status. This feature allows team communication and messaging, but it is still being developed, so certain aspects like real-time updates or message delivery may not function reliably.',
            'details' => 'The messaging system is designed to facilitate team collaboration through channels and direct messages. However, because this is an experimental feature, you might experience delays in message delivery, missing notifications, or other unexpected behavior that we are working to resolve.',
            'recommendation' => 'We encourage you to test this feature and share your thoughts with the administrator. If you encounter any problems or have ideas for improvements, please let us know so we can make this messaging system more robust and user-friendly.'
        ],
        'system-alerts.php' => [
            'pageName' => 'System Alerts & Notifications',
            'pageFile' => 'system-alerts.php',
            'description' => 'You have clicked on the System Alerts page, which is currently under active development. This page displays important system notifications and alerts, but because it is still being built, the alert delivery mechanism may not be fully reliable at this time.',
            'details' => 'The alerts system is designed to keep you informed about critical events, inventory changes, and system updates. However, this feature is experimental, which means that alerts might not appear immediately, or some notifications may be missing altogether while we continue to develop and test this functionality.',
            'recommendation' => 'Your experience with this feature matters to us, so if you notice that alerts are not showing up correctly or if you have suggestions on what types of alerts would be most helpful, please reach out to the administrator. This feedback helps us prioritize which improvements to implement first.'
        ],
        'docs.php' => [
            'pageName' => 'Documentation Hub',
            'pageFile' => 'docs.php',
            'description' => 'You are viewing the Documentation Hub, which is currently an experimental feature that has not yet been properly compiled or rewritten. This page serves as a central location for all system documentation, but because it is still in development, the content may contain repetitive words, incomplete sections, or information that does not accurately reflect the current state of the application.',
            'details' => 'The documentation you see here is temporary and is being actively revised to improve clarity, accuracy, and organization. This means that while you can browse through the available guides and references, some documentation may be outdated, duplicated, or unreliable. Additionally, because this feature is still experimental, the documentation hub itself might not be included in the final release of the application, so it could be removed or significantly restructured in future updates.',
            'recommendation' => 'We appreciate your understanding as we work to improve our documentation. If you find any errors, repetitive content, or confusing sections, please contact the administrator with specific details so we can prioritize those areas for rewriting. Your feedback is especially valuable because it helps us identify which parts of the documentation need the most attention and ensures that the final version will be clear and helpful for all users.'
        ],
        'dashboard.php' => [
            'pageName' => 'Dashboard - Welcome',
            'pageFile' => 'dashboard.php',
            'isWelcome' => true,
            'appInfo' => getAppInfo(),
            'description' => 'This demo application helps you manage inventory and track projects in one place. It is designed to support day-to-day operations like monitoring stock levels, reviewing activity, and organizing work timelines.',
            'details' => 'Use the navigation to access key modules such as inventory tracking (items, stock levels, and updates), project monitoring (status, start/end dates, calendar views), and dashboards/reports for quick summaries. The interface and data may be simplified for demonstration purposes.',
            'recommendation' => 'To get started, review the dashboard summary, then visit the Inventory section to explore stock information and the Projects section to view timelines and schedules. If anything looks incorrect or you have improvement ideas, please share feedback so we can refine the workflow.'
        ]
    ];
}

/**
 * Get application information from config
 * 
 * @return array Application info including version and team
 */
function getAppInfo() {
    // Load app config - always read from config/app.php
    $configPath = __DIR__ . '/../../../config/app.php';
    $config = file_exists($configPath) ? require $configPath : [];
    
    return [
        'name' => $config['app_name'] ?? 'Inventory Management System',
        'version' => $config['app_version'] ?? '0.3.1', // Fallback only if config is missing
        'team' => [
            'Brian Dondriano',
            'Brisbane Silva',
            'Gian Benedict Carino',
            'Kate Rosales'
        ]
    ];
}

/**
 * Get experimental warning configuration for current page
 * 
 * @param string $currentPage Filename of the current page
 * @return array|null Configuration array or null if not experimental
 */
function getExperimentalPageConfig($currentPage) {
    $pages = getExperimentalPages();
    return $pages[$currentPage] ?? null;
}

/**
 * Render experimental badge for navigation links
 * 
 * Optional helper to add visual indicators in navigation
 * 
 * @param string $cssClass Additional CSS classes
 * @return string HTML for experimental badge
 */
function renderExperimentalBadge($cssClass = '') {
    return sprintf(
        '<span class="experimental-badge %s" title="Experimental Feature">⚠️ Beta</span>',
        htmlspecialchars($cssClass, ENT_QUOTES, 'UTF-8')
    );
}
