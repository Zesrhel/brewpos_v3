<?php
require_once 'db.php';
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }

// Handle delete actions (remove sales entries for a product within a time scope)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_sales') {
  $productId = intval($_POST['product_id'] ?? 0);
  $scope = $_POST['scope'] ?? '';
  if ($productId > 0 && in_array($scope, ['day','week'], true)) {
    if ($scope === 'day') {
      $stmt = $pdo->prepare("DELETE FROM product_sales WHERE product_id = ? AND DATE(created_at) = CURDATE()");
      $stmt->execute([$productId]);
    } else {
      $stmt = $pdo->prepare("DELETE FROM product_sales WHERE product_id = ? AND YEARWEEK(created_at, 1) = YEARWEEK(CURDATE(), 1)");
      $stmt->execute([$productId]);
    }
    header('Location: sales.php');
    exit;
  }
}

// Time ranges
$todayStart = date('Y-m-d 00:00:00');
$todayEnd   = date('Y-m-d 23:59:59');

// ISO week (Mon-Sun) range for current week
$monday = new DateTime();
$monday->setTime(0,0,0);
if ((int)$monday->format('N') !== 1) {
  $monday->modify('last monday');
}
$sunday = clone $monday; $sunday->modify('sunday'); $sunday->setTime(23,59,59);

