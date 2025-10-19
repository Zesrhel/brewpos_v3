<?php
require_once 'db.php';
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }

// Handle delete actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
  if ($_POST['action'] === 'delete_sales') {
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
  } elseif ($_POST['action'] === 'delete_gcash') {
    $orderId = intval($_POST['order_id'] ?? 0);
    if ($orderId > 0) {
      try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("DELETE FROM product_sales WHERE order_id = ?");
        $stmt->execute([$orderId]);
        $stmt = $pdo->prepare("DELETE FROM orders WHERE id = ?");
        $stmt->execute([$orderId]);
        $pdo->commit();
      } catch (Exception $e) {
        $pdo->rollBack();
      }
      header('Location: sales.php');
      exit;
    }
  }
}

// Date filter handling
$date_from = $_GET['date_from'] ?? date('Y-m-d');
$date_to = $_GET['date_to'] ?? date('Y-m-d');
$payment_filter = $_GET['payment_type'] ?? 'all';

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

// Build filtered query with payment type breakdown
$where_conditions = [];
$params = [];

if ($date_from && $date_to) {
  $where_conditions[] = "DATE(o.created_at) BETWEEN ? AND ?";
  $params[] = $date_from;
  $params[] = $date_to;
}

if ($payment_filter !== 'all') {
  $where_conditions[] = "o.payment_type = ?";
  $params[] = $payment_filter;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Fetch filtered sales with payment breakdown
$stmt = $pdo->prepare("
  SELECT 
    ps.product_id, 
    ps.product_name, 
    SUM(ps.quantity) as qty, 
    SUM(ps.total_amount) as amount,
    SUM(CASE WHEN o.payment_type = 'cash' THEN ps.total_amount ELSE 0 END) as cash_amount,
    SUM(CASE WHEN o.payment_type = 'gcash' THEN ps.total_amount ELSE 0 END) as gcash_amount,
    SUM(CASE WHEN o.payment_type = 'cash' THEN ps.quantity ELSE 0 END) as cash_qty,
    SUM(CASE WHEN o.payment_type = 'gcash' THEN ps.quantity ELSE 0 END) as gcash_qty
  FROM product_sales ps
  JOIN orders o ON ps.order_id = o.id
  $where_clause
  GROUP BY ps.product_id, ps.product_name
  ORDER BY amount DESC
");
$stmt->execute($params);
$filteredRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
      background: #28a745;
      color: white;
      border-radius: 12px;
      padding: 25px 30px;
      margin-bottom: 25px;
      box-shadow: 0 2px 8px rgba(40,167,69,0.2);
      border: none;
    }
    .card {
      border-radius: 12px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.08);
      border: 1px solid #e9ecef;
      margin-bottom: 20px;
      background: #ffffff;
    }
    .card-header {
      background-color: #ffffff;
      color: #333;
      border-bottom: 2px solid #28a745;
      border-radius: 12px 12px 0 0 !important;
      padding: 18px 24px;
      font-weight: 700;
      font-size: 1.1rem;
    }
    .card-body {
      padding: 24px;
    }
    .table {
      margin-bottom: 0;
    }
    .table th {
      background-color: #f8f9fa;
      border-top: none;
      border-bottom: 2px solid #dee2e6;
      font-weight: 600;
      color: #495057;
      padding: 12px;
      text-transform: uppercase;
      font-size: 0.85rem;
      letter-spacing: 0.5px;
    }
    .table td {
      padding: 14px 12px;
      vertical-align: middle;
    }
    .table tbody tr:hover {
      background-color: #f8f9fa;
    }
    .table tfoot th {
      background-color: #e9ecef;
      font-weight: 700;
      border-top: 2px solid #dee2e6;
      padding: 14px 12px;
    }
    .btn-primary {
      background: #28a745;
      border: none;
      border-radius: 8px;
      padding: 10px 24px;
      font-weight: 600;
      transition: all 0.2s ease;
    }
    .btn-primary:hover {
      background: #218838;
      transform: translateY(-1px);
      box-shadow: 0 4px 12px rgba(40,167,69,0.3);
    }
    .btn-light {
      border-radius: 8px;
      padding: 10px 20px;
      font-weight: 600;
      border: 2px solid #ffffff;
    }
    .btn-light:hover {
      background: #ffffff;
      border-color: #ffffff;
    }
    .form-control, .form-select {
      border-radius: 8px;
      border: 1px solid #ced4da;
      padding: 10px 14px;
    }
    .form-control:focus, .form-select:focus {
      border-color: #28a745;
      box-shadow: 0 0 0 0.2rem rgba(40,167,69,0.15);
    }
    .form-label {
      font-weight: 600;
      color: #495057;
      margin-bottom: 8px;
      font-size: 0.9rem;
    }
  </style>
  </head>
