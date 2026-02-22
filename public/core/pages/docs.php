<?php
require_once __DIR__ . '/../../../vendor/autoload.php';

use App\Controller\AuthController;

$auth = new AuthController();
$auth->requireLogin();
$user = $auth->getCurrentUser();

$message = '';
$messageType = '';

// Collect markdown files from two roots
$roots = [
    'Project Docs' => realpath(__DIR__ . '/../../../docs'),
    'Container Docs' => realpath(__DIR__ . '/../../../container/documentation'),
];

// Recursively scan using scandir to avoid SPL requirements and reduce 500s
function collectMdFiles(string $root, string $label, array &$files, array &$indexMap): void {
    if (!is_dir($root)) return;
    $rootReal = realpath($root);
    if ($rootReal === false) return;
    $stack = [$rootReal];
    while ($stack) {
        $dir = array_pop($stack);
        $items = @scandir($dir);
        if ($items === false) continue;
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            // Ensure path stays within root
            $real = realpath($path);
            if ($real === false || strpos($real, $rootReal) !== 0) continue;
            if (is_dir($real)) {
                $stack[] = $real;
            } else {
                $ext = strtolower(pathinfo($real, PATHINFO_EXTENSION));
                if ($ext === 'md') {
                    $id = substr(sha1($real), 0, 12); // stable ID
                    $rel = ltrim(str_replace($rootReal . DIRECTORY_SEPARATOR, '', $real), DIRECTORY_SEPARATOR);
                    $files[] = [
                        'id' => $id,
                        'label' => $label,
                        'root' => $rootReal,
                        'relative' => $rel,
                        'name' => basename($real),
                        'path' => $real,
                    ];
                    $indexMap[$id] = $real;
                }
            }
        }
    }
}

$files = [];
$indexMap = [];
try {
    foreach ($roots as $label => $root) {
        if (!empty($root)) {
            collectMdFiles($root, $label, $files, $indexMap);
        }
    }
} catch (\Throwable $e) {
    $message = 'Failed to scan documentation folders';
    $messageType = 'danger';
}

// Custom sort with priority for important docs
$priorityOrder = [
    '00_INTRODUCTION.md' => 1,
    'GETTING_STARTED.md' => 2,
    '01_SYSTEM_OVERVIEW.md' => 3,
    'README.md' => 4,
    'FEATURES_OVERVIEW.md' => 5,
    'API_EXAMPLES_AND_WORKFLOWS.md' => 6,
    'FRONTEND_GUIDE.md' => 7,
    'BACKEND_GUIDE.md' => 8,
    'CHANGELOG.md' => 9,
    'INSTALLATION_VERIFICATION.md' => 10,
    'SETUP_INSTRUCTIONS.md' => 11,
    'DOCKER_SETUP.md' => 12,
    'START_HERE.md' => 13,
    'INDEX.md' => 14,
    'DOCKER_QUICKSTART.md' => 15,
    'DOCKER_QUICKSTART_NEW.md' => 16,
    'CLOUDFLARE_TUNNEL_SETUP.md' => 17,
    'COMPLETE_SYSTEM_DOCUMENTATION.md' => 18,
    'PROJECT_STRUCTURE.md' => 19,
    'TROUBLESHOOTING.md' => 20,
    'KNOWN_ISSUES.md' => 21,
    'DOCKER_DEBUGGING.md' => 22,
];

usort($files, function($a, $b) use ($priorityOrder) {
    // Get priority (lower number = higher priority)
    $priorityA = $priorityOrder[$a['name']] ?? 999;
    $priorityB = $priorityOrder[$b['name']] ?? 999;
    
    // If priorities are different, sort by priority
    if ($priorityA !== $priorityB) {
        return $priorityA <=> $priorityB;
    }
    
    // If same priority, sort by label then name
    return [$a['label'], strtolower($a['relative'])] <=> [$b['label'], strtolower($b['relative'])];
});

$activeId = $_GET['id'] ?? '';

// If no document is selected, auto-select the first one
if (empty($activeId) && !empty($files)) {
    $activeId = $files[0]['id'];
}

$activeContent = '';
$activeTitle = '';
$activeLabel = '';
$isAjax = isset($_GET['ajax']) && $_GET['ajax'] === '1';

if ($activeId) {
    if (isset($indexMap[$activeId])) {
        $path = $indexMap[$activeId];
        // Safety: ensure file is within allowed roots
        $allowed = false;
        foreach ($roots as $rootLabel => $rootPath) {
            $rootReal = $rootPath ? realpath($rootPath) : '';
            $pathReal = realpath($path);
            if ($rootReal && $pathReal && strpos($pathReal, $rootReal) === 0) { $allowed = true; break; }
        }
        if ($allowed && is_readable($path)) {
            $raw = @file_get_contents($path);
            if ($raw !== false) {
                $activeContent = $raw;
                foreach ($files as $f) {
                    if ($f['id'] === $activeId) { $activeTitle = $f['name']; $activeLabel = $f['label']; break; }
                }
            } else {
                if ($isAjax) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'error' => 'Failed to read the selected document']);
                    exit;
                }
                $message = 'Failed to read the selected document';
                $messageType = 'danger';
            }
        } else {
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Document not accessible']);
                exit;
            }
            $message = 'Document not accessible';
            $messageType = 'danger';
        }
    } else {
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Document not found']);
            exit;
        }
        $message = 'Document not found';
        $messageType = 'danger';
    }
}

