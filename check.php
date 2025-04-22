<?php
$hash = '$2y$12$8SPygoFkwyh5VYK4SkiukuuYuLyrtrYK/rGOHFAY1ji9a37Q1l.vq';
$password = 'password'; // try your guess here

if (password_verify($password, $hash)) {
    echo "Correct password: $password\n";
} else {
    echo "Incorrect.\n";
}
?>
