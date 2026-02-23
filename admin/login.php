<?php
session_start();
require_once('../config/database.php');

$cookie_prefix = 'ft_remember_';
$cookie_username = $cookie_prefix . 'username';
$cookie_password = $cookie_prefix . 'password';
$cookie_expiry = time() + (30 * 24 * 60 * 60); // 30 days

// Pre-fill from cookies when loading the page (GET)
$saved_username = '';
$saved_password = '';
$remember_checked = false;
if (!isset($_POST['login']) && isset($_COOKIE[$cookie_username])) {
    $saved_username = $_COOKIE[$cookie_username];
    $saved_password = isset($_COOKIE[$cookie_password]) ? $_COOKIE[$cookie_password] : '';
    $remember_checked = true;
}

if (isset($_POST['login'])) {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);

    $stmt = $db->prepare("SELECT * FROM admins WHERE username = ? AND password = ?");
    $stmt->execute([$username, $password]);
    $admin = $stmt->fetch();

    if ($admin) {
        $_SESSION['admin_id'] = $admin['id'];

        if ($remember) {
            setcookie($cookie_username, $username, $cookie_expiry, '/');
            setcookie($cookie_password, $password, $cookie_expiry, '/');
        } else {
            setcookie($cookie_username, '', time() - 3600, '/');
            setcookie($cookie_password, '', time() - 3600, '/');
        }

        header('Location: index.php');
        exit();
    }
    $error = "Invalid username or password";
    $saved_username = $username;
    $remember_checked = $remember;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - Family Tree</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'DM Sans', system-ui, sans-serif;
            min-height: 100vh;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #334155 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }
        .login-card {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.35);
            overflow: hidden;
            max-width: 400px;
            width: 100%;
        }
        .login-header {
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
            color: #fff;
            padding: 1.75rem;
            text-align: center;
        }
        .login-header h1 {
            font-size: 1.5rem;
            font-weight: 700;
            margin: 0 0 0.25rem 0;
        }
        .login-header p {
            font-size: 0.875rem;
            opacity: 0.9;
            margin: 0;
        }
        .login-body { padding: 1.75rem; }
        .form-label {
            font-weight: 500;
            color: #374151;
        }
        .form-control {
            border-radius: 10px;
            padding: 0.625rem 0.875rem;
            border: 1px solid #e5e7eb;
        }
        .form-control:focus {
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.2);
        }
        .form-control::placeholder { color: #9ca3af; }
        .btn-login {
            width: 100%;
            padding: 0.75rem;
            font-weight: 600;
            border-radius: 10px;
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
            border: none;
            transition: transform 0.15s, box-shadow 0.15s;
        }
        .btn-login:hover {
            background: linear-gradient(135deg, #1d4ed8 0%, #1e40af 100%);
            transform: translateY(-1px);
            box-shadow: 0 10px 20px -10px rgba(37, 99, 235, 0.5);
        }
        .alert-danger {
            border-radius: 10px;
            border: none;
            background: #fef2f2;
            color: #b91c1c;
        }
        .form-check-input:checked {
            background-color: #2563eb;
            border-color: #2563eb;
        }
        .form-check-label { color: #374151; }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="login-header">
            <h1>Family Tree</h1>
            <p>Admin sign in</p>
        </div>
        <div class="login-body">
            <?php if (isset($error)): ?>
                <div class="alert alert-danger mb-4" role="alert">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            <form method="POST" action="">
                <div class="mb-3">
                    <label for="username" class="form-label">Username</label>
                    <input type="text" id="username" name="username" class="form-control" required
                           placeholder="Enter username" autocomplete="username" autofocus
                           value="<?php echo htmlspecialchars($saved_username); ?>">
                </div>
                <div class="mb-3">
                    <label for="password" class="form-label">Password</label>
                    <input type="password" id="password" name="password" class="form-control" required
                           placeholder="Enter password" autocomplete="current-password"
                           value="<?php echo htmlspecialchars($saved_password); ?>">
                </div>
                <div class="mb-4 form-check">
                    <input type="checkbox" class="form-check-input" id="remember" name="remember" value="1"<?php echo $remember_checked ? ' checked' : ''; ?>>
                    <label class="form-check-label" for="remember">Remember username and password</label>
                </div>
                <button type="submit" name="login" class="btn btn-primary btn-login">Login</button>
            </form>
        </div>
    </div>
</body>
</html>
