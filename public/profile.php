<?php
/**
 * User Profile Page
 * Manage user account, profile photo, password, and personal information
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Controller\AuthController;

$authController = new AuthController();
$authController->requireLogin();

$user = $authController->getCurrentUser();

// Get user ID (MongoDB uses _id)
$userId = isset($user['_id']) ? (string)$user['_id'] : (isset($user['id']) ? $user['id'] : '');

// Handle form submissions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        try {
            switch ($_POST['action']) {
                case 'update_profile':
                    // Update profile information
                    $updates = [
                        'username' => trim($_POST['username'] ?? ''),
                        'firstname' => trim($_POST['firstname'] ?? ''),
                        'lastname' => trim($_POST['lastname'] ?? ''),
                        'nickname' => trim($_POST['nickname'] ?? ''),
                        'display_name' => trim($_POST['display_name'] ?? ''),
                        'email' => trim($_POST['email'] ?? ''),
                        'whatsapp' => trim($_POST['whatsapp'] ?? ''),
                        'website' => trim($_POST['website'] ?? ''),
                        'telegram' => trim($_POST['telegram'] ?? ''),
                        'bio' => trim($_POST['bio'] ?? ''),
                        'role' => $_POST['role'] ?? 'user'
                    ];

                    $result = $authController->updateUserProfile($userId, $updates);
                    $message = $result['message'];
                    $messageType = $result['success'] ? 'success' : 'danger';
                    
                    if ($result['success']) {
                        $user = $authController->getCurrentUser(); // Reload user data
                    }
                    break;

                case 'change_password':
                    $oldPassword = $_POST['old_password'] ?? '';
                    $newPassword = $_POST['new_password'] ?? '';
                    
                    if (empty($oldPassword) || empty($newPassword)) {
                        $message = 'Both old and new passwords are required';
                        $messageType = 'danger';
                    } elseif (strlen($newPassword) < 6) {
                        $message = 'New password must be at least 6 characters';
                        $messageType = 'danger';
                    } else {
                        $result = $authController->changePassword($userId, $oldPassword, $newPassword);
                        $message = $result['message'];
                        $messageType = $result['success'] ? 'success' : 'danger';
                    }
                    break;

                case 'upload_photo':
                    if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
                        $result = $authController->uploadProfilePhoto($userId, $_FILES['profile_photo']);
                        $message = $result['message'];
                        $messageType = $result['success'] ? 'success' : 'danger';
                        
                        if ($result['success']) {
                            $user = $authController->getCurrentUser(); // Reload user data
                            $userId = isset($user['_id']) ? (string)$user['_id'] : (isset($user['id']) ? $user['id'] : '');
                        }
                    } else {
                        $fileError = $_FILES['profile_photo']['error'] ?? UPLOAD_ERR_NO_FILE;
                        $errorMessages = [
                            UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize in php.ini',
                            UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE in HTML form',
                            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
                            UPLOAD_ERR_NO_FILE => 'Please select a photo to upload',
                            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
                            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
                            UPLOAD_ERR_EXTENSION => 'Upload stopped by PHP extension'
                        ];
                        $message = $errorMessages[$fileError] ?? 'Unknown upload error';
                        $messageType = 'danger';
                    }
                    break;

                case 'remove_photo':
                    $result = $authController->removeProfilePhoto($userId);
                    $message = $result['message'];
                    $messageType = $result['success'] ? 'success' : 'danger';
                    
                    if ($result['success']) {
                        $user = $authController->getCurrentUser(); // Reload user data
                        $userId = isset($user['_id']) ? (string)$user['_id'] : (isset($user['id']) ? $user['id'] : '');
                    }
                    break;
            }
        } catch (Exception $e) {
            $message = 'Error: ' . $e->getMessage();
            $messageType = 'danger';
            error_log('Profile error: ' . $e->getMessage());
        }
    }
}

// Start output buffering
ob_start();
?>

<style>
/* Profile Page Specific Styles */
.profile-container {
  max-width: 1400px;
  margin: 0 auto;
  padding: 1.5rem;
}

.profile-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1.5rem;
  padding-bottom: 0;
  border-bottom: none;
}

.profile-title {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 1.25rem;
  font-weight: 600;
  color: var(--text-primary);
}

.profile-tabs {
  display: flex;
  gap: 0.5rem;
}

