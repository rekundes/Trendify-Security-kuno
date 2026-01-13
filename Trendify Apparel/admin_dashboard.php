<?php
// Admin dashboard converted from static HTML to PHP to use the PHP peso symbol
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
        <a href="main.html">Dashboard</a>
        <a href="women.html">Women Store</a>
        <a href="men.html">Men Store</a>
        <a href="cart.html">Orders</a>
        <a href="create_acc.html">Users</a>
        <a href="sign-in.html">Sign In</a>
      </nav>
    </aside>
    <main>
      <div class="header">
        <h1 style="margin:0;font-size:20px">Overview</h1>
        <div style="color:var(--muted)">Welcome back, Admin</div>
      </div>

      <section class="cards">
        <div class="card">
          <div style="color:var(--muted);font-size:12px">Total Sales</div>
          <div class="value"><?= '₱' ?>12,430</div>
        </div>
        <div class="card">
          <div style="color:var(--muted);font-size:12px">Orders</div>
          <div class="value">1,024</div>
        </div>
        <div class="card">
          <div style="color:var(--muted);font-size:12px">Products</div>
          <div class="value">214</div>
        </div>
        <div class="card">
          <div style="color:var(--muted);font-size:12px">Customers</div>
          <div class="value">3,820</div>
        </div>
      </section>

      <section class="table">
        <h2 style="margin:0 0 12px 0;font-size:16px">Recent Orders</h2>
        <table>
          <thead>
            <tr><th>Order</th><th>Customer</th><th>Status</th><th>Total</th></tr>
          </thead>
          <tbody>
            <tr><td>#1024</td><td>Sarah J.</td><td>Shipped</td><td><?= '₱' ?>89.00</td></tr>
            <tr><td>#1023</td><td>Mark D.</td><td>Processing</td><td><?= '₱' ?>45.50</td></tr>
            <tr><td>#1022</td><td>Lina W.</td><td>Delivered</td><td><?= '₱' ?>120.00</td></tr>
            <tr><td>#1021</td><td>Tom R.</td><td>Cancelled</td><td><?= '₱' ?>0.00</td></tr>
          </tbody>
        </table>
      </section>

      <section style="margin-top:18px">
        <h2 style="margin:0 0 12px 0;font-size:16px">Top Products</h2>
        <div class="products">
          <div class="prod"><img src="img/img/img/men-shirt6.jpg" alt="p"><div><div style="font-weight:600">porche 911</div><div style="color:var(--muted);font-size:13px"><?= '₱' ?>24.00 • 120 sold</div></div></div>
          <div class="prod"><img src="img/img/women-top3.jpg" alt="p"><div><div style="font-weight:600">black top</div><div style="color:var(--muted);font-size:13px"><?= '₱' ?>48.00 • 90 sold</div></div></div>
          <div class="prod"><img src="img/img/img/men-shoes1.jpg" alt="p"><div><div style="font-weight:600">Nike Air Jordan 1 Retro High OG</div><div style="color:var(--muted);font-size:13px"><?= '₱' ?>65.00 • 75 sold</div></div></div>
        </div>
      </section>

    </main>
  </div>
</body>
</html>
