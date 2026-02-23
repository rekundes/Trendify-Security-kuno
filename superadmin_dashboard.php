<?php
require_once 'config.php';

// Check if user is superadmin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'superadmin') {
    header('Location: sign-in.html');
    exit;
}

$admin_name = ($_SESSION['first_name'] ?? 'Superadmin') . ' ' . ($_SESSION['last_name'] ?? '');

// Handle order completion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['complete_order_id'])) {
    $order_id = intval($_POST['complete_order_id']);
    $update_sql = "UPDATE orders SET status = 'Delivered' WHERE order_id = ?";
    $stmt = $conn->prepare($update_sql);
    if ($stmt) {
        $stmt->bind_param('i', $order_id);
        $stmt->execute();
        $stmt->close();
    }
    header('Location: superadmin_dashboard.php');
    exit;
}

// Handle not delivered
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['not_delivered_id'])) {
    $order_id = intval($_POST['not_delivered_id']);
    $update_sql = "UPDATE orders SET status = 'Processing' WHERE order_id = ?";
    $stmt = $conn->prepare($update_sql);
    if ($stmt) {
        $stmt->bind_param('i', $order_id);
        $stmt->execute();
        $stmt->close();
    }
    header('Location: superadmin_dashboard.php');
    exit;
}

// Get statistics from database using prepared statements
$total_sales = (function() { global $conn; $stmt = $conn->prepare("SELECT SUM(total_amount) as total FROM orders WHERE status = 'Delivered'"); $stmt->execute(); $res = $stmt->get_result(); $row = $res->fetch_assoc(); $stmt->close(); return $row['total'] ?? 0; })();
$total_orders = (function() { global $conn; $stmt = $conn->prepare("SELECT COUNT(*) as count FROM orders"); $stmt->execute(); $res = $stmt->get_result(); $row = $res->fetch_assoc(); $stmt->close(); return $row['count'] ?? 0; })();
$total_customers = (function() { global $conn; $stmt = $conn->prepare("SELECT COUNT(*) as count FROM users WHERE role = 'customer'"); $stmt->execute(); $res = $stmt->get_result(); $row = $res->fetch_assoc(); $stmt->close(); return $row['count'] ?? 0; })();
$total_admins = (function() { global $conn; $stmt = $conn->prepare("SELECT COUNT(*) as count FROM users WHERE role IN ('admin', 'superadmin')"); $stmt->execute(); $res = $stmt->get_result(); $row = $res->fetch_assoc(); $stmt->close(); return $row['count'] ?? 0; })();
$total_products = 72;

// Get recent orders
$recent_orders = [];
$orders_sql = "SELECT o.order_id, o.order_date, o.status, o.total_amount, 
                      o.first_name, o.last_name,
                      DATE_ADD(o.order_date, INTERVAL 7 DAY) as estimated_delivery
               FROM orders o
               ORDER BY o.order_id DESC LIMIT 5";
$result = $conn->query($orders_sql);
if ($result && $result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $recent_orders[] = $row;
    }
}

// Get system admins
$admins_list = [];
$admins_sql = "SELECT user_id, email, first_name, last_name, role, created_at FROM users WHERE role IN ('admin', 'superadmin') ORDER BY created_at DESC";
$result = $conn->query($admins_sql);
if ($result && $result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $admins_list[] = $row;
    }
}

