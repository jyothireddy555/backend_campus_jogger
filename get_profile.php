<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");
require "db.php";

$email = $_POST['email'];

$stmt = $conn->prepare(
  "SELECT 
     COALESCE(profile_name, name) AS name,
     profile_image
   FROM users
   WHERE email=?"
);
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();

if ($result) {
  echo json_encode([
    "status" => "success",
    "name" => $result['name'],
    "profile_image" => $result['profile_image']
  ]);
} else {
  echo json_encode(["status" => "not_found"]);
}

