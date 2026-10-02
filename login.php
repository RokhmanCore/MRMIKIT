<?php
require_once __DIR__ . '/config/config.php';
if (!empty($_SESSION['user'])) { header('Location: dashboard.php'); exit; }
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $st = $pdo->prepare('SELECT * FROM users WHERE username = ? AND aktif = 1 LIMIT 1');
    $st->execute([$username]);
    $user = $st->fetch();
    if ($user && password_verify($password, $user['password_hash'])) {
        $_SESSION['user'] = ['id'=>$user['id'],'nama'=>$user['nama'],'username'=>$user['username'],'role'=>$user['role']];
        header('Location: dashboard.php'); exit;
    }
    $error = 'Username atau password salah.';
}
?>
<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>MRMIKIT - Login</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><style>body{background:#eef5f2}.login{max-width:430px;margin:8vh auto}.brand{font-weight:800;color:#0f6b4e}</style></head><body><div class="container"><div class="login"><div class="card shadow-sm border-0"><div class="card-body p-4"><h2 class="brand mb-1">MRMIKIT</h2><div class="text-muted mb-4">MRMIK IT · Akreditasi LARSI</div><?php if($error):?><div class="alert alert-danger"><?=htmlspecialchars($error)?></div><?php endif;?><form method="post"><label class="form-label">Username</label><input class="form-control mb-3" name="username" required><label class="form-label">Password</label><input class="form-control mb-3" type="password" name="password" required><button class="btn btn-success w-100">Masuk</button></form><div class="small text-muted mt-3">Akun awal: admin / admin123</div></div></div></div></div></body></html>