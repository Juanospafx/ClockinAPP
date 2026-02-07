<?php
  $page_title = 'Add User';
  require_once('../../backend/includes/load.php');
  // Checkin What level user has permission to view this page
  page_require_level(1); // Assuming only level 1 users can add other users
?>
<?php include_once('../../backend/layouts/header.php'); ?>
<div class="row">
  <div class="col-md-12">
    <?php echo display_msg($msg); ?>
  </div>
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
              <!-- Roles will be dynamically loaded here or hardcoded for now -->
              <option value="1">Admin</option>
              <option value="2" selected>User</option>
              <option value="3">Special User</option>
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
document.getElementById('addUserForm').addEventListener('submit', async function(event) {
    event.preventDefault();

    const username = document.getElementById('username').value;
    const password = document.getElementById('password').value;
    const role_id = document.getElementById('role_id').value;
    const responseMessageDiv = document.getElementById('responseMessage');

    try {
        const response = await fetch('../../backend/Users.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                action: 'create',
                username: username,
                password: password,
                role_id: role_id
            })
        });

        const result = await response.json();

        if (result.success) {
            responseMessageDiv.className = 'alert alert-success';
            responseMessageDiv.textContent = result.message;
            // Optionally clear the form or redirect
            document.getElementById('addUserForm').reset();
        } else {
            responseMessageDiv.className = 'alert alert-danger';
            responseMessageDiv.textContent = result.message || 'An error occurred.';
        }
    } catch (error) {
        responseMessageDiv.className = 'alert alert-danger';
        responseMessageDiv.textContent = 'Network error or server is unreachable.';
        console.error('Error:', error);
    }
});
</script>
<?php include_once('../../backend/layouts/footer.php'); ?>
