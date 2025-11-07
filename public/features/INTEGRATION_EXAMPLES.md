# Integration Examples

Step-by-step guide showing exactly how to add the experimental modal to your pages.

## 🎯 Example 1: profile.php

### Before (Original File)
```php
<?php
session_start();
// ... existing code ...
?>
<!DOCTYPE html>
<html>
<head>
    <title>User Profile</title>
    <link rel="stylesheet" href="assets/css/main.css">
</head>
<body>
    <h1>User Profile</h1>
    <!-- Your existing content -->
</body>
</html>
```

### After (With Experimental Modal)
```php
<?php
session_start();
// ✨ ADD THIS LINE
require_once __DIR__ . '/features/experimental-modal-integration.php';
// ... existing code ...
?>
<!DOCTYPE html>
<html>
<head>
    <title>User Profile</title>
    <link rel="stylesheet" href="assets/css/main.css">
    
    <!-- ✨ ADD THIS LINE -->
    <?php renderExperimentalModalAssets('profile'); ?>
</head>
<body>
    <h1>
        User Profile
        <!-- ✨ OPTIONAL: Add badge indicator -->
        <?php renderExperimentalBadge(); ?>
    </h1>
    
    <!-- Your existing content -->
    
    <!-- ✨ ADD THIS LINE BEFORE </body> -->
    <?php initializeExperimentalModal('profile', [
        'title' => 'Profile Management - Experimental',
        'description' => 'The profile management system is under development. Some features may not work as expected.'
    ]); ?>
</body>
</html>
```

---

## 🎯 Example 2: conversations.php

### Before (Original File)
```php
<?php
session_start();
// ... existing code ...
?>
<!DOCTYPE html>
<html>
<head>
    <title>Conversations</title>
    <link rel="stylesheet" href="assets/css/main.css">
</head>
<body>
    <h1>Conversations</h1>
    <!-- Your existing content -->
</body>
</html>
```

### After (With Experimental Modal)
```php
<?php
session_start();
// ✨ ADD THIS LINE
require_once __DIR__ . '/features/experimental-modal-integration.php';
// ... existing code ...
?>
<!DOCTYPE html>
<html>
<head>
    <title>Conversations</title>
    <link rel="stylesheet" href="assets/css/main.css">
    
    <!-- ✨ ADD THIS LINE -->
    <?php renderExperimentalModalAssets('conversations'); ?>
</head>
<body>
    <h1>
        Conversations
        <?php renderExperimentalBadge(); ?>
    </h1>
    
    <!-- Your existing content -->
    
    <!-- ✨ ADD THIS LINE BEFORE </body> -->
    <?php initializeExperimentalModal('conversations', [
        'title' => 'Conversations - Experimental',
        'description' => 'The messaging feature is in early development. Message delivery and notifications are not yet reliable.'
    ]); ?>
</body>
</html>
```

---

## 🎯 Example 3: system-alerts.php

### Before (Original File)
```php
<?php
session_start();
// ... existing code ...
?>
<!DOCTYPE html>
<html>
<head>
    <title>System Alerts</title>
    <link rel="stylesheet" href="assets/css/main.css">
</head>
<body>
    <h1>System Alerts</h1>
    <!-- Your existing content -->
</body>
</html>
```

### After (With Experimental Modal)
```php
<?php
session_start();
// ✨ ADD THIS LINE
require_once __DIR__ . '/features/experimental-modal-integration.php';
// ... existing code ...
?>
<!DOCTYPE html>
<html>
<head>
    <title>System Alerts</title>
    <link rel="stylesheet" href="assets/css/main.css">
    
    <!-- ✨ ADD THIS LINE -->
    <?php renderExperimentalModalAssets('system-alerts'); ?>
</head>
<body>
    <h1>
        System Alerts
        <?php renderExperimentalBadge(); ?>
    </h1>
    
    <!-- Your existing content -->
    
    <!-- ✨ ADD THIS LINE BEFORE </body> -->
    <?php initializeExperimentalModal('system-alerts', [
        'title' => 'System Alerts - Experimental',
        'description' => 'The alerts dashboard is being built. Alert routing and notification rules may not function correctly.'
    ]); ?>
</body>
</html>
```

---

## 📋 Summary of Changes

For each page, you need to add **ONLY 3 LINES**:

### Line 1 (After session_start):
```php
require_once __DIR__ . '/features/experimental-modal-integration.php';
```

### Line 2 (In <head>):
```php
<?php renderExperimentalModalAssets('page-id'); ?>
```

### Line 3 (Before </body>):
```php
<?php initializeExperimentalModal('page-id', [
    'title' => 'Your Title',
    'description' => 'Your description'
]); ?>
```

---

## 🎨 Customization Options

### Minimal Integration (Default Messages)
```php
<?php initializeExperimentalModal('profile'); ?>
```

### Custom Messages
```php
<?php initializeExperimentalModal('profile', [
    'title' => 'My Custom Title',
    'description' => 'My custom description.'
]); ?>
```

### With Badge Indicator
```php
<h1>
    Page Title
    <?php renderExperimentalBadge(); ?>
</h1>
```

---

## 🚀 Quick Copy-Paste Snippets

### For profile.php:
```php
// Top of file (after session_start)
require_once __DIR__ . '/features/experimental-modal-integration.php';

// In <head>
<?php renderExperimentalModalAssets('profile'); ?>

// Before </body>
<?php initializeExperimentalModal('profile'); ?>
```

### For conversations.php:
```php
// Top of file (after session_start)
require_once __DIR__ . '/features/experimental-modal-integration.php';

// In <head>
<?php renderExperimentalModalAssets('conversations'); ?>

// Before </body>
<?php initializeExperimentalModal('conversations'); ?>
```

### For system-alerts.php:
```php
// Top of file (after session_start)
require_once __DIR__ . '/features/experimental-modal-integration.php';

// In <head>
<?php renderExperimentalModalAssets('system-alerts'); ?>

// Before </body>
<?php initializeExperimentalModal('system-alerts'); ?>
```

---

## ✅ Testing Checklist

After integration, verify:

- [ ] Modal appears when visiting page for first time
- [ ] Modal doesn't appear again in same browser session
- [ ] "Go Back" button works (returns to previous page)
- [ ] "Continue" button works (closes modal, stays on page)
- [ ] X button in corner works
- [ ] Clicking outside modal works
- [ ] Escape key closes modal
- [ ] Badge indicator shows (if added)
- [ ] Mobile responsive
- [ ] Dark mode works (if applicable)

---

## 🗑️ How to Remove Later

To remove the experimental modal from a page:

1. Delete the 3 lines you added
2. Remove the badge indicator (optional)
3. That's it!

The page will work exactly as before.

---

## 💡 Pro Tips

1. **Test in Private/Incognito**: Session storage is cleared each time
2. **Customize Messages**: Make them specific to each feature
3. **Add Badge**: Helps users identify experimental pages
4. **Monitor Feedback**: Ask users what they think

---

## 🐛 Common Issues

**Issue**: Modal shows on every page load
- **Fix**: Clear sessionStorage or check for JavaScript errors

**Issue**: Styles look broken
- **Fix**: Ensure CSS file path is correct

**Issue**: "Go Back" doesn't work
- **Fix**: Make sure browser history exists, or it defaults to dashboard

---

**Need help?** Check the main README.md or contact your administrator.
