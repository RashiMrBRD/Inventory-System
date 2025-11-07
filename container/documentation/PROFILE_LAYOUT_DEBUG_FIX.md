# Profile.php Layout Rendering Fix (Docker/Nginx)

## Problem

Profile page displays content but **missing sidebar and application layout** when running in Docker with Nginx reverse proxy.

### Expected Behavior (Screenshot 1):
✅ Sidebar visible on the left  
✅ Header with "User Profile" title  
✅ Full application wrapper with navigation  
✅ Proper layout with tabs (Overview, Activity, Billing, Teams)  

### Actual Behavior (Screenshot 2):
❌ No sidebar  
❌ Missing application header  
❌ Raw content without layout wrapper  
❌ Content renders but no app structure  

---

## Root Causes Identified

### **1. Early Cache Headers**
```php
// ❌ Problem: Headers sent before output buffering
header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

require_once __DIR__ . '/../vendor/autoload.php';
// ... 700+ lines of code ...
ob_start(); // Too late!
```

In Docker/Nginx environment, early headers can interfere with PHP output buffering, causing content to be flushed before layout wrapping.

### **2. Experimental Warning Feature**
The experimental warning system uses absolute paths:
```php
<link rel="stylesheet" href="/features/experimental-warning/experimental-warning.css?v=...">
<script src="/features/experimental-warning/experimental-warning.js?v=..."></script>
```

In reverse proxy setups, these paths may not resolve correctly, causing JavaScript errors that break page rendering.

### **3. Output Buffering Timing**
Profile.php has extensive POST processing (200+ lines) before `ob_start()`, which can cause issues if any output occurs during that processing.

---

## Solutions Applied

### **1. Removed Early Cache Headers from profile.php**

**Before:**
```php
<?php
// Prevent caching for reverse proxy compatibility
header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

require_once __DIR__ . '/../vendor/autoload.php';
```

**After:**
```php
<?php
require_once __DIR__ . '/../vendor/autoload.php';
// Cache headers now handled in layout.php
```

### **2. Added Cache Headers to layout.php (Global)**

```php
// Set cache control headers for dynamic pages
if (!headers_sent()) {
    header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
}
```

Plus meta tags:
```html
<meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
<meta http-equiv="Pragma" content="no-cache">
<meta http-equiv="Expires" content="0">
```

### **3. Temporarily Disabled Experimental Warning**

```php
// TEMPORARILY DISABLED FOR DEBUGGING
// require_once __DIR__ . '/features/experimental-warning/experimental-warning.php';
// renderExperimentalWarning('User Profile Management');
```

### **4. Added Debug Logging**

```php
// Get page content
$pageContent = ob_get_clean();

// Debug: Check if content was captured
if (empty($pageContent)) {
    error_log('Profile: Warning - $pageContent is empty!');
}

// Debug: Log before including layout
error_log('Profile: About to include layout.php, pageContent length: ' . strlen($pageContent));

// Include layout
$layoutPath = __DIR__ . '/components/layout.php';
if (!file_exists($layoutPath)) {
    error_log('Profile: ERROR - layout.php not found at: ' . $layoutPath);
    die('Layout file not found');
}

include $layoutPath;

// Debug: Log after including layout
error_log('Profile: Layout included successfully');
```

---

## Testing Steps

### **1. Deploy Changes**

```bash
cd /path/to/inventory_demo
git pull origin master

# Rebuild and restart container
docker-compose -f container/docker-compose.yml down
docker-compose -f container/docker-compose.yml up -d --build
```

### **2. Check Logs for Debug Output**

```bash
# Watch PHP error log
docker exec inventory_web tail -f /var/www/html/var/logs/php_errors.log

# Or check container logs
docker-compose -f container/docker-compose.yml logs -f web
```

**Expected Log Output:**
```
Profile: About to include layout.php, pageContent length: 45678
Profile: Layout included successfully
```

**Problem Indicators:**
```
Profile: Warning - $pageContent is empty!
Profile: ERROR - layout.php not found at: /var/www/html/public/components/layout.php
```

### **3. Test Profile Page**

```bash
# Access profile page
http://your-domain.com/profile.php
```

**Check for:**
- ✅ Sidebar visible
- ✅ Header present
- ✅ Full layout wrapper
- ✅ Tabs working
- ✅ No JavaScript errors in browser console (F12)

### **4. Check Browser Console**

Open Developer Tools (F12) → Console tab

**Look for errors like:**
```
Failed to load resource: /features/experimental-warning/experimental-warning.css
Failed to load resource: /features/experimental-warning/experimental-warning.js
Uncaught ReferenceError: initExperimentalWarning is not defined
```

### **5. Check Network Tab**

Developer Tools → Network tab → Reload profile.php

**Verify:**
- ✅ `profile.php` returns 200 status
- ✅ CSS files load (core.css, components.css, layout.css)
- ✅ JS files load (sidebar.js, mobile-menu.js, toast.js)
- ✅ Response Content-Type is `text/html`

---

## Nginx Configuration

If profile page still doesn't show layout, check Nginx reverse proxy config:

```nginx
location / {
    proxy_pass http://inventory_backend;
    proxy_set_header Host $host;
    proxy_set_header X-Real-IP $remote_addr;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto $scheme;
    
    # Important: Don't buffer
    proxy_buffering off;
    
    # Pass through cache headers
    proxy_ignore_headers Cache-Control;
    proxy_no_cache 1;
    proxy_cache_bypass 1;
}

# Static assets
location ~* \.(css|js|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot)$ {
    proxy_pass http://inventory_backend;
    proxy_set_header Host $host;
    
    # Cache static assets
    expires 30d;
    add_header Cache-Control "public, immutable";
}

# PHP files - NO CACHE
location ~ \.php$ {
    proxy_pass http://inventory_backend;
    proxy_set_header Host $host;
    proxy_set_header X-Real-IP $remote_addr;
    
    # Disable caching for PHP
    add_header Cache-Control "no-store, no-cache, must-revalidate";
    proxy_no_cache 1;
    proxy_cache_bypass 1;
}
```