function renderMarkdownBasic(string $md): string {
    // Enhanced markdown renderer with modern features
    $html = htmlspecialchars($md, ENT_QUOTES, 'UTF-8');

    // Code fences with language support ```language
    $html = preg_replace_callback('/```(\w*)\n?([\s\S]*?)```/m', function($m) {
        $lang = !empty($m[1]) ? htmlspecialchars($m[1]) : '';
        $code = trim($m[2]);
        $langLabel = $lang ? '<span style="position:absolute;top:0.5rem;right:0.5rem;font-size:0.75rem;color:rgba(255,255,255,0.5);text-transform:uppercase;">' . $lang . '</span>' : '';
        return '<pre class="md-code">' . $langLabel . '<code>' . $code . '</code></pre>';
    }, $html);

    // Inline code `code` (before other inline formatting)
    $html = preg_replace('/`([^`]+)`/', '<code class="md-inline">$1</code>', $html);

    // Headings with auto-generated IDs for anchor links
    for ($i=6; $i>=1; $i--) {
        $pattern = '/^' . str_repeat('#', $i) . ' (.+)$/m';
        $html = preg_replace_callback($pattern, function($m) use ($i) {
            $text = $m[1];
            $id = strtolower(preg_replace('/[^a-z0-9]+/', '-', strip_tags($text)));
            $id = trim($id, '-');
            return '<h' . $i . ' id="' . $id . '">' . $text . '</h' . $i . '>';
        }, $html);
    }

    // Horizontal rules
    $html = preg_replace('/^(-{3,}|\*{3,})$/m', '<hr>', $html);

    // Blockquotes
    $html = preg_replace_callback('/^&gt; (.+)$/m', function($m) {
        return '<blockquote>' . $m[1] . '</blockquote>';
    }, $html);
    // Merge consecutive blockquotes
    $html = preg_replace('/<\/blockquote>\n<blockquote>/', "\n", $html);

    // Tables (GitHub Flavored Markdown style)
    $html = preg_replace_callback('/(\|.+\|(\n|\r\n))+/m', function($m) {
        $rows = explode("\n", trim($m[0]));
        if (count($rows) < 2) return $m[0];
        
        $table = '<table>';
        foreach ($rows as $idx => $row) {
            $row = trim($row, '|');
            $cells = array_map('trim', explode('|', $row));
            
            // Skip separator row (contains dashes)
            if ($idx === 1 && preg_match('/^[\s\-:|]+$/', $row)) continue;
            
            $tag = ($idx === 0) ? 'th' : 'td';
            $table .= '<tr>';
            foreach ($cells as $cell) {
                $table .= '<' . $tag . '>' . $cell . '</' . $tag . '>';
            }
            $table .= '</tr>';
        }
        $table .= '</table>';
        return $table;
    }, $html);

    // Ordered lists
    $html = preg_replace('/^\d+\. (.+)$/m', '<li-ol>$1</li-ol>', $html);
    $html = preg_replace_callback('/(?:<li-ol>.+<\/li-ol>\n?)+/m', function($m) {
        $content = str_replace(['<li-ol>', '</li-ol>'], ['<li>', '</li>'], $m[0]);
        return '<ol>' . $content . '</ol>';
    }, $html);

    // Unordered lists
    $html = preg_replace('/^[\-\*\+] (.+)$/m', '<li-ul>$1</li-ul>', $html);
    $html = preg_replace_callback('/(?:<li-ul>.+<\/li-ul>\n?)+/m', function($m) {
        $content = str_replace(['<li-ul>', '</li-ul>'], ['<li>', '</li>'], $m[0]);
        return '<ul>' . $content . '</ul>';
    }, $html);

    // Bold **text** or __text__
    $html = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $html);
    $html = preg_replace('/__(.+?)__/', '<strong>$1</strong>', $html);

    // Italic *text* or _text_
    $html = preg_replace('/\*(.+?)\*/', '<em>$1</em>', $html);
    $html = preg_replace('/_(.+?)_/', '<em>$1</em>', $html);

    // Strikethrough ~~text~~
    $html = preg_replace('/~~(.+?)~~/', '<del>$1</del>', $html);

    // Links [text](url)
    $html = preg_replace('/\[([^\]]+)\]\(([^\)]+)\)/', '<a href="$2" target="_blank" rel="noopener noreferrer">$1</a>', $html);

    // Images ![alt](url)
    $html = preg_replace('/!\[([^\]]*)\]\(([^\)]+)\)/', '<img src="$2" alt="$1" loading="lazy">', $html);

    // Paragraphs (split on double newlines)
    $parts = preg_split("/\n\n+/", $html);
    foreach ($parts as &$p) {
        $p = trim($p);
        // Skip if already a block element
        if (preg_match('/^\s*<(h\d|ul|ol|pre|blockquote|table|hr|img)/', $p) || empty($p)) continue;
        $p = '<p>' . $p . '</p>';
    }
    return implode("\n", $parts);
}

$rendered = $activeContent ? renderMarkdownBasic($activeContent) : '';

// Handle AJAX requests
if ($isAjax && $activeId) {
    // Find current document index for navigation
    $currentIndex = -1;
    foreach ($files as $idx => $f) {
        if ($f['id'] === $activeId) {
            $currentIndex = $idx;
            break;
        }
    }
    
    $prevDoc = ($currentIndex > 0) ? $files[$currentIndex - 1] : null;
    $nextDoc = ($currentIndex >= 0 && $currentIndex < count($files) - 1) ? $files[$currentIndex + 1] : null;
    
    // Generate navigation HTML
    ob_start();
    if ($prevDoc || $nextDoc): ?>
      <?php if ($prevDoc): ?>
      <a href="/docs?id=<?php echo urlencode($prevDoc['id']); ?>" class="docs-nav-btn docs-nav-btn-prev">
        <svg class="docs-nav-btn-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
        </svg>
        <div class="docs-nav-btn-content">
          <span class="docs-nav-btn-label">Previous <span class="docs-kbd">Alt+←</span></span>
          <span class="docs-nav-btn-title"><?php echo htmlspecialchars($prevDoc['name']); ?></span>
        </div>
      </a>
      <?php else: ?>
      <div class="docs-nav-spacer"></div>
      <?php endif; ?>
      
      <?php if ($nextDoc): ?>
      <a href="/docs?id=<?php echo urlencode($nextDoc['id']); ?>" class="docs-nav-btn docs-nav-btn-next">
        <div class="docs-nav-btn-content" style="text-align: right;">
          <span class="docs-nav-btn-label">Next <span class="docs-kbd">Alt+→</span></span>
          <span class="docs-nav-btn-title"><?php echo htmlspecialchars($nextDoc['name']); ?></span>
        </div>
        <svg class="docs-nav-btn-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
        </svg>
      </a>
      <?php else: ?>
      <div class="docs-nav-spacer"></div>
      <?php endif; ?>
    <?php endif;
    $navigationHtml = ob_get_clean();
    
    // Generate progress HTML
    ob_start();
    if ($currentIndex >= 0 && count($files) > 0): 
        $progress = (($currentIndex + 1) / count($files)) * 100;
    ?>
      <span><?php echo $currentIndex + 1; ?> of <?php echo count($files); ?></span>
      <div class="docs-progress-bar">
        <div class="docs-progress-fill" style="width: <?php echo $progress; ?>%;"></div>
      </div>
      <span><?php echo round($progress); ?>%</span>
    <?php endif;
    $progressHtml = ob_get_clean();
    
    // Return JSON response
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'content' => $rendered,
        'navigation' => $navigationHtml,
        'progress' => $progressHtml,
        'title' => $activeTitle,
        'docId' => $activeId
    ]);
    exit;
}

ob_start();

// ========== EXPERIMENTAL FEATURE WARNING ==========
// To remove this warning system, delete the following 2 lines and the features/experimental-warning folder
require_once __DIR__ . '/../../features/experimental-warning/experimental-warning.php';
renderExperimentalWarning('Documentation Hub');
// System auto-detects page and shows contextual warning with natural language
// ===================================================
?>
<style>
/* Modern Documentation Styles - Neutral Design */

/* Full Height Layout */
html, body {
  height: 100%;
  margin: 0;
  overflow: hidden;
}

.docs-page-container {
  display: flex;
  flex-direction: column;
  height: 100vh;
  width: 100%;
  background: var(--bg-secondary);
}

