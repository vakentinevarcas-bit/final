<?php
session_start();
require_once __DIR__ . '/../Cadiz/db.php';

if (isset($_SESSION['admin_id']) && !empty($_SESSION['admin_id'])) {
    header('Location: admin_dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes, viewport-fit=cover">
  <title>Cadiz Go Admin Portal</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,400;14..32,500;14..32,600;14..32,700&family=Playfair+Display:ital,wght@0,500;0,600;1,500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body {
      font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
      background: linear-gradient(160deg, #121212 0%, #1a1a2e 35%, #0B72D9 100%);
      min-height: 100vh; display: flex; align-items: center; justify-content: center;
      padding: 1rem; margin: 0; position: relative; overflow-x: hidden;
    }
    body::before {
      content: ""; position: fixed; top: -25%; right: -15%; width: 75%; height: 75%;
      background: radial-gradient(circle at 30% 40%, rgba(11, 114, 217, 0.18) 0%, rgba(30, 79, 184, 0.04) 70%);
      border-radius: 50%; z-index: 0; pointer-events: none;
      animation: floatBg 10s ease-in-out infinite alternate;
    }
    body::after {
      content: ""; position: fixed; bottom: -15%; left: -10%; width: 70%; height: 70%;
      background: radial-gradient(circle at 70% 60%, rgba(247, 124, 0, 0.12) 0%, rgba(247, 210, 30, 0.03) 70%);
      border-radius: 50%; z-index: 0; pointer-events: none;
      animation: floatBg2 12s ease-in-out infinite alternate;
    }
    @keyframes floatBg {
      0% { transform: translate(0, 0) scale(1); }
      100% { transform: translate(-3%, 3%) scale(1.06); }
    }
    @keyframes floatBg2 {
      0% { transform: translate(0, 0) scale(1); }
      100% { transform: translate(3%, -3%) scale(1.04); }
    }
    .auth-card {
      position: relative; z-index: 10; width: 100%; max-width: 440px;
      background: rgba(255, 255, 255, 0.96); backdrop-filter: blur(20px);
      -webkit-backdrop-filter: blur(20px); border-radius: 2.2rem;
      box-shadow: 0 30px 50px rgba(18, 18, 18, 0.3), 0 8px 20px rgba(11, 114, 217, 0.2);
      border: 1px solid rgba(11, 114, 217, 0.15); padding: 2.2rem 1.8rem;
      transition: all 0.3s ease; color: #121212; animation: cardEntry 0.5s ease forwards;
    }
    @keyframes cardEntry {
      0% { opacity: 0; transform: translateY(25px) scale(0.98); }
      100% { opacity: 1; transform: translateY(0) scale(1); }
    }
    .brand { text-align: center; margin-bottom: 1.5rem; }
    .logo-image {
      width: 70px; height: 70px; margin: 0 auto 0.6rem; border-radius: 20px;
      background: #FFFFFF; border: 2px solid rgba(11, 114, 217, 0.2);
      display: flex; align-items: center; justify-content: center; overflow: hidden;
      box-shadow: 0 10px 20px rgba(18, 18, 18, 0.2); transition: transform 0.3s ease;
    }
    .logo-image img { width: 100%; height: 100%; object-fit: contain; padding: 8px; }
    .logo-image:hover { transform: scale(1.04); }
    .system-title {
      font-family: 'Playfair Display', 'Times New Roman', serif; font-size: 2.2rem;
      font-weight: 600; letter-spacing: -0.5px; color: #121212; margin-bottom: 0.1rem;
      display: flex; align-items: center; justify-content: center; gap: 0.3rem; line-height: 1.2;
    }
    .system-title span {
      font-weight: 700; background: linear-gradient(135deg, #121212, #1E4FB8);
      -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;
    }
    .subtitle {
      font-size: 0.8rem; font-weight: 500; color: #6A4A2C; letter-spacing: 0.4px;
      text-transform: uppercase; margin-top: 0.1rem; display: flex; align-items: center;
      justify-content: center; gap: 0.3rem;
    }
    .subtitle i { font-size: 0.7rem; color: #F57C00; }
    .admin-badge {
      display: inline-flex; align-items: center; gap: 0.3rem; background: #121212;
      color: #F7D21E; font-size: 0.7rem; font-weight: 700; padding: 0.3rem 0.8rem;
      border-radius: 1rem; margin-top: 0.4rem; letter-spacing: 0.5px; text-transform: uppercase;
    }
    .admin-badge i { font-size: 0.65rem; }
    .form-container { margin-top: 0.3rem; }
    .input-group { margin-bottom: 1.1rem; position: relative; }
    .input-label {
      display: flex; align-items: center; gap: 0.3rem; font-weight: 600; font-size: 0.75rem;
      letter-spacing: 0.3px; color: #121212; margin-bottom: 0.3rem; text-transform: uppercase;
    }
    .input-label i { font-size: 0.8rem; color: #0B72D9; }
    .input-field {
      width: 100%; padding: 0.85rem 1rem; padding-left: 2.5rem; background: #FFFFFF;
      border: 1.5px solid rgba(11, 114, 217, 0.2); border-radius: 1.2rem; font-size: 0.9rem;
      font-family: 'Inter', sans-serif; font-weight: 500; color: #121212;
      transition: all 0.25s ease; outline: none;
    }
    .input-field:focus {
      border-color: #0B72D9; box-shadow: 0 6px 14px rgba(11, 114, 217, 0.15), 0 0 0 3px rgba(11, 114, 217, 0.06);
      transform: translateY(-1px);
    }
    .input-field::placeholder { color: rgba(18, 18, 18, 0.3); }
    .input-icon {
      position: absolute; left: 0.9rem; top: 2.45rem; transform: translateY(-50%);
      color: #8ED8F8; font-size: 0.95rem; transition: all 0.25s ease; pointer-events: none;
    }
    .input-field:focus ~ .input-icon { color: #0B72D9; }
    .options-row {
      display: flex; align-items: center; justify-content: space-between;
      margin: 0.2rem 0 1.2rem; font-size: 0.78rem; flex-wrap: wrap; gap: 0.4rem;
    }
    .remember-me {
      display: flex; align-items: center; gap: 0.3rem; color: #6A4A2C;
      font-weight: 500; cursor: pointer; transition: color 0.2s;
    }
    .remember-me:hover { color: #0B72D9; }
    .remember-me input[type="checkbox"] { accent-color: #0B72D9; width: 0.9rem; height: 0.9rem; cursor: pointer; }
    .forgot-link {
      color: #1E4FB8; font-weight: 600; text-decoration: none; border-bottom: 1px solid transparent;
      transition: all 0.2s;
    }
    .forgot-link:hover { border-bottom: 1px solid #0B72D9; color: #0B72D9; }
    .auth-btn {
      width: 100%; background: linear-gradient(115deg, #121212, #1E4FB8); border: none;
      padding: 0.85rem; border-radius: 2rem; font-weight: 700; font-size: 0.95rem;
      letter-spacing: 0.3px; color: #FFFFFF; box-shadow: 0 10px 20px rgba(18, 18, 18, 0.4);
      cursor: pointer; display: flex; align-items: center; justify-content: center;
      gap: 0.4rem; transition: all 0.3s ease; text-transform: uppercase;
      position: relative; overflow: hidden;
    }
    .auth-btn::before {
      content: ''; position: absolute; top: 50%; left: 50%; width: 0; height: 0;
      border-radius: 50%; background: rgba(247, 210, 30, 0.18);
      transform: translate(-50%, -50%); transition: width 0.5s, height 0.5s;
    }
    .auth-btn:hover::before { width: 300px; height: 300px; }
    .auth-btn:hover {
      background: linear-gradient(115deg, #1E4FB8, #0B72D9);
      box-shadow: 0 14px 24px rgba(18, 18, 18, 0.5); transform: translateY(-2px);
    }
    .auth-btn:active { transform: translateY(0) scale(0.98); }
    .auth-btn i { transition: transform 0.3s ease; position: relative; z-index: 1; }
    .auth-btn:hover i { transform: translateX(3px); }
    .auth-btn span { position: relative; z-index: 1; }
    .security-notice {
      text-align: center; font-size: 0.7rem; color: #6A4A2C; margin-top: 1rem;
      display: flex; align-items: center; justify-content: center; gap: 0.3rem;
    }
    .security-notice i { color: #4CAF50; font-size: 0.75rem; }
    @keyframes successPulse {
      0% { box-shadow: 0 0 0 0 rgba(76, 175, 80, 0.4); }
      70% { box-shadow: 0 0 0 12px rgba(76, 175, 80, 0); }
      100% { box-shadow: 0 0 0 0 rgba(76, 175, 80, 0); }
    }
    @keyframes errorShake {
      0%, 100% { transform: translateX(0); }
      10%, 30%, 50%, 70%, 90% { transform: translateX(-4px); }
      20%, 40%, 60%, 80% { transform: translateX(4px); }
    }
    .auth-btn.success {
      animation: successPulse 0.7s ease; background: linear-gradient(115deg, #4CAF50, #388E3C);
    }
    .auth-btn.error {
      animation: errorShake 0.45s ease; background: linear-gradient(115deg, #F57C00, #E65100);
    }
    @media screen and (max-width: 500px) {
      .auth-card { padding: 1.8rem 1.3rem; border-radius: 1.8rem; }
      .system-title { font-size: 1.8rem; }
      .logo-image { width: 60px; height: 60px; border-radius: 16px; }
      .input-field { padding: 0.8rem 0.9rem; padding-left: 2.3rem; font-size: 0.85rem; }
      .input-icon { left: 0.8rem; font-size: 0.85rem; }
    }
    @media screen and (max-width: 380px) {
      body { padding: 0.5rem; }
      .auth-card { padding: 1.5rem 1rem; }
      .input-field { padding-left: 2.1rem; }
      .input-icon { left: 0.7rem; }
      .auth-btn { font-size: 0.85rem; padding: 0.75rem; }
    }
  </style>
</head>
<body>
  <div class="auth-card">
    <div class="brand">
      <div class="logo-image">
        <img src="Tourism office logo.png" alt="Cadiz Go Logo">
      </div>
      <div class="system-title">Cadiz<span>Go</span></div>
      <div class="subtitle"><i class="fas fa-lock"></i> Administrator Access</div>
      <div class="admin-badge"><i class="fas fa-crown"></i> Admin Portal</div>

    <div class="form-container">
      <form onsubmit="handleAdminLogin(event)">
        <div class="input-group">
          <label class="input-label"><i class="fas fa-user-shield"></i> Admin Username</label>
          <input type="text" class="input-field" id="adminUsername" placeholder="admin@gmail.com" required autocomplete="username">
          <i class="fas fa-user-cog input-icon"></i>
        </div>
        <div class="input-group">
          <label class="input-label"><i class="fas fa-lock"></i> Password</label>
          <input type="password" class="input-field" id="adminPassword" placeholder="········" required autocomplete="current-password">
          <i class="fas fa-key input-icon"></i>
        </div>
        <div class="options-row">
          <label class="remember-me"><input type="checkbox"> Remember device</label>
          <a href="#" class="forgot-link" id="adminForgotLink">Forgot credentials?</a>
        </div>
        <button type="submit" class="auth-btn" id="adminLoginBtn"><span>Access Admin</span> <i class="fas fa-arrow-right"></i></button>
      </form>
      <div class="security-notice">
        <i class="fas fa-shield-check"></i> Encrypted · Authorized personnel only
      </div>
  </div>

  <script>
    (function() {
      var adminLoginBtn = document.getElementById('adminLoginBtn');
      var adminForgotLink = document.getElementById('adminForgotLink');

      adminForgotLink.addEventListener('click', function(e) {
        e.preventDefault();
        alert('Admin credential recovery will be sent to the registered administrator email.');
      });

      window.handleAdminLogin = function(event) {
        event.preventDefault();
        var username = document.getElementById('adminUsername').value.trim();
        var password = document.getElementById('adminPassword').value;
        if (!username || !password) {
          adminLoginBtn.classList.add('error');
          setTimeout(function() { adminLoginBtn.classList.remove('error'); }, 450);
          alert('Username and password are required.');
          return;
        }

        adminLoginBtn.disabled = true;
        adminLoginBtn.querySelector('span').textContent = 'Verifying...';
        adminLoginBtn.querySelector('i').className = 'fas fa-spinner fa-spin';

        fetch('admin_login.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            username: username,
            password: password
          })
        })
        .then(function(res) { return res.json(); })
        .then(function(data) {
          if (data.success) {
            adminLoginBtn.classList.add('success');
            adminLoginBtn.querySelector('span').textContent = 'Welcome!';
            adminLoginBtn.querySelector('i').className = 'fas fa-check';
            setTimeout(function() {
              window.location.href = 'admin_dashboard.php';
            }, 600);
          } else {
            adminLoginBtn.classList.add('error');
            adminLoginBtn.querySelector('span').textContent = 'Access Admin';
            adminLoginBtn.querySelector('i').className = 'fas fa-arrow-right';
            adminLoginBtn.disabled = false;
            setTimeout(function() {
              adminLoginBtn.classList.remove('error');
            }, 450);
            alert(data.message || 'Invalid admin credentials.');
          }
        })
        .catch(function(err) {
          console.error(err);
          adminLoginBtn.classList.remove('success');
          adminLoginBtn.querySelector('span').textContent = 'Access Admin';
          adminLoginBtn.querySelector('i').className = 'fas fa-arrow-right';
          adminLoginBtn.disabled = false;
          alert('Network error. Please try again.');
        });
      };
    })();
  </script>
</body>
</html>
