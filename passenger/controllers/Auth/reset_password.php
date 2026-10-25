<style>
body{
    background:#fef8f3;
    font-family:'Segoe UI',sans-serif;
    display:flex;
    justify-content:center;
    align-items:center;
    height:100vh;
    margin:0;
}

.card{
    width:90%;
    max-width:380px;
    background:#fef8f3;
    border:2px solid #c7c28a;
    border-radius:16px;
    padding:25px;
}

h3{
    text-align:center;
    color:#2e4836;
}

input{
    width:100%;
    padding:10px;
    margin-top:10px;
    border:1px solid #c7c28a;
    border-radius:10px;
    background:#f9f4da;
}

button{
    width:100%;
    margin-top:15px;
    padding:10px;
    border:none;
    border-radius:999px;
    background:#2e4836;
    color:#fff;
}
</style>

<div class="card">
    <h3>Reset Password</h3>

    <form method="POST" action="/passenger/forgot-password/update">

        <input type="text" name="code" placeholder="Enter Code" required>
        <input type="password" name="password" placeholder="New Password" required>

        <button type="submit">Update Password</button>

    </form>
</div>