<?php
require 'db.php';

$err = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (!empty($name) && !empty($username) && !empty($password)) {
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

        try {
            $stmt = $pdo->prepare("INSERT INTO users (name, username, password, role) VALUES (:name, :username, :password, 'cashier')");
            $stmt->execute([
                ':name' => $name,
                ':username' => $username,
                ':password' => $hashedPassword
            ]);
            header('Location: add_cashier_form.php?success=1');
            exit;
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                $err = 'Username already exists. Please choose another.';
            } else {
                $err = 'Error: ' . $e->getMessage();
            }
        }
    } else {
        $err = 'Please fill in all fields.';
    }
}

if (isset($_GET['success'])) $success = 'Cashier added successfully!';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Add Cashier — BrewPOS</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    :root{--brand:#7a4b2a;--muted:#6b6b6b}
    body{min-height:100vh;display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,#fff7ee,#f0fff6);padding:20px}
    .card-wrap{max-width:980px;width:100%;border-radius:12px;overflow:hidden;box-shadow:0 10px 30px rgba(0,0,0,.08);background:#fff}
    .left-panel{background:linear-gradient(180deg,var(--brand),#9b6a43);color:#fff;padding:36px;display:flex;flex-direction:column;justify-content:center}
    .brand{font-weight:800;font-size:1.25rem}
    .sub{opacity:.95;margin-top:6px;color:rgba(255,255,255,.95)}
    .illustr{width:100%;border-radius:8px;margin-top:18px;object-fit:cover}
    .right{padding:28px}
    .small-muted{color:var(--muted)}
    @media (max-width:767.98px){ .left-panel{display:none} .card-wrap{max-width:420px} }
  </style>
</head>
<body>
  <div class="card-wrap d-flex">
    <div class="left-panel col-md-5 d-none d-md-flex">
      <div>
        <center><div class="brand">BrewPOS</div></center>
        <center><div class="brand">Tiger Bubble Tea</div></center>
        <img src="milktea2.jpg" alt="tea" class="illustr">
      </div>
    </div>

    <div class="right col-md-7">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
          <h3 class="mb-0" style="color:var(--brand)">ADD NEW CASHIER</h3>
        </div>
      </div>

      <?php if ($success): ?>
        <div class="alert alert-success py-2"><?= htmlspecialchars($success) ?></div>
      <?php elseif ($err): ?>
        <div class="alert alert-danger py-2"><?= htmlspecialchars($err) ?></div>
      <?php endif; ?>

      <form action="" method="POST" class="needs-validation" novalidate>
        <div class="mb-3">
          <label class="form-label small-muted">Full name</label>
          <input type="text" id="name" name="name" class="form-control form-control-lg" placeholder="e.g. Juan Dela Cruz" required autofocus>
        </div>

        <div class="mb-3">
          <label class="form-label small-muted">Username (login)</label>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-person-fill"></i></span>
            <input type="text" id="username" name="username" class="form-control form-control-lg" placeholder="username" required>
          </div>
        </div>

        <div class="mb-3">
          <label class="form-label small-muted">Password</label>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-key-fill"></i></span>
            <input id="password" type="password" name="password" class="form-control form-control-lg" placeholder="Password" required>
            <button id="togglePwd" type="button" class="btn btn-outline-secondary" title="Show password"><i class="bi bi-eye"></i></button>
          </div>
          <div class="form-text">Please, remember your username and password </div>
        </div>

        <div class="d-grid mb-3">
          <button class="btn btn-lg" style="background:var(--brand);color:#fff">Add Cashier</button>
        </div>

        <div class="d-flex justify-content-between">
          <a href="login.php" class="btn btn-outline-secondary">Back to Login</a>
          <a href="index.php" class="small-muted">Cancel</a>
        </div>
      </form>
    </div>
  </div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
  // password toggle
  (function(){
    const t = document.getElementById('togglePwd');
    const p = document.getElementById('password');
    t.addEventListener('click', function(){
      const icon = this.querySelector('i');
      if (p.type === 'password') { p.type = 'text'; icon.className = 'bi bi-eye-slash'; this.title = 'Hide password'; }
      else { p.type = 'password'; icon.className = 'bi bi-eye'; this.title = 'Show password'; }
    });

    // simple client validation UI
    const forms = document.querySelectorAll('.needs-validation');
    Array.prototype.slice.call(forms).forEach(function(form){
      form.addEventListener('submit', function(event){
        if (!form.checkValidity()){ event.preventDefault(); event.stopPropagation(); form.classList.add('was-validated'); }
      }, false);
    });
  })();
</script>
</body>
</html>
