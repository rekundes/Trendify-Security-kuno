<?php
require_once 'config.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || $_SESSION['is_admin'] != 1) {
    header('Location: sign-in.html');
    exit;
}

$admin_name = ($_SESSION['first_name'] ?? 'Admin') . ' ' . ($_SESSION['last_name'] ?? '');

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
    header('Location: admin_orders.php');
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
    header('Location: admin_orders.php');
    exit;
}

// Get all orders with user info
$sql = "SELECT o.order_id, o.user_id, o.order_date, o.status, o.total_amount, 
               u.first_name, u.last_name, u.email
        FROM orders o
        LEFT JOIN users u ON o.user_id = u.user_id
        ORDER BY o.order_date DESC";
$result = $conn->query($sql);
if (!$result) {
    // If query fails, it might be due to missing column. Try without order_date
    $sql = "SELECT o.order_id, o.user_id, CURRENT_TIMESTAMP as order_date, o.status, o.total_amount, 
                   u.first_name, u.last_name, u.email
            FROM orders o
            LEFT JOIN users u ON o.user_id = u.user_id
            ORDER BY o.order_id DESC";
    $result = $conn->query($sql);
}
$orders = [];
if ($result && $result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $orders[] = $row;
    }
}

// Calculate stats
$total_revenue = 0;
$stats = ['Processing' => 0, 'Shipped' => 0, 'Delivered' => 0, 'Cancelled' => 0];
foreach ($orders as $order) {
    $total_revenue += $order['total_amount'];
    if (isset($stats[$order['status']])) {
        $stats[$order['status']]++;
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Orders Management - Trendify Admin</title>
  <link rel="stylesheet" href="styles.css">
  <style>
    :root{--bg:#f5f7fb;--card:#fff;--muted:#6b7280;--accent:#111827}
    body{font-family:Inter,system-ui,Segoe UI,Arial,Helvetica,sans-serif;background:var(--bg);margin:0}
    .layout{display:flex;min-height:100vh}
    .sidebar{width:240px;background:#0f1724;color:#fff;padding:20px;box-sizing:border-box}
    .brand{font-weight:700;font-size:18px;margin-bottom:18px}
    .nav a{display:block;color:#cbd5e1;text-decoration:none;padding:10px;border-radius:6px;margin-bottom:6px;transition:all 0.2s}
    .nav a:hover{background:rgba(255,255,255,0.06);color:#fff}
    .nav a.active{background:rgba(59,130,246,0.2);color:#3b82f6}
    main{flex:1;padding:24px}
    .header{display:flex;justify-content:space-between;align-items:center;margin-bottom:20px}
    .table-container{background:var(--card);padding:20px;border-radius:10px;overflow-x:auto}
    table{width:100%;border-collapse:collapse}
    th,td{text-align:left;padding:12px;border-bottom:1px solid #eef2f7;color:#111827}
    th{font-size:13px;color:var(--muted);background:#f9fafb;font-weight:600}
    tr:hover{background:#f9fafb}
    .badge{display:inline-block;padding:6px 10px;border-radius:4px;font-size:12px;font-weight:600}
    .badge-processing{background:#fef3c7;color:#92400e}
    .badge-shipped{background:#dbeafe;color:#0284c7}
    .badge-delivered{background:#dcfce7;color:#166534}
    .badge-cancelled{background:#fee2e2;color:#dc2626}
    .stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:16px;margin-bottom:20px}
    .stat-card{background:var(--card);padding:16px;border-radius:10px;box-shadow:0 1px 2px rgba(0,0,0,0.04)}
    .stat-value{font-size:24px;font-weight:700;color:#111827}
    .stat-label{font-size:13px;color:var(--muted);margin-top:8px}
    .select{padding:8px;border:1px solid #e5e7eb;border-radius:6px;font-size:14px}
    @media (max-width:720px){.sidebar{display:none}.layout{flex-direction:column}main{padding:12px}}
  </style>
</head>
<body>
  <div class="layout">
    <aside class="sidebar">
      <div class="brand"><img src="img/logo.png" alt="Trendify logo" style="width:32px;height:32px;object-fit:contain;vertical-align:middle;margin-right:10px;border-radius:4px">Trendify Admin</div>
      <nav class="nav">
        <a href="admin_dashboard.php">Dashboard</a>
        <a href="admin_orders.php" class="active">Orders</a>
        <a href="admin_users.php">Users</a>
        <a href="logout.php">Sign Out</a>
      </nav>
    </aside>
    <main>
      <div class="header">
        <h1 style="margin:0;font-size:24px">Orders Management</h1>
        <div style="color:var(--muted)">Welcome back, <?= htmlspecialchars($admin_name) ?></div>
      </div>

      <div class="stats">
        <div class="stat-card">
          <div class="stat-value">₱<?= number_format($total_revenue, 2) ?></div>
          <div class="stat-label">Total Revenue</div>
        </div>
        <div class="stat-card">
          <div class="stat-value"><?= count($orders) ?></div>
          <div class="stat-label">Total Orders</div>
        </div>
        <div class="stat-card">
          <div class="stat-value"><?= $stats['Delivered'] ?></div>
          <div class="stat-label">Delivered</div>
        </div>
        <div class="stat-card">
          <div class="stat-value"><?= $stats['Processing'] ?></div>
          <div class="stat-label">Processing</div>
        </div>
      </div>

      <div class="table-container">
        <h2 style="margin:0 0 16px 0;font-size:18px">All Orders</h2>
        <?php if (count($orders) > 0): ?>
          <form method="POST">
          <table>
            <thead>
              <tr>
                <th>Order ID</th>
                <th>Customer Name</th>
                <th>Email</th>
                <th>Amount</th>
                <th>Status</th>
                <th>Order Date</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($orders as $order): ?>
                <tr>
                  <td>#<?= htmlspecialchars($order['order_id']) ?></td>
                  <td><?= htmlspecialchars(($order['first_name'] ?? '') . ' ' . ($order['last_name'] ?? '')) ?></td>
                  <td><?= htmlspecialchars($order['email'] ?? 'N/A') ?></td>
                  <td>₱<?= number_format($order['total_amount'] ?? 0, 2) ?></td>
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
                  <td><?= date('M d, Y H:i', strtotime($order['order_date'] ?? 'now')) ?></td>
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
          <p style="text-align:center;color:var(--muted);padding:20px">No orders found</p>
        <?php endif; ?>
      </div>
    </main>
  </div>
  <script>
    function deleteOrder(orderId) {
      if (confirm('Are you sure you want to delete order #' + orderId + '? This cannot be undone.')) {
        fetch('delete_order.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          credentials: 'include',
          body: JSON.stringify({ order_id: orderId })
        })
        .then(res => res.json())
        .then(data => {
          if (data.success) {
            alert('Order deleted successfully');
            location.reload();
          } else {
            alert('Error deleting order: ' + data.message);
          }
        })
        .catch(err => {
          alert('Error: ' + err.message);
        });
      }
    }
  </script>
</body>
</html>
<?php
$conn->close();
?>
