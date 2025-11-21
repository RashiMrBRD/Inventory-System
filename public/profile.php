<?php
/**
 * User Profile Page
 * Manage user account, profile photo, password, and personal information
 */

// Prevent caching for reverse proxy compatibility
header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

require_once __DIR__ . '/../vendor/autoload.php';

use App\Controller\AuthController;
use App\Service\SessionService;
use App\Model\User as UserModel;
use App\Service\SecurityEventService;

$authController = new AuthController();
$authController->requireLogin();

$user = $authController->getCurrentUser();

// Ensure user data is valid - if null after requireLogin, try to re-fetch
if (!$user || !is_array($user)) {
    error_log('Profile: User data is null or invalid, session_id: ' . ($_SESSION['user_id'] ?? 'none'));
    
    // Try to reload user from session
    if (isset($_SESSION['user_id'])) {
        $userModel = new UserModel();
        $user = $userModel->findById($_SESSION['user_id']);
        
        if (!$user || !is_array($user)) {
            error_log('Profile: Failed to reload user, clearing session');
            session_destroy();
            header('Location: /login');
            exit();
        }
    } else {
        header('Location: /login');
        exit();
    }
}

// Get user ID (MongoDB uses _id)
$userId = isset($user['_id']) ? (string)$user['_id'] : (isset($user['id']) ? $user['id'] : '');

