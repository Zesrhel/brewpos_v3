<?php
require_once 'db.php';
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    $stmt = $pdo->prepare('SELECT id, username, password, name, role FROM users WHERE username = ? LIMIT 1');
    $stmt->execute([$username]);
    $u = $stmt->fetch(PDO::FETCH_ASSOC);

    // Auto-provision requested admin account if it does not yet exist
    if (!$u && $username === 'zesrhel' && $password === 'zes123') {
        $create = $pdo->prepare('INSERT INTO users (name, username, password, role) VALUES (?, ?, ?, ?)');
        $create->execute(['Zesrhel Admin', $username, password_hash($password, PASSWORD_DEFAULT), 'admin']);
        $stmt->execute([$username]);
        $u = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    if ($u && password_verify($password, $u['password'])) {
        if (($u['role'] ?? '') === 'admin') {
            $_SESSION['user_id'] = $u['id'];
            $_SESSION['name'] = $u['name'] ?? '';
            $_SESSION['username'] = $u['username'] ?? null;
            header('Location: index.php');
            exit;
        }
        $err = 'Access restricted to administrators.';
    } else {
        $err = 'Invalid username or password.';
    }
}
?>
<!doctype html>
<html lang="en">
  <meta charset="utf-8">
  <title>BrewPOS — Login</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    :root{--brand:#7a4b2a;--accent:#ffb74d;}
    body{min-height:100vh;display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,#fff7ee,#f0fff6);}
    .auth-card{max-width:920px;width:100%;border-radius:12px;overflow:hidden;box-shadow:0 10px 30px rgba(0,0,0,.08)}
    .auth-left{background:linear-gradient(180deg,var(--brand),#9b6a43);color:#fff;padding:30px;display:flex;flex-direction:column;justify-content:center;align-items:flex-start}
    .brand-title{font-weight:800;font-size:1.4rem;letter-spacing:.6px}
    .brand-sub{opacity:.95}
    .illustr{opacity:.95;border-radius:8px;margin-top:16px;width:100%}
    .auth-right{padding:28px;background:#fff}
    .form-heading{font-weight:700;color:var(--brand);margin-bottom:8px}
    .small-muted{color:#6b6b6b;font-size:.9rem}
    @media (max-width:767.98px){ .auth-left{display:none} .auth-card{max-width:420px} }
  </style>
</head>
<body>
  <div class="auth-card d-flex">
    <div class="auth-left col-6 d-none d-md-block">
     <center><div class="brand-title">BrewPOS</div></center> 
     <center><div class="brand-title">Tiger Bubble Tea</div></center> 
      <img src="milktea2.jpg" alt="tea" class="illustr mt-3">
    </div>

    <div class="auth-right col">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
          <h2><div class="form-heading">Sign in</div></h2>
          <div class="small-muted">Enter your credentials to continue</div>
        </div>
      </div>

      <?php if($err): ?>
        <div class="alert alert-danger py-2"><?= htmlspecialchars($err) ?></div>
      <?php endif; ?>

      <form method="post" class="needs-validation" novalidate>
        <div class="mb-3">
          <label class="form-label small-muted">Username</label>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-person-fill"></i></span>
            <input name="username" class="form-control" placeholder="Username" required autofocus>
          </div>
        </div>

        <div class="mb-3">
          <label class="form-label small-muted">Password</label>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-lock-fill"></i></span>
            <input id="password" name="password" type="password" class="form-control" placeholder="Password" required>
            <button id="togglePwd" type="button" class="btn btn-outline-secondary" title="Show password"><i class="bi bi-eye"></i></button>
          </div>
        </div>

        <div class="d-grid mb-3">
          <button class="btn" style="background:var(--brand);color:#fff">Sign in</button>
        </div>

        <div class="d-flex justify-content-between align-items-center"></div>
      </form>
    </div>
  </div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
  // password toggle
  document.getElementById('togglePwd').addEventListener('click', function(){
    const pwd = document.getElementById('password');
    const icon = this.querySelector('i');
    if(pwd.type === 'password'){ pwd.type = 'text'; icon.className = 'bi bi-eye-slash'; this.title='Hide password'; }
    else { pwd.type = 'password'; icon.className = 'bi bi-eye'; this.title='Show password'; }
  });

  // simple client validation UI
  (function(){
    'use strict'
    const forms = document.querySelectorAll('.needs-validation');
    Array.prototype.slice.call(forms).forEach(function(form){
      form.addEventListener('submit', function(event){
        if (!form.checkValidity()){ event.preventDefault(); event.stopPropagation(); form.classList.add('was-validated'); }
      }, false)
    });
  })();
</script>
</body>
</html>