<body>
<div class="container my-4">
  <div class="header-container">
    <div class="d-flex justify-content-between align-items-center">
      <div>
        <h2 class="mb-0"><i class="fas fa-chart-line me-2"></i>Sales Report</h2>
        <p class="mb-0 mt-2">Filter and view sales transactions</p>
      </div>
      <div>
        <a href="index.php" class="btn btn-light"><i class="fas fa-arrow-left me-1"></i> Back to POS</a>
      </div>
    </div>
  </div>

  <!-- Filter Form -->
  <div class="card mb-3">
    <div class="card-body">
      <form method="get" class="row g-3">
        <div class="col-md-3">
          <label class="form-label">Date From</label>
          <input type="date" name="date_from" class="form-control" value="<?php echo htmlspecialchars($date_from); ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">Date To</label>
          <input type="date" name="date_to" class="form-control" value="<?php echo htmlspecialchars($date_to); ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">Payment Type</label>
          <select name="payment_type" class="form-select">
            <option value="all" <?php echo $payment_filter === 'all' ? 'selected' : ''; ?>>All</option>
            <option value="cash" <?php echo $payment_filter === 'cash' ? 'selected' : ''; ?>>Cash</option>
            <option value="gcash" <?php echo $payment_filter === 'gcash' ? 'selected' : ''; ?>>GCash</option>
          </select>
        </div>
        <div class="col-md-3 d-flex align-items-end">
          <button type="submit" class="btn btn-primary w-100"><i class="fas fa-filter me-1"></i> Filter</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Filtered Results -->
  <div class="card mb-3">
    <div class="card-header">
      <i class="fas fa-filter me-2"></i>Filtered Sales Report (<?php echo date('M d, Y', strtotime($date_from)); ?> - <?php echo date('M d, Y', strtotime($date_to)); ?>)
    </div>
    <div class="card-body">
      <div class="table-responsive">
        <table class="table table-hover align-middle">
          <thead>
            <tr>
              <th>Product</th>
              <th class="text-end">Total Qty</th>
              <th class="text-end">Cash Qty</th>
              <th class="text-end">GCash Qty</th>
              <th class="text-end">Total Amount</th>
              <th class="text-end">Cash Amount</th>
              <th class="text-end">GCash Amount</th>
            </tr>
          </thead>
          <tbody>
            <?php 
            $totalAmount = 0;
            $totalCash = 0;
            $totalGCash = 0;
            foreach ($filteredRows as $r): 
              $totalAmount += $r['amount'];
              $totalCash += $r['cash_amount'];
              $totalGCash += $r['gcash_amount'];
            ?>
            <tr>
              <td><?php echo htmlspecialchars($r['product_name']); ?></td>
              <td class="text-end"><?php echo (int)$r['qty']; ?></td>
              <td class="text-end"><?php echo (int)$r['cash_qty']; ?></td>
              <td class="text-end"><?php echo (int)$r['gcash_qty']; ?></td>
              <td class="text-end"><?php echo peso($r['amount']); ?></td>
              <td class="text-end"><?php echo peso($r['cash_amount']); ?></td>
              <td class="text-end"><?php echo peso($r['gcash_amount']); ?></td>
            </tr>
            <?php endforeach; if (empty($filteredRows)): ?>
            <tr><td colspan="7" class="text-center text-muted">No sales found for selected criteria</td></tr>
            <?php endif; ?>
          </tbody>
          <tfoot>
            <tr>
              <th>Total</th>
              <th></th>
              <th></th>
              <th></th>
              <th class="text-end"><?php echo peso($totalAmount); ?></th>
              <th class="text-end"><?php echo peso($totalCash); ?></th>
              <th class="text-end"><?php echo peso($totalGCash); ?></th>
            </tr>
            <tr>
              <th colspan="4" class="text-end">Tax Amount (12% included):</th>
              <th class="text-end"><?php echo peso($totalAmount - ($totalAmount / 1.12)); ?></th>
              <th class="text-end"><?php echo peso($totalCash - ($totalCash / 1.12)); ?></th>
              <th class="text-end"><?php echo peso($totalGCash - ($totalGCash / 1.12)); ?></th>
            </tr>
          </tfoot>
        </table>
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
              <?php 
              $dailyTotal = 0;
              foreach ($dailyRows as $r): 
                $dailyTotal += $r['amount'];
              ?>
              <tr>
                <td><?php echo htmlspecialchars($r['product_name']); ?></td>
                <td class="text-end"><?php echo (int)$r['qty']; ?></td>
                <td class="text-end"><?php echo peso($r['amount']); ?></td>
              </tr>
              <?php endforeach; if (empty($dailyRows)): ?>
              <tr><td colspan="3" class="text-center text-muted">No sales today</td></tr>
              <?php endif; ?>
            </tbody>
            <tfoot>
              <tr>
                <th>Total</th>
                <th></th>
                <th class="text-end"><?php echo peso($dailyTotal); ?></th>
              </tr>
              <tr>
                <th colspan="2" class="text-end">Tax Amount (12% included):</th>
                <th class="text-end"><?php echo peso($dailyTotal - ($dailyTotal / 1.12)); ?></th>
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
              <tr>
                <th colspan="2" class="text-end">Tax Amount (12% included):</th>
                <th class="text-end"><?php echo peso($totalWeek - ($totalWeek / 1.12)); ?></th>
              </tr>
            </tfoot>
          </table>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- GCash Transactions -->
  <?php
  // Fetch GCash transactions with reference numbers
  $gcash_stmt = $pdo->query("
    SELECT o.id as order_id, o.order_number, o.gcash_ref, o.total, o.created_at, u.name as cashier_name
    FROM orders o
    LEFT JOIN users u ON o.cashier_id = u.id
    WHERE o.payment_type = 'gcash' AND o.gcash_ref IS NOT NULL
    ORDER BY o.created_at DESC
    LIMIT 50
  ");
  $gcash_transactions = $gcash_stmt->fetchAll(PDO::FETCH_ASSOC);
  ?>

  <?php if (!empty($gcash_transactions)): ?>
  <div class="card mt-4">
    <div class="card-header">
      <i class="fas fa-mobile-alt me-2"></i>Recent GCash Transactions
    </div>
    <div class="card-body">
      <div class="table-responsive">
        <table class="table table-hover align-middle">
          <thead>
            <tr>
              <th>Order Number</th>
              <th>GCash Reference</th>
              <th class="text-end">Amount</th>
              <th>Cashier</th>
              <th>Date/Time</th>
              <th class="text-center">Action</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($gcash_transactions as $txn): ?>
            <tr>
              <td><strong><?php echo htmlspecialchars($txn['order_number']); ?></strong></td>
              <td><code><?php echo htmlspecialchars($txn['gcash_ref']); ?></code></td>
              <td class="text-end"><?php echo peso($txn['total']); ?></td>
              <td><?php echo htmlspecialchars($txn['cashier_name'] ?? 'N/A'); ?></td>
              <td><?php echo date('M d, Y h:i A', strtotime($txn['created_at'])); ?></td>
              <td class="text-center">
                <form method="post" onsubmit="return confirm('Delete this GCash transaction? This will remove the order and related sales data.');" class="d-inline">
                  <input type="hidden" name="action" value="delete_gcash" />
                  <input type="hidden" name="order_id" value="<?php echo (int)$txn['order_id']; ?>" />
                  <button class="btn btn-sm btn-outline-danger">
                    <i class="fas fa-trash"></i> Delete
                  </button>
                </form>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>