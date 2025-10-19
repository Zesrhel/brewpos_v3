<?php
require_once 'db.php';
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }

// Check if product_id column exists in inventory_items table
$columnCheck = $pdo->query("SHOW COLUMNS FROM inventory_items LIKE 'product_id'")->fetch();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete_id'])) {
        // Delete item
        $id = intval($_POST['delete_id']);
        $stmt = $pdo->prepare('DELETE FROM inventory_items WHERE id = ?');
        $stmt->execute([$id]);
        $_SESSION['success'] = "Item deleted successfully";
        header('Location: inventory.php');
        exit;
    } 
    elseif (isset($_POST['id']) && $_POST['id'] == 'new') {
        // Add new item
        $item_name = trim($_POST['item_name']);
        $quantity = floatval($_POST['quantity']);
        $unit = trim($_POST['unit']);
        $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : null;
        
        if ($item_name && $quantity >= 0) {
            $stmt = $pdo->prepare('INSERT INTO inventory_items (item_name, quantity, unit, product_id) VALUES (?, ?, ?, ?)');
            $stmt->execute([$item_name, $quantity, $unit, $product_id]);
            $_SESSION['success'] = "Item added successfully";
            header('Location: inventory.php');
            exit;
        }
    }
    elseif (isset($_POST['id'])) {
        // Update existing item
        $id = intval($_POST['id']);
        $quantity = floatval($_POST['quantity']);
        
        // Prevent negative quantities
        if ($quantity < 0) {
            $_SESSION['error'] = "Quantity cannot be negative";
            header('Location: inventory.php');
            exit;
        }
        
        $stmt = $pdo->prepare('UPDATE inventory_items SET quantity = ? WHERE id = ?');
        $stmt->execute([$quantity, $id]);
        $_SESSION['success'] = "Item updated successfully";
        header('Location: inventory.php');
        exit;
    }
}

