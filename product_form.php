<?php
require_once 'db.php';
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }
$id = $_GET['id'] ?? null;
$name=''; $sku=''; $price='';
$error = '';

if ($id) {
  $stmt = $pdo->prepare('SELECT * FROM products WHERE id=?');
  $stmt->execute([$id]);
  $r = $stmt->fetch(PDO::FETCH_ASSOC);
  if ($r) {
    $name = $r['name'];
    $sku = $r['sku'];
    $price = $r['price'];
  }
}

if ($_SERVER['REQUEST_METHOD']==='POST') {
  $name = trim($_POST['name'] ?? '');
  $sku = trim($_POST['sku'] ?? '');
  $price = $_POST['price'] ?? '0';

  if ($sku === '' || $name === '') {
    $error = 'Product SKU and Name are required.';
  } else {
    try {
      $pdo->beginTransaction();

      if ($id) {
        // Update existing product
        $pdo->prepare('UPDATE products SET sku=?, name=?, price=? WHERE id=?')->execute([$sku,$name,$price,$id]);
      } else {
        // Insert new product
        $pdo->prepare('INSERT INTO products (sku, name, price) VALUES (?, ?, ?)')->execute([$sku, $name, $price]);
      }

      $pdo->commit();
      header('Location: products_list.php');
      exit;
      
    } catch (PDOException $e) {
      $pdo->rollBack();
      if ($e->getCode() === '23000') {
        if (strpos($e->getMessage(), 'sku')) {
          $error = 'SKU already exists. Please use a unique SKU.';
        } else {
          $error = 'Database constraint error: ' . $e->getMessage();
        }
      } else {
        $error = 'Database error: ' . $e->getMessage();
      }
    } catch (Exception $e) {
      $pdo->rollBack();
      $error = 'Error: ' . $e->getMessage();
    }
  }
}
?>
<!doctype html><html><head>
<meta charset="utf-8"><title>Product Form</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head><body style="background:#fff8f2;padding:18px;">
<div class="container">
  <div class="card p-3">
    <h4><?php echo $id? 'Edit' : 'Add'; ?> Product</h4>
    <?php if($error): ?>
      <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <form method="post">
      <div class="mb-2">
        <label class="form-label">Stock keeping unit</label>
        <input name="sku" class="form-control" placeholder="SKU" value="<?php echo htmlspecialchars($sku); ?>" required/>
      </div>
      <div class="mb-2"><label class="form-label">Name</label><input name="name" class="form-control" placeholder="Name" value="<?php echo htmlspecialchars($name); ?>" required/></div>
      <div class="mb-2"><label class="form-label">Price</label><input name="price" type="number" step="0.01" class="form-control" placeholder="Price" value="<?php echo htmlspecialchars($price); ?>" required/></div>
      <div class="d-flex justify-content-between"><a href="products_list.php" class="btn btn-outline-secondary">Cancel</a><button class="btn btn-primary">Save</button></div>
    </form>
  </div>
</div>
</body></html>