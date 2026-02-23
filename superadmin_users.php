<?php
require_once 'config.php';

// Check if user is superadmin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'superadmin') {
    header('Location: sign-in.html');
    exit;
}

$admin_name = ($_SESSION['first_name'] ?? 'Superadmin') . ' ' . ($_SESSION['last_name'] ?? '');

// Get filter
$role_filter = isset($_GET['role']) ? $_GET['role'] : '';
$filter_applied = $role_filter && in_array($role_filter, ['customer', 'admin', 'superadmin']);

// Pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Get total users with prepared statement
$total = 0;
if ($filter_applied) {
    $count_stmt = $conn->prepare("SELECT COUNT(*) as count FROM users WHERE role = ?");
    $count_stmt->bind_param('s', $role_filter);
    $count_stmt->execute();
    $count_res = $count_stmt->get_result();
    $total = $count_res->fetch_assoc()['count'];
    $count_stmt->close();
} else {
    $count_stmt = $conn->prepare("SELECT COUNT(*) as count FROM users");
    $count_stmt->execute();
    $count_res = $count_stmt->get_result();
    $total = $count_res->fetch_assoc()['count'];
    $count_stmt->close();
}

$total_pages = ceil($total / $per_page);

// Get users with prepared statement
$users = [];
if ($filter_applied) {
    $users_stmt = $conn->prepare("SELECT user_id, email, first_name, last_name, role, created_at FROM users WHERE role = ? ORDER BY created_at DESC LIMIT ?, ?");
    $users_stmt->bind_param('sii', $role_filter, $offset, $per_page);
    $users_stmt->execute();
    $result = $users_stmt->get_result();
} else {
    $users_stmt = $conn->prepare("SELECT user_id, email, first_name, last_name, role, created_at FROM users ORDER BY created_at DESC LIMIT ?, ?");
    $users_stmt->bind_param('ii', $offset, $per_page);
    $users_stmt->execute();
    $result = $users_stmt->get_result();
}

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
}
$users_stmt->close();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Users & Admins Management - Superadmin</title>
  <link rel="stylesheet" href="styles.css">
  <style>
    :root{--bg:#f5f7fb;--card:#fff;--muted:#6b7280;--accent:#111827}
    body{font-family:Inter,system-ui,Segoe UI,Arial,Helvetica,sans-serif;background:var(--bg);margin:0}
    .layout{display:flex;min-height:100vh}
    .sidebar{width:260px;background:#0a0f1f;color:#fff;padding:20px;box-sizing:border-box;overflow-y:auto}
    .brand{font-weight:700;font-size:18px;margin-bottom:20px;color:#fbbf24}
    .nav a{display:block;color:#cbd5e1;text-decoration:none;padding:12px;border-radius:6px;margin-bottom:6px;transition:all 0.2s}
    .nav a:hover{background:rgba(255,255,255,0.08);color:#fff}
    .nav a.active{background:#1f2937;color:#fbbf24;border-left:3px solid #fbbf24;padding-left:9px}
    main{flex:1;padding:24px;overflow-y:auto}
    .header{display:flex;justify-content:space-between;align-items:center;margin-bottom:24px}
    .header-actions{display:flex;gap:12px}
    .btn-primary{display:inline-block;padding:10px 16px;background:#3b82f6;color:#fff;border-radius:6px;text-decoration:none;font-weight:500;cursor:pointer;border:none;transition:all 0.2s}
    .btn-primary:hover{background:#2563eb}
    .filters{display:flex;gap:12px;margin-bottom:20px;align-items:center}
    .filters a{padding:8px 12px;background:#fff;border:1px solid #d1d5db;border-radius:6px;text-decoration:none;color:#111827;cursor:pointer;font-size:14px;transition:all 0.2s}
    .filters a:hover{background:#f3f4f6}
    .filters a.active{background:#3b82f6;color:#fff;border-color:#3b82f6}
    .section{background:var(--card);padding:16px;border-radius:10px;box-shadow:0 1px 3px rgba(0,0,0,0.06)}
    table{width:100%;border-collapse:collapse}
    th{text-align:left;padding:12px;border-bottom:2px solid #e5e7eb;color:var(--muted);font-size:12px;text-transform:uppercase;letter-spacing:0.5px;font-weight:600}
    td{padding:12px;border-bottom:1px solid #eef2f7;color:#374151}
    tr:hover{background:#f9fafb}
    .badge{display:inline-block;padding:4px 8px;border-radius:4px;font-size:11px;font-weight:600;text-transform:uppercase}
    .badge.superadmin{background:#fef3c7;color:#92400e}
    .badge.admin{background:#dbeafe;color:#1e3a8a}
    .badge.customer{background:#d1fae5;color:#065f46}
    .actions{display:flex;gap:6px}
    .btn-sm{padding:6px 10px;font-size:12px;border:none;border-radius:4px;cursor:pointer;transition:all 0.2s}
    .btn-danger{background:#dc2626;color:#fff}
    .btn-danger:hover{background:#b91c1c}
    .pagination{display:flex;justify-content:center;gap:8px;margin-top:20px}
    .pagination a, .pagination span{padding:8px 12px;border:1px solid #d1d5db;border-radius:6px;text-decoration:none;color:#111827}
    .pagination a:hover{background:#f3f4f6}
    .pagination .active{background:#3b82f6;color:#fff;border-color:#3b82f6}
    .empty{text-align:center;padding:40px;color:var(--muted)}
    @media (max-width:768px){.sidebar{width:100%;max-height:auto}.layout{flex-direction:column}main{padding:16px}}
  </style>
</head>
<body>
  <div class="layout">
    <aside class="sidebar">
      <div class="brand">🔐 Trendify Superadmin</div>
      <nav class="nav">
        <a href="superadmin_dashboard.php">Dashboard</a>
        <a href="superadmin_orders.php">Orders</a>
        <a href="superadmin_users.php" class="active">Users & Admins</a>
        <a href="superadmin_system.php">System</a>
        <a href="logout.php">Sign Out</a>
      </nav>
    </aside>
    <main>
      <div class="header">
        <div>
          <h1 style="margin:0;font-size:24px">Users & Admins</h1>
          <div style="color:var(--muted);font-size:14px;margin-top:4px">Total Users: <strong><?= $total ?></strong></div>
        </div>
        <div class="header-actions">
          <button class="btn-primary" onclick="showCreateAdmin()">+ Add Admin</button>
        </div>
      </div>

      <div class="filters">
        <a href="superadmin_users.php" class="<?= empty($role_filter) ? 'active' : '' ?>">All Users</a>
        <a href="superadmin_users.php?role=customer" class="<?= $role_filter === 'customer' ? 'active' : '' ?>">Customers</a>
        <a href="superadmin_users.php?role=admin" class="<?= $role_filter === 'admin' ? 'active' : '' ?>">Admins</a>
        <a href="superadmin_users.php?role=superadmin" class="<?= $role_filter === 'superadmin' ? 'active' : '' ?>">Superadmins</a>
      </div>

      <section class="section">
        <?php if (count($users) > 0): ?>
        <table>
          <thead>
            <tr>
              <th>Name</th>
              <th>Email</th>
              <th>Joined</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($users as $user): ?>
            <tr>
              <td><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></td>
              <td><?= htmlspecialchars($user['email']) ?></td>
              <td><?= date('M d, Y', strtotime($user['created_at'])) ?></td>
              <td>
                <div class="actions">
                  <?php if ($user['role'] === 'customer'): ?>
                    <button class="btn-sm btn-danger" onclick="deleteUser(<?= $user['user_id'] ?>, '<?= htmlspecialchars($user['email']) ?>')">Delete</button>
                  <?php elseif ($user['user_id'] !== $_SESSION['user_id']): ?>
                    <button class="btn-sm btn-danger" onclick="removeAdmin(<?= $user['user_id'] ?>, '<?= htmlspecialchars($user['email']) ?>')">Remove Admin</button>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>

        <?php if ($total_pages > 1): ?>
        <div class="pagination">
          <?php if ($page > 1): ?>
            <a href="superadmin_users.php?page=1<?= $role_filter ? '&role=' . urlencode($role_filter) : '' ?>">First</a>
            <a href="superadmin_users.php?page=<?= $page - 1 ?><?= $role_filter ? '&role=' . urlencode($role_filter) : '' ?>">Prev</a>
          <?php endif; ?>
          
          <span><?= $page ?> / <?= $total_pages ?></span>
          
          <?php if ($page < $total_pages): ?>
            <a href="superadmin_users.php?page=<?= $page + 1 ?><?= $role_filter ? '&role=' . urlencode($role_filter) : '' ?>">Next</a>
            <a href="superadmin_users.php?page=<?= $total_pages ?><?= $role_filter ? '&role=' . urlencode($role_filter) : '' ?>">Last</a>
          <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php else: ?>
        <div class="empty">No users found</div>
        <?php endif; ?>
      </section>
    </main>
  </div>

  <script>
    function showCreateAdmin() {
      const email = prompt('Enter admin email:');
      if (!email) return;
      
      const password = prompt('Enter admin password (min 8 characters):');
      if (!password || password.length < 8) {
        alert('Password must be at least 8 characters');
        return;
      }
      
      const firstName = prompt('Enter first name:');
      if (!firstName) return;
      
      const lastName = prompt('Enter last name:');
      if (!lastName) return;

      fetch('superadmin_create_admin.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          email: email,
          password: password,
          first_name: firstName,
          last_name: lastName
        })
      })
      .then(r => r.json())
      .then(d => {
        if (d.success) {
          alert('Admin account created successfully');
          location.reload();
        } else {
          alert('Error: ' + d.message);
        }
      })
      .catch(e => alert('Error: ' + e));
    }

    function removeAdmin(userId, email) {
      if (confirm(`Are you sure you want to remove ${email} as admin?`)) {
        fetch('superadmin_delete_admin.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ user_id: userId })
        })
        .then(r => r.json())
        .then(d => {
          if (d.success) {
            alert('Admin removed successfully');
            location.reload();
          } else {
            alert('Error: ' + d.message);
          }
        })
        .catch(e => alert('Error: ' + e));
      }
    }

    function deleteUser(userId, email) {
      if (confirm(`Are you sure you want to delete ${email}? This cannot be undone.`)) {
        fetch('superadmin_delete_user.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ user_id: userId })
        })
        .then(r => r.json())
        .then(d => {
          if (d.success) {
            alert('User deleted successfully');
            location.reload();
          } else {
            alert('Error: ' + d.message);
          }
        })
        .catch(e => alert('Error: ' + e));
      }
    }
  </script>
</body>
</html>
