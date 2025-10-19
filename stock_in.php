<?php
require_once 'db.php';
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }

function ensureStockInLogTable(PDO $pdo): void {
  $existsStmt = $pdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'stock_in_log'");
  if ($existsStmt && (int)$existsStmt->fetchColumn() === 1) {
    return;
  }

  $createSql = "CREATE TABLE stock_in_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    product_name VARCHAR(255) NOT NULL,
    quantity_added INT NOT NULL,
    previous_quantity INT NOT NULL,
    new_quantity INT NOT NULL,
    user_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_product_id (product_id),
    INDEX idx_created_at (created_at)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

  try {
    $pdo->exec($createSql);
  } catch (PDOException $e) {
    $errorCode = $e->errorInfo[1] ?? null;

    if ($errorCode === 1813) {
      // Tablespace file exists. Attempt to remove leftover files then recreate table.
      try {
        $dataDir = rtrim($pdo->query("SELECT @@datadir")->fetchColumn(), "\\/ ");
        $schema = $pdo->query("SELECT DATABASE()")->fetchColumn();
        if ($dataDir && $schema) {
          $basePath = $dataDir . DIRECTORY_SEPARATOR . $schema;
          $ibdPath = $basePath . DIRECTORY_SEPARATOR . 'stock_in_log.ibd';
          $cfgPath = $basePath . DIRECTORY_SEPARATOR . 'stock_in_log.cfg';
          if (is_file($ibdPath)) {@unlink($ibdPath);} 
          if (is_file($cfgPath)) {@unlink($cfgPath);} 
        }
      } catch (Throwable $fileEx) {
        throw new PDOException('Unable to remove existing tablespace files: ' . $fileEx->getMessage(), (int)($e->getCode()), $e);
      }

      // Second attempt after cleanup
      $pdo->exec($createSql);
    } else {
      throw $e;
    }
  }
}

try {
  ensureStockInLogTable($pdo);
} catch (Exception $e) {
  $_SESSION['error'] = 'Failed to initialize stock log table: ' . $e->getMessage();
}

