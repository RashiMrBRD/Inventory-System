(function() {
  'use strict';
  
  // Check if on demo domain
  var isDemoDomain = window.location.hostname === 'demo.rashlink.eu.org';
  if (typeof window.debugLog === 'function') {
    window.debugLog('[Registration Link] isDemoDomain:', isDemoDomain);
  }
  
  function addRegisterLink() {
    // Only add link if not first run
    var isFirstRun = window.location.search.includes('isFirstRun=true');
    if (typeof window.debugLog === 'function') {
      window.debugLog('[Registration Link] isFirstRun:', isFirstRun);
    }
    if (isFirstRun) return;
    
    // Never show registration link on demo domain
    if (isDemoDomain) return;
    
    // Check if allow_registration setting is enabled
    // This is set via a data attribute on the body element
    var allowRegistration = document.body.getAttribute('data-allow-registration') === 'true';
    if (typeof window.debugLog === 'function') {
      window.debugLog('[Registration Link] data-allow-registration attribute:', document.body.getAttribute('data-allow-registration'));
      window.debugLog('[Registration Link] allowRegistration:', allowRegistration);
    }
    
    // If attribute is not set yet, wait and try again
    if (!document.body.hasAttribute('data-allow-registration')) {
      if (typeof window.debugLog === 'function') {
        window.debugLog('[Registration Link] Attribute not set yet, retrying...');
      }
      setTimeout(addRegisterLink, 100);
      return;
    }
    
    if (!allowRegistration) {
      if (typeof window.debugLog === 'function') {
        window.debugLog('[Registration Link] Registration not allowed, skipping');
      }
      return;
    }
    
    // Wait for login form to appear
    var loginForm = document.querySelector('.login-form');
    if (typeof window.debugLog === 'function') {
      window.debugLog('[Registration Link] loginForm found:', !!loginForm);
    }
    if (!loginForm) {
      setTimeout(addRegisterLink, 100);
      return;
    }
    
    // Check if link already exists
    if (document.querySelector('.login-footer')) {
      if (typeof window.debugLog === 'function') {
        window.debugLog('[Registration Link] Link already exists');
      }
      return;
    }
    
    if (typeof window.debugLog === 'function') {
      window.debugLog('[Registration Link] Adding registration link');
    }
    // Create and add registration link
    var footer = document.createElement('div');
    footer.className = 'login-footer';
    footer.innerHTML = '<p>Don\'t have an account? <a href="#" onclick="showRegistrationForm();return false;">Register</a></p>';
    loginForm.parentNode.insertBefore(footer, loginForm.nextSibling);
    if (typeof window.debugLog === 'function') {
      window.debugLog('[Registration Link] Link added to DOM');
      window.debugLog('[Registration Link] Link element:', footer);
      window.debugLog('[Registration Link] Link visible:', footer.offsetParent !== null);
      window.debugLog('[Registration Link] Link display:', window.getComputedStyle(footer).display);
    }
  }
  
  // Wait for page to be visible
  function waitForVisibility() {
    if (document.documentElement.style.visibility === 'visible') {
      if (typeof window.debugLog === 'function') {
        window.debugLog('[Registration Link] Page visible, adding link...');
      }
      setTimeout(addRegisterLink, 100);
    } else {
      setTimeout(waitForVisibility, 50);
    }
  }
  
  // Start waiting
  waitForVisibility();
  
  // Make functions globally available
  window.showRegistrationForm = function() {
    var loginForm = document.querySelector('.login-form');
    if (loginForm) {
      loginForm.innerHTML = '<div style="margin-bottom:1.5rem"><h3 style="font-size:1.125rem;font-weight:600;margin:0 0 0.5rem 0">Create Account</h3><p style="font-size:0.875rem;color:var(--text-secondary);margin:0">Fill in the details to create your account</p></div><div class="form-group" style="margin-bottom:1rem"><label for="reg_email" class="form-label" style="display:block;margin-bottom:0.5rem;font-weight:500">Email</label><input type="email" id="reg_email" name="email" class="form-input" style="width:100%;padding:0.625rem;border:1px solid var(--border-color);border-radius:var(--radius-md);font-size:0.875rem" placeholder="Enter your email" required></div><div class="form-group" style="margin-bottom:1rem"><label for="reg_username" class="form-label" style="display:block;margin-bottom:0.5rem;font-weight:500">Username</label><input type="text" id="reg_username" name="username" class="form-input" style="width:100%;padding:0.625rem;border:1px solid var(--border-color);border-radius:var(--radius-md);font-size:0.875rem" placeholder="Choose a username" required></div><div class="form-group" style="margin-bottom:1rem"><label for="reg_password" class="form-label" style="display:block;margin-bottom:0.5rem;font-weight:500">Password</label><input type="password" id="reg_password" name="password" class="form-input" style="width:100%;padding:0.625rem;border:1px solid var(--border-color);border-radius:var(--radius-md);font-size:0.875rem" placeholder="Create a password" required></div><div class="form-group" style="margin-bottom:1.5rem"><label for="reg_confirm_password" class="form-label" style="display:block;margin-bottom:0.5rem;font-weight:500">Confirm Password</label><input type="password" id="reg_confirm_password" name="confirm_password" class="form-input" style="width:100%;padding:0.625rem;border:1px solid var(--border-color);border-radius:var(--radius-md);font-size:0.875rem" placeholder="Confirm your password" required></div><input type="hidden" name="action" value="register"><input type="hidden" name="csrf_token" value=""><button type="submit" class="btn btn-primary" style="width:100%;padding:0.75rem;background:var(--color-primary);color:#fff;border:none;border-radius:var(--radius-md);font-size:0.875rem;font-weight:500;cursor:pointer">Create Account</button>';
      var footer = document.querySelector('.login-footer');
      if (footer) {
        footer.innerHTML = '<p>Already have an account? <a href="#" onclick="showLoginForm();return false;">Sign in</a></p>';
      }
      var csrfInput = loginForm.querySelector('input[name="csrf_token"]');
      if (csrfInput) {
        var cookies = document.cookie.split(';');
        for (var i = 0; i < cookies.length; i++) {
          var cookie = cookies[i].trim();
          if (cookie.indexOf('csrf_token=') === 0) {
            csrfInput.value = cookie.substring('csrf_token='.length);
            break;
          }
        }
      }
    }
  };
  
  window.showLoginForm = function() {
    location.reload();
  };
})();
