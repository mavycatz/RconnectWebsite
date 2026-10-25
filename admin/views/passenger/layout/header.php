<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <link rel="icon" type="image/png" href="/assets/images/logo/logoR.png">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
         <title><?= htmlspecialchars($passengerPageTitle ?? 'Passenger Management', ENT_QUOTES, 'UTF-8') ?></title>

        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
         <link href="https://fonts.googleapis.com/css2?family=League+Spartan:wght@400;700&family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
         <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
        <link rel="stylesheet" href="/public/login/custom-alert.css">
        <style>
            /* navbaradmin.php also starts a body fade from opacity 0 mid-body,
               which restarts after first paint. Keep this page static. */
            body { animation: none !important; }
        </style>
