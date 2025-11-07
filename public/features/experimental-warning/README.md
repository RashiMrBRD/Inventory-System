# Experimental Feature Warning System

A modular, shadcn-inspired warning modal system for experimental features that are not production-ready.

## 🎯 Purpose

This system displays a user-friendly warning modal when users access experimental features, informing them that the feature is under development and encouraging them to provide feedback to administrators.

## 📦 Components

This system consists of three main files:

- **`experimental-warning.css`** - Shadcn-styled modal and overlay styles with dark mode support
- **`experimental-warning.js`** - JavaScript modal functionality with localStorage session management
- **`experimental-warning.php`** - PHP helper functions for easy integration

## ✨ Features

- 🎨 **Shadcn Design System** - Beautiful, accessible modal design
- 🌙 **Dark Mode Support** - Automatically adapts to system preferences
- ♿ **Accessibility** - ARIA labels, focus trapping, keyboard navigation
- 🔄 **Always Show Mode** - Modal appears every time by default (configurable)
- 💾 **Optional Session Management** - Can remember dismissal for 24 hours via localStorage
- 🔒 **XSS Protection** - Safe HTML escaping
- 📱 **Responsive** - Works on all screen sizes
- ⚡ **Lightweight** - No external dependencies
- 🗑️ **Easy Removal** - Self-contained in features folder

## 🚀 Quick Start

### Option 1: Using PHP Helper (Recommended)

Add these lines at the beginning of your experimental page (after opening `<?php`):

```php
<?php
// At the top of your page
require_once __DIR__ . '/features/experimental-warning/experimental-warning.php';
?>
<!DOCTYPE html>
<html>
<head>
    <title>My Experimental Page</title>
    <!-- Your other head content -->
</head>
<body>
    <?php 
    // Render the warning system
    renderExperimentalWarning('User Profile Management'); 
    ?>
    
    <!-- Your page content here -->
</body>
</html>
```

### Option 2: Manual Integration

```html
<!DOCTYPE html>
<html>
<head>
    <title>My Experimental Page</title>
    <link rel="stylesheet" href="features/experimental-warning/experimental-warning.css">
</head>
<body>
    <!-- Your page content -->
    
    <script src="features/experimental-warning/experimental-warning.js"></script>
    <script>
        initExperimentalWarning({
            pageName: 'User Profile Management',
            title: '⚠️ Experimental Feature',
            description: 'This feature is currently in experimental status.',
            contactInfo: 'Please contact the administrator for more information.'
        });
    </script>
</body>
</html>
```

## ⚙️ Configuration Options

### PHP Function: `renderExperimentalWarning($pageName, $options)`

**Parameters:**
- `$pageName` (string, required) - Display name of the feature
- `$options` (array, optional) - Configuration options

**Available Options:**
```php
$options = [
    'title' => '⚠️ Experimental Feature',
    'description' => 'Custom description text',
    'contactInfo' => 'Custom contact information',
    'autoInit' => true // Set to false to manually initialize
];
```

**Examples:**

```php
// Basic usage
renderExperimentalWarning('User Profile');

// Custom configuration
renderExperimentalWarning('Chat System', [
    'title' => '🚧 Beta Feature',
    'description' => 'This chat system is in active development.',
    'contactInfo' => 'Report bugs to support@example.com'
]);

// Manual initialization
renderExperimentalWarning('Advanced Analytics', ['autoInit' => false]);
?>
<script>
    // Initialize with custom logic
    if (userRole !== 'admin') {
        initExperimentalWarning({
            pageName: 'Advanced Analytics'
        });
    }
</script>
```

### JavaScript Function: `initExperimentalWarning(options)`

**Parameters:**
```javascript
{
    pageName: 'Feature Name',           // Required
    title: '⚠️ Experimental Feature',   // Optional
    description: 'Description text',    // Optional
    contactInfo: 'Contact information'  // Optional
}
```

## 📁 Currently Experimental Pages

The following pages have experimental warnings enabled:

1. **profile.php** - User Profile Management
2. **conversations.php** - Conversations & Messaging
3. **system-alerts.php** - System Alerts & Notifications

To add more pages, simply include the warning system as shown above.

## 🎨 Customization

### Styling

All styles are in `experimental-warning.css`. You can customize:

- Colors (HSL values for easy theming)
- Animations (durations and easing)
- Spacing and sizing
- Dark mode behavior

### Behavior

In `experimental-warning.js` (lines 14-20), you can modify:

**To show modal every time (current setting):**
```javascript
const CONFIG = {
  alwaysShow: true  // Modal appears on every visit
};
```

**To enable 24-hour session management:**
```javascript
const CONFIG = {
  alwaysShow: false,  // Modal dismissed for 24 hours after "Continue"
  sessionDuration: 24 * 60 * 60 * 1000  // Adjust duration
};
```

Other configurable options:
- Animation speed
- Button actions
- localStorage key name

### Content

In `experimental-warning.php`, you can:

- Edit default messages
- Add page-specific configurations
- Customize warning conditions

## 🗑️ How to Remove

To completely remove this system:

1. **Delete the folder:**
   ```bash
   rm -rf public/features/experimental-warning/
   ```

2. **Remove includes from pages:**
   - Open `profile.php`, `conversations.php`, `system-alerts.php`
   - Remove these lines:
     ```php
     require_once __DIR__ . '/features/experimental-warning/experimental-warning.php';
     renderExperimentalWarning('Page Name');
     ```

That's it! No database changes, no configuration files to clean up.

## 🔧 Advanced Usage

### Conditional Display

```php
<?php
if (shouldShowExperimentalWarning('profile.php')) {
    renderExperimentalWarning('User Profile', [
        'description' => 'Custom message for this environment'
    ]);
}
?>
```

### Custom Dismiss Duration

```javascript
// In your page, after including the JS file
const CONFIG = {
    storageKey: 'my-custom-key',
    sessionDuration: 7 * 24 * 60 * 60 * 1000 // 7 days
};
```

### Adding Visual Badge to Navigation

```php
<a href="profile.php">
    Profile <?php echo renderExperimentalBadge('ml-2'); ?>
</a>
```

## 🐛 Troubleshooting

**Modal doesn't appear:**
- Check browser console for JavaScript errors
- Verify CSS and JS files are loading (Network tab)
- Clear localStorage: `localStorage.removeItem('experimental-warning-dismissed')`

**Styling issues:**
- Ensure CSS file loads before page content
- Check for CSS conflicts with existing styles
- Verify no z-index conflicts (modal uses z-index: 9999)

**Session not persisting:**
- Check if localStorage is enabled in browser
- Verify no browser extensions blocking localStorage
- Check browser privacy settings

## 📝 Browser Support

- ✅ Chrome 90+
- ✅ Firefox 88+
- ✅ Safari 14+
- ✅ Edge 90+
- ✅ Mobile browsers (iOS Safari, Chrome Android)

## 📄 License

This code is part of the Inventory Management System project.

## 🤝 Contributing

To improve this system:
1. Edit files in `public/features/experimental-warning/`
2. Test on target pages
3. Update this README with changes

---

**Version:** 1.0.0  
**Last Updated:** November 7, 2025  
**Maintained by:** Development Team
