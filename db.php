<?php
$conn = new mysqli("localhost", "root", "", "campus_app");
if ($conn->connect_error) {
  die("Database error");
}
?>

