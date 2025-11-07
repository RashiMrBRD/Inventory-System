# Experimental Feature Modal

A beautiful, accessible modal system for warning users about experimental features. Built with shadcn/ui design principles.

## 📁 Files Structure

```
public/features/
├── experimental-modal.css              # Styles (shadcn-inspired)
├── experimental-modal.js               # JavaScript logic
├── experimental-modal-integration.php  # PHP helper functions
└── README.md                          # This file

public/api/
└── check-experimental-feature.php     # API endpoint (optional)
```

## 🚀 Quick Integration

### Step 1: Include the Helper File

At the top of your page (after `session_start()`):

```php
<?php
require_once __DIR__ . '/features/experimental-modal-integration.php';
?>
```

### Step 2: Add Assets to `<head>`

In your page's `<head>` section:

```php
<?php renderExperimentalModalAssets('profile'); ?>
```

### Step 3: Initialize Before `</body>`

Before your closing `</body>` tag:

```php
<?php initializeExperimentalModal('profile'); ?>
```

## 🎨 Full Example

```php
<?php
session_start();
require_once __DIR__ . '/features/experimental-modal-integration.php';
?>
<!DOCTYPE html>
<html>
<head>
    <title>Profile - Experimental</title>
    <?php renderExperimentalModalAssets('profile'); ?>
</head>
<body>
    <h1>
        User Profile
        <?php renderExperimentalBadge(); ?>
    </h1>
    
    <!-- Your page content -->
    
    <?php initializeExperimentalModal('profile'); ?>
</body>
</html>
```

## ⚙️ Custom Configuration

You can customize the modal content:

```php
<?php
$customConfig = [
    'title' => 'Custom Title',
    'description' => 'Your custom description here.'
];
initializeExperimentalModal('my-page', $customConfig);
?>
```

## 📊 Supported Pages

The system is configured for these pages:

| Page | ID | Status |
|------|----|----|
| profile.php | `profile` | ✅ Ready |
| conversations.php | `conversations` | ✅ Ready |
| system-alerts.php | `system-alerts` | ✅ Ready |

## 🎯 Features

✅ **Shadcn-Inspired Design** - Beautiful, modern UI  
✅ **Session-Based** - Only shows once per session  
✅ **Fully Accessible** - ARIA labels, keyboard navigation, focus trap  
✅ **Responsive** - Works on all screen sizes  
✅ **Dark Mode Support** - Automatically adapts  
✅ **Smooth Animations** - Fade in/out, scale effects  
✅ **Easy to Remove** - All code is isolated in features folder  

## 🎨 Modal Structure

The modal includes:

1. **Warning Icon** - Visual indicator (yellow warning triangle)
2. **Title** - Clear feature name
3. **Description** - What users should know
4. **Feature List** - Key warnings (incomplete, data loss, performance)
5. **Info Box** - Contact administrator message
6. **Actions**:
   - **Go Back** - Returns to previous page
   - **I Understand, Continue** - Dismisses modal and proceeds

## 🔧 Customization

### Change Colors

Edit `experimental-modal.css` and modify HSL values:

```css
.experimental-modal-icon {
    background: hsl(48 96% 89%);  /* Warning yellow background */
    color: hsl(25 95% 53%);       /* Warning orange text */
}
```

### Change Behavior

Edit `experimental-modal.js`:

```javascript
// Change session storage key
const key = `experimental_modal_shown_${pageId}`;

// Change animation duration
setTimeout(() => overlay.remove(), 200); // 200ms
```

## 🗑️ Easy Removal

To completely remove this feature:

1. Delete the entire `public/features/` folder
2. Remove these lines from your pages:
   ```php
   require_once __DIR__ . '/features/experimental-modal-integration.php';
   <?php renderExperimentalModalAssets('page-id'); ?>
   <?php initializeExperimentalModal('page-id'); ?>
   ```
3. Delete `public/api/check-experimental-feature.php` (optional)

That's it! No other files are affected.

## 🔒 Security

- XSS Protection: All user inputs are escaped
- No external dependencies
- Session-based tracking (no cookies)
- HTTPS recommended for production

## ♿ Accessibility

- Full keyboard navigation support
- Focus trap when modal is open
- ARIA labels and roles
- Screen reader friendly
- Respects `prefers-reduced-motion`
- High contrast colors (WCAG AAA)

## 📱 Browser Support

- Chrome/Edge: ✅ Latest 2 versions
- Firefox: ✅ Latest 2 versions
- Safari: ✅ Latest 2 versions
- Mobile: ✅ iOS Safari, Chrome Mobile

## 🐛 Troubleshooting

**Modal doesn't show:**
- Check browser console for errors
- Ensure JavaScript is enabled
- Verify file paths are correct

**Modal shows every time:**
- Check if sessionStorage is enabled
- Clear browser session storage
- Check for JavaScript errors

**Styling issues:**
- Ensure CSS file is loaded
- Check for CSS conflicts
- Inspect element in browser DevTools

## 📝 Changelog

### Version 1.0.0 (Initial Release)
- Created modal system with shadcn design
- Added PHP integration helpers
- Added session-based tracking
- Full accessibility support
- Dark mode support
- Mobile responsive

## 💡 Tips

1. **Test First**: Try on a dev/staging environment
2. **Customize Text**: Update warnings to match your features
3. **Add Badge**: Use `renderExperimentalBadge()` for visual indicator
4. **Monitor Feedback**: Track user reactions and adjust messaging

## 📞 Support

For questions or suggestions, contact your system administrator.

---

**Made with ❤️ using shadcn/ui design principles**