function b32chars() { return 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'; }
function base32Decode($b32) {
  $b32 = strtoupper(preg_replace('/[^A-Z2-7]/', '', $b32));
  $alphabet = b32chars();
  $bits = '';
  for ($i=0; $i<strlen($b32); $i++) {
    $val = strpos($alphabet, $b32[$i]);
    if ($val === false) continue;
    $bits .= str_pad(decbin($val), 5, '0', STR_PAD_LEFT);
  }
  $bytes = '';
  for ($i=0; $i+8 <= strlen($bits); $i+=8) {
    $bytes .= chr(bindec(substr($bits, $i, 8)));
  }
  return $bytes;
}
function totpCode($secret, $timeSlice = 30, $digits = 6, $timestamp = null) {
  $secretKey = base32Decode($secret);
  $timestamp = $timestamp ?? time();
  $counter = floor($timestamp / $timeSlice);
  $binCounter = pack('N*', 0) . pack('N*', $counter);
  $hash = hash_hmac('sha1', $binCounter, $secretKey, true);
  $offset = ord(substr($hash, -1)) & 0x0F;
  $truncated = unpack('N', substr($hash, $offset, 4))[1] & 0x7FFFFFFF;
  $code = $truncated % pow(10, $digits);
  return str_pad((string)$code, $digits, '0', STR_PAD_LEFT);
}
function verifyTotp($secret, $code, $window = 1, $digits = 6) {
  $code = preg_replace('/\D/', '', (string)$code);
  $now = time();
  for ($i=-$window; $i<=$window; $i++) {
    $calc = totpCode($secret, 30, $digits, $now + ($i * 30));
    if (hash_equals($calc, $code)) return true;
  }
  return false;
}
function generateBase32Secret($length = 32) {
  $alphabet = b32chars();
  $out = '';
  for ($i=0; $i<$length; $i++) { $out .= $alphabet[random_int(0, strlen($alphabet)-1)]; }
  return $out;
}
function generateRecoveryCodes($count = 10) {
  $codes = [];
  for ($i=0; $i<$count; $i++) {
    $codes[] = strtoupper(bin2hex(random_bytes(4)) . '-' . bin2hex(random_bytes(2)));
  }
  return $codes;
}

function parseUserAgent($userAgent) {
  $browser = 'Unknown Browser';
  $os = 'Unknown OS';
  
  // Parse Browser
  if (preg_match('/Edg\/([0-9.]+)/', $userAgent)) {
    $browser = 'Edge';
  } elseif (preg_match('/Chrome\/([0-9.]+)/', $userAgent)) {
    $browser = 'Chrome';
  } elseif (preg_match('/Safari\/([0-9.]+)/', $userAgent) && !preg_match('/Chrome/', $userAgent)) {
    $browser = 'Safari';
  } elseif (preg_match('/Firefox\/([0-9.]+)/', $userAgent)) {
    $browser = 'Firefox';
  } elseif (preg_match('/MSIE|Trident/', $userAgent)) {
    $browser = 'Internet Explorer';
  } elseif (preg_match('/Opera|OPR/', $userAgent)) {
    $browser = 'Opera';
  }
  
  // Parse OS
  if (preg_match('/Windows NT 10/', $userAgent)) {
    $os = 'Windows 10/11';
  } elseif (preg_match('/Windows NT 6.3/', $userAgent)) {
    $os = 'Windows 8.1';
  } elseif (preg_match('/Windows NT 6.2/', $userAgent)) {
    $os = 'Windows 8';
  } elseif (preg_match('/Windows NT 6.1/', $userAgent)) {
    $os = 'Windows 7';
  } elseif (preg_match('/Windows/', $userAgent)) {
    $os = 'Windows';
  } elseif (preg_match('/Mac OS X ([0-9_]+)/', $userAgent, $matches)) {
    $os = 'macOS';
  } elseif (preg_match('/Linux/', $userAgent)) {
    $os = 'Linux';
  } elseif (preg_match('/Android ([0-9.]+)/', $userAgent, $matches)) {
    $os = 'Android ' . $matches[1];
  } elseif (preg_match('/iPhone|iPad|iPod/', $userAgent)) {
    $os = 'iOS';
  }
  
  return ['browser' => $browser, 'os' => $os];
}

function logSecurityEvent(string $desc, string $type = 'security', array $meta = []): void {
  if (!isset($_SESSION)) { session_start(); }
  
  // Capture IP and location data
  $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
  
  // Try to get location from session if available
  $location = [];
  if (!empty($_SESSION['user_location'])) {
    $location = $_SESSION['user_location'];
  }
  
  // Capture User Agent and parse device info
  $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
  $deviceInfo = parseUserAgent($userAgent);
  
  // Session-level log (UI fallback) with meta
  $events = $_SESSION['security_events'] ?? [];
  $eventMeta = array_merge(['ip' => $ip, 'user_agent' => $userAgent, 'browser' => $deviceInfo['browser'], 'os' => $deviceInfo['os']], $meta);
  if (!empty($location)) {
    $eventMeta['location'] = $location;
  }
  $events[] = [
    'time' => date('Y-m-d H:i:s'), 
    'event' => $desc, 
    'type' => $type,
    'meta' => $eventMeta
  ];
  if (count($events) > 50) { $events = array_slice($events, -50); }
  $_SESSION['security_events'] = $events;

  // Persist to DB (best-effort)
  try {
    global $userId;
    if (!empty($userId)) {
      if (!isset($meta['ip'])) {
        $meta['ip'] = $ip;
      }
      if (!isset($meta['path'])) {
        $meta['path'] = $_SERVER['REQUEST_URI'] ?? '';
      }
      if (!isset($meta['user_agent'])) {
        $meta['user_agent'] = $userAgent;
      }
      if (!isset($meta['browser'])) {
        $meta['browser'] = $deviceInfo['browser'];
      }
      if (!isset($meta['os'])) {
        $meta['os'] = $deviceInfo['os'];
      }
      if (!empty($location)) {
        $meta['location'] = $location;
      }
      $svc = new SecurityEventService();
      $svc->log($userId, $desc, $type, $meta);
    }
  } catch (\Throwable $e) {
    // Ignore persistence failures
  }
}

// Prepare profile analytics (Last Login, 2FA, Devices)
$lastLoginDisplay = '—';
$lastActivityDisplay = '—';
$deviceBrowser = '';
$deviceOS = '';
$ipAddress = '';
$deviceLocation = '';
$sessionsList = [];
$twoFAEnabled = (bool)($user['mfa_enabled'] ?? $user['two_factor_enabled'] ?? $user['two_factor'] ?? false);
$twoFAStatus = $twoFAEnabled ? 'Enabled' : 'Disabled';
$dbSecurityEvents = [];

try {
    if (!empty($userId)) {
        $sessionService = new SessionService();
        $sessionsLimit = max(1, min(100, (int)($_GET['sess_limit'] ?? 5)));
        $latestSessions = $sessionService->getAllSessions($userId, 1);
        if (!empty($latestSessions)) {
            $latest = $latestSessions[0];
            $lastLoginDisplay = $latest['login_time'] ?? '—';
            $lastActivityDisplay = $latest['last_activity'] ?? '—';
            $deviceBrowser = $latest['browser'] ?? '';
            $deviceOS = $latest['os'] ?? '';
            $ipAddress = $latest['ip_address'] ?? '';
            $loc = $latest['location'] ?? [];
            $deviceLocation = trim(($loc['city'] ?? '') . (isset($loc['city']) && isset($loc['country']) ? ', ' : '') . ($loc['country'] ?? ''));
        }
        // Get ALL sessions for client-side pagination (up to 100)
        $allSessionsList = $sessionService->getAllSessions($userId, 100);
        // Get limited sessions for initial PHP rendering
        $sessionsList = array_slice($allSessionsList, 0, $sessionsLimit);
        // Load DB-backed security events (fallback to session later in UI)
        try {
            $secSvc = new SecurityEventService();
            $dbSecurityEvents = $secSvc->list($userId, 50);
        } catch (\Throwable $e) {}
    }
} catch (\Throwable $e) {
    // Non-fatal: fall back to placeholders
}

// Handle form submissions
$message = '';
$messageType = '';

// Session flash support (e.g., redirects from join-team)
if (!isset($_SESSION)) { session_start(); }
if (empty($message) && !empty($_SESSION['profile_flash'])) {
    $flash = $_SESSION['profile_flash'];
    $message = $flash['message'] ?? '';
    $messageType = $flash['type'] ?? 'info';
    unset($_SESSION['profile_flash']);
}

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

                // Leave a team (non-owners)
                case 'leave_team':
                    try {
                        $teamId = $_POST['team_id'] ?? '';
                        $teams = $user['teams'] ?? [];
                        if (!$teamId || !is_array($teams) || count($teams) === 0) {
                            throw new \Exception('Invalid team');
                        }
                        $newTeams = [];
                        $removed = false;
                        foreach ($teams as $t) {
                            $id = is_array($t) ? ($t['id'] ?? '') : (string)$t;
                            $owner = is_array($t) ? ($t['owner'] ?? '') : '';
                            if ($id === $teamId) {
                                if ($owner === $userId) {
                                    throw new \Exception('Owner must delete the team instead');
                                }
                                $removed = true;
                                continue;
                            }
                            $newTeams[] = $t;
                        }
                        if (!$removed) { throw new \Exception('Team not found'); }
                        $userModel = new UserModel();
                        $ok = $userModel->updateUser($userId, ['teams' => $newTeams]);
                        if ($ok) {
                            $user = $authController->getCurrentUser();
                            $message = 'Left the team';
                            $messageType = 'success';
                            logSecurityEvent('Left a team', 'security', ['team_id' => $teamId]);
                        } else {
                            $message = 'Failed to leave team';
                            $messageType = 'danger';
                        }
                    } catch (\Throwable $e) {
                        $message = 'Error leaving team';
                        $messageType = 'danger';
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

                case 'toggle_mfa':
                  $enable = isset($_POST['enable']) && $_POST['enable'] == '1';
                  try {
                    $userModel = new UserModel();
                    $ok = $userModel->updateUser($userId, ['mfa_enabled' => $enable]);
                    if ($ok) {
                      $user = $authController->getCurrentUser();
                      $twoFAEnabled = (bool)($user['mfa_enabled'] ?? $user['two_factor_enabled'] ?? $user['two_factor'] ?? false);
                      $twoFAStatus = $twoFAEnabled ? 'Enabled' : 'Disabled';
                      $message = $twoFAEnabled ? 'Two-Factor Authentication enabled' : 'Two-Factor Authentication disabled';
                      $messageType = 'success';
                      logSecurityEvent($twoFAEnabled ? 'Enabled 2FA' : 'Disabled 2FA');
                    } else {
                      $message = 'Failed to update Two-Factor Authentication';
                      $messageType = 'danger';
                    }
                  } catch (\Throwable $e) {
                    $message = 'Error updating Two-Factor Authentication';
                    $messageType = 'danger';
                  }
                  break;

                case 'terminate_other_sessions':
                    try {
                    $ss = new SessionService();
                    $terminated = $ss->terminateOtherSessions($userId);
                    if ($terminated > 0) {
                      $message = 'Terminated ' . $terminated . ' other session' . ($terminated > 1 ? 's' : '');
                      $messageType = 'success';
                      logSecurityEvent('Terminated ' . $terminated . ' other sessions');
                    } else {
                      $message = 'No other active sessions to terminate';
                      $messageType = 'warning';
                      logSecurityEvent('Attempted to terminate other sessions (none active)');
                    }
                    // Refresh sessions list
                    $sessionsLimit = max(1, min(100, (int)($_GET['sess_limit'] ?? 10)));
                    $sessionsList = $ss->getAllSessions($userId, $sessionsLimit);
                  } catch (\Throwable $e) {
                    $message = 'Failed to terminate sessions';
                    $messageType = 'danger';
                  }
                  break;

                case 'end_session':
                    try {
                        $sid = $_POST['session_id'] ?? '';
                        if ($sid) {
                      $ss = new SessionService();
                      $ok = $ss->endSession($sid);
                      if ($ok) {
                        $message = 'Session ended successfully';
                        $messageType = 'success';
                        logSecurityEvent('Ended session ' . substr($sid, 0, 8) . '…');
                      } else {
                        $message = 'Failed to end session';
                        $messageType = 'danger';
                      }
                      $sessionsLimit = max(1, min(100, (int)($_GET['sess_limit'] ?? 10)));
                      $sessionsList = $ss->getAllSessions($userId, $sessionsLimit);
                    }
                  } catch (\Throwable $e) {
                    $message = 'Failed to end session';
                    $messageType = 'danger';
                  }
                  break;

                case 'start_mfa_setup':
                    try {
                    $userModel = new UserModel();
                    $secret = generateBase32Secret(32);
                    $ok = $userModel->updateUser($userId, ['mfa_temp_secret' => $secret]);
                    if ($ok) {
                      $user = $authController->getCurrentUser();
                      $message = '2FA setup started. Scan the QR and verify code.';
                      $messageType = 'success';
                      logSecurityEvent('Started 2FA setup');
                    } else {
                      $message = 'Failed to start 2FA setup';
                      $messageType = 'danger';
                    }
                  } catch (\Throwable $e) {
                    $message = 'Error starting 2FA setup';
                    $messageType = 'danger';
                  }
                  break;

                case 'verify_mfa_setup':
                    try {
                        $code = $_POST['totp_code'] ?? '';
                        $tempSecret = $user['mfa_temp_secret'] ?? '';
                        if ($tempSecret && $code && verifyTotp($tempSecret, $code)) {
                      $recovery = generateRecoveryCodes(10);
                      $userModel = new UserModel();
                      $ok = $userModel->updateUser($userId, [
                        'mfa_enabled' => true,
                        'mfa_secret' => $tempSecret,
                        'mfa_temp_secret' => null,
                        'mfa_recovery_codes' => $recovery
                      ]);
                      if ($ok) {
                        $user = $authController->getCurrentUser();
                        $twoFAEnabled = true;
                        $twoFAStatus = 'Enabled';
                        $message = 'Two-Factor Authentication enabled. Save your recovery codes.';
                        $messageType = 'success';
                        logSecurityEvent('Verified 2FA setup');
                        logSecurityEvent('Generated recovery codes');
                      } else {
                        $message = 'Failed to enable 2FA';
                        $messageType = 'danger';
                      }
                    } else {
                      $message = 'Invalid authentication code';
                      $messageType = 'danger';
                    }
                  } catch (\Throwable $e) {
                    $message = 'Error verifying 2FA setup';
                    $messageType = 'danger';
                  }
                  break;

                case 'cancel_mfa_setup':
                    try {
                        $userModel = new UserModel();
                        $ok = $userModel->updateUser($userId, ['mfa_temp_secret' => null]);
                        if ($ok) {
                            $user = $authController->getCurrentUser();
                            $message = '2FA setup canceled';
                            $messageType = 'success';
                            logSecurityEvent('Canceled 2FA setup');
                        } else {
                            $message = 'Failed to cancel 2FA setup';
                            $messageType = 'danger';
                        }
                    } catch (\Throwable $e) {
                        $message = 'Error canceling 2FA setup';
                        $messageType = 'danger';
                    }
                    break;

                case 'disable_mfa':
                    try {
                        $userModel = new UserModel();
                        $ok = $userModel->updateUser($userId, [
                            'mfa_enabled' => false,
                            'mfa_secret' => null,
                            'mfa_recovery_codes' => []
                        ]);
                        if ($ok) {
                      $user = $authController->getCurrentUser();
                      $twoFAEnabled = false;
                      $twoFAStatus = 'Disabled';
                      $message = 'Two-Factor Authentication disabled';
                      $messageType = 'success';
                      logSecurityEvent('Disabled 2FA');
                    } else {
                      $message = 'Failed to disable 2FA';
                      $messageType = 'danger';
                    }
                  } catch (\Throwable $e) {
                    $message = 'Error disabling 2FA';
                    $messageType = 'danger';
                  }
                  break;

                case 'regenerate_recovery_codes':
                    try {
                        $codes = generateRecoveryCodes(10);
                        $userModel = new UserModel();
                        $ok = $userModel->updateUser($userId, ['mfa_recovery_codes' => $codes]);
                        if ($ok) {
                          $user = $authController->getCurrentUser();
                          $message = 'Recovery codes regenerated';
                          $messageType = 'success';
                          logSecurityEvent('Regenerated recovery codes');
                        } else {
                          $message = 'Failed to regenerate recovery codes';
                          $messageType = 'danger';
                        }
                    } catch (\Throwable $e) {
                        $message = 'Error regenerating recovery codes';
                        $messageType = 'danger';
                    }
                    break;

                // Add a billing method (masked/non-sensitive only)
                case 'add_billing_method':
                    try {
                        $type = $_POST['type'] ?? '';
                        $provider = trim($_POST['provider'] ?? '');
                        $label = trim($_POST['label'] ?? '');
                        $name = trim($_POST['name'] ?? '');
                        $last4 = preg_replace('/\D/', '', $_POST['last4'] ?? '');
                        $methods = $user['billing_methods'] ?? [];
                        $entry = ['type' => $type, 'provider' => $provider];
                        if ($type === 'credit_card') {
                            $entry['label'] = $name;
                            $entry['masked'] = $last4 ? ('•••• ' . substr($last4, -4)) : '';
                        } else {
                            $entry['label'] = $label ?: $name;
                        }
                        $methods[] = $entry;
                        $userModel = new UserModel();
                        $ok = $userModel->updateUser($userId, ['billing_methods' => $methods]);
                        if ($ok) {
                            $user = $authController->getCurrentUser();
                            $message = 'Payment method added';
                            $messageType = 'success';
                        } else {
                            $message = 'Failed to add payment method';
                            $messageType = 'danger';
                        }
                    } catch (\Throwable $e) {
                        $message = 'Error adding payment method';
                        $messageType = 'danger';
                    }
                    break;

                // Remove billing method by index
                case 'remove_billing_method':
                    try {
                        $idx = (int)($_POST['index'] ?? -1);
                        $methods = $user['billing_methods'] ?? [];
                        if ($idx >= 0 && $idx < count($methods)) {
                            array_splice($methods, $idx, 1);
                            $userModel = new UserModel();
                            $ok = $userModel->updateUser($userId, ['billing_methods' => $methods]);
                            if ($ok) {
                                $user = $authController->getCurrentUser();
                                $message = 'Payment method removed';
                                $messageType = 'success';
                            } else {
                                $message = 'Failed to remove payment method';
                                $messageType = 'danger';
                            }
                        } else {
                            $message = 'Invalid method index';
                            $messageType = 'danger';
                        }
                    } catch (\Throwable $e) {
                        $message = 'Error removing payment method';
                        $messageType = 'danger';
                    }
                    break;

                // Create a new team (owner)
                case 'create_team':
                    try {
                        $teamName = trim($_POST['team_name'] ?? '');
                        if ($teamName === '') { throw new \Exception('Team name required'); }
                        $teams = $user['teams'] ?? [];
                        $token = bin2hex(random_bytes(8));
                        $host = (isset($_SERVER['HTTP_HOST']) ? (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] : '');
                        $invite = $host . '/join-team.php?token=' . $token;
                        $teams[] = [
                            'id' => bin2hex(random_bytes(6)),
                            'name' => $teamName,
                            'created_at' => date('Y-m-d H:i:s'),
                            'invite_link' => $invite,
                            'token' => $token,
                            'owner' => $userId
                        ];
                        $userModel = new UserModel();
                        $ok = $userModel->updateUser($userId, ['teams' => $teams]);
                        if ($ok) {
                            $user = $authController->getCurrentUser();
                            $message = 'Team created';
                            $messageType = 'success';
                        } else {
                            $message = 'Failed to create team';
                            $messageType = 'danger';
                        }
                    } catch (\Throwable $e) {
                        $message = 'Error creating team';
                        $messageType = 'danger';
                    }
                    break;

                // Invite member via email (log only)
                case 'invite_team_email':
                    try {
                        $teamId = $_POST['team_id'] ?? '';
                        $email = trim($_POST['email'] ?? '');
                        if ($email === '') { throw new \Exception('Email required'); }
                        logSecurityEvent('Sent team invite to ' . $email, 'security', ['team_id' => $teamId]);
                        $message = 'Invite link prepared for ' . htmlspecialchars($email);
                        $messageType = 'success';
                    } catch (\Throwable $e) {
                        $message = 'Error preparing invite';
                        $messageType = 'danger';
                    }
                    break;

                // Remove a team by ID
                case 'remove_team':
                    try {
                        $teamId = $_POST['team_id'] ?? '';
                        $teams = $user['teams'] ?? [];
                        if (!$teamId || !is_array($teams) || count($teams) === 0) {
                            throw new \Exception('Invalid team');
                        }
                        $newTeams = [];
                        $removed = false;
                        foreach ($teams as $t) {
                            $id = is_array($t) ? ($t['id'] ?? '') : (string)$t;
                            $owner = is_array($t) ? ($t['owner'] ?? '') : '';
                            if ($id === $teamId) {
                                if ($owner !== $userId) {
                                    throw new \Exception('Only the team owner can delete the team');
                                }
                                $removed = true;
                                continue;
                            }
                            $newTeams[] = $t;
                        }
                        if (!$removed) { throw new \Exception('Team not found'); }
                        $userModel = new UserModel();
                        $ok = $userModel->updateUser($userId, ['teams' => $newTeams]);
                        if ($ok) {
                            $user = $authController->getCurrentUser();
                            $message = 'Team deleted';
                            $messageType = 'success';
                            logSecurityEvent('Deleted a team', 'security', ['team_id' => $teamId]);
                        } else {
                            $message = 'Failed to delete team';
                            $messageType = 'danger';
                        }
                    } catch (\Throwable $e) {
                        $message = 'Error deleting team';
                        $messageType = 'danger';
                    }
                    break;
            }
        } catch (Exception $e) {
            $message = 'Error: ' . $e->getMessage();
            $messageType = 'danger';
            error_log('Profile error: ' . $e->getMessage());
        }
    }

    // Post/Redirect/Get: avoid resubmission on refresh
    if (!headers_sent() && !empty($message)) {
        if (!isset($_SESSION)) { session_start(); }
        $_SESSION['profile_flash'] = ['message' => $message, 'type' => $messageType ?: 'info'];
        $redirectUrl = $_SERVER['PHP_SELF'];
        // preserve relevant query params if present
        $qs = [];
        if (isset($_GET['tab'])) { $qs['tab'] = $_GET['tab']; }
        if (isset($_GET['sess_limit'])) { $qs['sess_limit'] = $_GET['sess_limit']; }
        if (!empty($qs)) { $redirectUrl .= '?' . http_build_query($qs); }
        header('Location: ' . $redirectUrl);
        exit;
    }
}

