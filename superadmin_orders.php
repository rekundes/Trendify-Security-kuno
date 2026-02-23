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
    header('Location: superadmin_orders.php');
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
    header('Location: superadmin_orders.php');
    exit;
}

// Get all orders with filtering
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$where_clause = '';
if ($status_filter && in_array($status_filter, ['Processing', 'Shipped', 'Delivered', 'Cancelled'])) {
    $where_clause = " WHERE status = '" . $conn->real_escape_string($status_filter) . "'";
}

// Pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Get total orders and list using prepared statements
$total = 0;
$orders = [];
if ($where_clause !== '') {
  // status filter present
  $count_stmt = $conn->prepare("SELECT COUNT(*) as count FROM orders WHERE status = ?");
  $count_stmt->bind_param('s', $status_filter);
  $count_stmt->execute();
  $count_res = $count_stmt->get_result();
  $total = $count_res->fetch_assoc()['count'];
  $count_stmt->close();

  $orders_sql = "SELECT o.*, 
          COUNT(oi.item_id) as item_count,
          SUM(oi.quantity) as total_qty
           FROM orders o
           LEFT JOIN order_items oi ON o.order_id = oi.order_id
           WHERE o.status = ?
           GROUP BY o.order_id
           ORDER BY o.order_date DESC
           LIMIT ?, ?";
  $stmt = $conn->prepare($orders_sql);
  if ($stmt) {
    $stmt->bind_param('sii', $status_filter, $offset, $per_page);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $res->num_rows > 0) {
      while ($row = $res->fetch_assoc()) {
        $orders[] = $row;
      }
    }
    $stmt->close();
  }
} else {
  // no filter
  $count_stmt = $conn->prepare("SELECT COUNT(*) as count FROM orders");
  $count_stmt->execute();
  $count_res = $count_stmt->get_result();
  $total = $count_res->fetch_assoc()['count'];
  $count_stmt->close();

  $orders_sql = "SELECT o.*, 
          COUNT(oi.item_id) as item_count,
          SUM(oi.quantity) as total_qty
           FROM orders o
           LEFT JOIN order_items oi ON o.order_id = oi.order_id
           GROUP BY o.order_id
           ORDER BY o.order_date DESC
           LIMIT ?, ?";
  $stmt = $conn->prepare($orders_sql);
  if ($stmt) {
    $stmt->bind_param('ii', $offset, $per_page);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $res->num_rows > 0) {
      while ($row = $res->fetch_assoc()) {
        $orders[] = $row;
      }
    }
    $stmt->close();
  }
}

$total_pages = ceil($total / $per_page);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Orders Management - Superadmin</title>
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
    .badge.processing{background:#fef3c7;color:#92400e}
    .badge.shipped{background:#dbeafe;color:#1e3a8a}
    .badge.delivered{background:#d1fae5;color:#065f46}
    .badge.cancelled{background:#fee2e2;color:#991b1b}
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
        <a href="superadmin_orders.php" class="active">Orders</a>
        <a href="superadmin_users.php">Users & Admins</a>
        <a href="superadmin_system.php">System</a>
        <a href="logout.php">Sign Out</a>
      </nav>
    </aside>
    <main>
      <div class="header">
        <h1 style="margin:0;font-size:24px">Orders Management</h1>
        <div style="color:var(--muted);font-size:14px">Total Orders: <strong><?= $total ?></strong></div>
      </div>

      <div class="filters">
        <a href="superadmin_orders.php" class="<?= empty($status_filter) ? 'active' : '' ?>">All Orders</a>
        <a href="superadmin_orders.php?status=Processing" class="<?= $status_filter === 'Processing' ? 'active' : '' ?>">Processing</a>
        <a href="superadmin_orders.php?status=Shipped" class="<?= $status_filter === 'Shipped' ? 'active' : '' ?>">Shipped</a>
        <a href="superadmin_orders.php?status=Delivered" class="<?= $status_filter === 'Delivered' ? 'active' : '' ?>">Delivered</a>
        <a href="superadmin_orders.php?status=Cancelled" class="<?= $status_filter === 'Cancelled' ? 'active' : '' ?>">Cancelled</a>
      </div>

      <section class="section">
        <?php if (count($orders) > 0): ?>
        <form method="POST">
        <table>
          <thead>
            <tr>
              <th>Order ID</th>
              <th>Customer</th>
              <th>Email</th>
              <th>Items</th>
              <th>Amount</th>
              <th>Status</th>
              <th>Date</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($orders as $order): ?>
            <tr>
              <td>#<?= htmlspecialchars($order['order_id']) ?></td>
              <td><?= htmlspecialchars($order['first_name'] . ' ' . $order['last_name']) ?></td>
              <td><?= htmlspecialchars($order['email']) ?></td>
              <td><?= $order['item_count'] . ' item' . ($order['item_count'] != 1 ? 's' : '') ?></td>
              <td>₱<?= number_format($order['total_amount'], 2) ?></td>
              <td>
                <?php
                $status = htmlspecialchars($order['status']);
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
              <td><?= date('M d, Y', strtotime($order['order_date'])) ?></td>
              <td>
                <button type="submit" name="complete_order_id" value="<?= htmlspecialchars($order['order_id']) ?>" style="background:#10b981;color:#fff;border:none;padding:6px 10px;border-radius:4px;cursor:pointer;font-size:12px;margin-right:4px">Delivered</button>
                <button type="submit" name="not_delivered_id" value="<?= htmlspecialchars($order['order_id']) ?>" style="background:#ef4444;color:#fff;border:none;padding:6px 10px;border-radius:4px;cursor:pointer;font-size:12px">Not Delivered</button>
              </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
        </table>
        </form>

        <?php if ($total_pages > 1): ?>
        <div class="pagination">
          <?php if ($page > 1): ?>
            <a href="superadmin_orders.php?page=1<?= $status_filter ? '&status=' . urlencode($status_filter) : '' ?>">First</a>
            <a href="superadmin_orders.php?page=<?= $page - 1 ?><?= $status_filter ? '&status=' . urlencode($status_filter) : '' ?>">Prev</a>
          <?php endif; ?>
          
          <span><?= $page ?> / <?= $total_pages ?></span>
          
          <?php if ($page < $total_pages): ?>
            <a href="superadmin_orders.php?page=<?= $page + 1 ?><?= $status_filter ? '&status=' . urlencode($status_filter) : '' ?>">Next</a>
            <a href="superadmin_orders.php?page=<?= $total_pages ?><?= $status_filter ? '&status=' . urlencode($status_filter) : '' ?>">Last</a>
          <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php else: ?>
        <div class="empty">No orders found</div>
        <?php endif; ?>
      </section>
    </main>
  </div>
</body>
</html>
