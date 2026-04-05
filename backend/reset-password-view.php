<?php
$token = $_GET['token'] ?? '';
if (!$token) {
    die('Invalid or missing token.');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Reset Password | Edducare</title>
    <link rel="stylesheet" href="assets/reset-password.css" />
  
</head>
<body>

  <div class="container">
    <h2>🔐 Reset Your Password</h2>
    <p class="subheading">Enter your username and new password to continue.</p>

    <form id="resetForm">
      <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>" />

      <label for="username">👤 Username</label>
      <input type="text" id="username" name="username" required />

      <label for="password">🔑 New Password</label>
      <input type="password" id="password" name="password" required minlength="8" />

      <label for="confirm">🔁 Confirm Password</label>
      <input type="password" id="confirm" name="confirm" required minlength="8" />

      <input type="submit" value="Reset Password" />
    </form>

    <div class="loader" id="loader"></div>
    <div class="message" id="message"></div>
    <div class="footer-note">© <?= date('Y') ?> Edducare. All rights reserved.</div>
  </div>

  <script>
    const form = document.getElementById('resetForm');
    const message = document.getElementById('message');
    const loader = document.getElementById('loader');

    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      message.textContent = '';
      loader.style.display = 'block';

      const token = form.token.value;
      const username = form.username.value.trim();
      const password = form.password.value;
      const confirm = form.confirm.value;

      if (password !== confirm) {
        loader.style.display = 'none';
        message.textContent = "Passwords do not match.";
        message.style.color = "red";
        return;
      }

      try {
        const response = await fetch('https://edducare.finafid.org/api/auth/reset-password', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ token, username, password })
        });

        const data = await response.json();
        loader.style.display = 'none';

        if (response.ok && data.success) {
          message.textContent = data.message || "Password reset successful.";
          message.style.color = "green";
          form.reset();
        } else {
          message.textContent = data.error || "Failed to reset password.";
          message.style.color = "red";
        }
      } catch (err) {
        loader.style.display = 'none';
        message.textContent = "Something went wrong. Please try again.";
        message.style.color = "red";
      }
    });
  </script>

</body>
</html>