// Start output buffering
ob_start();

// ========== EXPERIMENTAL FEATURE WARNING ==========
// To remove this warning system, delete the following 2 lines and the features/experimental-warning folder
require_once __DIR__ . '/features/experimental-warning/experimental-warning.php';
renderExperimentalWarning('User Profile Management');
// System auto-detects page and shows contextual warning with natural language
// ===================================================
?>

<style>
/* Profile Page Specific Styles */
html, body {
  height: 100%;
}
.profile-container {
  max-width: 100%;
  width: 100%;
  margin: 0 auto;
  padding: 0.75rem 1rem;
  box-sizing: border-box;
  min-height: 100vh;
  height: 100%;
  display: flex;
  flex-direction: column;
}

.profile-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 0.75rem;
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
  position: relative;
}

.profile-tab.active {
  background: var(--bg-secondary);
  color: var(--text-primary);
  font-weight: 600;
  box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.profile-tab:hover:not(.active) {
  background: hsl(0 0% 97%);
}

.profile-grid {
  display: grid;
  grid-template-columns: 220px 1fr;
  gap: 1rem;
  align-items: start;
  height: 100%;
  min-height: 0;
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
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 0.75rem;
  align-items: start;
}

.form-section {
  margin-top: 0.75rem;
  padding-top: 0.75rem;
  border-top: 1px solid var(--border-color);
}

.form-section-title {
  font-size: 0.9375rem;
  font-weight: 600;
  margin-bottom: 0.5rem;
  color: var(--text-primary);
}

.card {
  height: fit-content;
}

.card-content {
  padding: 0.75rem;
}

.form-group {
  margin-bottom: 0.5rem;
  min-width: 0;
}

.form-label {
  display: block;
  font-size: 0.8125rem;
  margin-bottom: 0.25rem;
  font-weight: 500;
  color: var(--text-primary);
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

/* Enhanced biopage layout */
.metrics-grid {
  display: grid;
  grid-template-columns: repeat(5, 1fr);
  gap: 0.5rem;
  margin-bottom: 0.5rem;
}

.metric-card {
  background: white;
  border: 1px solid var(--border-color);
  border-radius: var(--radius-md);
  padding: 0.5rem 0.75rem;
  display: flex;
  flex-direction: column;
  gap: 0.125rem;
  min-width: 0;
}

.metric-value { 
  font-size: 0.9375rem; 
  font-weight: 700; 
  color: var(--text-primary); 
  line-height: 1.2; 
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.metric-label { 
  font-size: 0.6875rem; 
  color: var(--text-secondary); 
  text-transform: uppercase; 
  letter-spacing: 0.025em;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.profile-tab-content { display: none; }
.profile-tab-content.active { display: block; }

.badge {
  display: inline-flex;
  align-items: center;
  gap: 0.375rem;
  padding: 0.25rem 0.5rem;
  font-size: 0.75rem;
  border-radius: 9999px;
  background: hsl(214 95% 93%);
  color: hsl(222 47% 17%);
  border: 1px solid var(--border-color);
  font-weight: 600;
}

@media (max-width: 1280px) {
  .metrics-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); }
}

@media (max-width: 640px) {
  .metrics-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}

/* Shadcn Table Styling */
.profile-tab-content table {
  width: 100%;
  border-collapse: separate;
  border-spacing: 0;
  font-size: 0.8125rem;
}

.profile-tab-content table thead {
  border-bottom: 1px solid var(--border-color);
}

.profile-tab-content table thead tr {
  border-bottom: 1px solid var(--border-color);
}

.profile-tab-content table thead th {
  padding: 0.5rem;
  text-align: left;
  font-weight: 500;
  color: var(--text-secondary);
  border-bottom: 1px solid var(--border-color);
  white-space: nowrap;
  font-size: 0.75rem;
}

.profile-tab-content table tbody tr {
  border-bottom: 1px solid var(--border-color);
  transition: background-color 0.2s;
}

.profile-tab-content table tbody tr:hover {
  background-color: hsl(0 0% 98%);
}

.profile-tab-content table tbody tr:last-child {
  border-bottom: 0;
}

.profile-tab-content table tbody td {
  padding: 0.5rem;
  border-bottom: 1px solid var(--border-color);
  white-space: nowrap;
  font-size: 0.8125rem;
}

/* Compact Forms */
.form-input, .form-select {
  width: 100%;
  padding: 0.375rem 0.5rem;
  font-size: 0.8125rem;
  height: 2rem;
  box-sizing: border-box;
}

.btn {
  padding: 0.375rem 0.75rem;
  font-size: 0.8125rem;
  height: 2rem;
}

.btn-sm {
  padding: 0.25rem 0.5rem;
  font-size: 0.75rem;
  height: 1.75rem;
}

/* Compact Alert */
.alert {
  padding: 0.5rem 0.75rem;
  font-size: 0.8125rem;
  margin-bottom: 0.5rem;
}

/* Card Header Compact */
.card-header {
  padding: 0.75rem;
  border-bottom: 1px solid var(--border-color);
}

.card-title {
  font-size: 0.9375rem;
  font-weight: 600;
  margin: 0;
}

/* Right Column Sections */
.profile-grid > div:last-child {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  min-width: 0;
  overflow: hidden;
}

/* Activity Row Compact */
.activity-row {
  padding: 0.5rem;
  font-size: 0.8125rem;
}

/* Profile Photo Smaller */
.profile-photo-section {
  border-radius: var(--radius-md);
}

.profile-photo-placeholder svg {
  width: 80px;
  height: 80px;
}

/* Upload Button Full Width */
.w-full {
  width: 100%;
}

/* Pagination Styles */
.card-footer {
  background: var(--bg-secondary);
  border-bottom-left-radius: var(--radius-md);
  border-bottom-right-radius: var(--radius-md);
}

#sessions-pagination {
  min-height: 2rem;
}

#sessions-pagination-controls,
#activity-pagination-controls {
  display: flex;
  gap: 0.25rem;
  align-items: center;
  flex-wrap: wrap;
}

