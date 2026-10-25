<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Maintenance</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: #eceed9;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            font-family: system-ui, -apple-system, sans-serif;
        }
        .maintenance-card {
            background: #fff;
            border-radius: 24px;
            padding: 48px;
            text-align: center;
            box-shadow: 0 8px 32px rgba(0,0,0,0.08);
            max-width: 480px;
        }
        .icon {
            font-size: 64px;
            margin-bottom: 16px;
        }
        h1 {
            color: #295738;
            font-weight: 700;
            font-size: 1.75rem;
        }
        p {
            color: #6c757d;
            margin-bottom: 8px;
        }
    </style>
</head>
<body>
    <div class="maintenance-card">
        <div class="icon">🔧</div>
        <h1>Under Maintenance</h1>
        <p>The system is currently undergoing scheduled maintenance.</p>
        <p class="text-muted small">Please check back later. Super administrators can still access the system.</p>
        <a href="/public/login/index.php" class="btn btn-sm mt-3" style="background:#295738;color:white;border:none;border-radius:50px;padding:8px 24px;">
            Back to Login
        </a>
    </div>
</body>
</html>
