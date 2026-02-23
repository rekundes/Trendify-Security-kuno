<?php
require_once 'config.php';

// Check if user is superadmin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'superadmin') {
    header('Location: sign-in.html');
    exit;
}

$admin_name = ($_SESSION['first_name'] ?? 'Superadmin') . ' ' . ($_SESSION['last_name'] ?? '');

// Get system stats
$db_info = [
    'users' => (function() { global $conn; $r = $conn->prepare("SELECT COUNT(*) as count FROM users"); $r->execute(); $res = $r->get_result(); $row = $res->fetch_assoc(); $r->close(); return $row['count']; })(),
    'customers' => (function() { global $conn; $r = $conn->prepare("SELECT COUNT(*) as count FROM users WHERE role = 'customer'"); $r->execute(); $res = $r->get_result(); $row = $res->fetch_assoc(); $r->close(); return $row['count']; })(),
    'admins' => (function() { global $conn; $r = $conn->prepare("SELECT COUNT(*) as count FROM users WHERE role IN ('admin', 'superadmin')"); $r->execute(); $res = $r->get_result(); $row = $res->fetch_assoc(); $r->close(); return $row['count']; })(),
    'orders' => (function() { global $conn; $r = $conn->prepare("SELECT COUNT(*) as count FROM orders"); $r->execute(); $res = $r->get_result(); $row = $res->fetch_assoc(); $r->close(); return $row['count']; })(),
    'products' => (function() { global $conn; $r = $conn->prepare("SELECT COUNT(*) as count FROM products"); $r->execute(); $res = $r->get_result(); $row = $res->fetch_assoc(); $r->close(); return $row['count']; })(),
    'order_items' => (function() { global $conn; $r = $conn->prepare("SELECT COUNT(*) as count FROM order_items"); $r->execute(); $res = $r->get_result(); $row = $res->fetch_assoc(); $r->close(); return $row['count']; })(),
];
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>System Management - Superadmin</title>
  <link rel="stylesheet" href="styles.css">
  <style>
    :root{--bg:#f5f7fb;--card:#fff;--muted:#6b7280;--accent:#111827;--danger:#dc2626}
    body{font-family:Inter,system-ui,Segoe UI,Arial,Helvetica,sans-serif;background:var(--bg);margin:0}
    .layout{display:flex;min-height:100vh}
    .sidebar{width:260px;background:#0a0f1f;color:#fff;padding:20px;box-sizing:border-box;overflow-y:auto}
    .brand{font-weight:700;font-size:18px;margin-bottom:20px;color:#fbbf24}
    .nav a{display:block;color:#cbd5e1;text-decoration:none;padding:12px;border-radius:6px;margin-bottom:6px;transition:all 0.2s}
    .nav a:hover{background:rgba(255,255,255,0.08);color:#fff}
    .nav a.active{background:#1f2937;color:#fbbf24;border-left:3px solid #fbbf24;padding-left:9px}
    main{flex:1;padding:24px;overflow-y:auto}
    .header{margin-bottom:24px}
    .header h1{margin:0;font-size:24px;color:var(--accent)}
    .section{background:var(--card);padding:20px;border-radius:10px;box-shadow:0 1px 3px rgba(0,0,0,0.06);margin-bottom:20px}
    .section-title{font-size:16px;font-weight:600;margin-bottom:16px;color:var(--accent)}
    .stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px;margin-bottom:20px}
    .stat{background:#f3f4f6;padding:12px;border-radius:8px}
    .stat-label{font-size:12px;color:var(--muted);text-transform:uppercase;letter-spacing:0.5px}
    .stat-value{font-size:18px;font-weight:700;color:var(--accent);margin-top:4px}
    .btn{display:inline-block;padding:10px 16px;background:#3b82f6;color:#fff;border:none;border-radius:6px;cursor:pointer;font-weight:500;transition:all 0.2s;text-decoration:none}
    .btn:hover{background:#2563eb;transform:translateY(-1px)}
    .btn-danger{background:var(--danger)}
    .btn-danger:hover{background:#b91c1c}
    .warning{background:#fef3c7;border-left:4px solid #fbbf24;padding:12px;border-radius:6px;margin-bottom:16px;color:#92400e;font-size:14px}
    table{width:100%;border-collapse:collapse;margin-top:12px}
    th{text-align:left;padding:10px;border-bottom:2px solid #e5e7eb;color:var(--muted);font-size:12px;text-transform:uppercase;letter-spacing:0.5px;font-weight:600}
    td{padding:10px;border-bottom:1px solid #eef2f7}
    .code{background:#f3f4f6;padding:12px;border-radius:6px;font-family:monospace;font-size:13px;color:#111827;margin:12px 0}
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
        <a href="superadmin_users.php">Users & Admins</a>
        <a href="superadmin_system.php" class="active">System</a>
        <a href="logout.php">Sign Out</a>
      </nav>
    </aside>
    <main>
      <div class="header">
        <h1>System Management</h1>
      </div>

      <section class="section">
        <div class="section-title">Database Overview</div>
        <div class="stats">
          <div class="stat">
            <div class="stat-label">Total Users</div>
            <div class="stat-value"><?= $db_info['users'] ?></div>
          </div>
          <div class="stat">
            <div class="stat-label">Customers</div>
            <div class="stat-value"><?= $db_info['customers'] ?></div>
          </div>
          <div class="stat">
            <div class="stat-label">Admins</div>
            <div class="stat-value"><?= $db_info['admins'] ?></div>
          </div>
          <div class="stat">
            <div class="stat-label">Orders</div>
            <div class="stat-value"><?= $db_info['orders'] ?></div>
          </div>
          <div class="stat">
            <div class="stat-label">Products</div>
            <div class="stat-value"><?= $db_info['products'] ?></div>
          </div>
          <div class="stat">
            <div class="stat-label">Order Items</div>
            <div class="stat-value"><?= $db_info['order_items'] ?></div>
          </div>
        </div>
      </section>

      <section class="section">
        <div class="section-title">System Information</div>
        <table>
          <tr>
            <td>PHP Version:</td>
            <td><code><?= phpversion() ?></code></td>
          </tr>
          <tr>
            <td>MySQL Version:</td>
            <td><code><?= $conn->server_info ?></code></td>
          </tr>
          <tr>
            <td>Database:</td>
            <td><code>trendify_db</code></td>
          </tr>
          <tr>
            <td>Server OS:</td>
            <td><code><?= php_uname() ?></code></td>
          </tr>
        </table>
      </section>

      <section class="section">
        <div class="section-title">Database Maintenance</div>
        <p style="color:#6b7280;font-size:14px;margin:0 0 16px 0">Perform system maintenance tasks. Use with caution!</p>
        
        <div class="warning">
          ⚠️ These actions cannot be undone. Make sure you have a backup before proceeding.
        </div>

        <div style="display:flex;gap:12px;flex-wrap:wrap">
          <button class="btn" onclick="clearLockouts()">Clear Account Lockouts</button>
          <button class="btn btn-danger" onclick="truncateLogins()">Clear Login Attempts</button>
        </div>
      </section>

      <section class="section">
        <div class="section-title">Database Tables</div>
        <table>
          <thead>
            <tr>
              <th>Table Name</th>
              <th>Row Count</th>
            </tr>
          </thead>
          <tbody>
            <?php
            $tables = ['users', 'products', 'orders', 'order_items', 'login_attempts'];
            $table_counts = [
                'users' => (function() { global $conn; $stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM users"); $stmt->execute(); $res = $stmt->get_result(); $row = $res->fetch_assoc(); $stmt->close(); return $row['cnt']; })(),
                'products' => (function() { global $conn; $stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM products"); $stmt->execute(); $res = $stmt->get_result(); $row = $res->fetch_assoc(); $stmt->close(); return $row['cnt']; })(),
                'orders' => (function() { global $conn; $stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM orders"); $stmt->execute(); $res = $stmt->get_result(); $row = $res->fetch_assoc(); $stmt->close(); return $row['cnt']; })(),
                'order_items' => (function() { global $conn; $stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM order_items"); $stmt->execute(); $res = $stmt->get_result(); $row = $res->fetch_assoc(); $stmt->close(); return $row['cnt']; })(),
                'login_attempts' => (function() { global $conn; $stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM login_attempts"); $stmt->execute(); $res = $stmt->get_result(); $row = $res->fetch_assoc(); $stmt->close(); return $row['cnt']; })(),
            ];
            foreach ($tables as $table) {
                $count = $table_counts[$table];
                echo "<tr><td><code>$table</code></td><td>$count</td></tr>";
            }
            ?>
          </tbody>
        </table>
      </section>

      <section class="section">
        <div class="section-title">Superadmin Account</div>
        <p style="color:#6b7280;font-size:14px;margin:0 0 12px 0">Current superadmin: <strong><?= htmlspecialchars($admin_name) ?></strong></p>
        <p style="color:#6b7280;font-size:13px;margin:0">Email: <strong><?= htmlspecialchars($_SESSION['email']) ?></strong></p>
      </section>
    </main>
  </div>

  <script>
    function clearLockouts() {
      if (confirm('This will clear all account lockouts. Continue?')) {
        fetch('superadmin_maintenance.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ action: 'clear_lockouts' })
        })
        .then(r => r.json())
        .then(d => {
          alert(d.message || 'Lockouts cleared');
          location.reload();
        })
        .catch(e => alert('Error: ' + e));
      }
    }

    function truncateLogins() {
      if (confirm('This will clear all login attempts. Continue?')) {
        fetch('superadmin_maintenance.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ action: 'truncate_logins' })
        })
        .then(r => r.json())
        .then(d => {
          alert(d.message || 'Login attempts cleared');
          location.reload();
        })
        .catch(e => alert('Error: ' + e));
      }
    }
  </script>
</body>
</html>
<?php $conn->close(); ?>
