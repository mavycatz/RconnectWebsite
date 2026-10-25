<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo "<pre>";
    echo "METHOD: POST\n";
    print_r($_POST);
    exit;
}
?>

<!DOCTYPE html>
<html>
<body>

<form method="POST">
    <input type="text" name="sample" value="123">
    <button type="submit">SUBMIT</button>
</form>

</body>
</html>