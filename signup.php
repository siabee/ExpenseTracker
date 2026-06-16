<?php
include("config.php");
session_start();

$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $myfirstname = mysqli_real_escape_string($db, $_POST['firstname']);
    $mylastname  = mysqli_real_escape_string($db, $_POST['lastname']);
    $mynickname  = mysqli_real_escape_string($db, $_POST['nickname']);
    $mypassword  = mysqli_real_escape_string($db, $_POST['password']);

    $sql = "INSERT INTO userDetails_TB (firstName, lastName, nickName, password) VALUES ('$myfirstname', '$mylastname', '$mynickname','$mypassword')";
    $result = mysqli_query($db, $sql);

    if ($result) {
        $_SESSION['create_user'] = $mynickname;
        header("location: login.php");
    } else {
        $error ="Error creating account!";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="templates/style.css">
    <title>Create an Account</title>
</head>
<body>
    <div class="container">
        <h2>Create an Account</h2>
        <form action="" method="post">
            <div class="fillUP">
                <label>First Name</label>
                <input type="text" name="firstname" required>
            </div>
            <div class="fillUP">
                <label>Last Name</label>
                <input type="text" name="lastname" required>
            </div>
            <div class="fillUP">
                <label>Nickname</label>
                <input type="text" name="nickname" required>
            </div>
            <div class="fillUP">
                <label>Password</label>
                <input type="password" name="password" required>
            </div>
            <button class="btn" type="submit">Sign Up</button>
            <div class="loginLink">
                <p>Already have an account? <a href="login.php">Login here</a>.</p>
            </div>
        </form>
    </div>
</body>
</html>
