<?php
require_once __DIR__ . '/config/config.php';

if (!empty($_SESSION['user'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $st = $pdo->prepare('SELECT * FROM users WHERE username = ? AND aktif = 1 LIMIT 1');
    $st->execute([$username]);
    $user = $st->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        $_SESSION['user'] = [
            'id' => $user['id'],
            'nama' => $user['nama'],
            'username' => $user['username'],
            'role' => $user['role']
        ];
        header('Location: dashboard.php');
        exit;
    }

    $error = 'Username atau password salah.';
}
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Masuk · MRMIKIT</title>
<style>
:root{
    --green:#171717;
    --green-dark:#090909;
    --green-soft:#f3f3f3;
    --red:#c92a3d;
    --red-dark:#9f1f30;
    --ink:#171717;
    --muted:#727272;
    --line:#dedede;
}
*{box-sizing:border-box}
body{
    margin:0;
    min-height:100vh;
    font-family:Inter,ui-sans-serif,system-ui,-apple-system,"Segoe UI",Arial,sans-serif;
    color:var(--ink);
    background:
      radial-gradient(circle at 12% 12%,rgba(201,42,61,.08),transparent 28%),
      radial-gradient(circle at 88% 88%,rgba(0,0,0,.08),transparent 30%),
      linear-gradient(135deg,#f7f7f7 0%,#eeeeee 52%,#e6e6e6 100%);
    display:flex;
    align-items:center;
    justify-content:center;
    padding:28px 18px;
}
.login-shell{
    width:min(960px,100%);
    min-height:570px;
    display:grid;
    grid-template-columns:46% 54%;
    overflow:hidden;
    border:1px solid rgba(255,255,255,.9);
    border-radius:28px;
    background:rgba(255,255,255,.94);
    box-shadow:0 24px 70px rgba(18,73,57,.15),0 3px 12px rgba(18,73,57,.06);
}
.brand-panel{
    position:relative;
    padding:48px;
    color:#fff;
    background:
      radial-gradient(circle at 78% 20%,rgba(201,42,61,.22),transparent 24%),
      radial-gradient(circle at 15% 90%,rgba(255,255,255,.06),transparent 28%),
      linear-gradient(145deg,#090909,#171717 62%,#242424);
    display:flex;
    flex-direction:column;
    justify-content:space-between;
}
.brand-panel:after{
    content:"";
    position:absolute;
    width:230px;height:230px;
    right:-90px;bottom:-95px;
    border:35px solid rgba(255,255,255,.08);
    border-radius:50%;
}
.logo-mark{
    width:62px;height:62px;
    display:grid;place-items:center;
    border-radius:18px;
    background:rgba(201,42,61,.16);
    border:1px solid rgba(255,255,255,.2);
    box-shadow:inset 0 1px rgba(255,255,255,.18);
    font-size:29px;
    font-weight:800;
    letter-spacing:-2px;
}
.brand-panel h1{
    margin:22px 0 8px;
    font-size:42px;
    line-height:1;
    letter-spacing:-1.8px;
}
.brand-panel .tagline{
    max-width:330px;
    margin:0;
    color:rgba(255,255,255,.78);
    font-size:15px;
    line-height:1.7;
}
.brand-footer{
    color:rgba(255,255,255,.7);
    font-size:12px;
}
.form-panel{
    padding:58px 64px;
    display:flex;
    align-items:center;
}
.form-wrap{width:100%;max-width:420px;margin:auto}
.eyebrow{
    display:inline-flex;
    align-items:center;
    gap:7px;
    color:var(--red);
    font-size:12px;
    font-weight:800;
    letter-spacing:.12em;
    text-transform:uppercase;
    margin-bottom:10px;
}
.eyebrow:before{
    content:"";
    width:22px;height:3px;
    border-radius:10px;
    background:var(--red);
}
.form-wrap h2{
    margin:0;
    font-size:31px;
    letter-spacing:-.8px;
}
.subtitle{
    margin:8px 0 30px;
    color:var(--muted);
    font-size:14px;
}
.alert{
    padding:12px 14px;
    margin-bottom:18px;
    border-radius:12px;
    color:#a52b38;
    background:#fff0f1;
    border:1px solid #ffd2d6;
    font-size:13px;
}
.field{margin-bottom:19px}
.field label{
    display:block;
    margin-bottom:8px;
    font-size:13px;
    font-weight:700;
}
.input-wrap{position:relative}
.input-wrap .icon{
    position:absolute;
    left:15px;top:50%;
    transform:translateY(-50%);
    color:#7d948c;
    pointer-events:none;
}
.form-control{
    width:100%;
    height:50px;
    border:1px solid var(--line);
    border-radius:13px;
    outline:0;
    padding:0 15px 0 43px;
    background:#fbfdfc;
    color:var(--ink);
    font-size:14px;
    transition:.18s ease;
}
.form-control:focus{
    background:#fff;
    border-color:#c92a3d;
    box-shadow:0 0 0 4px rgba(201,42,61,.10);
}
.btn-login{
    width:100%;
    height:52px;
    border:0;
    border-radius:13px;
    margin-top:4px;
    color:#fff;
    background:linear-gradient(135deg,#9f1f30,#c92a3d);
    font-size:14px;
    font-weight:800;
    cursor:pointer;
    box-shadow:0 9px 22px rgba(201,42,61,.22);
    transition:.18s ease;
}
.btn-login:hover{
    transform:translateY(-1px);
    box-shadow:0 12px 26px rgba(201,42,61,.28);
}
.help{
    margin-top:23px;
    text-align:center;
    color:#8a9b95;
    font-size:12px;
}
@media(max-width:760px){
    body{padding:14px}
    .login-shell{
        min-height:auto;
        grid-template-columns:1fr;
        border-radius:22px;
    }
    .brand-panel{
        min-height:235px;
        padding:30px;
    }
    .brand-panel h1{font-size:32px}
    .brand-footer{margin-top:35px}
    .form-panel{padding:38px 28px 42px}
}
</style>
</head>
<body>
<main class="login-shell">
    <section class="brand-panel">
        <div>
            <h1>MRMIKIT</h1>
            <p class="tagline">Sistem manajemen dokumen dan indikator mutu untuk mendukung pengelolaan MRMIK IT dan persiapan akreditasi LARSI.</p>
        </div>
        <div class="brand-footer">RSU Permata Medika Kebumen · MRMIK IT</div>
    </section>

    <section class="form-panel">
        <div class="form-wrap">
            <div class="eyebrow">Secure access</div>
            <h2>Selamat datang kembali</h2>
            <p class="subtitle">Masuk untuk melanjutkan ke dashboard MRMIKIT.</p>

            <?php if ($error): ?>
                <div class="alert"><?=htmlspecialchars($error)?></div>
            <?php endif; ?>

            <form method="post" autocomplete="on">
                <div class="field">
                    <label for="username">Username</label>
                    <div class="input-wrap">
                        <span class="icon">◉</span>
                        <input id="username" class="form-control" name="username" autocomplete="username" placeholder="Masukkan username" required autofocus>
                    </div>
                </div>

                <div class="field">
                    <label for="password">Password</label>
                    <div class="input-wrap">
                        <span class="icon">◆</span>
                        <input id="password" class="form-control" type="password" name="password" autocomplete="current-password" placeholder="Masukkan password" required>
                    </div>
                </div>

                <button class="btn-login" type="submit">Masuk ke MRMIKIT&nbsp; →</button>
            </form>

            <div class="help">Akses sistem internal · Data dilindungi dengan autentikasi pengguna</div>
        </div>
    </section>
</main>
</body>
</html>