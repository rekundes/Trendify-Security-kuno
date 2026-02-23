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
    header('Location: admin_dashboard.php');
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
    header('Location: admin_dashboard.php');
    exit;
}

// Get statistics from database using prepared statements
$total_sales = (function() { global $conn; $stmt = $conn->prepare("SELECT SUM(total_amount) as total FROM orders WHERE status = 'Delivered'"); $stmt->execute(); $res = $stmt->get_result(); $row = $res->fetch_assoc(); $stmt->close(); return $row['total'] ?? 0; })();
$total_orders = (function() { global $conn; $stmt = $conn->prepare("SELECT COUNT(*) as count FROM orders"); $stmt->execute(); $res = $stmt->get_result(); $row = $res->fetch_assoc(); $stmt->close(); return $row['count'] ?? 0; })();
$total_customers = (function() { global $conn; $stmt = $conn->prepare("SELECT COUNT(*) as count FROM users WHERE is_admin = 0"); $stmt->execute(); $res = $stmt->get_result(); $row = $res->fetch_assoc(); $stmt->close(); return $row['count'] ?? 0; })();
$total_products = 72;

// Get recent orders
$recent_orders = [];
$orders_sql = "SELECT o.order_id, o.order_date, o.status, o.total_amount, 
                      o.first_name, o.last_name,
                      DATE_ADD(o.order_date, INTERVAL 7 DAY) as estimated_delivery
               FROM orders o
               ORDER BY o.order_id DESC LIMIT 4";
$result = $conn->query($orders_sql);
if ($result && $result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $recent_orders[] = $row;
    }
}

// Get top products from order items
$top_products = [];
$products_sql = "SELECT product_name, SUM(quantity) as total_sold, AVG(price) as avg_price
                 FROM order_items
                 GROUP BY product_name
                 ORDER BY total_sold DESC
                 LIMIT 3";