// Get recent users
$recent_users = [];
$users_sql = "SELECT user_id, email, first_name, last_name, created_at FROM users WHERE role = 'customer' ORDER BY created_at DESC LIMIT 5";
$result = $conn->query($users_sql);
if ($result && $result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $recent_users[] = $row;
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Superadmin Dashboard - Trendify Apparel</title>
  <link rel="stylesheet" href="styles.css">
  <style>
    :root{--bg:#f5f7fb;--card:#fff;--muted:#6b7280;--accent:#111827;--danger:#dc2626;--success:#16a34a}
    body{font-family:Inter,system-ui,Segoe UI,Arial,Helvetica,sans-serif;background:var(--bg);margin:0}
    .layout{display:flex;min-height:100vh}
    .sidebar{width:260px;background:#0a0f1f;color:#fff;padding:20px;box-sizing:border-box;overflow-y:auto}
    .brand{font-weight:700;font-size:18px;margin-bottom:20px;color:#fbbf24}
    .nav a{display:block;color:#cbd5e1;text-decoration:none;padding:12px;border-radius:6px;margin-bottom:6px;transition:all 0.2s}
    .nav a:hover{background:rgba(255,255,255,0.08);color:#fff}
    .nav a.active{background:#1f2937;color:#fbbf24;border-left:3px solid #fbbf24;padding-left:9px}
    main{flex:1;padding:24px;overflow-y:auto}
    .header{display:flex;justify-content:space-between;align-items:center;margin-bottom:24px}
    .header h1{margin:0;font-size:24px;color:var(--accent)}
    .user-info{color:var(--muted);font-size:14px}
    .cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:16px;margin-bottom:24px}
    .card{background:var(--card);padding:16px;border-radius:10px;box-shadow:0 1px 3px rgba(0,0,0,0.06);border-left:4px solid #3b82f6}
    .card.danger{border-left-color:var(--danger)}
    .card.success{border-left-color:var(--success)}
    .card .label{color:var(--muted);font-size:12px;text-transform:uppercase;letter-spacing:0.5px}
    .card .value{font-size:24px;font-weight:700;margin-top:8px;color:var(--accent)}
    .section{background:var(--card);padding:16px;border-radius:10px;margin-bottom:20px;box-shadow:0 1px 3px rgba(0,0,0,0.06)}
    .section-title{font-size:16px;font-weight:600;margin-bottom:16px;color:var(--accent)}
    table{width:100%;border-collapse:collapse}
    th{text-align:left;padding:12px;border-bottom:2px solid #e5e7eb;color:var(--muted);font-size:12px;text-transform:uppercase;letter-spacing:0.5px;font-weight:600}
    td{padding:12px;border-bottom:1px solid #eef2f7;color:#374151}
    tr:hover{background:#f9fafb}
    .badge{display:inline-block;padding:4px 8px;border-radius:4px;font-size:11px;font-weight:600;text-transform:uppercase}
    .badge.superadmin{background:#fef3c7;color:#92400e}
    .badge.admin{background:#dbeafe;color:#1e3a8a}
    .badge.customer{background:#d1fae5;color:#065f46}
    .btn{display:inline-block;padding:8px 12px;border-radius:6px;border:none;cursor:pointer;font-size:13px;font-weight:500;transition:all 0.2s;text-decoration:none}
    .btn-primary{background:#3b82f6;color:#fff}
    .btn-primary:hover{background:#2563eb}
    .btn-danger{background:var(--danger);color:#fff}
    .btn-danger:hover{background:#b91c1c}
    .btn-small{padding:6px 10px;font-size:12px}
    .actions{display:flex;gap:6px}
    .empty-state{text-align:center;padding:40px;color:var(--muted)}
    @media (max-width:768px){.sidebar{width:100%;max-height:auto;position:relative}.layout{flex-direction:column}main{padding:16px}.cards{grid-template-columns:repeat(2,1fr)}}
    @media (max-width:480px){.cards{grid-template-columns:1fr}.sidebar{display:none}}
  </style>
</head>
<body>
  <div class="layout">
    <aside class="sidebar">
      <div class="brand">🔐 Trendify Superadmin</div>
      <nav class="nav">
        <a href="superadmin_dashboard.php" class="active">Dashboard</a>
        <a href="superadmin_orders.php">Orders</a>
        <a href="superadmin_users.php">Users & Admins</a>
        <a href="superadmin_reviews.php">Reviews</a>
        <a href="superadmin_system.php">System</a>
        <a href="logout.php">Sign Out</a>
      </nav>
    </aside>
    <main>
      <div class="header">
        <h1>Superadmin Dashboard</h1>
        <div class="user-info">Welcome back, <strong><?= htmlspecialchars($admin_name) ?></strong></div>
      </div>

      <section class="cards">
        <div class="card success">
          <div class="label">Total Sales</div>
          <div class="value">₱<?= number_format($total_sales, 2) ?></div>
        </div>
        <div class="card">
          <div class="label">Orders</div>
          <div class="value"><?= $total_orders ?></div>
        </div>
        <div class="card">
          <div class="label">Customers</div>
          <div class="value"><?= $total_customers ?></div>
        </div>
        <div class="card danger">
          <div class="label">Admins & Superadmins</div>
          <div class="value"><?= $total_admins ?></div>
        </div>
        <div class="card">
          <div class="label">Products</div>
          <div class="value"><?= $total_products ?></div>
        </div>
      </section>

      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(450px,1fr));gap:20px">
        <section class="section">
          <div class="section-title">Recent Orders</div>
          <?php if (count($recent_orders) > 0): ?>
          <form method="POST">
          <table>
            <thead>
              <tr>
                <th>Order ID</th>
                <th>Customer</th>
                <th>Amount</th>
                <th>Status</th>
                <th>Est. Delivery</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($recent_orders as $order): ?>
              <?php 
                $delivery_date = strtotime($order['estimated_delivery']);
                $today = strtotime(date('Y-m-d'));
                $is_past_due = $delivery_date < $today && strtolower($order['status']) !== 'delivered';
              ?>
              <tr>
                <td>#<?= htmlspecialchars($order['order_id']) ?></td>
                <td><?= htmlspecialchars($order['first_name'] . ' ' . $order['last_name']) ?></td>
                <td>₱<?= number_format($order['total_amount'], 2) ?></td>
                <td>
                  <?php
                    $status = htmlspecialchars($order['status'] ?? 'Processing');
                    $badge_styles = [
                      'Processing' => 'display:inline-block;padding:6px 10px;border-radius:4px;font-size:12px;font-weight:600;background:#fef3c7;color:#92400e',
                      'Shipped' => 'display:inline-block;padding:6px 10px;border-radius:4px;font-size:12px;font-weight:600;background:#dbeafe;color:#0284c7',
                      'Delivered' => 'display:inline-block;padding:6px 10px;border-radius:4px;font-size:12px;font-weight:600;background:#dcfce7;color:#166534',
                      'Cancelled' => 'display:inline-block;padding:6px 10px;border-radius:4px;font-size:12px;font-weight:600;background:#fee2e2;color:#dc2626'
                    ];
                    $style = $badge_styles[$status] ?? 'display:inline-block;padding:6px 10px;border-radius:4px;font-size:12px;font-weight:600;background:#f3f4f6;color:#374151';
                  ?>
                  <span style="<?= $style ?>"><?= $status ?></span>
                </td>
                <td><?= isset($order['estimated_delivery']) ? date('M d, Y', strtotime($order['estimated_delivery'])) : 'N/A' ?></td>
                <td>
                  <button type="submit" name="complete_order_id" value="<?= htmlspecialchars($order['order_id']) ?>" style="background:#10b981;color:#fff;border:none;padding:6px 10px;border-radius:4px;cursor:pointer;font-size:12px;margin-right:4px">Delivered</button>
                  <button type="submit" name="not_delivered_id" value="<?= htmlspecialchars($order['order_id']) ?>" style="background:#ef4444;color:#fff;border:none;padding:6px 10px;border-radius:4px;cursor:pointer;font-size:12px">Not Delivered</button>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
          </form>
          <?php else: ?>
          <div class="empty-state">No orders yet</div>
          <?php endif; ?>
        </section>

        <section class="section">
          <div class="section-title">System Admins</div>
          <?php if (count($admins_list) > 0): ?>
          <table>
            <thead>
              <tr>
                <th>Name</th>
                <th>Email</th>
                <th>Role</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($admins_list as $admin): ?>
              <tr>
                <td><?= htmlspecialchars($admin['first_name'] . ' ' . $admin['last_name']) ?></td>
                <td><?= htmlspecialchars($admin['email']) ?></td>
                <td><span class="badge <?= $admin['role'] === 'superadmin' ? 'superadmin' : 'admin' ?>"><?= htmlspecialchars($admin['role']) ?></span></td>
                <td>
                  <div class="actions">
                    <button class="btn btn-danger btn-small" onclick="deleteAdmin(<?= $admin['user_id'] ?>, '<?= htmlspecialchars($admin['email']) ?>')">Remove</button>
                  </div>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
          <?php else: ?>
          <div class="empty-state">No admins</div>
          <?php endif; ?>
        </section>
      </div>

      <section class="section" style="margin-top:20px">
        <div class="section-title">Recent Customers</div>
        <?php if (count($recent_users) > 0): ?>
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
            <?php foreach ($recent_users as $user): ?>
            <tr>
              <td><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></td>
              <td><?= htmlspecialchars($user['email']) ?></td>
              <td><?= date('M d, Y', strtotime($user['created_at'])) ?></td>
              <td>
                <div class="actions">
                  <button class="btn btn-danger btn-small" onclick="deleteUser(<?= $user['user_id'] ?>, '<?= htmlspecialchars($user['email']) ?>')">Delete</button>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <?php else: ?>
        <div class="empty-state">No customers</div>
        <?php endif; ?>
      </section>
    </main>
  </div>

  <script>
    function deleteAdmin(userId, email) {
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
