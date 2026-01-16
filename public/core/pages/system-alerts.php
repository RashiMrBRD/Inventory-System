<?php
require_once __DIR__ . '/../../../vendor/autoload.php';

use App\Controller\AuthController;
use App\Service\AlertService;

$auth = new AuthController();
$auth->requireLogin();
$user = $auth->getCurrentUser();

// Ensure user data is valid
if (!$user || !is_array($user)) {
    error_log('System Alerts: User data is null or invalid');
    header('Location: login.php');
    exit();
}

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'create_alert') {
        $title = trim($_POST['title'] ?? '');
        $body = trim($_POST['body'] ?? '');
        $type = trim($_POST['type'] ?? 'info');
        if ($title === '' || $body === '') {
            $message = 'Title and body are required';
            $messageType = 'danger';
        } else {
            $svc = new AlertService();
            $ok = $svc->create($title, $body, $type, [
                'user_id' => (string)($user['_id'] ?? ($user['id'] ?? '')),
                'username' => $user['username'] ?? 'user',
            ]);
            $message = $ok ? 'Alert created' : 'Failed to create alert';
            $messageType = $ok ? 'success' : 'danger';
        }
        if (!headers_sent()) {
            if (!isset($_SESSION)) { session_start(); }
            $_SESSION['profile_flash'] = ['message' => $message, 'type' => $messageType];
            header('Location: system-alerts.php');
            exit;
        }
    }
}

if (!isset($_SESSION)) { session_start(); }
if (empty($message) && !empty($_SESSION['profile_flash'])) {
    $flash = $_SESSION['profile_flash'];
    $message = $flash['message'] ?? '';
    $messageType = $flash['type'] ?? 'info';
    unset($_SESSION['profile_flash']);
}

$svc = new AlertService();
$alerts = $svc->list(50);

ob_start();

// ========== EXPERIMENTAL FEATURE WARNING ==========
// To remove this warning system, delete the following 2 lines and the features/experimental-warning folder
require_once __DIR__ . '/../../features/experimental-warning/experimental-warning.php';
renderExperimentalWarning('System Alerts & Notifications');
// System auto-detects page and shows contextual warning with natural language
// ===================================================
?>
<div class="page-banner" style="margin-bottom: 1rem;">
  <div class="page-banner-content">
    <div class="page-banner-left">
      <h1 class="page-banner-title">System Alerts</h1>
      <div class="page-banner-meta">
        <div class="page-banner-meta-item"><strong>Total:</strong> <?php echo count($alerts); ?></div>
      </div>
    </div>
  </div>
</div>
<?php if ($message): ?>
<div class="alert alert-<?php echo htmlspecialchars($messageType); ?>" style="margin-bottom:1rem; position:relative; padding:0.75rem 1rem; border-radius:8px;">
  <button type="button" onclick="this.parentElement.remove()" style="position:absolute;right:8px;top:6px;background:none;border:none;font-size:14px;cursor:pointer">&times;</button>
  <?php echo htmlspecialchars($message); ?>
</div>
<?php endif; ?>

<div class="card" style="margin-bottom:1rem;">
  <div class="card-header"><h3 class="card-title">Create Alert</h3></div>
  <div class="card-content">
    <form method="POST" style="display:grid; gap:0.5rem;">
      <input type="hidden" name="action" value="create_alert">
      <div class="form-group">
        <label class="form-label">Title</label>
        <input type="text" name="title" class="form-input" required>
      </div>
      <div class="form-group">
        <label class="form-label">Body</label>
        <textarea name="body" class="form-input" rows="3" required></textarea>
      </div>
      <div class="form-group">
        <label class="form-label">Type</label>
        <select name="type" class="form-select">
          <option value="info">Info</option>
          <option value="success">Success</option>
          <option value="warning">Warning</option>
          <option value="danger">Danger</option>
        </select>
      </div>
      <div>
        <button type="submit" class="btn btn-primary">Post Alert</button>
      </div>
    </form>
  </div>
</div>

<div class="card">
  <div class="card-header"><h3 class="card-title">Recent Alerts</h3></div>
  <div class="card-content" style="display:grid; gap:0.5rem;">
    <?php if (count($alerts)===0): ?>
      <div class="form-helper">No alerts yet</div>
    <?php else: ?>
      <?php foreach ($alerts as $a): 
        $type = $a['type'] ?? 'info';
        $style = 'background: hsl(214 95% 93%); color: hsl(222 47% 17%); border:1px solid var(--border-color);';
        if ($type==='success') $style = 'background: hsl(143 85% 96%); color: hsl(140 61% 13%); border:1px solid hsl(143 85% 80%);';
        if ($type==='warning') $style = 'background: hsl(48 96% 89%); color: hsl(25 95% 16%); border:1px solid hsl(48 96% 76%);';
        if ($type==='danger') $style = 'background: hsl(0 86% 97%); color: hsl(0 74% 24%); border:1px solid hsl(0 86% 90%);';
      ?>
        <div style="padding:0.75rem; border-radius:8px; <?php echo $style; ?> display:flex; align-items:flex-start; justify-content:space-between; gap:0.75rem;">
          <div>
            <div style="font-weight:700;"><?php echo htmlspecialchars($a['title']); ?></div>
            <div><?php echo nl2br(htmlspecialchars($a['body'])); ?></div>
          </div>
          <div class="form-helper" style="white-space:nowrap;"><?php echo htmlspecialchars($a['time']); ?></div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>
<?php
$pageTitle = 'System Alerts';
$pageContent = ob_get_clean();
include __DIR__ . '/../../components/layout.php';


