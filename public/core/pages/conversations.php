<?php
require_once __DIR__ . '/../../../vendor/autoload.php';

use App\Controller\AuthController;
use App\Service\ConversationService;

$auth = new AuthController();
$auth->requireLogin();
$user = $auth->getCurrentUser();
$userId = isset($user['_id']) ? (string)$user['_id'] : ($user['id'] ?? '');
$username = $user['display_name'] ?? ($user['username'] ?? 'User');

$message = '';
$messageType = '';

$teamId = $_GET['team_id'] ?? '';
$channel = trim($_GET['channel'] ?? 'general');
if ($channel === '') $channel = 'general';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'post_message') {
        $body = trim($_POST['message'] ?? '');
        $teamId = $_POST['team_id'] ?? '';
        $channel = trim($_POST['channel'] ?? 'general') ?: 'general';
        if ($body !== '') {
            $svc = new ConversationService();
            $ok = $svc->postMessage($userId, $username, $body, [
                'team_id' => $teamId ?: null,
                'channel' => $channel,
            ]);
            if ($ok) {
                $message = 'Message sent';
                $messageType = 'success';
            } else {
                $message = 'Failed to send message';
                $messageType = 'danger';
            }
        } else {
            $message = 'Message is required';
            $messageType = 'danger';
        }
        if (!headers_sent()) {
            $_SESSION['profile_flash'] = ['message' => $message, 'type' => $messageType];
            $qs = http_build_query(['team_id' => $teamId, 'channel' => $channel]);
            header('Location: conversations.php?' . $qs);
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

$svc = new ConversationService();
$list = $svc->listMessages([
    'team_id' => $teamId ?: null,
    'channel' => $channel,
], 100);

$teams = $user['teams'] ?? [];

ob_start();

// ========== EXPERIMENTAL FEATURE WARNING ==========
// To remove this warning system, delete the following 2 lines and the features/experimental-warning folder
require_once __DIR__ . '/../../features/experimental-warning/experimental-warning.php';
renderExperimentalWarning('Conversations & Messaging');
// System auto-detects page and shows contextual warning with natural language
// ===================================================
?>
<div class="page-banner" style="margin-bottom: 1rem;">
  <div class="page-banner-content">
    <div class="page-banner-left">
      <h1 class="page-banner-title">Conversations</h1>
      <div class="page-banner-meta">
        <div class="page-banner-meta-item"><strong>Channel:</strong> <?php echo htmlspecialchars($channel); ?></div>
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

<div class="card">
  <div class="card-content" style="display:grid; gap:0.75rem;">
    <form method="GET" style="display:flex; gap:0.5rem; align-items:center; flex-wrap:wrap;">
      <select name="team_id" class="form-select" style="min-width:200px;">
        <option value="">All Teams</option>
        <?php foreach ($teams as $t): $tid = is_array($t)?($t['id']??''):(string)$t; ?>
          <option value="<?php echo htmlspecialchars($tid); ?>" <?php echo $tid===$teamId?'selected':''; ?>><?php echo htmlspecialchars(is_array($t)?($t['name']??'Team'):(string)$t); ?></option>
        <?php endforeach; ?>
      </select>
      <input type="text" name="channel" class="form-input" value="<?php echo htmlspecialchars($channel); ?>" placeholder="Channel (e.g., general)" style="max-width:220px;">
      <button type="submit" class="btn btn-secondary">Apply</button>
    </form>

    <div style="max-height:420px; overflow:auto; border:1px solid var(--border-color); border-radius:8px; padding:0.75rem; background:white;">
      <?php if (count($list)===0): ?>
        <div class="form-helper">No messages yet</div>
      <?php else: ?>
        <?php foreach ($list as $m): ?>
          <div style="display:flex; justify-content:space-between; border-bottom:1px solid var(--border-color); padding:0.375rem 0;">
            <div>
              <strong><?php echo htmlspecialchars($m['username'] ?? 'User'); ?></strong>
              <span class="form-helper">#<?php echo htmlspecialchars($m['channel'] ?? 'general'); ?></span>
              <div><?php echo nl2br(htmlspecialchars($m['message'] ?? '')); ?></div>
            </div>
            <div class="form-helper" style="white-space:nowrap; margin-left:1rem;"><?php echo htmlspecialchars($m['time'] ?? ''); ?></div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <form method="POST" style="display:flex; gap:0.5rem; align-items:center;">
      <input type="hidden" name="action" value="post_message">
      <input type="hidden" name="team_id" value="<?php echo htmlspecialchars($teamId); ?>">
      <input type="hidden" name="channel" value="<?php echo htmlspecialchars($channel); ?>">
      <input type="text" name="message" class="form-input" placeholder="Type a message..." style="flex:1;">
      <button type="submit" class="btn btn-primary">Send</button>
    </form>
  </div>
</div>
<?php
$pageTitle = 'Conversations';
$pageContent = ob_get_clean();
include __DIR__ . '/../../components/layout.php';