.profile-tab {
  padding: 0.4rem 0.875rem;
  border-radius: var(--radius-md);
  font-size: 0.875rem;
  font-weight: 500;
  color: var(--text-secondary);
  background: transparent;
  border: none;
  cursor: pointer;
  transition: all 0.2s;
}

.profile-tab.active {
  background: var(--bg-secondary);
  color: var(--text-primary);
}

.profile-tab:hover {
  background: var(--bg-secondary);
}

.profile-grid {
  display: grid;
  grid-template-columns: 280px 1fr;
  gap: 1.25rem;
  align-items: start;
}

.profile-photo-section {
  position: relative;
  width: 100%;
  padding-top: 100%;
  background: hsl(20 20% 90%);
  border-radius: var(--radius-lg);
  overflow: hidden;
}

.profile-photo-section img {
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.profile-photo-placeholder {
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  font-size: 3.5rem;
  color: hsl(20 10% 60%);
}

.remove-photo-btn {
  position: absolute;
  top: 0.75rem;
  right: 0.75rem;
  width: 2rem;
  height: 2rem;
  border-radius: 50%;
  background: rgba(255, 255, 255, 0.9);
  border: none;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.2s;
  box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.remove-photo-btn:hover {
  background: white;
  box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.remove-photo-btn svg {
  width: 14px;
  height: 14px;
}

.form-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1rem;
}

.form-section {
  margin-top: 1.5rem;
  padding-top: 1.5rem;
  border-top: 1px solid var(--border-color);
}

.form-section-title {
  font-size: 1rem;
  font-weight: 600;
  margin-bottom: 1rem;
  color: var(--text-primary);
}

.card {
  height: fit-content;
}

.card-content {
  padding: 1.25rem;
}

.form-group {
  margin-bottom: 1rem;
}

.form-label {
  font-size: 0.875rem;
  margin-bottom: 0.375rem;
}

.form-helper {
  font-size: 0.75rem;
  margin-top: 0.25rem;
}

@media (max-width: 1024px) {
  .profile-grid {
    grid-template-columns: 1fr;
  }
  
  .form-row {
    grid-template-columns: 1fr;
  }
}
</style>

<div class="profile-container">
  <!-- Header -->
  <div class="profile-header">
    <div class="profile-title">
      <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/>
        <circle cx="9" cy="7" r="4"/>
        <path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/>
      </svg>
      Users
    </div>
    
    <div style="display: flex; align-items: center; gap: 1rem;">
      <div class="profile-tabs">
        <button class="profile-tab" onclick="window.location.href='users.php'">All Users</button>
        <button class="profile-tab active">Settings</button>
      </div>
      
      <button class="btn btn-primary" onclick="window.location.href='users.php?action=add'">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <circle cx="12" cy="12" r="10"/>
          <line x1="12" y1="8" x2="12" y2="16"/>
          <line x1="8" y1="12" x2="16" y2="12"/>
        </svg>
        Add New User
      </button>
    </div>
  </div>

  <?php if ($message): ?>
  <div class="alert alert-<?php echo htmlspecialchars($messageType); ?>" style="margin-bottom: 1.25rem; padding: 0.875rem 1rem; border-radius: var(--radius-md); font-size: 0.875rem;">
    <?php echo htmlspecialchars($message); ?>
  </div>
  <?php endif; ?>

  <!-- Main Grid -->
  <div class="profile-grid">
    <!-- Left Column: Account Management -->
    <div class="card">
      <div class="card-header">
        <h3 class="card-title">Account Management</h3>
      </div>
      <div class="card-content">
        <!-- Profile Photo -->
        <div class="profile-photo-section">
          <?php if (!empty($user['profile_photo'])): ?>
            <img src="<?php echo htmlspecialchars($user['profile_photo']); ?>" alt="Profile Photo">
            <form method="POST" style="display: inline;">
              <input type="hidden" name="action" value="remove_photo">
              <button type="submit" class="remove-photo-btn" title="Remove Photo">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <line x1="18" y1="6" x2="6" y2="18"/>
                  <line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
              </button>
            </form>
          <?php else: ?>
            <div class="profile-photo-placeholder">
              <svg width="120" height="120" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                <path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/>
                <circle cx="12" cy="7" r="4"/>
              </svg>
            </div>
          <?php endif; ?>
        </div>

        <!-- Upload Photo Button -->
        <form method="POST" enctype="multipart/form-data" id="photoUploadForm" style="margin-top: 0.875rem;">
          <input type="hidden" name="action" value="upload_photo">
          <input type="file" id="profile_photo" name="profile_photo" accept="image/*" style="display: none;" onchange="document.getElementById('photoUploadForm').submit();">
          <button type="button" class="btn btn-secondary w-full" onclick="document.getElementById('profile_photo').click();">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/>
              <polyline points="17 8 12 3 7 8"/>
              <line x1="12" y1="3" x2="12" y2="15"/>
            </svg>
            Upload Photo
          </button>
        </form>

        <!-- Password Change -->
        <form method="POST" style="margin-top: 1.5rem;">
          <input type="hidden" name="action" value="change_password">
          
          <div class="form-group">
            <label for="old_password" class="form-label">Old Password</label>
            <input type="password" id="old_password" name="old_password" class="form-input" placeholder="••••••••" required>
          </div>

          <div class="form-group" style="margin-bottom: 0.5rem;">
            <label for="new_password" class="form-label">New Password</label>
            <input type="password" id="new_password" name="new_password" class="form-input" placeholder="••••••••" required minlength="6">
            <span class="form-helper">Minimum 6 characters</span>
          </div>

          <button type="submit" class="btn btn-secondary w-full" style="margin-top: 0.75rem;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
              <path d="M7 11V7a5 5 0 0110 0v4"/>
            </svg>
            Change Password
          </button>
        </form>
      </div>
    </div>

    <!-- Right Column: Profile Information -->
    <div class="card">
      <div class="card-header">
        <h3 class="card-title">Profile Information</h3>
      </div>
      <div class="card-content">
        <form method="POST" id="profileForm">
          <input type="hidden" name="action" value="update_profile">
          
          <!-- Username & First Name -->
          <div class="form-row">
            <div class="form-group">
              <label for="username" class="form-label">Username</label>
              <input type="text" id="username" name="username" class="form-input" value="<?php echo htmlspecialchars($user['username'] ?? ''); ?>" required>
            </div>
            
            <div class="form-group">
              <label for="firstname" class="form-label">First Name</label>
              <input type="text" id="firstname" name="firstname" class="form-input" value="<?php echo htmlspecialchars($user['firstname'] ?? ''); ?>">
            </div>
          </div>

          <!-- Nickname & Role -->
          <div class="form-row">
            <div class="form-group">
              <label for="nickname" class="form-label">Nickname</label>
              <input type="text" id="nickname" name="nickname" class="form-input" value="<?php echo htmlspecialchars($user['nickname'] ?? ''); ?>">
            </div>
            
            <div class="form-group">
              <label for="role" class="form-label">Role</label>
              <select id="role" name="role" class="form-select">
                <option value="user" <?php echo ($user['role'] ?? '') === 'user' ? 'selected' : ''; ?>>User</option>
                <option value="subscriber" <?php echo ($user['role'] ?? '') === 'subscriber' ? 'selected' : ''; ?>>Subscriber</option>
                <option value="admin" <?php echo ($user['role'] ?? '') === 'admin' ? 'selected' : ''; ?>>Admin</option>
              </select>
            </div>
          </div>

          <!-- Last Name & Display Name -->
          <div class="form-row">
            <div class="form-group">
              <label for="lastname" class="form-label">Last Name</label>
              <input type="text" id="lastname" name="lastname" class="form-input" value="<?php echo htmlspecialchars($user['lastname'] ?? ''); ?>">
            </div>
            
            <div class="form-group">
              <label for="display_name" class="form-label">Display Name Publicly as</label>
              <input type="text" id="display_name" name="display_name" class="form-input" value="<?php echo htmlspecialchars($user['display_name'] ?? $user['username'] ?? ''); ?>">
            </div>
          </div>

          <!-- Contact Info Section -->
          <div class="form-section">
            <h4 class="form-section-title">Contact Info</h4>
            
            <!-- Email & WhatsApp -->
            <div class="form-row">
              <div class="form-group">
                <label for="email" class="form-label">Email (required)</label>
                <input type="email" id="email" name="email" class="form-input" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" required>
              </div>
              
              <div class="form-group">
                <label for="whatsapp" class="form-label">WhatsApp</label>
                <input type="text" id="whatsapp" name="whatsapp" class="form-input" placeholder="@gene-rod" value="<?php echo htmlspecialchars($user['whatsapp'] ?? ''); ?>">
              </div>
            </div>

            <!-- Website & Telegram -->
            <div class="form-row">
              <div class="form-group">
                <label for="website" class="form-label">Website</label>
                <input type="url" id="website" name="website" class="form-input" placeholder="https://example.com" value="<?php echo htmlspecialchars($user['website'] ?? ''); ?>">
              </div>
              
              <div class="form-group">
                <label for="telegram" class="form-label">Telegram</label>
                <input type="text" id="telegram" name="telegram" class="form-input" placeholder="@gene-rod" value="<?php echo htmlspecialchars($user['telegram'] ?? ''); ?>">
              </div>
            </div>
          </div>

          <!-- Save Button -->
          <div style="margin-top: 1.5rem; display: flex; justify-content: flex-end;">
            <button type="submit" class="btn btn-primary" style="min-width: 150px;">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"/>
                <polyline points="17 21 17 13 7 13 7 21"/>
                <polyline points="7 3 7 8 15 8"/>
              </svg>
              Save Changes
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<script>
// Update header avatar with new photo
function updateHeaderAvatar(photoUrl) {
  const headerAvatar = document.getElementById('header-user-avatar');
  if (headerAvatar) {
    if (photoUrl) {
      headerAvatar.innerHTML = `<img src="${photoUrl}" alt="Profile" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">`;
    } else {
      // Show initial letter again
      const username = '<?php echo addslashes($username ?? 'U'); ?>';
      const initial = username.charAt(0).toUpperCase();
      headerAvatar.innerHTML = `<span id="user-initial">${initial}</span>`;
    }
  }
}

// Auto-submit photo upload
document.getElementById('profile_photo')?.addEventListener('change', function() {
  if (this.files && this.files[0]) {
    // Show loading state
    const uploadBtn = this.previousElementSibling;
    const originalHTML = uploadBtn.innerHTML;
    uploadBtn.disabled = true;
    uploadBtn.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="animation: spin 1s linear infinite;"><path d="M21 12a9 9 0 11-6.219-8.56"/></svg> Uploading...';
    
    // Add spin animation
    const style = document.createElement('style');
    style.textContent = '@keyframes spin { to { transform: rotate(360deg); } }';
    document.head.appendChild(style);
  }
});

// Handle remove photo button with confirmation
document.querySelectorAll('form button[type="submit"]').forEach(btn => {
  const form = btn.closest('form');
  const action = form?.querySelector('input[name="action"]')?.value;
  
  if (action === 'remove_photo') {
    form.addEventListener('submit', function(e) {
      if (!confirm('Are you sure you want to remove your profile photo?')) {
        e.preventDefault();
        return false;
      }
    });
  }
});

// Form validation
document.getElementById('profileForm')?.addEventListener('submit', function(e) {
  const email = document.getElementById('email').value;
  if (!email || !email.includes('@')) {
    e.preventDefault();
    if (typeof Toast !== 'undefined') {
      Toast.error('Please enter a valid email address');
    } else {
      alert('Please enter a valid email address');
    }
    return false;
  }
});

// Show success/error toasts and update avatar
<?php if ($message && $messageType): ?>
window.addEventListener('DOMContentLoaded', function() {
  if (typeof Toast !== 'undefined') {
    Toast.<?php echo $messageType === 'success' ? 'success' : 'error'; ?>('<?php echo addslashes($message); ?>');
  }
  
  <?php if ($messageType === 'success'): ?>
    // Update header avatar on successful upload/removal
    <?php if (isset($_POST['action']) && $_POST['action'] === 'upload_photo' && !empty($user['profile_photo'])): ?>
      updateHeaderAvatar('<?php echo addslashes($user['profile_photo']); ?>');
    <?php elseif (isset($_POST['action']) && $_POST['action'] === 'remove_photo'): ?>
      updateHeaderAvatar(null);
    <?php endif; ?>
  <?php endif; ?>
});
<?php endif; ?>
</script>

<?php
// Get page content
$pageContent = ob_get_clean();

// Set page title
$pageTitle = 'User Profile';

// Include layout
include __DIR__ . '/components/layout.php';
?>
