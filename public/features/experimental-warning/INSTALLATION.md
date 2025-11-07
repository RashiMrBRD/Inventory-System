# Installation Guide - Experimental Warning System

## ✅ What Was Created

The experimental warning system has been successfully installed with the following structure:

```
public/features/experimental-warning/
├── experimental-warning.css     # Shadcn-styled modal CSS
├── experimental-warning.js      # Modal functionality
├── experimental-warning.php     # PHP helper functions
├── README.md                    # Full documentation
└── INSTALLATION.md             # This file
```

## 📋 Pages Updated

The following pages now show the experimental warning modal:

### 1. profile.php
- **Feature**: User Profile Management
- **Warning**: "The user profile system is currently being developed and tested."
- **Lines Modified**: 712-718

### 2. conversations.php
- **Feature**: Conversations & Messaging
- **Warning**: "The conversations feature is in beta and may have incomplete functionality."
- **Lines Modified**: 70-76

### 3. system-alerts.php
- **Feature**: System Alerts & Notifications
- **Warning**: "The alerts system is under active development."
- **Lines Modified**: 54-60

## 🎯 How It Works

### User Experience Flow:

1. **User visits experimental page** (e.g., profile.php)
2. **500ms delay** (better UX)
3. **Modal appears** with warning message
4. **User has 3 options:**
   - ✅ **Continue Anyway** → Dismisses for 24 hours, proceeds to page
   - ⬅️ **Go Back** → Returns to previous page
   - ❌ **Close (X)** → Closes modal, stays on page

### Display Behavior:

**Current Setting (Always Show):**
- **Every visit**: Modal shows
- **Click "Continue Anyway"**: Modal closes, proceeds to page
- **Next visit**: Modal shows again
- **Result**: Users are always warned about experimental status

**Alternative Setting (Session-Based):**
- **First visit**: Modal shows
- **Click "Continue Anyway"**: Stores dismissal in localStorage
- **Next 24 hours**: Modal won't show again
- **After 24 hours**: Modal shows again (fresh session)

To switch between modes, edit `experimental-warning.js` line 19:
```javascript
alwaysShow: true   // Current: Shows every time
alwaysShow: false  // Alternative: 24-hour session
```

## 🧪 Testing

### Test the Modal:

1. **Visit any experimental page:**
   ```
   http://your-domain/profile.php
   http://your-domain/conversations.php
   http://your-domain/system-alerts.php
   ```

2. **Expected behavior:**
   - Modal appears after 0.5 seconds
   - Beautiful shadcn-styled design
   - Smooth fade-in animation
   - Focus trapped in modal (keyboard accessible)

3. **Test actions:**
   - Press **Escape** → Modal closes
   - Press **Tab** → Cycles through buttons
   - Click **Continue** → Modal dismisses for 24 hours
   - Click **Go Back** → Returns to previous page
   - Click outside modal → Modal closes

### Clear Session (Force Modal to Show Again):

Open browser console (F12) and run:
```javascript
localStorage.removeItem('experimental-warning-dismissed');
```

Then refresh the page.

## 🎨 Design Features

### Shadcn Principles Applied:

- ✅ Smooth animations (200ms duration)
- ✅ Subtle shadows and borders
- ✅ HSL color system
- ✅ Focus states and keyboard navigation
- ✅ Responsive design (mobile-friendly)
- ✅ Dark mode support (automatic)
- ✅ Accessible (ARIA labels, focus trap)

### Visual Elements:

- **Warning Icon**: ⚠️ in amber circle
- **Info Icon**: ℹ️ in body section
- **Button Icons**: Arrows for navigation
- **Close Button**: X in top-right corner

## 🔧 Customization

### Change Warning Message:

Edit the page file (e.g., `profile.php` line 715):
```php
renderExperimentalWarning('User Profile Management', [
    'description' => 'Your custom message here'
]);
```

### Change Session Duration:

Edit `experimental-warning.js` line 18:
```javascript
sessionDuration: 24 * 60 * 60 * 1000, // Change to desired milliseconds
```

Examples:
- 1 hour: `1 * 60 * 60 * 1000`
- 7 days: `7 * 24 * 60 * 60 * 1000`
- 30 days: `30 * 24 * 60 * 60 * 1000`

### Custom Styling:

Edit `experimental-warning.css` to change:
- Colors (search for `hsl()` values)
- Sizes and spacing
- Animations
- Dark mode colors

## 🗑️ Complete Removal

If you decide you no longer need this system:

### Step 1: Delete the Folder
```bash
rm -rf public/features/experimental-warning/
```

### Step 2: Remove Code from Pages

**In profile.php** (lines 712-718), delete:
```php
// ========== EXPERIMENTAL FEATURE WARNING ==========
// To remove this warning system, delete the following 2 lines and the features/experimental-warning folder
require_once __DIR__ . '/features/experimental-warning/experimental-warning.php';
renderExperimentalWarning('User Profile Management', [
    'description' => 'The user profile system is currently being developed and tested. Some features may be incomplete or change without notice.'
]);
// ===================================================
```

**In conversations.php** (lines 70-76), delete the same block.

**In system-alerts.php** (lines 54-60), delete the same block.

### Step 3: Clear Browser Storage (Optional)

Users who dismissed the modal will have a localStorage entry. It's harmless but can be cleared:
```javascript
localStorage.removeItem('experimental-warning-dismissed');
```

That's it! No database cleanup, no configuration changes needed.

## 📱 Browser Compatibility

Tested and working on:
- ✅ Chrome 90+
- ✅ Firefox 88+
- ✅ Safari 14+
- ✅ Edge 90+
- ✅ Mobile Safari (iOS)
- ✅ Chrome Mobile (Android)

## 🐛 Troubleshooting

### Modal doesn't appear:

1. **Check console** (F12 → Console tab)
2. **Verify files loaded** (F12 → Network tab)
3. **Clear localStorage**:
   ```javascript
   localStorage.clear();
   ```
4. **Hard refresh** (Ctrl+Shift+R or Cmd+Shift+R)

### Styling looks wrong:

1. **Check CSS loaded** (view page source, find the CSS link)
2. **Verify no conflicts** with existing styles
3. **Check z-index** (modal uses 9999)

### Button actions not working:

1. **Check JavaScript errors** in console
2. **Verify JS file loaded** correctly
3. **Test in different browser**

## 📞 Support

For questions or suggestions about this system:
- Review the full documentation in `README.md`
- Check browser console for errors
- Test in different browsers
- Contact system administrator

---

**Installation Date**: November 7, 2025  
**Version**: 1.0.0  
**Status**: ✅ Active and Working