#sessions-pagination-controls button,
#activity-pagination-controls button {
  min-width: 2rem;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}

#sessions-pagination-controls button:disabled,
#activity-pagination-controls button:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}

#sessions-pagination-controls button:hover:not(:disabled),
#activity-pagination-controls button:hover:not(:disabled) {
  background: var(--bg-secondary);
  border-color: var(--border-color);
}

/* Keyboard shortcut badges */
kbd {
  font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
  font-size: 0.6875rem;
  font-weight: 600;
  line-height: 1;
  padding: 0.125rem 0.25rem;
  border-radius: 3px;
  border: 1px solid var(--border-color);
  background: var(--bg-secondary);
  color: var(--text-primary);
  box-shadow: 0 1px 0 rgba(0,0,0,0.05);
}

/* Ensure proper box-sizing throughout */
* {
  box-sizing: border-box;
}

/* Card improvements */
.card {
  overflow: hidden;
}

.card-content > form {
  width: 100%;
}

/* Ensure inputs don't overflow their containers */
input[type="text"],
input[type="email"],
input[type="url"],
input[type="password"],
select,
textarea {
  max-width: 100%;
}

/* Better responsive for metrics */
@media (max-width: 1024px) {
  .metrics-grid {
    grid-template-columns: repeat(3, 1fr);
  }
}

