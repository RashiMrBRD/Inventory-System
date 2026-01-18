<div id="invite-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9999; align-items: center; justify-content: center;">
  <div style="background: var(--bg-primary); border-radius: var(--radius-md); padding: 2rem; width: 100%; max-width: 500px; max-height: 90vh; overflow-y: auto; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1);">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
      <h2 style="margin: 0; font-size: 1.5rem; font-weight: 600; color: var(--text-primary);">Generate Invite Link</h2>
      <button onclick="closeInviteModal()" style="background: none; border: none; cursor: pointer; color: var(--text-secondary); font-size: 1.5rem; padding: 0.25rem;">&times;</button>
    </div>

    <form id="invite-form" onsubmit="generateInvite(event)">
      <div style="margin-bottom: 1rem;">
        <label style="display: block; margin-bottom: 0.5rem; font-weight: 500; color: var(--text-primary);">Role to Assign</label>
        <select id="invite-role" class="form-input" style="width: 100%; padding: 0.5rem; border: 2px solid var(--border-color); border-radius: var(--radius-md); background: var(--bg-primary); color: var(--text-primary);">
          <option value="user">User</option>
          <option value="manager">Manager</option>
          <option value="viewer">Viewer</option>
        </select>
        <span style="font-size: 0.75rem; color: var(--text-secondary); margin-top: 0.25rem; display: block;">
          <?php if ($isAdmin): ?>
            As admin, you can invite users with any role.
          <?php else: ?>
            As a user, you can only invite others with User role.
          <?php endif; ?>
        </span>
      </div>

      <div style="margin-bottom: 1rem;">
        <label style="display: block; margin-bottom: 0.5rem; font-weight: 500; color: var(--text-primary);">Email (Optional)</label>
        <input type="email" id="invite-email" class="form-input" placeholder="Restrict to specific email" style="width: 100%; padding: 0.5rem; border: 2px solid var(--border-color); border-radius: var(--radius-md); background: var(--bg-primary); color: var(--text-primary);">
        <span style="font-size: 0.75rem; color: var(--text-secondary); margin-top: 0.25rem; display: block;">Leave empty to allow anyone with the link to register</span>
      </div>

      <div style="margin-bottom: 1rem;">
        <label style="display: block; margin-bottom: 0.5rem; font-weight: 500; color: var(--text-primary);">Max Uses</label>
        <input type="number" id="invite-max-uses" class="form-input" value="1" min="1" max="100" style="width: 100%; padding: 0.5rem; border: 2px solid var(--border-color); border-radius: var(--radius-md); background: var(--bg-primary); color: var(--text-primary);">
        <span style="font-size: 0.75rem; color: var(--text-secondary); margin-top: 0.25rem; display: block;">How many times this invite can be used (1-100)</span>
      </div>

      <div style="margin-bottom: 1rem;">
        <label style="display: block; margin-bottom: 0.5rem; font-weight: 500; color: var(--text-primary);">Expires In</label>
        <select id="invite-expires" class="form-input" style="width: 100%; padding: 0.5rem; border: 2px solid var(--border-color); border-radius: var(--radius-md); background: var(--bg-primary); color: var(--text-primary);">
          <option value="24h">24 hours</option>
          <option value="7d" selected>7 days</option>
          <option value="30d">30 days</option>
        </select>
      </div>

      <div style="margin-bottom: 1.5rem;">
        <label style="display: block; margin-bottom: 0.5rem; font-weight: 500; color: var(--text-primary);">Message (Optional)</label>
        <textarea id="invite-message" class="form-input" placeholder="Add a personal message..." rows="3" style="width: 100%; padding: 0.5rem; border: 2px solid var(--border-color); border-radius: var(--radius-md); background: var(--bg-primary); color: var(--text-primary); resize: vertical;"></textarea>
      </div>

      <button type="submit" class="btn btn-primary" style="width: 100%; padding: 0.75rem; background: var(--color-primary); color: white; border: none; border-radius: var(--radius-md); font-weight: 500; cursor: pointer; transition: all 0.2s;" onmouseover="this.style.backgroundColor='hsl(214 95% 40%)'" onmouseout="this.style.backgroundColor='var(--color-primary)'">
        Generate Invite Link
      </button>
    </form>

    <div id="invite-result" style="display: none; margin-top: 1.5rem; padding: 1rem; background: var(--bg-secondary); border-radius: var(--radius-md); border: 1px solid var(--border-color);">
      <div style="margin-bottom: 0.75rem;">
        <label style="display: block; margin-bottom: 0.5rem; font-weight: 500; color: var(--text-primary);">Invite Link</label>
        <div style="display: flex; gap: 0.5rem;">
          <input type="text" id="invite-url" readonly style="flex: 1; padding: 0.5rem; border: 2px solid var(--border-color); border-radius: var(--radius-md); background: var(--bg-primary); color: var(--text-primary); font-family: monospace; font-size: 0.875rem;">
          <button onclick="copyInviteLink()" style="padding: 0.5rem 1rem; background: var(--color-primary); color: white; border: none; border-radius: var(--radius-md); cursor: pointer; font-weight: 500; transition: all 0.2s;" onmouseover="this.style.backgroundColor='hsl(214 95% 40%)'" onmouseout="this.style.backgroundColor='var(--color-primary)'">
            Copy
          </button>
        </div>
      </div>
      <div style="font-size: 0.875rem; color: var(--text-secondary);">
        <p><strong>Role:</strong> <span id="result-role"></span></p>
        <p><strong>Expires:</strong> <span id="result-expires"></span></p>
        <p><strong>Max Uses:</strong> <span id="result-max-uses"></span></p>
      </div>
    </div>
  </div>
</div>

<script>
function openInviteModal() {
  document.getElementById('invite-modal').style.display = 'flex';
  document.getElementById('invite-result').style.display = 'none';
  document.getElementById('invite-form').reset();
}

function closeInviteModal() {
  document.getElementById('invite-modal').style.display = 'none';
}

async function generateInvite(event) {
  event.preventDefault();

  const role = document.getElementById('invite-role').value;
  const email = document.getElementById('invite-email').value;
  const maxUses = parseInt(document.getElementById('invite-max-uses').value);
  const expiresIn = document.getElementById('invite-expires').value;
  const message = document.getElementById('invite-message').value;

  try {
    const response = await fetch('/api/invites.php?action=generate', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({
        role: role,
        email: email || null,
        max_uses: maxUses,
        expires_in: expiresIn,
        message: message
      })
    });

    const result = await response.json();

    if (result.success) {
      document.getElementById('invite-url').value = result.data.invite_url;
      document.getElementById('result-role').textContent = result.data.role;
      document.getElementById('result-expires').textContent = result.data.expires_in;
      document.getElementById('result-max-uses').textContent = result.data.max_uses;
      document.getElementById('invite-result').style.display = 'block';
      document.getElementById('invite-form').style.display = 'none';
    } else {
      alert(result.message || 'Failed to generate invite link');
    }
  } catch (error) {
    console.error('Error generating invite:', error);
    alert('Failed to generate invite link. Please try again.');
  }
}

function copyInviteLink() {
  const inviteUrl = document.getElementById('invite-url');
  inviteUrl.select();
  document.execCommand('copy');
  alert('Invite link copied to clipboard!');
}

// Close modal when clicking outside
document.getElementById('invite-modal').addEventListener('click', function(event) {
  if (event.target === this) {
    closeInviteModal();
  }
});
</script>