/* Sticky Hero Section */
.docs-hero {
  position: sticky;
  top: 0;
  z-index: 100;
  background: linear-gradient(135deg, #111827 0%, #1f2937 100%);
  padding: 1.5rem 2rem;
  border-bottom: 1px solid var(--border-color);
  box-shadow: 0 2px 8px rgba(0,0,0,0.08);
  flex-shrink: 0;
}

.docs-hero-content {
  max-width: 1400px;
  margin: 0 auto;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 2rem;
}

.docs-hero-left {
  flex: 1;
  min-width: 0;
}

.docs-hero-title {
  font-size: 1.5rem;
  font-weight: 700;
  color: white;
  margin: 0 0 0.25rem 0;
  letter-spacing: -0.025em;
}

.docs-hero-subtitle {
  font-size: 0.875rem;
  color: rgba(255,255,255,0.7);
  margin: 0;
}

.docs-search-wrapper {
  position: relative;
  flex: 0 0 400px;
}

.docs-search-input {
  width: 100%;
  padding: 0.625rem 1rem 0.625rem 2.5rem;
  border: 1px solid rgba(255,255,255,0.15);
  border-radius: 6px;
  background: rgba(255,255,255,0.08);
  color: white;
  font-size: 0.875rem;
  transition: all 0.2s;
}

.docs-search-input::placeholder {
  color: rgba(255,255,255,0.5);
}

.docs-search-input:focus {
  outline: none;
  border-color: rgba(255,255,255,0.3);
  background: rgba(255,255,255,0.12);
  box-shadow: 0 0 0 3px rgba(255,255,255,0.1);
}

.docs-search-icon {
  position: absolute;
  left: 0.875rem;
  top: 50%;
  transform: translateY(-50%);
  color: rgba(255,255,255,0.5);
  pointer-events: none;
}

/* Main Layout Container */
.docs-wrapper {
  flex: 1;
  overflow: hidden;
  display: flex;
  flex-direction: column;
}

.docs-layout {
  flex: 1;
  display: grid;
  grid-template-columns: 1fr 320px;
  gap: 0;
  max-width: 1400px;
  margin: 0 auto;
  width: 100%;
  height: 100%;
  overflow: hidden;
}

/* Main Content Area */
.docs-content {
  height: 100%;
  overflow-y: auto;
  overflow-x: hidden;
  background: white;
  border-right: 1px solid var(--border-color);
  position: relative;
  transition: opacity 0.2s ease-in-out;
}

.docs-content::-webkit-scrollbar {
  width: 8px;
}

.docs-content::-webkit-scrollbar-track {
  background: var(--bg-secondary);
}

.docs-content::-webkit-scrollbar-thumb {
  background: var(--border-color);
  border-radius: 4px;
}

.docs-content::-webkit-scrollbar-thumb:hover {
  background: var(--color-gray-400);
}

.docs-content-inner {
  padding: 2rem;
  padding-bottom: 4rem;
  max-width: 900px;
  min-height: calc(100% - 4rem);
}

.docs-breadcrumb {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  margin-bottom: 1.5rem;
  font-size: 0.8125rem;
  color: var(--text-secondary);
}

.docs-breadcrumb-item {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.docs-breadcrumb-link {
  color: var(--text-secondary);
  text-decoration: none;
  transition: color 0.2s;
}

.docs-breadcrumb-link:hover {
  color: var(--text-primary);
}

.docs-breadcrumb-sep {
  color: var(--border-color-dark);
}

.docs-content-header {
  margin-bottom: 2rem;
  padding-bottom: 1.5rem;
  border-bottom: 1px solid var(--border-color);
}

.docs-content-title {
  font-size: 2rem;
  font-weight: 700;
  color: var(--text-primary);
  margin: 0 0 0.5rem 0;
  line-height: 1.2;
}

.docs-content-meta {
  font-size: 0.875rem;
  color: var(--text-secondary);
  display: flex;
  align-items: center;
  gap: 1rem;
}

.docs-btn {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.5rem 1rem;
  border-radius: 6px;
  font-size: 0.875rem;
  font-weight: 500;
  border: 1px solid var(--border-color);
  background: white;
  color: var(--text-primary);
  cursor: pointer;
  transition: all 0.15s;
}

.docs-btn:hover {
  background: var(--bg-secondary);
  border-color: var(--border-color-dark);
}

.docs-btn-icon {
  width: 16px;
  height: 16px;
}

/* Right Sidebar - Switchable */
.docs-sidebar {
  align-self: start;
  height: auto;
  max-height: 72vh;
  margin-bottom: 3rem;
  overflow: hidden;
  background: var(--bg-secondary);
  display: flex;
  flex-direction: column;
  border: 1px solid var(--border-color);
  border-radius: 8px;
  box-shadow: 0 10px 30px rgba(15, 23, 42, 0.08);
}

.docs-sidebar-header {
  padding: 1rem 1.25rem;
  border-bottom: 1px solid var(--border-color);
  background: white;
  flex-shrink: 0;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
}

.docs-sidebar-title {
  font-size: 0.875rem;
  font-weight: 600;
  color: var(--text-primary);
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.docs-sidebar-badge {
  font-size: 0.6875rem;
  padding: 0.125rem 0.5rem;
  background: var(--bg-secondary);
  color: var(--text-secondary);
  border-radius: 10px;
  font-weight: 500;
}

.docs-sidebar-toggle {
  padding: 0.375rem 0.625rem;
  border-radius: 4px;
  font-size: 0.75rem;
  font-weight: 500;
  border: 1px solid var(--border-color);
  background: white;
  color: var(--text-secondary);
  cursor: pointer;
  transition: all 0.15s;
  display: inline-flex;
  align-items: center;
  gap: 0.375rem;
}

.docs-sidebar-toggle:hover {
  background: var(--bg-secondary);
  color: var(--text-primary);
}

.docs-sidebar-toggle svg {
  width: 12px;
  height: 12px;
}

.docs-sidebar-scroll {
  flex: 1;
  min-height: 0;
  overflow-y: auto;
  overflow-x: hidden;
  padding: 1rem 1.25rem 1.5rem;
  max-height: calc(72vh - 4.75rem);
}

.docs-sidebar-scroll::-webkit-scrollbar {
  width: 6px;
}

.docs-sidebar-scroll::-webkit-scrollbar-track {
  background: transparent;
}

.docs-sidebar-scroll::-webkit-scrollbar-thumb {
  background: var(--border-color);
  border-radius: 3px;
}

.docs-sidebar-scroll::-webkit-scrollbar-thumb:hover {
  background: var(--color-gray-400);
}

/* Contents Navigation */
.docs-sidebar-group {
  margin-bottom: 1.25rem;
}

.docs-sidebar-group:last-child {
  margin-bottom: 0;
}

.docs-sidebar-group-title {
  font-size: 0.6875rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: var(--text-secondary);
  padding: 0 0 0.5rem 0;
  margin: 0 0 0.5rem 0;
  border-bottom: 1px solid var(--border-color);
}

.docs-sidebar-link {
  display: block;
  padding: 0.5rem 0.75rem;
  border-radius: 4px;
  text-decoration: none;
  color: var(--text-secondary);
  font-size: 0.8125rem;
  line-height: 1.4;
  transition: all 0.15s;
  margin-bottom: 2px;
}

.docs-sidebar-link:hover {
  background: white;
  color: var(--text-primary);
}

.docs-sidebar-link.active {
  background: var(--color-gray-900);
  color: white;
  font-weight: 500;
}

/* Table of Contents */
.docs-toc-list {
  list-style: none;
  padding: 0;
  margin: 0;
  width: 100%;
  overflow: visible;
}

.docs-toc-item {
  margin-bottom: 2px;
}

.docs-toc-link {
  display: block;
  padding: 0.5rem 0.75rem;
  border-radius: 4px;
  font-size: 0.8125rem;
  color: var(--text-secondary);
  text-decoration: none;
  transition: all 0.15s;
  line-height: 1.4;
}

.docs-toc-link:hover {
  background: white;
  color: var(--text-primary);
}

.docs-toc-link.active {
  background: var(--color-gray-900);
  color: white;
  font-weight: 500;
}

.docs-toc-link-h1 { 
  padding-left: 0.75rem;
  font-weight: 600;
}
.docs-toc-link-h2 { 
  padding-left: 0.75rem;
  font-weight: 500;
}
.docs-toc-link-h3 { 
  padding-left: 1.25rem; 
  font-size: 0.75rem;
  color: var(--text-secondary);
}
.docs-toc-link-h4 { 
  padding-left: 1.75rem; 
  font-size: 0.75rem;
  color: var(--text-secondary);
}

/* Sidebar View States */
.docs-sidebar-view {
  display: none;
}

.docs-sidebar-view.active {
  display: block;
}

/* Enhanced Markdown Rendering */
.md-render {
  line-height: 1.75;
  color: var(--text-primary);
}

.md-render h1, .md-render h2, .md-render h3,
.md-render h4, .md-render h5, .md-render h6 {
  font-weight: 700;
  line-height: 1.3;
  margin: 2rem 0 1rem 0;
  color: var(--text-primary);
  scroll-margin-top: 5rem;
  position: relative;
}

.md-render h1:first-child, .md-render h2:first-child,
.md-render h3:first-child { margin-top: 0; }

.md-render h1 { font-size: 2.25rem; border-bottom: 2px solid var(--border-color); padding-bottom: 0.5rem; }
.md-render h2 { font-size: 1.875rem; border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem; }
.md-render h3 { font-size: 1.5rem; }
.md-render h4 { font-size: 1.25rem; }
.md-render h5 { font-size: 1.125rem; }
.md-render h6 { font-size: 1rem; color: var(--text-secondary); }

.md-render p {
  margin: 1rem 0;
  line-height: 1.75;
}

.md-render a {
  color: var(--color-gray-900);
  text-decoration: underline;
  text-decoration-color: var(--border-color-dark);
  text-underline-offset: 2px;
  transition: all 0.15s;
  font-weight: 500;
}

.md-render a:hover {
  color: var(--color-primary);
  text-decoration-color: var(--color-primary);
}

.md-render ul, .md-render ol {
  padding-left: 1.5rem;
  margin: 1rem 0;
}

.md-render li {
  margin: 0.5rem 0;
  line-height: 1.75;
}

.md-render ul {
  list-style-type: disc;
}

.md-render ol {
  list-style-type: decimal;
}

.md-render code.md-inline {
  background: hsl(220 14% 96%);
  color: hsl(340 82% 52%);
  padding: 0.125rem 0.375rem;
  border-radius: 4px;
  font-size: 0.875em;
  font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
  font-weight: 500;
}

.md-render pre.md-code {
  background: hsl(222 47% 11%);
  border: 1px solid hsl(215 16% 47% / 0.2);
  border-radius: 8px;
  padding: 1rem 1.25rem;
  margin: 1.5rem 0;
  overflow-x: auto;
  position: relative;
  box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
}

.md-render pre.md-code code {
  color: hsl(213 31% 91%);
  font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
  font-size: 0.875rem;
  line-height: 1.7;
}

.md-render blockquote {
  border-left: 3px solid var(--color-gray-800);
  padding-left: 1rem;
  margin: 1.5rem 0;
  color: var(--text-secondary);
  font-style: italic;
  background: var(--bg-secondary);
  padding: 1rem 1rem 1rem 1.5rem;
  border-radius: 0 6px 6px 0;
}

.md-render table {
  width: 100%;
  border-collapse: collapse;
  margin: 1.5rem 0;
  font-size: 0.875rem;
}

.md-render table th {
  background: var(--bg-secondary);
  padding: 0.75rem 1rem;
  text-align: left;
  font-weight: 600;
  border-bottom: 2px solid var(--border-color);
}

.md-render table td {
  padding: 0.75rem 1rem;
  border-bottom: 1px solid var(--border-color);
}

.md-render table tr:hover {
  background: var(--bg-secondary);
}

.md-render hr {
  border: 0;
  border-top: 1px solid var(--border-color);
  margin: 2rem 0;
}

.md-render img {
  max-width: 100%;
  height: auto;
  border-radius: 8px;
  margin: 1.5rem 0;
}

.docs-empty {
  text-align: center;
  padding: 4rem 2rem;
  color: var(--text-secondary);
}

.docs-empty-icon {
  width: 64px;
  height: 64px;
  margin: 0 auto 1rem;
  opacity: 0.5;
}

.docs-empty-title {
  font-size: 1.125rem;
  font-weight: 600;
  color: var(--text-primary);
  margin: 0 0 0.5rem 0;
}

.docs-empty-text {
  font-size: 0.875rem;
}

/* Next/Previous Navigation - shadcn inspired */
.docs-navigation {
  display: flex;
  justify-content: space-between;
  align-items: stretch;
  gap: 0.75rem;
  margin-top: 3rem;
  padding-top: 1.5rem;
  border-top: 1px solid var(--border-color);
}

.docs-nav-btn {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.625rem 1rem;
  border-radius: 6px;
  border: 1px solid var(--border-color);
  background: white;
  color: var(--text-primary);
  text-decoration: none;
  transition: all 0.15s cubic-bezier(0.4, 0, 0.2, 1);
  font-size: 0.875rem;
  flex: 0 1 auto;
  min-width: 0;
  max-width: 280px;
  box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
  outline: none;
}

.docs-nav-btn:hover {
  background: var(--bg-secondary);
  border-color: var(--border-color-dark);
  box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
}

.docs-nav-btn:focus-visible {
  border-color: var(--color-gray-900);
  box-shadow: 0 0 0 3px rgba(0, 0, 0, 0.05);
}

.docs-nav-btn-content {
  display: flex;
  flex-direction: column;
  gap: 0.125rem;
  flex: 1;
  min-width: 0;
}

.docs-nav-btn-label {
  font-size: 0.6875rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.025em;
  color: var(--text-secondary);
  line-height: 1;
}

.docs-nav-btn-title {
  font-weight: 500;
  font-size: 0.875rem;
  color: var(--text-primary);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  line-height: 1.4;
}

.docs-nav-btn-icon {
  flex-shrink: 0;
  width: 16px;
  height: 16px;
  color: var(--text-secondary);
  transition: all 0.15s cubic-bezier(0.4, 0, 0.2, 1);
}

.docs-nav-btn:hover .docs-nav-btn-icon {
  color: var(--text-primary);
}

.docs-nav-btn-prev:hover .docs-nav-btn-icon {
  transform: translateX(-2px);
}

.docs-nav-btn-next:hover .docs-nav-btn-icon {
  transform: translateX(2px);
}

.docs-nav-spacer {
  flex: 1;
}


/* Document Progress Indicator */
.docs-progress {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.625rem;
  margin-top: 0.75rem;
  padding: 0.5rem 0.75rem;
  font-size: 0.75rem;
  color: var(--text-secondary);
  background: transparent;
  border-radius: 6px;
}

.docs-progress-bar {
  flex: 1;
  max-width: 180px;
  height: 3px;
  background: var(--border-color);
  border-radius: 1.5px;
  overflow: hidden;
}

.docs-progress-fill {
  height: 100%;
  background: var(--color-gray-900);
  transition: width 0.3s ease;
  border-radius: 1.5px;
}

/* Keyboard Shortcut Hints */
.docs-kbd {
  display: inline-flex;
  align-items: center;
  padding: 0.125rem 0.375rem;
  font-size: 0.6875rem;
  font-family: monospace;
  background: var(--bg-secondary);
  border: 1px solid var(--border-color);
  border-radius: 3px;
  color: var(--text-secondary);
  margin-left: 0.5rem;
  font-weight: 500;
}

/* Book-like chapter numbering */
.md-render h1::before,
.md-render h2::before {
  color: var(--text-secondary);
  font-weight: 400;
  margin-right: 0.75rem;
  font-size: 0.875em;
}

@media (max-width: 768px) {
  html,
  body {
    height: auto;
    min-height: 100%;
    overflow: visible;
    background: var(--bg-secondary);
  }

  .docs-page-container {
    height: auto;
    min-height: 100vh;
    background: var(--bg-secondary);
  }

  .docs-hero {
    position: sticky;
    top: calc(var(--header-height, 64px) + env(safe-area-inset-top, 0));
    padding: 1rem 1.25rem;
    border-radius: 0 0 1rem 1rem;
  }

  .docs-hero-content {
    flex-direction: column;
    align-items: flex-start;
    gap: 1rem;
  }

  .docs-hero-title {
    font-size: 1.25rem;
  }

  .docs-search-wrapper {
    width: 100%;
    flex: 1 1 auto;
  }

  .docs-search-input {
    font-size: 0.875rem;
  }

  .docs-wrapper {
    overflow: visible;
  }

  .docs-layout {
    display: flex;
    flex-direction: column;
    height: auto;
    max-width: 100%;
    padding-bottom: env(safe-area-inset-bottom, 0);
  }

  .docs-content {
    order: 1;
    height: auto;
    max-height: none;
    overflow: visible;
    border-right: none;
    border-bottom: 1px solid var(--border-color);
    background: var(--bg-primary);
  }

  .docs-content-inner {
    padding: 1.25rem 1rem 4.5rem;
    max-width: 100%;
  }

  .docs-breadcrumb {
    flex-wrap: wrap;
    gap: 0.25rem 0.5rem;
  }

  .docs-content-title {
    font-size: 1.5rem;
  }

  .docs-content-meta {
    flex-wrap: wrap;
    gap: 0.5rem;
  }

  .docs-sidebar {
    order: 3;
    width: 100%;
    margin: 1rem 0 0;
    border: none;
    border-radius: 1rem;
    box-shadow: none;
    background: transparent;
    max-height: none;
  }

  .docs-sidebar-header {
    border-radius: 1rem 1rem 0 0;
    padding: 0.875rem 1rem;
  }

  .docs-sidebar-scroll {
    max-height: none;
    padding: 0.75rem 1rem 1.25rem;
    background: white;
    border-radius: 0 0 1rem 1rem;
  }

  .docs-sidebar-view.active .docs-sidebar-link,
  .docs-sidebar-view.active .docs-toc-link {
    padding: 0.5rem 0.75rem;
  }

  .docs-sidebar-toggle {
    padding: 0.375rem 0.75rem;
  }

  .docs-navigation {
    flex-direction: column;
    gap: 0.5rem;
  }

  .docs-nav-btn {
    width: 100%;
    max-width: none;
  }

  .docs-progress {
    justify-content: space-between;
  }

  .docs-progress-bar {
    max-width: none;
  }

  .md-render {
    font-size: 0.9375rem;
  }

  .md-render h1 {
    font-size: 1.75rem;
  }

  .md-render h2 {
    font-size: 1.5rem;
  }

  .md-render pre.md-code {
    margin: 1.25rem 0;
  }

  .docs-empty {
    padding: 3rem 1.5rem;
  }
}
</style>

<div class="docs-page-container">
  <!-- Sticky Hero Section -->
  <div class="docs-hero">
    <div class="docs-hero-content">
      <div class="docs-hero-left">
        <h1 class="docs-hero-title">📚 Documentation Hub</h1>
        <p class="docs-hero-subtitle">Comprehensive guides and resources</p>
      </div>
      <div class="docs-search-wrapper">
        <svg class="docs-search-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <circle cx="11" cy="11" r="8"></circle>
          <path d="m21 21-4.35-4.35"></path>
        </svg>
        <input 
          type="text" 
          class="docs-search-input" 
          id="docsSearch" 
          placeholder="Search documentation... (Press '/' to focus)"
          autocomplete="off"
        />
      </div>
    </div>
  </div>

  <!-- Main Layout -->
  <div class="docs-wrapper">
    <div class="docs-layout">
      <!-- Main Content Area -->
      <main class="docs-content">
        <div class="docs-content-inner">
          <?php if ($activeId && isset($indexMap[$activeId])): ?>
            <!-- Breadcrumb Navigation -->
            <nav class="docs-breadcrumb" aria-label="Breadcrumb">
              <div class="docs-breadcrumb-item">
                <a href="/docs" class="docs-breadcrumb-link">Documentation</a>
              </div>
              <span class="docs-breadcrumb-sep">/</span>
              <div class="docs-breadcrumb-item">
                <span><?php echo htmlspecialchars($activeLabel); ?></span>
              </div>
              <span class="docs-breadcrumb-sep">/</span>
              <div class="docs-breadcrumb-item">
                <span><?php echo htmlspecialchars($activeTitle); ?></span>
              </div>
            </nav>

            <!-- Content Header -->
            <header class="docs-content-header">
              <h1 class="docs-content-title"><?php echo htmlspecialchars($activeTitle ?: 'Document'); ?></h1>
              <div class="docs-content-meta">
                <span>📂 <?php echo htmlspecialchars($activeLabel); ?></span>
                <button type="button" class="docs-btn" onclick="copyDocLink()" title="Copy link">
                  <svg class="docs-btn-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                  </svg>
                  Copy Link
                </button>
              </div>
            </header>
            
            <!-- Markdown Content -->
            <div class="md-render" id="docContent">
              <?php echo $rendered; ?>
            </div>
            
            <!-- Next/Previous Navigation -->
            <?php
            // Find current document index
            $currentIndex = -1;
            foreach ($files as $idx => $f) {
              if ($f['id'] === $activeId) {
                $currentIndex = $idx;
                break;
              }
            }
            
            $prevDoc = ($currentIndex > 0) ? $files[$currentIndex - 1] : null;
            $nextDoc = ($currentIndex >= 0 && $currentIndex < count($files) - 1) ? $files[$currentIndex + 1] : null;
            ?>
            <?php if ($prevDoc || $nextDoc): ?>
            <nav class="docs-navigation">
              <?php if ($prevDoc): ?>
              <a href="/docs?id=<?php echo urlencode($prevDoc['id']); ?>" class="docs-nav-btn docs-nav-btn-prev">
                <svg class="docs-nav-btn-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                </svg>
                <div class="docs-nav-btn-content">
                  <span class="docs-nav-btn-label">Previous <span class="docs-kbd">Alt+←</span></span>
                  <span class="docs-nav-btn-title"><?php echo htmlspecialchars($prevDoc['name']); ?></span>
                </div>
              </a>
              <?php else: ?>
              <div class="docs-nav-spacer"></div>
              <?php endif; ?>
              
              <?php if ($nextDoc): ?>
              <a href="/docs?id=<?php echo urlencode($nextDoc['id']); ?>" class="docs-nav-btn docs-nav-btn-next">
                <div class="docs-nav-btn-content" style="text-align: right;">
                  <span class="docs-nav-btn-label">Next <span class="docs-kbd">Alt+→</span></span>
                  <span class="docs-nav-btn-title"><?php echo htmlspecialchars($nextDoc['name']); ?></span>
                </div>
                <svg class="docs-nav-btn-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                </svg>
              </a>
              <?php else: ?>
              <div class="docs-nav-spacer"></div>
              <?php endif; ?>
            </nav>
            
            <!-- Progress Indicator -->
            <?php if ($currentIndex >= 0 && count($files) > 0): 
              $progress = (($currentIndex + 1) / count($files)) * 100;
            ?>
            <div class="docs-progress">
              <span><?php echo $currentIndex + 1; ?> of <?php echo count($files); ?></span>
              <div class="docs-progress-bar">
                <div class="docs-progress-fill" style="width: <?php echo $progress; ?>%;"></div>
              </div>
              <span><?php echo round($progress); ?>%</span>
            </div>
            <?php endif; ?>
            <?php endif; ?>
          <?php else: ?>
            <!-- Empty State -->
            <div class="docs-empty">
              <svg class="docs-empty-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
              </svg>
              <div class="docs-empty-title">Select a Document</div>
              <div class="docs-empty-text">Use the search above or browse contents in the sidebar →</div>
            </div>
          <?php endif; ?>
        </div>
      </main>

      <!-- Right Sidebar: Switchable Contents/TOC -->
      <aside class="docs-sidebar">
        <div class="docs-sidebar-header">
          <div class="docs-sidebar-title" id="sidebarTitle">
            <span id="sidebarIcon">📑</span>
            <span id="sidebarLabel">Contents</span>
            <span class="docs-sidebar-badge" id="sidebarBadge"><?php echo count($files); ?></span>
          </div>
          <button type="button" class="docs-sidebar-toggle" id="sidebarToggle" onclick="toggleSidebarView()">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
            </svg>
            <span id="toggleText">TOC</span>
          </button>
        </div>
        
        <div class="docs-sidebar-scroll">
          <!-- Contents View -->
          <div class="docs-sidebar-view active" id="contentsView">
            <?php
            $currentLabel = null;
            foreach ($files as $f):
              if ($currentLabel !== $f['label']) {
                if ($currentLabel !== null) echo '</div>'; // Close previous group
                echo '<div class="docs-sidebar-group">';
                echo '<div class="docs-sidebar-group-title">' . htmlspecialchars($f['label']) . '</div>';
                $currentLabel = $f['label'];
              }
              $isActive = ($f['id'] === $activeId);
            ?>
            <a href="/docs?id=<?php echo urlencode($f['id']); ?>" class="docs-sidebar-link <?php echo $isActive ? 'active' : ''; ?>" data-doc-name="<?php echo htmlspecialchars($f['relative']); ?>">
              <?php echo htmlspecialchars($f['name']); ?>
            </a>
            <?php endforeach; ?>
            <?php if ($currentLabel !== null) echo '</div>'; // Close last group ?>
            <?php if (count($files) === 0): ?>
              <div class="docs-empty">
                <svg class="docs-empty-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                <div class="docs-empty-title">No Documentation</div>
                <div class="docs-empty-text">No markdown files found</div>
              </div>
            <?php endif; ?>
          </div>

          <!-- TOC View -->
          <div class="docs-sidebar-view" id="tocView">
            <div id="tocPlaceholder" class="docs-empty" style="padding: 2rem 1rem;">
              <div class="docs-empty-text">Select a document to see the table of contents</div>
            </div>
            <ul class="docs-toc-list" id="tocList" style="display: none;">
              <!-- Generated by JavaScript -->
            </ul>
          </div>
        </div>
      </aside>
    </div>
  </div>
</div> <!-- Close docs-page-container -->

<script>
// ==================== Documentation Hub JavaScript ====================

// 1. Sidebar Toggle Function
function toggleSidebarView() {
  const contentsView = document.getElementById('contentsView');
  const tocView = document.getElementById('tocView');
  const icon = document.getElementById('sidebarIcon');
  const label = document.getElementById('sidebarLabel');
  const toggleText = document.getElementById('toggleText');
  const badge = document.getElementById('sidebarBadge');
  
  const isContentsActive = contentsView.classList.contains('active');
  
  if (isContentsActive) {
    // Switch to TOC view
    contentsView.classList.remove('active');
    tocView.classList.add('active');
    icon.textContent = '📋';
    label.textContent = 'On This Page';
    toggleText.textContent = 'Contents';
    badge.style.display = 'none';
  } else {
    // Switch to Contents view
    contentsView.classList.add('active');
    tocView.classList.remove('active');
    icon.textContent = '📑';
    label.textContent = 'Contents';
    toggleText.textContent = 'TOC';
    badge.style.display = 'inline-block';
  }
}

// 2. Copy Document Link
function copyDocLink() {
  const url = new URL(window.location.href);
  navigator.clipboard.writeText(url.toString()).then(() => {
    if (typeof Toast !== 'undefined') Toast.success('📋 Link copied to clipboard');
  }).catch(() => {
    if (typeof Toast !== 'undefined') Toast.error('Failed to copy link');
  });
}

// 3. Table of Contents Generation & Active Section Tracking
function generateTableOfContents() {
  const content = document.getElementById('docContent');
  const tocList = document.getElementById('tocList');
  const tocPlaceholder = document.getElementById('tocPlaceholder');
  
  if (!content || !tocList) return;
  
  // Clear previous TOC
  tocList.innerHTML = '';
  
  const headings = content.querySelectorAll('h1, h2, h3, h4');
  
  if (headings.length === 0) {
    tocList.style.display = 'none';
    if (tocPlaceholder) tocPlaceholder.style.display = 'block';
    return;
  }
  
  // Show TOC, hide placeholder
  tocList.style.display = 'block';
  if (tocPlaceholder) tocPlaceholder.style.display = 'none';
  
  headings.forEach((heading, index) => {
    // Ensure heading has an ID
    if (!heading.id) {
      heading.id = 'heading-' + index;
    }
    
    const level = heading.tagName.toLowerCase();
    const text = heading.textContent;
    const id = heading.id;
    
    const li = document.createElement('li');
    li.className = 'docs-toc-item';
    
    const a = document.createElement('a');
    a.href = '#' + id;
    a.className = 'docs-toc-link docs-toc-link-' + level;
    a.textContent = text;
    a.onclick = function(e) {
      e.preventDefault();
      const target = document.getElementById(id);
      if (target) {
        const contentArea = document.querySelector('.docs-content');
        if (contentArea) {
          const targetOffset = target.offsetTop - contentArea.offsetTop;
          contentArea.scrollTo({ top: targetOffset - 20, behavior: 'smooth' });
        }
        history.pushState(null, '', '#' + id);
      }
    };
    
    li.appendChild(a);
    tocList.appendChild(li);
  });
  
  // Track active section while scrolling
  const contentArea = document.querySelector('.docs-content');
  if (!contentArea) return;
  
  let ticking = false;
  function updateActiveTocLink() {
    const scrollPos = contentArea.scrollTop + 100;
    let activeHeading = null;
    
    headings.forEach(heading => {
      const headingOffset = heading.offsetTop - contentArea.offsetTop;
      if (headingOffset <= scrollPos) {
        activeHeading = heading;
      }
    });
    
    document.querySelectorAll('.docs-toc-link').forEach(link => {
      link.classList.remove('active');
    });
    
    if (activeHeading) {
      const activeLink = tocList.querySelector('a[href="#' + activeHeading.id + '"]');
      if (activeLink) {
        activeLink.classList.add('active');
        // Auto-scroll TOC to keep active link visible
        scrollTocToActive(activeLink);
      }
    }
    
    ticking = false;
  }
  
  contentArea.addEventListener('scroll', function() {
    if (!ticking) {
      window.requestAnimationFrame(updateActiveTocLink);
      ticking = true;
    }
  });
  
  // Initial check
  updateActiveTocLink();
}

// Scroll TOC sidebar to keep active link visible
function scrollTocToActive(activeLink) {
  const sidebarScroll = document.querySelector('.docs-sidebar-scroll');
  if (!sidebarScroll || !activeLink) return;
  
  // Only scroll if in TOC view
  const tocView = document.getElementById('tocView');
  if (!tocView || !tocView.classList.contains('active')) return;
  
  const linkRect = activeLink.getBoundingClientRect();
  const scrollRect = sidebarScroll.getBoundingClientRect();
  
  // Check if link is not fully visible
  const isAbove = linkRect.top < scrollRect.top + 60; // Account for header
  const isBelow = linkRect.bottom > scrollRect.bottom - 20;
  
  if (isAbove || isBelow) {
    const parentLi = activeLink.closest('.docs-toc-item');
    if (parentLi) {
      const scrollTop = parentLi.offsetTop - sidebarScroll.offsetTop - (sidebarScroll.clientHeight / 3);
      sidebarScroll.scrollTo({ top: Math.max(0, scrollTop), behavior: 'smooth' });
    }
  }
}

// 4. Copy Code Button for Code Blocks
function addCopyButtonsToCodeBlocks() {
  const codeBlocks = document.querySelectorAll('pre.md-code');
  
  codeBlocks.forEach(pre => {
    if (pre.querySelector('.copy-code-btn')) return;
    
    const button = document.createElement('button');
    button.className = 'copy-code-btn';
    button.innerHTML = `<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>`;
    button.title = 'Copy code';
    button.style.cssText = 'position:absolute;top:0.5rem;right:0.5rem;padding:0.375rem 0.5rem;background:rgba(255,255,255,0.1);border:1px solid rgba(255,255,255,0.2);border-radius:4px;color:rgba(255,255,255,0.8);cursor:pointer;transition:all 0.2s;';
    
    button.addEventListener('mouseover', () => { button.style.background = 'rgba(255,255,255,0.2)'; });
    button.addEventListener('mouseout', () => { button.style.background = 'rgba(255,255,255,0.1)'; });
    
    button.addEventListener('click', function() {
      const code = pre.querySelector('code');
      if (!code) return;
      navigator.clipboard.writeText(code.textContent).then(() => {
        button.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg>';
        if (typeof Toast !== 'undefined') Toast.success('Code copied!');
        setTimeout(() => { button.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>'; }, 2000);
      }).catch(() => { if (typeof Toast !== 'undefined') Toast.error('Failed to copy'); });
    });
    
    pre.style.position = 'relative';
    pre.appendChild(button);
  });
}

// 5. Live Search Functionality
function initializeDocumentSearch() {
  const searchInput = document.getElementById('docsSearch');
  const sidebarLinks = document.querySelectorAll('.docs-sidebar-link');
  const badge = document.getElementById('sidebarBadge');
  
  if (!searchInput || sidebarLinks.length === 0) return;
  
  // Keyboard shortcut: Press '/' to focus search
  document.addEventListener('keydown', function(e) {
    if (e.key === '/' && !['INPUT', 'TEXTAREA'].includes(document.activeElement.tagName)) {
      e.preventDefault();
      searchInput.focus();
      // Switch to Contents view when searching
      const contentsView = document.getElementById('contentsView');
      if (!contentsView.classList.contains('active')) {
        toggleSidebarView();
      }
    }
    
    if (e.key === 'Escape' && document.activeElement === searchInput) {
      searchInput.value = '';
      searchInput.blur();
      filterDocuments('');
    }
  });
  
  searchInput.addEventListener('input', function() {
    const query = this.value.toLowerCase().trim();
    // Auto-switch to Contents view when typing
    const contentsView = document.getElementById('contentsView');
    if (query && !contentsView.classList.contains('active')) {
      toggleSidebarView();
    }
    filterDocuments(query);
  });
  
  function filterDocuments(query) {
    let visibleCount = 0;
    
    sidebarLinks.forEach(link => {
      const docName = link.getAttribute('data-doc-name') || '';
      const text = link.textContent.toLowerCase();
      const matches = text.includes(query) || docName.toLowerCase().includes(query);
      
      if (matches || query === '') {
        link.style.display = 'block';
        visibleCount++;
      } else {
        link.style.display = 'none';
      }
    });
    
    // Hide empty groups
    document.querySelectorAll('.docs-sidebar-group').forEach(group => {
      const visibleLinks = Array.from(group.querySelectorAll('.docs-sidebar-link')).filter(
        link => link.style.display !== 'none'
      );
      group.style.display = visibleLinks.length === 0 ? 'none' : 'block';
    });
    
    // Update badge
    if (badge) {
      badge.textContent = query ? (visibleCount + ' found') : sidebarLinks.length;
    }
  }
}

// 6. Add Heading Anchor Links
function addHeadingAnchors() {
  const content = document.getElementById('docContent');
  if (!content) return;
  
  content.querySelectorAll('h1, h2, h3, h4, h5, h6').forEach(heading => {
    if (!heading.id) return;
    
    const anchor = document.createElement('a');
    anchor.href = '#' + heading.id;
    anchor.className = 'heading-anchor';
    anchor.innerHTML = '#';
    anchor.title = 'Link to this section';
    anchor.style.cssText = 'margin-left:0.5rem;font-weight:400;color:var(--border-color);text-decoration:none;opacity:0;transition:opacity 0.2s;';
    
    heading.style.position = 'relative';
    heading.appendChild(anchor);
    
    heading.addEventListener('mouseenter', () => { anchor.style.opacity = '1'; });
    heading.addEventListener('mouseleave', () => { anchor.style.opacity = '0'; });
    
    anchor.addEventListener('click', function(e) {
      e.preventDefault();
      const url = new URL(window.location.href);
      url.hash = heading.id;
      navigator.clipboard.writeText(url.toString()).then(() => {
        if (typeof Toast !== 'undefined') Toast.success('🔗 Link copied');
      });
    });
  });
}

// 7. AJAX Navigation System
let isNavigating = false;

function navigateToDocument(docId, pushState = true) {
  if (isNavigating) {
    console.log('Navigation already in progress, skipping...');
    return;
  }
  
  console.log('Navigating to document:', docId);
  isNavigating = true;
  
  // Show loading state
  const contentArea = document.querySelector('.docs-content');
  if (contentArea) {
    contentArea.style.opacity = '0.5';
    contentArea.style.pointerEvents = 'none';
  }
  
  // Fetch the new document via AJAX
  fetch(`docs.php?id=${encodeURIComponent(docId)}&ajax=1`)
    .then(response => {
      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }
      return response.json();
    })
    .then(data => {
      console.log('AJAX response:', data);
      
      if (!data.success) {
        throw new Error(data.error || 'Failed to load document');
      }
      
      // Update the main content
      const docContent = document.getElementById('docContent');
      if (docContent) {
        docContent.innerHTML = data.content;
      }
      
      // Update navigation buttons
      const navContainer = document.querySelector('.docs-navigation');
      if (navContainer && data.navigation) {
        navContainer.innerHTML = data.navigation;
      }
      
      // Update progress indicator
      const progressContainer = document.querySelector('.docs-progress');
      if (progressContainer && data.progress) {
        progressContainer.innerHTML = data.progress;
      }
      
      // Re-initialize AJAX links for new nav buttons
      initializeAjaxLinks();
      
      // Update sidebar active state
      document.querySelectorAll('.docs-sidebar-link').forEach(link => {
        link.classList.remove('active');
        const linkDocId = new URL(link.href).searchParams.get('id');
        if (linkDocId === docId) {
          link.classList.add('active');
          scrollSidebarToActive(link);
        }
      });
      
      // Update browser URL
      if (pushState) {
        const newUrl = `docs.php?id=${encodeURIComponent(docId)}`;
        history.pushState({ docId }, '', newUrl);
      }
      
      // Regenerate TOC and other features
      generateTableOfContents();
      addCopyButtonsToCodeBlocks();
      addHeadingAnchors();
      
      // Scroll content to top with smooth animation
      if (contentArea && !window.location.hash) {
        contentArea.scrollTo({ top: 0, behavior: 'smooth' });
      }
      
      // Fade content back in
      setTimeout(() => {
        if (contentArea) {
          contentArea.style.opacity = '1';
          contentArea.style.pointerEvents = 'auto';
        }
        isNavigating = false;
      }, 200);
      
      // Auto-switch to TOC view
      const contentsView = document.getElementById('contentsView');
      if (contentsView && contentsView.classList.contains('active')) {
        setTimeout(() => toggleSidebarView(), 300);
      }
      
      if (typeof Toast !== 'undefined') {
        Toast.success('📄 Document loaded', 1500);
      }
    })
    .catch(error => {
      console.error('Navigation error:', error);
      isNavigating = false;
      
      if (contentArea) {
        contentArea.style.opacity = '1';
        contentArea.style.pointerEvents = 'auto';
      }
      
      if (typeof Toast !== 'undefined') {
        Toast.error('Failed to load document. Redirecting...');
      }
      
      // Fallback to regular navigation after a brief delay
      setTimeout(() => {
        window.location.href = `docs.php?id=${encodeURIComponent(docId)}`;
      }, 1000);
    });
}

// Scroll sidebar to make active link visible
function scrollSidebarToActive(activeLink) {
  const sidebarScroll = document.querySelector('.docs-sidebar-scroll');
  if (!sidebarScroll || !activeLink) return;
  
  const linkRect = activeLink.getBoundingClientRect();
  const scrollRect = sidebarScroll.getBoundingClientRect();
  
  // Check if link is not fully visible
  const isAbove = linkRect.top < scrollRect.top;
  const isBelow = linkRect.bottom > scrollRect.bottom;
  
  if (isAbove || isBelow) {
    const scrollTop = activeLink.offsetTop - sidebarScroll.offsetTop - (sidebarScroll.clientHeight / 2) + (activeLink.clientHeight / 2);
    sidebarScroll.scrollTo({ top: scrollTop, behavior: 'smooth' });
  }
}

// Initialize AJAX links for navigation
function initializeAjaxLinks() {
  // Remove old listeners by cloning and replacing (prevents duplicates)
  const sidebarLinks = document.querySelectorAll('.docs-sidebar-link');
  sidebarLinks.forEach(link => {
    const newLink = link.cloneNode(true);
    link.parentNode.replaceChild(newLink, link);
    
    newLink.addEventListener('click', function(e) {
      e.preventDefault();
      e.stopPropagation();
      const url = new URL(this.href);
      const docId = url.searchParams.get('id');
      if (docId) {
        navigateToDocument(docId);
      }
    });
  });
  
  // Next/Previous buttons
  const navButtons = document.querySelectorAll('.docs-nav-btn');
  navButtons.forEach(btn => {
    const newBtn = btn.cloneNode(true);
    btn.parentNode.replaceChild(newBtn, btn);
    
    newBtn.addEventListener('click', function(e) {
      e.preventDefault();
      e.stopPropagation();
      const url = new URL(this.href);
      const docId = url.searchParams.get('id');
      if (docId) {
        navigateToDocument(docId);
      }
    });
  });
}

// Handle browser back/forward
window.addEventListener('popstate', function(e) {
  if (e.state && e.state.docId) {
    navigateToDocument(e.state.docId, false);
  }
});

// Keyboard Navigation (Arrow Keys for Next/Previous)
function initializeKeyboardNavigation() {
  document.addEventListener('keydown', function(e) {
    // Don't trigger when typing in input fields
    if (['INPUT', 'TEXTAREA'].includes(document.activeElement.tagName)) return;
    
    // Alt + Left Arrow = Previous
    if (e.altKey && e.key === 'ArrowLeft') {
      e.preventDefault();
      const prevBtn = document.querySelector('.docs-nav-btn-prev');
      if (prevBtn) {
        const url = new URL(prevBtn.href);
        const docId = url.searchParams.get('id');
        if (docId) navigateToDocument(docId);
      }
    }
    
    // Alt + Right Arrow = Next
    if (e.altKey && e.key === 'ArrowRight') {
      e.preventDefault();
      const nextBtn = document.querySelector('.docs-nav-btn-next');
      if (nextBtn) {
        const url = new URL(nextBtn.href);
        const docId = url.searchParams.get('id');
        if (docId) navigateToDocument(docId);
      }
    }
  });
}

// 8. Reset scroll position when navigating to a new document
function resetScrollPosition() {
  const contentArea = document.querySelector('.docs-content');
  if (contentArea && !window.location.hash) {
    contentArea.scrollTo({ top: 0, behavior: 'instant' });
  }
}

// ==================== Initialize Everything on Page Load ====================
document.addEventListener('DOMContentLoaded', function() {
  generateTableOfContents();
  addCopyButtonsToCodeBlocks();
  initializeDocumentSearch();
  addHeadingAnchors();
  initializeKeyboardNavigation();
  initializeAjaxLinks(); // Initialize AJAX navigation
  resetScrollPosition();
  
  // If a document is already loaded, show TOC by default
  const docContent = document.getElementById('docContent');
  if (docContent && docContent.children.length > 0) {
    const contentsView = document.getElementById('contentsView');
    if (contentsView && contentsView.classList.contains('active')) {
      toggleSidebarView();
    }
  }
  
  // Auto-scroll to hash on page load
  if (window.location.hash) {
    setTimeout(() => {
      const target = document.querySelector(window.location.hash);
      if (target) {
        const contentArea = document.querySelector('.docs-content');
        if (contentArea) {
          const targetOffset = target.offsetTop - contentArea.offsetTop;
          contentArea.scrollTo({ top: targetOffset - 20, behavior: 'smooth' });
        }
      }
    }, 100);
  }
  
  // Set initial history state
  const urlParams = new URLSearchParams(window.location.search);
  const currentDocId = urlParams.get('id');
  if (currentDocId) {
    history.replaceState({ docId: currentDocId }, '', window.location.href);
  }
  
  // Scroll sidebar to active link on load
  setTimeout(() => {
    const activeLink = document.querySelector('.docs-sidebar-link.active');
    if (activeLink) {
      scrollSidebarToActive(activeLink);
    }
  }, 200);
});
</script>
<?php
$pageTitle = 'Documentation';
$pageContent = ob_get_clean();
include __DIR__ . '/../../components/layout.php';