// Ensure product_sales table exists (in case API hasn't created it yet)
try {
  $pdo->exec("CREATE TABLE IF NOT EXISTS product_sales (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NULL,
    product_id INT NOT NULL,
    product_name VARCHAR(255) NOT NULL,
    quantity INT NOT NULL DEFAULT 0,
    total_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_created_at (created_at),
    INDEX idx_product_id (product_id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
} catch (Exception $e) {
  // ignore
}

// Fetch daily sales per product (use MySQL DATE to avoid PHP/MySQL timezone mismatch)
$daily = $pdo->query("SELECT product_id, product_name, SUM(quantity) as qty, SUM(total_amount) as amount
                        FROM product_sales
                        WHERE DATE(created_at) = CURDATE()
                        GROUP BY product_id, product_name
                        ORDER BY amount DESC");
$dailyRows = $daily->fetchAll(PDO::FETCH_ASSOC);

// Fetch weekly sales per product (ISO week starts Monday, mode=1)
$weekly = $pdo->query("SELECT product_id, product_name, SUM(quantity) as qty, SUM(total_amount) as amount
                         FROM product_sales
                         WHERE YEARWEEK(created_at, 1) = YEARWEEK(CURDATE(), 1)
                         GROUP BY product_id, product_name
                         ORDER BY amount DESC");
$weeklyRows = $weekly->fetchAll(PDO::FETCH_ASSOC);

function peso($n){ return '₱' . number_format((float)$n, 2); }
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Sales — BrewPOS</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    body {
      background-color: #f8f9fa;
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      padding: 20px;
    }
    .header-container {
      background: linear-gradient(135deg, #6f42c1 0%, #d63384 100%);
      color: white;
      border-radius: 10px;
      padding: 20px;
      margin-bottom: 25px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }
    .card {
      border-radius: 12px;
      box-shadow: 0 6px 15px rgba(0,0,0,0.08);
      border: none;
      margin-bottom: 20px;
    }
    .card-header {
      background-color: #6f42c1;
      color: white;
      border-radius: 12px 12px 0 0 !important;
      padding: 15px 20px;
      font-weight: 600;
    }
    .table th {
      background-color: #e9ecef;
      border-top: none;
      font-weight: 600;
      color: #495057;
    }
    .btn-primary {
      background: linear-gradient(to right, #6f42c1, #d63384);
      border: none;
      border-radius: 8px;
      padding: 10px 20px;
      font-weight: 600;
    }
    .btn-primary:hover {
      background: linear-gradient(to right, #5a32a3, #b52a6f);
      transform: translateY(-2px);
      box-shadow: 0 4px 8px rgba(0,0,0,0.15);
    }
    .btn-outline-secondary {
      border-radius: 8px;
      padding: 10px 20px;
      font-weight: 600;
    }
  </style>
  </head>
<body>
<div class="container my-4">
  <div class="header-container">
    <div class="d-flex justify-content-between align-items-center">
      <div>
        <h2 class="mb-0"><i class="fas fa-chart-line me-2"></i>Sales Report</h2>
        <p class="mb-0 mt-2">Daily and Weekly product totals</p>
      </div>
      <div>
        <a href="index.php" class="btn btn-light"><i class="fas fa-arrow-left me-1"></i> Back to POS</a>
      </div>
    </div>
  </div>

  <div class="row">
    <div class="col-lg-6">
      <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
          <span><i class="fas fa-calendar-day me-2"></i>Today</span>
          <small class="text-white-50"><?php echo date('M d, Y'); ?></small>
        </div>
        <div class="card-body">
          <div class="table-responsive">
          <table class="table table-hover align-middle">
            <thead>
              <tr>
                <th>Product</th>
                <th class="text-end">Qty</th>
                <th class="text-end">Amount</th>
              </tr>
            </thead>
            <tbody>
              <?php $totalToday = 0; foreach ($dailyRows as $r): $totalToday += $r['amount']; ?>
              <tr>
                <td><?php echo htmlspecialchars($r['product_name']); ?></td>
                <td class="text-end"><?php echo (int)$r['qty']; ?></td>
                <td class="text-end">
                  <?php echo peso($r['amount']); ?>
                  <form method="post" class="d-inline ms-2" onsubmit="return confirm('Delete today\'s sales for this product?');">
                    <input type="hidden" name="action" value="delete_sales" />
                    <input type="hidden" name="product_id" value="<?php echo (int)$r['product_id']; ?>" />
                    <input type="hidden" name="scope" value="day" />
                    <button class="btn btn-sm btn-outline-danger">Delete</button>
                  </form>
                </td>
              </tr>
              <?php endforeach; if (empty($dailyRows)): ?>
              <tr><td colspan="3" class="text-center text-muted">No sales today</td></tr>
              <?php endif; ?>
            </tbody>
            <tfoot>
              <tr>
                <th>Total</th>
                <th></th>
                <th class="text-end"><?php echo peso($totalToday); ?></th>
              </tr>
            </tfoot>
          </table>
          </div>
        </div>
      </div>
    </div>

    <div class="col-lg-6">
      <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
          <span><i class="fas fa-calendar-week me-2"></i>This Week</span>
          <small class="text-white-50"><?php echo $monday->format('M d') . ' - ' . $sunday->format('M d, Y'); ?></small>
        </div>
        <div class="card-body">
          <div class="table-responsive">
          <table class="table table-hover align-middle">
            <thead>
              <tr>
                <th>Product</th>
                <th class="text-end">Qty</th>
                <th class="text-end">Amount</th>
              </tr>
            </thead>
            <tbody>
              <?php $totalWeek = 0; foreach ($weeklyRows as $r): $totalWeek += $r['amount']; ?>
              <tr>
                <td><?php echo htmlspecialchars($r['product_name']); ?></td>
                <td class="text-end"><?php echo (int)$r['qty']; ?></td>
                <td class="text-end">
                  <?php echo peso($r['amount']); ?>
                  <form method="post" class="d-inline ms-2" onsubmit="return confirm('Delete this week\'s sales for this product?');">
                    <input type="hidden" name="action" value="delete_sales" />
                    <input type="hidden" name="product_id" value="<?php echo (int)$r['product_id']; ?>" />
                    <input type="hidden" name="scope" value="week" />
                    <button class="btn btn-sm btn-outline-danger">Delete</button>
                  </form>
                </td>
              </tr>
              <?php endforeach; if (empty($weeklyRows)): ?>
              <tr><td colspan="3" class="text-center text-muted">No sales this week</td></tr>
              <?php endif; ?>
            </tbody>
            <tfoot>
              <tr>
                <th>Total</th>
                <th></th>
                <th class="text-end"><?php echo peso($totalWeek); ?></th>
              </tr>
            </tfoot>
          </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>