@media (max-width: 768px) {
  .metrics-grid {
    grid-template-columns: repeat(2, 1fr);
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
        <button class="profile-tab tab-trigger active" data-tab="overview">Overview</button>
        <button class="profile-tab tab-trigger" data-tab="activity">Activity</button>
        <button class="profile-tab tab-trigger" data-tab="billing">Billing</button>
        <button class="profile-tab tab-trigger" data-tab="teams">Teams</button>
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
  <div class="alert alert-<?php echo htmlspecialchars($messageType); ?>" style="position: relative;">
    <button type="button" class="alert-close" aria-label="Close" onclick="this.parentElement.remove()" style="position:absolute; right:8px; top:8px; background:transparent; border:none; cursor:pointer; font-size:14px; line-height:1;">&times;</button>
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
        <form method="POST" enctype="multipart/form-data" id="photoUploadForm" style="margin-top: 0.5rem;">
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

      </div>
    </div>

    <!-- Right Column: Profile Content -->
    <div style="display: flex; flex-direction: column; gap: 0.5rem;">
      <div class="metrics-grid">
        <div class="metric-card">
          <div class="metric-value"><?php echo htmlspecialchars($user['role'] ?? '—'); ?></div>
          <div class="metric-label">Role</div>
        </div>
        <div class="metric-card">
          <div class="metric-value"><?php echo htmlspecialchars($user['username'] ?? '—'); ?></div>
          <div class="metric-label">Username</div>
        </div>
        <div class="metric-card">
          <div class="metric-value"><?php echo htmlspecialchars($user['email'] ?? '—'); ?></div>
          <div class="metric-label">Email</div>
        </div>
        <div class="metric-card">
          <div class="metric-value"><?php echo htmlspecialchars($lastLoginDisplay); ?></div>
          <div class="metric-label">Last Login</div>
        </div>
        <div class="metric-card">
          <div class="metric-value"><?php echo htmlspecialchars($twoFAStatus); ?></div>
          <div class="metric-label">2FA</div>
        </div>
      </div>

      <div class="card">
        <div class="card-header">
          <h3 class="card-title">Account</h3>
        </div>
        <div class="card-content">
          <div id="profile-tab-overview" class="profile-tab-content active">
          </div>

          

          <div id="profile-tab-activity" class="profile-tab-content">
            <div class="form-section">
              <h4 class="form-section-title">Recent Activity</h4>
              <?php $events = (is_array($dbSecurityEvents) && count($dbSecurityEvents) > 0)
                  ? $dbSecurityEvents
                  : (is_array($_SESSION['security_events'] ?? []) ? array_reverse($_SESSION['security_events']) : []);
              ?>
              <?php if (is_array($events) && count($events) > 0): ?>
                <div class="card">
                  <div class="card-header" style="display:flex; align-items:center; justify-content: space-between;">
                    <div style="display:flex; align-items:center; gap: 0.5rem;">
                      <label for="activityFilter" class="form-label" style="margin:0;">Filter</label>
                      <select id="activityFilter" class="form-select" style="width:auto;" onchange="filterActivity(this.value)">
                        <option value="all">All</option>
                        <option value="security">Security</option>
                        <option value="session">Session</option>
                      </select>
                      <label for="activity_limit" class="form-label" style="margin:0 0 0 0.5rem;">Show</label>
                      <select id="activity_limit" class="form-select" style="width:auto;" onchange="loadActivityPage(1)">
                        <option value="5" selected>5</option>
                        <option value="10">10</option>
                        <option value="20">20</option>
                        <option value="50">50</option>
                      </select>
                    </div>
                    <div style="display:flex; gap: 0.5rem;">
                      <button type="button" class="btn btn-secondary" onclick="downloadActivityCSV()">Export CSV</button>
                    </div>
                  </div>
                  <div class="card-content" style="display:grid; gap: 0.5rem;">
                    <script id="activity-events-data" type="application/json"><?php echo json_encode($events); ?></script>
                    <?php foreach ($events as $ev): ?>
                      <div class="activity-row" data-type="<?php echo htmlspecialchars($ev['type'] ?? 'security'); ?>" style="display:flex; align-items:center; justify-content: space-between; border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 0.5rem 0.75rem;">
                        <div style="display:flex; flex-direction: column; gap: 0.125rem;">
                          <div style="font-weight:600; color: var(--text-primary);">
                            <?php echo htmlspecialchars($ev['event'] ?? 'Event'); ?>
                          </div>
                          <?php if (!empty($ev['meta'])): ?>
                            <?php 
                              $meta = $ev['meta'];
                              $displayIp = $meta['ip'] ?? '';
                              $browser = $meta['browser'] ?? '';
                              $os = $meta['os'] ?? '';
                              $location = '';
                              
                              // Build device info string
                              $deviceParts = [];
                              if (!empty($browser)) $deviceParts[] = $browser;
                              if (!empty($os)) $deviceParts[] = $os;
                              $deviceInfo = !empty($deviceParts) ? implode(' on ', $deviceParts) : '';
                              
                              // Check for geolocation data
                              if (!empty($meta['location'])) {
                                $loc = $meta['location'];
                                $locParts = [];
                                if (!empty($loc['city'])) $locParts[] = $loc['city'];
                                if (!empty($loc['country'])) $locParts[] = $loc['country'];
                                if (!empty($locParts)) {
                                  $location = implode(', ', $locParts);
                                }
                              } elseif (!empty($meta['city']) || !empty($meta['country'])) {
                                $locParts = [];
                                if (!empty($meta['city'])) $locParts[] = $meta['city'];
                                if (!empty($meta['country'])) $locParts[] = $meta['country'];
                                if (!empty($locParts)) {
                                  $location = implode(', ', $locParts);
                                }
                              }
                              
                              // Build display info with IP and device
                              $displayParts = [];
                              
                              // Add IP (convert localhost to actual label)
                              if (in_array($displayIp, ['::1', '127.0.0.1', 'localhost'])) {
                                if (!empty($location)) {
                                  $displayParts[] = $location;
                                }
                              } else {
                                $displayParts[] = $displayIp;
                                if (!empty($location)) {
                                  $displayParts[] = $location;
                                }
                              }
                              
                              // Add device info
                              if (!empty($deviceInfo)) {
                                $displayParts[] = $deviceInfo;
                              }
                              
                              $displayInfo = !empty($displayParts) ? implode(' • ', $displayParts) : '';
                            ?>
                            <?php if (!empty($displayInfo)): ?>
                              <div class="form-helper"><?php echo htmlspecialchars($displayInfo); ?></div>
                            <?php endif; ?>
                          <?php endif; ?>
                        </div>
                        <div class="form-helper" style="white-space: nowrap;"><?php echo htmlspecialchars($ev['time'] ?? ''); ?></div>
                      </div>
                    <?php endforeach; ?>
                  </div>
                  <!-- Activity Pagination -->
                  <div class="card-footer" id="activity-pagination" style="display:flex; align-items:center; justify-content:space-between; padding:0.75rem; border-top:1px solid var(--border-color);">
                    <div style="display:flex; align-items:center; gap:0.75rem;">
                      <div style="font-size:0.8125rem; color:var(--text-secondary);">
                        Showing <span id="activity-showing">1-<?php echo min(5, count($events)); ?></span> of <span id="activity-total"><?php echo count($events); ?></span>
                      </div>
                      <div style="font-size:0.6875rem; color:var(--text-secondary); display:flex; align-items:center; gap:0.25rem;" title="Use keyboard shortcuts to navigate">
                        <kbd style="padding:0.125rem 0.25rem; background:white; border:1px solid var(--border-color); border-radius:3px; font-size:0.6875rem;">Shift</kbd>
                        <span>+</span>
                        <kbd style="padding:0.125rem 0.25rem; background:white; border:1px solid var(--border-color); border-radius:3px; font-size:0.6875rem;">←</kbd>
                        <kbd style="padding:0.125rem 0.25rem; background:white; border:1px solid var(--border-color); border-radius:3px; font-size:0.6875rem;">→</kbd>
                      </div>
                    </div>
                    <div id="activity-pagination-controls" style="display:flex; gap:0.25rem;"></div>
                  </div>
                </div>
              <?php else: ?>
                <div class="card" style="border: 1px dashed var(--border-color);">
                  <div class="card-content">
                    <div class="form-helper">No recent activity to display</div>
                  </div>
                </div>
              <?php endif; ?>
            </div>
          </div>

          <div id="profile-tab-billing" class="profile-tab-content">
            <div class="form-section">
              <h4 class="form-section-title">Billing</h4>
              <div class="card">
                <div class="card-content" style="display:grid; gap: 1rem;">
                  <div style="display:flex; align-items:center; justify-content: space-between;">
                    <div>
                      <div style="font-weight:600;">Current Plan</div>
                      <div class="form-helper">Free (upgrade options coming soon)</div>
                    </div>
                    <span class="badge">Active</span>
                  </div>

                  <div style="border-top: 1px solid var(--border-color); padding-top: 0.75rem;">
                    <div style="font-weight:600; margin-bottom: 0.5rem;">Payment Methods (Philippines)</div>
                    <?php $methods = $user['billing_methods'] ?? []; ?>
                    <?php if (is_array($methods) && count($methods) > 0): ?>
                      <div style="display:grid; gap: 0.5rem;">
                        <?php foreach ($methods as $idx => $m): ?>
                          <div style="display:flex; align-items:center; justify-content: space-between; border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 0.5rem 0.75rem;">
                            <div>
                              <div style="font-weight:600;">
                                <?php echo htmlspecialchars(strtoupper($m['type'] ?? '')); ?> — <?php echo htmlspecialchars($m['provider'] ?? ''); ?>
                              </div>
                              <div class="form-helper">
                                <?php echo htmlspecialchars($m['label'] ?? ($m['masked'] ?? '')); ?>
                              </div>
                            </div>
                            <form method="POST" onsubmit="return confirm('Remove this payment method?');" style="margin:0;">
                              <input type="hidden" name="action" value="remove_billing_method">
                              <input type="hidden" name="index" value="<?php echo (int)$idx; ?>">
                              <button type="submit" class="btn btn-secondary btn-sm">Remove</button>
                            </form>
                          </div>
                        <?php endforeach; ?>
                      </div>
                    <?php else: ?>
                      <div class="form-helper">No payment methods added yet</div>
                    <?php endif; ?>

                    <div style="display:grid; grid-template-columns: repeat(3, minmax(0,1fr)); gap: 0.75rem; margin-top: 0.75rem;">
                      <!-- Credit Card (store masked only) -->
                      <form method="POST" style="border: 1px dashed var(--border-color); border-radius: var(--radius-md); padding: 0.75rem;">
                        <input type="hidden" name="action" value="add_billing_method">
                        <input type="hidden" name="type" value="credit_card">
                        <div style="font-weight:600; margin-bottom: 0.5rem;">Credit Card</div>
                        <div class="form-group"><input name="provider" class="form-input" placeholder="Provider (e.g., VISA, MasterCard)"></div>
                        <div class="form-group"><input name="name" class="form-input" placeholder="Name on Card"></div>
                        <div class="form-group"><input name="last4" class="form-input" placeholder="Last 4 digits"></div>
                        <div class="form-helper">We only store non-sensitive info (provider/name/last4)</div>
                        <button type="submit" class="btn btn-primary" style="margin-top: 0.5rem;">Add Card</button>
                      </form>

                      <!-- Bank Account -->
                      <form method="POST" style="border: 1px dashed var(--border-color); border-radius: var(--radius-md); padding: 0.75rem;">
                        <input type="hidden" name="action" value="add_billing_method">
                        <input type="hidden" name="type" value="bank">
                        <div style="font-weight:600; margin-bottom: 0.5rem;">Bank Account</div>
                        <div class="form-group"><input name="provider" class="form-input" placeholder="Bank (e.g., BDO, BPI)"></div>
                        <div class="form-group"><input name="label" class="form-input" placeholder="Account Name (masked acc no.)"></div>
                        <button type="submit" class="btn btn-primary" style="margin-top: 0.5rem;">Add Bank</button>
                      </form>

                      <!-- E-Wallet -->
                      <form method="POST" style="border: 1px dashed var(--border-color); border-radius: var(--radius-md); padding: 0.75rem;">
                        <input type="hidden" name="action" value="add_billing_method">
                        <input type="hidden" name="type" value="ewallet">
                        <div style="font-weight:600; margin-bottom: 0.5rem;">E‑Wallet</div>
                        <div class="form-group">
                          <select name="provider" class="form-select">
                            <option value="GCash">GCash</option>
                            <option value="PayMaya">PayMaya</option>
                          </select>
                        </div>
                        <div class="form-group"><input name="label" class="form-input" placeholder="Account Label (e.g., 09*********)"></div>
                        <button type="submit" class="btn btn-primary" style="margin-top: 0.5rem;">Add E‑Wallet</button>
                      </form>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div id="profile-tab-teams" class="profile-tab-content">
            <div class="form-section">
              <h4 class="form-section-title">Teams</h4>
              <div class="card" style="margin-bottom: 0.75rem;">
                <div class="card-content" style="display:flex; gap: 0.75rem; align-items: center;">
                  <form method="POST" style="display:flex; gap: 0.5rem; align-items:center;">
                    <input type="hidden" name="action" value="create_team">
                    <input type="text" name="team_name" class="form-input" placeholder="Team name" required>
                    <button type="submit" class="btn btn-primary">Create Team</button>
                  </form>
                </div>
              </div>
              <?php $teams = $user['teams'] ?? []; ?>
              <?php if (is_array($teams) && count($teams) > 0): ?>
                <div class="card">
                  <div class="card-content" style="display:grid; gap: 0.5rem;">
                    <?php foreach ($teams as $team): ?>
                      <div style="display:flex; align-items:flex-start; justify-content: space-between; border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 0.5rem 0.75rem; gap: 0.75rem;">
                        <div style="flex:1;">
                          <div style="font-weight:600; color: var(--text-primary);">
                            <?php echo htmlspecialchars(is_array($team) ? ($team['name'] ?? 'Team') : (string)$team); ?>
                          </div>
                          <div class="form-helper">Created: <?php echo htmlspecialchars($team['created_at'] ?? ''); ?></div>
                          <?php $invite = $team['invite_link'] ?? ''; ?>
                          <?php $isOwner = (($team['owner'] ?? '') === $userId); ?>
                          <?php if ($isOwner): ?>
                            <div style="margin-top: 0.5rem; display:flex; gap: 0.5rem; align-items:center;">
                              <input type="text" readonly class="form-input" value="<?php echo htmlspecialchars($invite); ?>" style="max-width: 420px;">
                              <button type="button" class="btn btn-secondary btn-sm" onclick="copyText('<?php echo htmlspecialchars($invite, ENT_QUOTES); ?>')">Copy Link</button>
                            </div>
                            <form method="POST" style="margin-top: 0.5rem; display:flex; gap: 0.5rem; align-items:center;">
                              <input type="hidden" name="action" value="invite_team_email">
                              <input type="hidden" name="team_id" value="<?php echo htmlspecialchars($team['id'] ?? ''); ?>">
                              <input type="email" name="email" class="form-input" placeholder="Invite via email" required>
                              <button type="submit" class="btn btn-secondary btn-sm">Send Invite</button>
                              <a class="btn btn-secondary btn-sm" href="mailto:?subject=<?php echo rawurlencode('Join team ' . ($team['name'] ?? '')); ?>&body=<?php echo rawurlencode('Use this link to join: ' . $invite); ?>">Mailto</a>
                            </form>
                          <?php endif; ?>
                        </div>
                        <div style="display:flex; flex-direction: column; align-items: flex-end; gap: 0.5rem;">
                          <?php $isOwner = (($team['owner'] ?? '') === $userId); ?>
                          <span class="badge"><?php echo $isOwner ? 'Owner' : 'Member'; ?></span>
                          <?php if ($isOwner): ?>
                            <form method="POST" onsubmit="return confirm('Delete this team? This cannot be undone.');">
                              <input type="hidden" name="action" value="remove_team">
                              <input type="hidden" name="team_id" value="<?php echo htmlspecialchars($team['id'] ?? ''); ?>">
                              <button type="submit" class="btn btn-secondary btn-sm">Delete Team</button>
                            </form>
                          <?php else: ?>
                            <form method="POST" onsubmit="return confirm('Leave this team?');">
                              <input type="hidden" name="action" value="leave_team">
                              <input type="hidden" name="team_id" value="<?php echo htmlspecialchars($team['id'] ?? ''); ?>">
                              <button type="submit" class="btn btn-secondary btn-sm">Leave Team</button>
                            </form>
                          <?php endif; ?>
                        </div>
                      </div>
                    <?php endforeach; ?>
                  </div>
                </div>
              <?php else: ?>
                <div class="card" style="border: 1px dashed var(--border-color);">
                  <div class="card-content">
                    <div class="form-helper">No teams joined yet</div>
                  </div>
                </div>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
// Profile tab switching (works with clicks and global keyboard .tab-trigger clicks)
function switchProfileTab(name) {
  // Toggle active state on buttons
  document.querySelectorAll('.profile-tabs .profile-tab').forEach(btn => {
    const isActive = btn.getAttribute('data-tab') === name;
    if (isActive) btn.classList.add('active'); else btn.classList.remove('active');
  });
  // Show matching content
  document.querySelectorAll('.profile-tab-content').forEach(section => {
    section.classList.remove('active');
  });
  let target = document.getElementById('profile-tab-' + name);
  // Fallback if requested tab does not exist anymore
  if (!target) {
    name = 'overview';
    target = document.getElementById('profile-tab-overview');
  }
  if (target) target.classList.add('active');
  try { localStorage.setItem('profile.activeTab', name); } catch(e) {}
}

document.addEventListener('DOMContentLoaded', function() {
  document.querySelectorAll('.profile-tabs .tab-trigger').forEach(btn => {
    btn.addEventListener('click', function() {
      const name = this.getAttribute('data-tab');
      if (name) switchProfileTab(name);
    });
  });
  try {
    const params = new URLSearchParams(window.location.search);
    const tabParam = params.get('tab');
    if (tabParam) {
      switchProfileTab(tabParam);
    } else {
      const savedTab = localStorage.getItem('profile.activeTab');
      if (savedTab) {
        switchProfileTab(savedTab);
      } else {
        // Ensure default tab (overview) is properly highlighted
        switchProfileTab('overview');
      }
    }
  } catch(e) {}
});

// Copy recovery codes to clipboard
function copyRecoveryCodes() {
  try {
    const container = document.getElementById('recovery-codes');
    if (!container) return;
    const codes = Array.from(container.querySelectorAll('code')).map(c => c.textContent.trim()).filter(Boolean);
    const text = codes.join('\n');
    if (navigator.clipboard && window.isSecureContext) {
      navigator.clipboard.writeText(text).then(() => {
        if (typeof Toast !== 'undefined') Toast.success('Recovery codes copied');
      }).catch(() => fallbackCopy(text));
    } else {
      fallbackCopy(text);
    }
  } catch (e) {
    if (typeof Toast !== 'undefined') Toast.error('Failed to copy codes');
  }
}

function fallbackCopy(text) {
  const ta = document.createElement('textarea');
  ta.value = text;
  ta.style.position = 'fixed';
  ta.style.opacity = '0';
  document.body.appendChild(ta);
  ta.select();
  try { document.execCommand('copy'); if (typeof Toast !== 'undefined') Toast.success('Recovery codes copied'); } catch {}
  document.body.removeChild(ta);
}

// Download recovery codes as a text file
function downloadRecoveryCodes() {
  const container = document.getElementById('recovery-codes');
  if (!container) return;
  const codes = Array.from(container.querySelectorAll('code')).map(c => c.textContent.trim()).filter(Boolean);
  if (codes.length === 0) return;
  const content = 'Your Recovery Codes\n\n' + codes.join('\n') + '\n\nStore these codes securely. Each code can be used once.';
  const blob = new Blob([content], { type: 'text/plain;charset=utf-8' });
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = 'recovery-codes.txt';
  document.body.appendChild(a);
  a.click();
  document.body.removeChild(a);
  URL.revokeObjectURL(url);
  if (typeof Toast !== 'undefined') Toast.info('Recovery codes downloaded');
}

// Generic copy helper
function copyText(text) {
  try {
    if (navigator.clipboard && window.isSecureContext) {
      navigator.clipboard.writeText(text).then(() => {
        if (typeof Toast !== 'undefined') Toast.success('Copied to clipboard');
      }).catch(() => fallbackCopy(text));
    } else {
      fallbackCopy(text);
    }
  } catch (e) {
    if (typeof Toast !== 'undefined') Toast.error('Copy failed');
  }
}

// Filter Activity rows by type
function filterActivity(type) {
  try {
    const rows = document.querySelectorAll('.activity-row');
    const t = (type || 'all').toLowerCase();
    rows.forEach(row => {
      const rowType = (row.getAttribute('data-type') || 'security').toLowerCase();
      row.style.display = (t === 'all' || t === rowType) ? 'flex' : 'none';
    });
  } catch (e) {
    if (typeof Toast !== 'undefined') Toast.error('Failed to filter activity');
  }
}

// Export Activity data (DB-backed or session fallback) as CSV
function downloadActivityCSV() {
  try {
    const holder = document.getElementById('activity-events-data');
    if (!holder) {
      if (typeof Toast !== 'undefined') Toast.error('No activity data found');
      return;
    }
    const events = JSON.parse(holder.textContent || '[]');
    if (!Array.isArray(events) || events.length === 0) {
      if (typeof Toast !== 'undefined') Toast.info('No activity to export');
      return;
    }

    const headers = ['time', 'type', 'event', 'ip', 'browser', 'os', 'path'];
    const lines = [headers.join(',')];
    const esc = (v) => '"' + String(v ?? '').replace(/"/g, '""') + '"';

    events.forEach(ev => {
      const time = ev.time || '';
      const type = ev.type || 'security';
      const event = ev.event || '';
      const ip = ev.meta && ev.meta.ip ? ev.meta.ip : '';
      const browser = ev.meta && ev.meta.browser ? ev.meta.browser : '';
      const os = ev.meta && ev.meta.os ? ev.meta.os : '';
      const path = ev.meta && ev.meta.path ? ev.meta.path : '';
      lines.push([time, type, event, ip, browser, os, path].map(esc).join(','));
    });

    const blob = new Blob([lines.join('\n')], { type: 'text/csv;charset=utf-8' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'security-activity.csv';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
    if (typeof Toast !== 'undefined') Toast.success('CSV exported');
  } catch (e) {
    if (typeof Toast !== 'undefined') Toast.error('Failed to export CSV');
  }
}

// Persist UI preferences (activity filter, sessions page size)
document.addEventListener('DOMContentLoaded', function() {
  try {
    // Activity filter
    const filterSel = document.getElementById('activityFilter');
    if (filterSel) {
      const saved = localStorage.getItem('profile.activityFilter');
      if (saved && filterSel.value !== saved) {
        filterSel.value = saved;
        filterActivity(saved);
      }
      filterSel.addEventListener('change', function() {
        localStorage.setItem('profile.activityFilter', this.value);
      });
    }

    // Sessions page size
    const sessSel = document.getElementById('sess_limit');
    if (sessSel) {
      const savedSess = localStorage.getItem('profile.sess_limit');
      const current = sessSel.value;
      if (savedSess && savedSess !== current) {
        sessSel.value = savedSess;
        // Submit the GET form to reload with preferred size
        if (sessSel.form) sessSel.form.submit();
      }
      sessSel.addEventListener('change', function() {
        localStorage.setItem('profile.sess_limit', this.value);
      });
    }
  } catch {}
});

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

// ===== AJAX Pagination for Sessions =====
let sessionsData = <?php echo json_encode($allSessionsList ?? []); ?>; // ALL sessions for client-side pagination
let sessionsCurrentPage = 1;
let sessionsPerPage = 5;

function loadSessionsPage(page) {
  const limit = parseInt(document.getElementById('sess_limit')?.value || 5);
  sessionsPerPage = limit;
  sessionsCurrentPage = page;
  
  // Save state
  try {
    localStorage.setItem('profile.sessions.page', page);
    localStorage.setItem('profile.sessions.limit', limit);
  } catch(e) {}
  
  // Client-side pagination
  const start = (page - 1) * sessionsPerPage;
  const end = start + sessionsPerPage;
  const pageData = sessionsData.slice(start, end);
  
  // Update table
  renderSessionsTable(pageData);
  renderSessionsPagination(sessionsData.length, page, sessionsPerPage);
}

function renderSessionsTable(sessions) {
  const tbody = document.querySelector('#profile-tab-security table tbody');
  if (!tbody) return;
  
  if (sessions.length === 0) {
    tbody.innerHTML = '<tr><td colspan="7" style="text-align:center; padding:1rem; color:var(--text-secondary);">No sessions found</td></tr>';
    return;
  }
  
  tbody.innerHTML = sessions.map(sess => {
    const loc = sess.location || {};
    const locStr = [loc.city, loc.country].filter(Boolean).join(', ');
    const client = [sess.browser, sess.os].filter(Boolean).join(' • ');
    
    // Replace localhost IPs with friendly label
    let displayIp = sess.ip_address || '';
    if (['::1', '127.0.0.1', 'localhost'].includes(displayIp)) {
      displayIp = 'Localhost';
    }
    
    let statusBadge = '<span class="badge" style="background: hsl(214 95% 93%); color: hsl(222 47% 17%);">Ended</span>';
    if (sess.is_current) {
      statusBadge = '<span class="badge" style="background: hsl(143 85% 96%); color: hsl(140 61% 20%); border-color: hsl(143 85% 80%);">Current</span>';
    } else if (sess.is_active) {
      statusBadge = '<span class="badge">Active</span>';
    }
    
    let actionBtn = '<span class="form-helper">—</span>';
    if (!sess.is_current && sess.is_active) {
      actionBtn = `<form method="POST" style="display:inline-block;" onsubmit="return confirm('End this session?');">
        <input type="hidden" name="action" value="end_session">
        <input type="hidden" name="session_id" value="${escapeHtml(sess.session_id || '')}">
        <button type="submit" class="btn btn-secondary btn-sm">End</button>
      </form>`;
    }
    
    return `<tr>
      <td style="padding: 0.5rem; border-bottom: 1px solid var(--border-color);">${escapeHtml(sess.login_time || '')}</td>
      <td style="padding: 0.5rem; border-bottom: 1px solid var(--border-color);">${escapeHtml(sess.last_activity || '')}</td>
      <td style="padding: 0.5rem; border-bottom: 1px solid var(--border-color);">${escapeHtml(displayIp)}</td>
      <td style="padding: 0.5rem; border-bottom: 1px solid var(--border-color);">${escapeHtml(locStr)}</td>
      <td style="padding: 0.5rem; border-bottom: 1px solid var(--border-color);">${escapeHtml(client)}</td>
      <td style="padding: 0.5rem; border-bottom: 1px solid var(--border-color);">${statusBadge}</td>
      <td style="padding: 0.5rem; border-bottom: 1px solid var(--border-color);">${actionBtn}</td>
    </tr>`;
  }).join('');
}

function renderSessionsPagination(total, currentPage, perPage) {
  const totalPages = Math.ceil(total / perPage);
  const start = (currentPage - 1) * perPage + 1;
  const end = Math.min(currentPage * perPage, total);
  
  // Debug logging
  console.log('Sessions Pagination:', { total, currentPage, perPage, totalPages, start, end });
  
  const showingEl = document.getElementById('sessions-showing');
  const totalEl = document.getElementById('sessions-total');
  if (showingEl) showingEl.textContent = `${start}-${end}`;
  if (totalEl) totalEl.textContent = total;
  
  const controls = document.getElementById('sessions-pagination-controls');
  if (!controls) {
    console.warn('sessions-pagination-controls element not found');
    return;
  }
  
  if (totalPages <= 1) {
    controls.innerHTML = '';
    console.log('Only 1 page, hiding pagination controls');
    return;
  }
  
  console.log(`Rendering ${totalPages} pages of pagination controls`);
  let html = '';
  
  // Previous button
  html += `<button class="btn btn-sm" onclick="loadSessionsPage(${currentPage - 1})" ${currentPage === 1 ? 'disabled' : ''}>
    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
  </button>`;
  
  // Page numbers
  for (let i = 1; i <= totalPages; i++) {
    if (i === 1 || i === totalPages || (i >= currentPage - 1 && i <= currentPage + 1)) {
      const active = i === currentPage ? 'background:var(--bg-secondary); font-weight:600;' : '';
      html += `<button class="btn btn-sm" onclick="loadSessionsPage(${i})" style="${active}">${i}</button>`;
    } else if (i === currentPage - 2 || i === currentPage + 2) {
      html += `<span style="padding:0 0.25rem; color:var(--text-secondary);">...</span>`;
    }
  }
  
  // Next button
  html += `<button class="btn btn-sm" onclick="loadSessionsPage(${currentPage + 1})" ${currentPage === totalPages ? 'disabled' : ''}>
    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
  </button>`;
  
  controls.innerHTML = html;
}

// ===== AJAX Pagination for Activity =====
let activityData = [];
let activityCurrentPage = 1;
let activityPerPage = 5;
let activityFilter = 'all';

function loadActivityPage(page) {
  const limit = parseInt(document.getElementById('activity_limit')?.value || 5);
  activityPerPage = limit;
  activityCurrentPage = page;
  
  // Save state
  try {
    localStorage.setItem('profile.activity.page', page);
    localStorage.setItem('profile.activity.limit', limit);
  } catch(e) {}
  
  // Get filtered data
  const filtered = activityFilter === 'all' 
    ? activityData 
    : activityData.filter(ev => (ev.type || 'security').toLowerCase() === activityFilter.toLowerCase());
  
  // Client-side pagination
  const start = (page - 1) * activityPerPage;
  const end = start + activityPerPage;
  const pageData = filtered.slice(start, end);
  
  // Update display
  renderActivityRows(pageData);
  renderActivityPagination(filtered.length, page, activityPerPage);
}

function renderActivityRows(events) {
  const container = document.querySelector('#profile-tab-activity .card-content');
  if (!container) return;
  
  // Keep the JSON data element
  const dataEl = document.getElementById('activity-events-data');
  
  if (events.length === 0) {
    container.innerHTML = (dataEl ? dataEl.outerHTML : '') + '<div class="form-helper" style="text-align:center; padding:1rem;">No activity to display</div>';
    return;
  }
  
  container.innerHTML = (dataEl ? dataEl.outerHTML : '') + events.map(ev => {
    let displayInfo = '';
    
    if (ev.meta) {
      const meta = ev.meta;
      const displayIp = meta.ip || '';
      const browser = meta.browser || '';
      const os = meta.os || '';
      let location = '';
      
      // Build device info string
      const deviceParts = [];
      if (browser) deviceParts.push(browser);
      if (os) deviceParts.push(os);
      const deviceInfo = deviceParts.length > 0 ? deviceParts.join(' on ') : '';
      
      // Check for geolocation data
      if (meta.location && (meta.location.city || meta.location.country)) {
        const locParts = [];
        if (meta.location.city) locParts.push(meta.location.city);
        if (meta.location.country) locParts.push(meta.location.country);
        location = locParts.join(', ');
      } else if (meta.city || meta.country) {
        const locParts = [];
        if (meta.city) locParts.push(meta.city);
        if (meta.country) locParts.push(meta.country);
        location = locParts.join(', ');
      }
      
      // Build display info with IP and device
      const displayParts = [];
      
      // Add IP (convert localhost to actual label)
      if (['::1', '127.0.0.1', 'localhost'].includes(displayIp)) {
        if (location) {
          displayParts.push(location);
        }
      } else {
        displayParts.push(displayIp);
        if (location) {
          displayParts.push(location);
        }
      }
      
      // Add device info
      if (deviceInfo) {
        displayParts.push(deviceInfo);
      }
      
      displayInfo = displayParts.length > 0 ? displayParts.join(' • ') : '';
    }
    
    const locationInfo = displayInfo ? `<div class="form-helper">${escapeHtml(displayInfo)}</div>` : '';
    return `<div class="activity-row" data-type="${escapeHtml(ev.type || 'security')}" style="display:flex; align-items:center; justify-content: space-between; border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 0.5rem 0.75rem;">
      <div style="display:flex; flex-direction: column; gap: 0.125rem;">
        <div style="font-weight:600; color: var(--text-primary);">${escapeHtml(ev.event || 'Event')}</div>
        ${locationInfo}
      </div>
      <div class="form-helper" style="white-space: nowrap;">${escapeHtml(ev.time || '')}</div>
    </div>`;
  }).join('');
}

function renderActivityPagination(total, currentPage, perPage) {
  const totalPages = Math.ceil(total / perPage);
  const start = (currentPage - 1) * perPage + 1;
  const end = Math.min(currentPage * perPage, total);
  
  document.getElementById('activity-showing').textContent = total > 0 ? `${start}-${end}` : '0';
  document.getElementById('activity-total').textContent = total;
  
  const controls = document.getElementById('activity-pagination-controls');
  if (!controls) return;
  
  if (totalPages <= 1) {
    controls.innerHTML = '';
    return;
  }
  
  let html = '';
  
  // Previous button
  html += `<button class="btn btn-sm" onclick="loadActivityPage(${currentPage - 1})" ${currentPage === 1 ? 'disabled' : ''}>
    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
  </button>`;
  
  // Page numbers
  for (let i = 1; i <= totalPages; i++) {
    if (i === 1 || i === totalPages || (i >= currentPage - 1 && i <= currentPage + 1)) {
      const active = i === currentPage ? 'background:var(--bg-secondary); font-weight:600;' : '';
      html += `<button class="btn btn-sm" onclick="loadActivityPage(${i})" style="${active}">${i}</button>`;
    } else if (i === currentPage - 2 || i === currentPage + 2) {
      html += `<span style="padding:0 0.25rem; color:var(--text-secondary);">...</span>`;
    }
  }
  
  // Next button
  html += `<button class="btn btn-sm" onclick="loadActivityPage(${currentPage + 1})" ${currentPage === totalPages ? 'disabled' : ''}>
    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
  </button>`;
  
  controls.innerHTML = html;
}

// Update filter function to work with pagination
function filterActivity(type) {
  activityFilter = type;
  activityCurrentPage = 1;
  
  try {
    localStorage.setItem('profile.activityFilter', type);
  } catch(e) {}
  
  loadActivityPage(1);
}

// HTML escape utility
function escapeHtml(text) {
  const div = document.createElement('div');
  div.textContent = text;
  return div.innerHTML;
}

// Initialize pagination on load
document.addEventListener('DOMContentLoaded', function() {
  // Load activity data from JSON
  try {
    const dataEl = document.getElementById('activity-events-data');
    if (dataEl) {
      activityData = JSON.parse(dataEl.textContent || '[]');
    }
  } catch(e) {
    activityData = [];
  }
  
  // Restore saved states
  try {
    // Sessions - always render pagination controls
    const savedSessionPage = parseInt(localStorage.getItem('profile.sessions.page') || 1);
    const savedSessionLimit = parseInt(localStorage.getItem('profile.sessions.limit') || 5);
    const sessLimitSel = document.getElementById('sess_limit');
    if (sessLimitSel && sessLimitSel.value != savedSessionLimit) {
      sessLimitSel.value = savedSessionLimit;
    }
    if (sessionsData.length > 0) {
      // Initialize with correct perPage from selector
      sessionsPerPage = savedSessionLimit;
      // Always render pagination, even if Security tab is not active
      renderSessionsPagination(sessionsData.length, savedSessionPage, savedSessionLimit);
      // Load the saved page (this will re-render pagination)
      loadSessionsPage(savedSessionPage);
    }
    
    // Activity
    const savedActivityPage = parseInt(localStorage.getItem('profile.activity.page') || 1);
    const savedActivityLimit = parseInt(localStorage.getItem('profile.activity.limit') || 5);
    const savedActivityFilter = localStorage.getItem('profile.activityFilter') || 'all';
    
    const actLimitSel = document.getElementById('activity_limit');
    if (actLimitSel && actLimitSel.value != savedActivityLimit) {
      actLimitSel.value = savedActivityLimit;
    }
    
    const actFilterSel = document.getElementById('activityFilter');
    if (actFilterSel && actFilterSel.value != savedActivityFilter) {
      actFilterSel.value = savedActivityFilter;
    }
    
    activityFilter = savedActivityFilter;
    if (activityData.length > 0) {
      loadActivityPage(savedActivityPage);
    }
  } catch(e) {}
});

// ===== Keyboard Shortcuts for Pagination =====
document.addEventListener('keydown', function(e) {
  // Check if Shift key is pressed with arrow keys
  if (!e.shiftKey) return;
  
  // Get current active tab
  const activeTab = localStorage.getItem('profile.activeTab') || 'overview';
  
  // Shift+Left Arrow: Previous page
  if (e.key === 'ArrowLeft') {
    e.preventDefault();
    
    if (activeTab === 'security') {
      // Sessions pagination
      const totalPages = Math.ceil(sessionsData.length / sessionsPerPage);
      if (sessionsCurrentPage > 1) {
        loadSessionsPage(sessionsCurrentPage - 1);
        console.log(`Sessions: Navigated to page ${sessionsCurrentPage}`);
      }
    } else if (activeTab === 'activity') {
      // Activity pagination
      const filtered = activityFilter === 'all' 
        ? activityData 
        : activityData.filter(ev => (ev.type || 'security').toLowerCase() === activityFilter.toLowerCase());
      const totalPages = Math.ceil(filtered.length / activityPerPage);
      if (activityCurrentPage > 1) {
        loadActivityPage(activityCurrentPage - 1);
        console.log(`Activity: Navigated to page ${activityCurrentPage}`);
      }
    }
  }
  
  // Shift+Right Arrow: Next page
  if (e.key === 'ArrowRight') {
    e.preventDefault();
    
    if (activeTab === 'security') {
      // Sessions pagination
      const totalPages = Math.ceil(sessionsData.length / sessionsPerPage);
      if (sessionsCurrentPage < totalPages) {
        loadSessionsPage(sessionsCurrentPage + 1);
        console.log(`Sessions: Navigated to page ${sessionsCurrentPage}`);
      }
    } else if (activeTab === 'activity') {
      // Activity pagination
      const filtered = activityFilter === 'all' 
        ? activityData 
        : activityData.filter(ev => (ev.type || 'security').toLowerCase() === activityFilter.toLowerCase());
      const totalPages = Math.ceil(filtered.length / activityPerPage);
      if (activityCurrentPage < totalPages) {
        loadActivityPage(activityCurrentPage + 1);
        console.log(`Activity: Navigated to page ${activityCurrentPage}`);
      }
    }
  }
});
</script>

<?php
// Get page content
$pageContent = ob_get_clean();

// Set page title
$pageTitle = 'User Profile';

// Include layout
include __DIR__ . '/components/layout.php';
?>
