import { renderHeader } from './header.js';

const root = document.getElementById('root');

root.innerHTML = `
  <div class="login-container">
    ${renderHeader()} <!-- imported header -->
    <form id="loginForm" class="login-form">
        <input type="text" name="username" placeholder="Username" required />
        <input type="password" name="password" placeholder="Password" required />
        <button type="submit">Login</button>
        <p id="error" class="error"></p>
    </form>
  </div>
`;

document.getElementById('loginForm').addEventListener('submit', async e => {
    e.preventDefault();
    const form = e.target;
    const data = {
        username: form.username.value,
        password: form.password.value
    };

    try {
        const res = await fetch('/login/index.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        const result = await res.json();

        if (result.success) {
            window.location.href = result.role === 'admin' ? '/admin/' : '/user/';
        } else {
            document.getElementById('error').textContent = result.message;
        }
    } catch (err) {
        document.getElementById('error').textContent = 'Server error';
        console.error(err);
    }
});
