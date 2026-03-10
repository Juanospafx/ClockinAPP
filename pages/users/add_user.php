<?php
declare(strict_types=1);
require_once __DIR__ . '/../../core/bootstrap.php';
require_once __DIR__ . '/../../core/services/AuthService.php';
$page_title = 'Add User';
if (AuthService::getCurrentUserRole() !== 'admin') {
    header('Location: /pages/login/login.html');
    exit;
}
?>
<?php include_once __DIR__ . '/../../views/header.php'; ?>
<div class="row">
  <div class="col-md-12"></div>
</div>
<div class="row">
  <div class="col-md-6 col-md-offset-3">
    <div class="panel panel-default">
      <div class="panel-heading">
        <strong>
          <span class="glyphicon glyphicon-th"></span>
          <span>Add New User</span>
        </strong>
      </div>
      <div class="panel-body">
        <form id="addUserForm" method="post">
          <div class="form-group">
            <label for="username">Username</label>
            <input type="text" class="form-control" name="username" id="username" placeholder="Username" required>
          </div>
          <div class="form-group">
            <label for="password">Password</label>
            <input type="password" class="form-control" name="password" id="password" placeholder="Password" required>
          </div>
          <div class="form-group">
            <label for="role">User Role</label>
            <select class="form-control" name="role_id" id="role_id">
              <option value="">Loading roles...</option>
            </select>
          </div>
          <button type="submit" class="btn btn-primary">Add User</button>
        </form>
        <div id="responseMessage" class="mt-3"></div>
      </div>
    </div>
  </div>
</div>
<script>
// Cargar roles dinámicamente
(async function loadRoles() {
    try {
        const res = await fetch('/api/v1/roles');
        const data = await res.json();
        if (data.ok && data.data && data.data.roles) {
            const select = document.getElementById('role_id');
            select.innerHTML = data.data.roles.map(r => `<option value="${r.id}" ${r.name === 'user' ? 'selected' : ''}>${r.name}</option>`).join('');
        }
    } catch (e) {
        console.error('Error loading roles:', e);
    }
})();

document.getElementById('addUserForm').addEventListener('submit', async function(event) {
    event.preventDefault();

    const username = document.getElementById('username').value;
    const password = document.getElementById('password').value;
    const role_id = document.getElementById('role_id').value;
    const responseMessageDiv = document.getElementById('responseMessage');

    try {
        const csrfToken = sessionStorage.getItem('csrf_token') || '';
        const headers = { 'Content-Type': 'application/json' };
        if (csrfToken) {
            headers['X-CSRF-Token'] = csrfToken;
        }
        const response = await fetch('/api/v1/users', {
            method: 'POST',
            headers: headers,
            body: JSON.stringify({ username: username, password: password, role_id: role_id })
        });

        const result = await response.json();
        const ok = result.ok === true;
        const message = ok ? result.data?.message : result.error?.message;

        if (ok) {
            responseMessageDiv.className = 'alert alert-success';
            responseMessageDiv.textContent = message || 'User created successfully.';
            // Optionally clear the form or redirect
            document.getElementById('addUserForm').reset();
        } else {
            responseMessageDiv.className = 'alert alert-danger';
            responseMessageDiv.textContent = message || 'An error occurred.';
        }
    } catch (error) {
        responseMessageDiv.className = 'alert alert-danger';
        responseMessageDiv.textContent = 'Network error or server is unreachable.';
        console.error('Error:', error);
    }
});
</script>
<?php include_once __DIR__ . '/../../views/footer.php'; ?>
