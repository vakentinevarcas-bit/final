(function () {
    const loginPanel = document.getElementById('loginPanel');
    const registerPanel = document.getElementById('registerPanel');
    const showRegisterBtn = document.getElementById('showRegisterBtn');
    const showLoginBtn = document.getElementById('showLoginBtn');
    const forgotLink = document.getElementById('forgotLink');
    const loginBtn = document.getElementById('loginBtn');
    const registerBtn = document.getElementById('registerBtn');

    function switchPanel(panelToShow, panelToHide, isReverse = false) {
        if (isReverse) {
            panelToShow.classList.add('reverse');
        } else {
            panelToShow.classList.remove('reverse');
        }

        panelToHide.classList.add('hidden-panel');
        panelToHide.classList.remove('visible-panel');

        setTimeout(() => {
            panelToShow.classList.remove('hidden-panel');
            panelToShow.classList.add('visible-panel');

            setTimeout(() => {
                panelToShow.classList.remove('reverse');
            }, 500);
        }, 100);
    }

    showRegisterBtn.addEventListener('click', (e) => {
        e.preventDefault();
        switchPanel(registerPanel, loginPanel);
    });

    showLoginBtn.addEventListener('click', (e) => {
        e.preventDefault();
        switchPanel(loginPanel, registerPanel, true);
    });

    forgotLink.addEventListener('click', function (e) {
        e.preventDefault();
        alert('Password reset link will be sent to your tourism office email.');
    });

    window.handleLogin = function (event) {
        event.preventDefault();
        const email = document.getElementById('loginEmail').value.trim();
        const password = document.getElementById('loginPassword').value;

        if (!email || !password) {
            alert('Please enter both email and password.');
            return;
        }

        const submitBtn = document.getElementById('loginBtn');
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span>Signing in…</span> <i class="fas fa-spinner fa-spin"></i>';

        var payload = { username: email, password: password };
        sendLogin(payload, submitBtn);
    };

    function sendLogin(payload, submitBtn) {
        fetch('login.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (data.success) {
                    submitBtn.classList.add('success');
                    submitBtn.innerHTML = '<span>Welcome!</span> <i class="fas fa-check"></i>';
                    setTimeout(function () {
                        window.location.href = 'Cadiz_Go.php';
                    }, 600);
                } else {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<span>Access Portal</span> <i class="fas fa-arrow-right"></i>';
                    alert(data.message || 'Login failed. Please check your credentials.');
                }
            })
            .catch(function (err) {
                console.error(err);
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<span>Access Portal</span> <i class="fas fa-arrow-right"></i>';
                alert('Network error. Please try again.');
            });
    }

    window.handleRegister = function (event) {
        event.preventDefault();
        const name = document.getElementById('regName').value.trim();
        const email = document.getElementById('regEmail').value.trim();
        const password = document.getElementById('regPassword').value;
        const confirm = document.getElementById('regConfirmPassword').value;

        if (!name || !email || !password || !confirm) {
            alert('Please fill in all fields.');
            return;
        }
        if (password.length < 8) {
            alert('Password must be at least 8 characters.');
            return;
        }
        if (password !== confirm) {
            alert('Passwords do not match.');
            return;
        }

        const submitBtn = document.getElementById('registerBtn');
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span>Creating account…</span> <i class="fas fa-spinner fa-spin"></i>';

        fetch('register.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                full_name: name,
                username: email,
                password: password,
                location: 'Cadiz City'
            })
        })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (data.success) {
                    submitBtn.classList.add('success');
                    submitBtn.innerHTML = '<span>Account created!</span> <i class="fas fa-check"></i>';
                    setTimeout(function () {
                        window.location.href = 'Cadiz_Go.php';
                    }, 600);
                } else {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<span>Create account</span> <i class="fas fa-user-plus"></i>';
                    alert(data.message || 'Registration failed. Please try again.');
                }
            })
            .catch(function (err) {
                console.error(err);
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<span>Create account</span> <i class="fas fa-user-plus"></i>';
                alert('Network error. Please try again.');
            });
    };

    document.querySelectorAll('.terms-text a').forEach(link => {
        link.addEventListener('click', function (e) {
            e.preventDefault();
            alert('Tourism office policy document.');
        });
    });

})();