---

## Diagnostic Commands

### **Check if Docker Container is Running**
```bash
docker ps | grep inventory
```

### **Check Container Logs**
```bash
docker logs inventory_web --tail=100 -f
```

### **Check PHP Error Log Inside Container**
```bash
docker exec inventory_web cat /var/www/html/var/logs/php_errors.log | tail -50
```

### **Check File Permissions**
```bash
docker exec inventory_web ls -la /var/www/html/public/profile.php
docker exec inventory_web ls -la /var/www/html/public/components/layout.php
```

Should show:
```
-rw-r--r-- 1 www-data www-data ... profile.php
-rw-r--r-- 1 www-data www-data ... layout.php
```

### **Test Direct Container Access (Bypass Nginx)**

```bash
# Get container IP
docker inspect inventory_web | grep IPAddress

# Test directly (replace with actual IP)
curl -I http://172.17.0.2/profile.php
```

Should return:
```
HTTP/1.1 200 OK
Content-Type: text/html; charset=UTF-8
Cache-Control: no-cache, no-store, must-revalidate
```

### **Check Nginx Logs**
```bash
docker logs inventory_nginx --tail=100 -f
```

Look for errors like:
```
404 /features/experimental-warning/experimental-warning.css
502 Bad Gateway
```

---

## Common Issues & Solutions

### **Issue 1: Content Shows But No Layout**

**Cause:** Output buffering failed or layout not included

**Solution:**
1. Check error logs for output buffer errors
2. Verify `$pageContent` is not empty
3. Ensure no output before `ob_start()`

### **Issue 2: 404 for CSS/JS Files**

**Cause:** Nginx not proxying asset requests correctly

**Solution:**
```nginx
location /assets/ {
    proxy_pass http://inventory_backend;
    proxy_set_header Host $host;
}
```

### **Issue 3: Blank Page**

**Cause:** PHP fatal error

**Solution:**
```bash
# Check PHP error log
docker exec inventory_web cat /var/www/html/var/logs/php_errors.log

# Enable error display (temporary)
docker exec inventory_web sed -i 's/display_errors = Off/display_errors = On/' /usr/local/etc/php/php.ini
docker exec inventory_web apache2ctl restart
```

### **Issue 4: Works Locally, Breaks in Docker**

**Cause:** Path resolution or permissions

**Solution:**
```bash
# Check paths
docker exec inventory_web php -r "
require '/var/www/html/vendor/autoload.php';
echo 'Autoload: OK\n';
echo 'Layout exists: ' . (file_exists('/var/www/html/public/components/layout.php') ? 'YES' : 'NO') . '\n';
"
```

### **Issue 5: Experimental Warning Breaks Layout**

**Cause:** Absolute paths not resolving in reverse proxy

**Solution:** Keep experimental warning disabled (already done) or fix paths:
```php
// Change in experimental-warning.php
<link rel="stylesheet" href="features/experimental-warning/experimental-warning.css">
<script src="features/experimental-warning/experimental-warning.js"></script>
```

---

## Re-Enable Experimental Warning (Later)

Once profile layout is confirmed working, you can re-enable:

```php
// In profile.php, uncomment these lines:
require_once __DIR__ . '/features/experimental-warning/experimental-warning.php';
renderExperimentalWarning('User Profile Management');
```

But ensure experimental-warning feature paths work in your Nginx setup:
```nginx
location /features/ {
    proxy_pass http://inventory_backend;
    proxy_set_header Host $host;
}
```

---

## Files Modified

### **1. profile.php**
- ✅ Removed early cache headers (line 7-10)
- ✅ Disabled experimental warning (line 735-737)
- ✅ Added debug logging (line 2289-2310)

### **2. layout.php**
- ✅ Added cache control headers (line 19-24)
- ✅ Added cache control meta tags (line 37-39)

### **3. config/app.php**
- ✅ Version bump: 0.3.1 → 0.3.2

---

## Success Criteria

✅ Profile page shows with full layout (sidebar + header)  
✅ No JavaScript errors in console  
✅ All CSS/JS assets load successfully  
✅ Tabs work correctly  
✅ Profile photo upload/management works  
✅ Debug logs show "Layout included successfully"  

---

## Next Steps

1. **Deploy changes** to Docker environment
2. **Check logs** for debug output
3. **Test profile.php** in browser
4. **Report back** with:
   - What you see in browser
   - Browser console errors (if any)
   - PHP error log output
   - Network tab status codes

---

## Commit Details

**Commit:** `31c66c3`  
**Message:** fix: profile.php layout rendering in Docker - remove early cache headers, add debug logging, disable experimental warning  
**Repository:** https://github.com/RashiMrBRD/Inventory-System.git

**Files Changed:**
- `public/profile.php`
- `public/components/layout.php`
- `config/app.php`

---

## Related Documentation

- [NGINX_REVERSE_PROXY_FIX.md](./NGINX_REVERSE_PROXY_FIX.md)
- [USER_DATA_VALIDATION_FIX.md](./USER_DATA_VALIDATION_FIX.md)
- [DASHBOARD_TRENDS_FIX.md](./DASHBOARD_TRENDS_FIX.md)
- [DOCKER_DEBUGGING.md](./DOCKER_DEBUGGING.md)
