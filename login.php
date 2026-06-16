<?php
include("config.php");
session_start();

$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $mynickname = mysqli_real_escape_string($db, $_POST['nickname']);
    $mypassword = mysqli_real_escape_string($db, $_POST['password']);

    $sql = "SELECT * FROM userDetails_TB WHERE nickName = '$mynickname' AND password = '$mypassword'";
    $result = mysqli_query($db, $sql);
    $count = mysqli_num_rows($result);

    if ($count == 1) {
        $row = mysqli_fetch_assoc($result);
        $_SESSION['login_user'] = $mynickname;
        $_SESSION['userID']     = $row['userID']; 
        header("location: dashboard.php");
        exit();
    } else {
        $error ="Invalid nickname or password!";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="templates/style.css">
    <title>Login your Account</title>
</head>
<body>
    <div class="container">
        <h2>Login your Account</h2>

        <?php if (!empty($error)): ?>
            <div class="error-box">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <form action="" method="post">
            <div class="fillUP">
                <label>Nickname</label>
                <input type="text" name="nickname" required>
            </div>
            <div class="fillUP">
                <label>Password</label>
                <input type="password" name="password" required>
            </div>
            <button class="btn" type="submit">Login</button>
            <div class="signLink">
                <p>Don't have an account? <a href="signup.php">Sign up here</a>.</p>
            </div>
        </form>
    </div>
</body>
</html>