// Get all inventory items
if ($columnCheck) {
    $items = $pdo->query('SELECT ii.*, p.name as product_name 
                          FROM inventory_items ii 
                          LEFT JOIN products p ON ii.product_id = p.id 
                          ORDER BY ii.item_name')->fetchAll(PDO::FETCH_ASSOC);
} else {
    $items = $pdo->query('SELECT ii.*, NULL as product_name 
                          FROM inventory_items ii 
                          ORDER BY ii.item_name')->fetchAll(PDO::FETCH_ASSOC);
}

// Get all products for dropdown
$products = $pdo->query('SELECT id, name FROM products ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html><html><head><meta charset="utf-8"><title>Inventory - BrewPOS</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
    body {
        background-color: #f8f9fa;
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        padding: 20px;
    }
    .header-container {
        background: #17a2b8;
        color: white;
        border-radius: 12px;
        padding: 25px 30px;
        margin-bottom: 25px;
        box-shadow: 0 2px 8px rgba(23,162,184,0.2);
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
        border-bottom: 2px solid #17a2b8;
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
    .btn-primary {
        background: #17a2b8;
        border: none;
        border-radius: 8px;
        padding: 10px 24px;
        font-weight: 600;
        transition: all 0.2s ease;
    }
    .btn-primary:hover {
        background: #138496;
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(23,162,184,0.3);
    }
    .btn-success {
        border-radius: 8px;
        padding: 10px 20px;
        font-weight: 600;
        transition: all 0.2s ease;
    }
    .btn-success:hover {
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
        border-color: #17a2b8;
        box-shadow: 0 0 0 0.2rem rgba(23,162,184,0.15);
    }
    .form-control-sm {
        padding: 6px 10px;
        font-size: 0.9rem;
    }
    .status-critical { 
        color: #dc3545; 
        font-weight: 700;
        font-size: 0.95rem;
    }
    .status-low { 
        color: #fd7e14; 
        font-weight: 700;
        font-size: 0.95rem;
    }
    .status-normal { 
        color: #28a745; 
        font-weight: 700;
        font-size: 0.95rem;
    }
</style>
</head><body>
<div class="container my-4">
  <div class="header-container">
    <div class="d-flex justify-content-between align-items-center">
      <div>
        <h2 class="mb-0"><i class="fas fa-warehouse me-2"></i>Inventory Management</h2>
        <p class="mb-0 mt-2">Track and update stock levels</p>
      </div>
      <div>
        <a href="stock_in.php" class="btn btn-success me-2"><i class="fas fa-box-open me-1"></i> Stock In</a>
        <a href="index.php" class="btn btn-light"><i class="fas fa-arrow-left me-1"></i> Back to POS</a>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addItemModal"><i class="fas fa-plus me-1"></i> Add New Item</button>
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
  
  <div class="card">
    <div class="card-body">
    <table class="table table-hover">
      <thead>
        <tr>
          <th>Item</th>
          <?php if ($columnCheck): ?>
            <th>Product</th>
          <?php endif; ?>
          <th>Quantity</th>
          <th>Unit</th>
          <th>Status</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach($items as $it): 
        // Determine status based on quantity
        $status = 'Normal';
        $statusClass = 'status-normal';
        if ($it['quantity'] <= 0) {
            $status = 'Out of Stock';
            $statusClass = 'status-critical';
        } elseif ($it['quantity'] < 5) {
            $status = 'Critical';
            $statusClass = 'status-critical';
        } elseif ($it['quantity'] < 10) {
            $status = 'Low';
            $statusClass = 'status-low';
        }
      ?>
        <tr>
          <form method="post">
          <td><?php echo htmlspecialchars($it['item_name']); ?></td>
          <?php if ($columnCheck): ?>
            <td><?php echo htmlspecialchars($it['product_name'] ?? 'N/A'); ?></td>
          <?php endif; ?>
          <td>
            <input name="quantity" type="number" step="<?php echo ($it['unit'] === 'pcs' || $it['unit'] === 'pieces') ? '1' : '0.01'; ?>" min="0" class="form-control form-control-sm" value="<?php echo ($it['unit'] === 'pcs' || $it['unit'] === 'pieces') ? (int)$it['quantity'] : $it['quantity']; ?>" required/>
          </td>
          <td><?php echo htmlspecialchars($it['unit']); ?></td>
          <td>
            <span class="<?php echo $statusClass; ?>">
              <?php echo $status; ?>
            </span>
          </td>
          <td>
            <input type="hidden" name="id" value="<?php echo $it['id']; ?>" />
            <button class="btn btn-sm btn-primary">Update</button>
            <button type="button" class="btn btn-sm btn-danger" onclick="confirmDelete(<?php echo $it['id']; ?>, '<?php echo htmlspecialchars($it['item_name']); ?>')">Delete</button>
          </td>
          </form>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    </div>
  </div>
</div>

<!-- Add Item Modal -->
<div class="modal fade" id="addItemModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Add New Inventory Item</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="post">
        <div class="modal-body">
          <input type="hidden" name="id" value="new">
          <div class="mb-3">
            <label class="form-label">Item Name</label>
            <input type="text" name="item_name" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Quantity</label>
            <input type="number" name="quantity" step="0.01" min="0" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Unit</label>
            <input type="text" name="unit" class="form-control" value="pcs" required>
          </div>
          <?php if ($columnCheck): ?>
            <div class="mb-3">
              <label class="form-label">Linked Product (Optional)</label>
              <select name="product_id" class="form-select">
                <option value="">-- Select Product --</option>
                <?php foreach($products as $product): ?>
                  <option value="<?php echo $product['id']; ?>"><?php echo htmlspecialchars($product['name']); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          <?php endif; ?>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Add Item</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Delete Confirmation Form -->
<form id="deleteForm" method="post" style="display: none;">
  <input type="hidden" name="delete_id" id="deleteId">
</form>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
function confirmDelete(id, name) {
  if (confirm('Are you sure you want to delete "' + name + '"?')) {
    document.getElementById('deleteId').value = id;
    document.getElementById('deleteForm').submit();
  }
}
</script>
</body></html>