// Handle stock-in submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $product_id = intval($_POST['product_id'] ?? 0);
  $quantity_to_add = intval($_POST['quantity'] ?? 0);
  
  if ($product_id > 0 && $quantity_to_add > 0) {
    try {
      $pdo->beginTransaction();
      
      // Get current stock
      $stmt = $pdo->prepare("SELECT p.name, COALESCE(i.quantity, 0) as current_qty 
                             FROM products p 
                             LEFT JOIN inventory i ON p.id = i.product_id 
                             WHERE p.id = ?");
      $stmt->execute([$product_id]);
      $product = $stmt->fetch(PDO::FETCH_ASSOC);
      
      if ($product) {
        $previous_qty = $product['current_qty'];
        $new_qty = $previous_qty + $quantity_to_add;
        
        // Update inventory
        $check = $pdo->prepare('SELECT COUNT(*) FROM inventory WHERE product_id = ?');
        $check->execute([$product_id]);
        $exists = $check->fetchColumn();
        
        if ($exists) {
          $pdo->prepare('UPDATE inventory SET quantity = ? WHERE product_id = ?')->execute([$new_qty, $product_id]);
        } else {
          $pdo->prepare('INSERT INTO inventory (product_id, quantity) VALUES (?, ?)')->execute([$product_id, $new_qty]);
        }
        
        // Log stock-in transaction
        $log_stmt = $pdo->prepare("INSERT INTO stock_in_log (product_id, product_name, quantity_added, previous_quantity, new_quantity, user_id) 
                                    VALUES (?, ?, ?, ?, ?, ?)");
        $log_stmt->execute([$product_id, $product['name'], $quantity_to_add, $previous_qty, $new_qty, $_SESSION['user_id']]);
        
        $pdo->commit();
        $_SESSION['success'] = "Stock-in successful: Added {$quantity_to_add} units to {$product['name']}";
      } else {
        $_SESSION['error'] = "Product not found";
      }
      
    } catch (Exception $e) {
      $pdo->rollBack();
      $_SESSION['error'] = "Error: " . $e->getMessage();
    }
    
    header('Location: stock_in.php');
    exit;
  }
}

// Get all products
$products = $pdo->query("SELECT p.*, COALESCE(i.quantity, 0) as stock_quantity 
                         FROM products p 
                         LEFT JOIN inventory i ON p.id = i.product_id 
                         ORDER BY p.name")->fetchAll(PDO::FETCH_ASSOC);

$logs = [];
$log_error = null;
try {
  ensureStockInLogTable($pdo);
  $stmtLogs = $pdo->query("SELECT s.*, u.name as user_name 
                           FROM stock_in_log s 
                           LEFT JOIN users u ON s.user_id = u.id 
                           ORDER BY s.created_at DESC 
                           LIMIT 50");
  if ($stmtLogs) {
    $logs = $stmtLogs->fetchAll(PDO::FETCH_ASSOC);
  }
} catch (Exception $e) {
  $log_error = 'Unable to load stock-in history: ' . $e->getMessage();
}
?>
<!doctype html>
<html><head>
<meta charset="utf-8"><title>Stock In - BrewPOS</title>
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
  .table th {
    background-color: #e9ecef;
    border-top: none;
    font-weight: 600;
    color: #495057;
  }
</style>
</head><body>
<div class="container my-4">
  <div class="header-container">
    <div class="d-flex justify-content-between align-items-center">
      <div>
        <h2 class="mb-0"><i class="fas fa-box-open me-2"></i>Stock In</h2>
        <p class="mb-0 mt-2">Add inventory stock and track transactions</p>
      </div>
      <div>
        <a href="index.php" class="btn btn-light"><i class="fas fa-arrow-left me-1"></i> Back to POS</a>
      </div>
    </div>
  </div>
  
  <?php if (isset($_SESSION['error'])): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
      <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endif; ?>
  
  <?php if (isset($_SESSION['success'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
      <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endif; ?>

  <?php if ($log_error): ?>
    <div class="alert alert-warning alert-dismissible fade show" role="alert">
      <?php echo htmlspecialchars($log_error); ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endif; ?>
  
  <!-- Stock In Form -->
  <div class="card mb-4">
    <div class="card-header bg-success text-white">
      <i class="fas fa-plus-circle me-2"></i>Add Stock
    </div>
    <div class="card-body">
      <form method="post" class="row g-3">
        <div class="col-md-6">
          <label class="form-label">Product</label>
          <select name="product_id" class="form-select" required>
            <option value="">-- Select Product --</option>
            <?php foreach($products as $p): ?>
              <option value="<?php echo $p['id']; ?>">
                <?php echo htmlspecialchars($p['name']); ?> (Current: <?php echo $p['stock_quantity']; ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label">Quantity to Add</label>
          <input type="number" name="quantity" class="form-control" min="1" required>
        </div>
        <div class="col-md-2 d-flex align-items-end">
          <button type="submit" class="btn btn-success w-100"><i class="fas fa-check me-1"></i> Add Stock</button>
        </div>
      </form>
    </div>
  </div>
  
  <!-- Stock In History -->
  <div class="card">
    <div class="card-header bg-success text-white">
      <i class="fas fa-history me-2"></i>Stock In History
    </div>
    <div class="card-body">
      <?php if (!$log_error): ?>
        <div class="table-responsive">
          <table class="table table-hover">
            <thead>
              <tr>
                <th>Date/Time</th>
                <th>Product</th>
                <th>Qty Added</th>
                <th>Previous</th>
                <th>New</th>
                <th>User</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach($logs as $log): ?>
              <tr>
                <td><?php echo date('M d, Y h:i A', strtotime($log['created_at'])); ?></td>
                <td><?php echo htmlspecialchars($log['product_name']); ?></td>
                <td><span class="badge bg-success">+<?php echo $log['quantity_added']; ?></span></td>
                <td><?php echo $log['previous_quantity']; ?></td>
                <td><?php echo $log['new_quantity']; ?></td>
                <td><?php echo htmlspecialchars($log['user_name'] ?? 'N/A'); ?></td>
              </tr>
              <?php endforeach; if (empty($logs)): ?>
              <tr><td colspan="6" class="text-center text-muted">No stock-in transactions yet</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body></html>