$result = $conn->query($products_sql);
if ($result && $result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $top_products[] = $row;
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Admin Dashboard - Trendify Apparel</title>
  <link rel="stylesheet" href="styles.css">
  <style>
    :root{--bg:#f5f7fb;--card:#fff;--muted:#6b7280;--accent:#111827}
    body{font-family:Inter,system-ui,Segoe UI,Arial,Helvetica,sans-serif;background:var(--bg);margin:0}
    .layout{display:flex;min-height:100vh}
    .sidebar{width:240px;background:#0f1724;color:#fff;padding:20px;box-sizing:border-box}
    .brand{font-weight:700;font-size:18px;margin-bottom:18px}
    .nav a{display:block;color:#cbd5e1;text-decoration:none;padding:10px;border-radius:6px;margin-bottom:6px}
    .nav a:hover{background:rgba(255,255,255,0.06);color:#fff}
    main{flex:1;padding:24px}
    .header{display:flex;justify-content:space-between;align-items:center;margin-bottom:20px}
    .cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:16px;margin-bottom:20px}
    .card{background:var(--card);padding:14px;border-radius:10px;box-shadow:0 1px 2px rgba(0,0,0,0.04)}
    .card .value{font-size:20px;font-weight:700}
    .table{background:var(--card);padding:12px;border-radius:10px}
    table{width:100%;border-collapse:collapse}
    th,td{text-align:left;padding:8px;border-bottom:1px solid #eef2f7;color:#111827}
    th{font-size:13px;color:var(--muted)}
    .products{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;margin-top:12px}
    .prod{background:var(--card);padding:10px;border-radius:8px;display:flex;gap:10px;align-items:center}
    .prod img{width:56px;height:56px;object-fit:cover;border-radius:6px}
    @media (max-width:720px){.sidebar{display:none}.layout{flex-direction:column}main{padding:12px}}
  </style>
</head>
<body>
  <div class="layout">
    <aside class="sidebar">
      <div class="brand"><img src="img/logo.png" alt="Trendify logo" style="width:32px;height:32px;object-fit:contain;vertical-align:middle;margin-right:10px;border-radius:4px">Trendify Admin</div>
      <nav class="nav">
        <a href="admin_dashboard.php">Dashboard</a>
        <a href="admin_orders.php">Orders</a>
        <a href="admin_users.php">Users</a>
        <a href="admin_reviews.php">Reviews</a>
        <a href="logout.php">Sign Out</a>
      </nav>
    </aside>
    <main>
      <div class="header">
        <h1 style="margin:0;font-size:20px">Overview</h1>
        <div style="color:var(--muted)">Welcome back, <?= htmlspecialchars($admin_name) ?></div>
      </div>

      <section class="cards">
        <div class="card">
          <div style="color:var(--muted);font-size:12px">Total Sales</div>
          <div class="value">₱<?= number_format($total_sales, 2) ?></div>
        </div>
        <div class="card">
          <div style="color:var(--muted);font-size:12px">Orders</div>
          <div class="value"><?= $total_orders ?></div>
        </div>
        <div class="card">
          <div style="color:var(--muted);font-size:12px">Products</div>
          <div class="value"><?= $total_products ?></div>
        </div>
        <div class="card">
          <div style="color:var(--muted);font-size:12px">Customers</div>
          <div class="value"><?= $total_customers ?></div>
        </div>
      </section>

      <section class="table">
        <h2 style="margin:0 0 12px 0;font-size:16px">Recent Orders</h2>
        <form method="POST">
        <table>
          <thead>
            <tr><th>Order</th><th>Customer</th><th>Status</th><th>Total</th><th>Est. Delivery</th><th>Action</th></tr>
          </thead>
          <tbody>
            <?php if (count($recent_orders) > 0): ?>
              <?php foreach ($recent_orders as $order): ?>
                <tr>
                  <td>#<?= htmlspecialchars($order['order_id']) ?></td>
                  <td><?= htmlspecialchars(($order['first_name'] ?? '') . ' ' . ($order['last_name'] ?? '')) ?></td>
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
                  <td>₱<?= number_format($order['total_amount'] ?? 0, 2) ?></td>
                  <td><?= isset($order['estimated_delivery']) ? date('M d, Y', strtotime($order['estimated_delivery'])) : 'N/A' ?></td>
                  <td>
                    <button type="submit" name="complete_order_id" value="<?= htmlspecialchars($order['order_id']) ?>" style="background:#10b981;color:#fff;border:none;padding:6px 10px;border-radius:4px;cursor:pointer;font-size:12px;margin-right:4px">Delivered</button>
                    <button type="submit" name="not_delivered_id" value="<?= htmlspecialchars($order['order_id']) ?>" style="background:#ef4444;color:#fff;border:none;padding:6px 10px;border-radius:4px;cursor:pointer;font-size:12px">Not Delivered</button>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr><td colspan="6" style="text-align:center;color:var(--muted)">No orders yet</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
        </form>
      </section>

      <section style="margin-top:18px">
        <h2 style="margin:0 0 12px 0;font-size:16px">Top Products</h2>
        <div class="products">
          <?php if (count($top_products) > 0): ?>
            <?php foreach ($top_products as $product): ?>
              <div class="prod">
                <img src="img/img/placeholder.jpg" alt="product" style="width:56px;height:56px;background:#f0f0f0">
                <div>
                  <div style="font-weight:600"><?= htmlspecialchars($product['product_name']) ?></div>
                  <div style="color:var(--muted);font-size:13px">₱<?= number_format($product['avg_price'] ?? 0, 2) ?> • <?= (int)$product['total_sold'] ?> sold</div>
                </div>
              </div>
            <?php endforeach; ?>
          <?php else: ?>
            <p style="color:var(--muted);font-size:13px">No products sold yet</p>
          <?php endif; ?>
        </div>
      </section>

    </main>
  </div>
</body>
</html>
<?php
$conn->close();
?>
