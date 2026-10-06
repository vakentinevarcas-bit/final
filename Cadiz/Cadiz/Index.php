<?php
session_start();
require_once __DIR__ . '/db.php';

if (isset($_SESSION['user_id'])) {
    header('Location: Cadiz_Go.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes, viewport-fit=cover">
  <title>Cadiz Go</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,400;14..32,500;14..32,600;14..32,700&family=Playfair+Display:ital,wght@0,500;0,600;1,500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
  <link rel="stylesheet" href="../css/login_user.css">
</head>
<body>
  <div class="auth-card">
    <div class="brand">
      <div class="logo-icon">
        <i class="fas fa-map-pin"></i>
      </div>
      <div class="system-title">
        Cadiz<span>Go</span>
      </div>
      <div class="subtitle">
        <i class="fas fa-umbrella-beach"></i> Tourism Office <i class="fas fa-compass"></i>
      </div>
    </div>

    <div class="form-container">
      <!-- LOGIN PANEL -->
      <div id="loginPanel" class="form-panel visible-panel">
        <form onsubmit="handleLogin(event)">
          <div class="input-group">
            <label class="input-label"><i class="far fa-envelope"></i> Email</label>
            <input type="email" class="input-field" id="loginEmail" placeholder="explore@cadizgo.com" required autocomplete="email">
            <i class="fas fa-envelope input-icon"></i>
          </div>
          <div class="input-group">
            <label class="input-label"><i class="fas fa-lock"></i> Password</label>
            <input type="password" class="input-field" id="loginPassword" placeholder="········" required autocomplete="current-password">
            <i class="fas fa-key input-icon"></i>
          </div>
          <div class="options-row">
            <label class="remember-me"><input type="checkbox" checked> Remember me</label>
            <a href="#" class="forgot-link" id="forgotLink">Forgot password?</a>
          </div>
          <button type="submit" class="auth-btn" id="loginBtn"><span>Access Portal</span> <i class="fas fa-arrow-right"></i></button>
        </form>
        <div class="divider">or connect with</div>
        <div class="social-login">
          <a href="https://accounts.google.com/o/oauth2/auth?client_id=YOUR_GOOGLE_CLIENT_ID&redirect_uri=YOUR_REDIRECT_URI&response_type=code&scope=email%20profile" class="social-btn" title="Sign in with Google" target="_blank" rel="noopener">
            <i class="fab fa-google"></i>
          </a>
          <a href="https://www.facebook.com/v12.0/dialog/oauth?client_id=YOUR_FACEBOOK_APP_ID&redirect_uri=YOUR_REDIRECT_URI&scope=email,public_profile" class="social-btn" title="Sign in with Facebook" target="_blank" rel="noopener">
            <i class="fab fa-facebook-f"></i>
          </a>
          <a href="https://appleid.apple.com/auth/authorize?client_id=YOUR_APPLE_CLIENT_ID&redirect_uri=YOUR_REDIRECT_URI&response_type=code&scope=name%20email" class="social-btn" title="Sign in with Apple" target="_blank" rel="noopener">
            <i class="fab fa-apple"></i>
          </a>
        </div>
        <div class="switch-prompt">
          New to Cadiz Go? <button id="showRegisterBtn" class="switch-link">Create account</button>
        </div>
      </div>

      <!-- REGISTER PANEL -->
      <div id="registerPanel" class="form-panel hidden-panel">
        <form onsubmit="handleRegister(event)">
          <div class="input-group">
            <label class="input-label"><i class="fas fa-user"></i> Full Name</label>
            <input type="text" class="input-field" id="regName" placeholder="Maria Santos" required autocomplete="name">
            <i class="fas fa-user-circle input-icon"></i>
          </div>
          <div class="input-group">
            <label class="input-label"><i class="far fa-envelope"></i> Email</label>
            <input type="email" class="input-field" id="regEmail" placeholder="maria@cadizgo.com" required autocomplete="email">
            <i class="fas fa-envelope input-icon"></i>
          </div>
          <div class="input-group">
            <label class="input-label"><i class="fas fa-lock"></i> Password</label>
            <input type="password" class="input-field" id="regPassword" placeholder="At least 8 characters" required autocomplete="new-password">
            <i class="fas fa-key input-icon"></i>
          </div>
          <div class="input-group">
            <label class="input-label"><i class="fas fa-check-circle"></i> Confirm Password</label>
            <input type="password" class="input-field" id="regConfirmPassword" placeholder="Re-enter password" required autocomplete="new-password">
            <i class="fas fa-shield-alt input-icon"></i>
          </div>
          <div class="terms-text">
            By creating an account you agree to our <a href="#">Terms</a> and <a href="#">Privacy Policy</a>.
          </div>
          <button type="submit" class="auth-btn" id="registerBtn"><span>Create account</span> <i class="fas fa-user-plus"></i></button>
        </form>
        <div class="divider">or sign up with</div>
        <div class="social-login">
          <a href="https://accounts.google.com/signup" class="social-btn" title="Sign up with Google" target="_blank" rel="noopener">
            <i class="fab fa-google"></i>
          </a>
          <a href="https://www.facebook.com/r.php" class="social-btn" title="Sign up with Facebook" target="_blank" rel="noopener">
            <i class="fab fa-facebook-f"></i>
          </a>
          <a href="https://appleid.apple.com/account" class="social-btn" title="Sign up with Apple" target="_blank" rel="noopener">
            <i class="fab fa-apple"></i>
          </a>
        </div>
        <div class="switch-prompt">
          Already have an account? <button id="showLoginBtn" class="switch-link">Sign in</button>
        </div>
      </div>
    </div>
  </div>

  <script src="../js/login_user.js">
    
  </script>
</body>
</html>