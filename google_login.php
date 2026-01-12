<?php
require "config.php";
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

require 'vendor/autoload.php';
require 'db.php';

$client = new Google_Client([
  'client_id' => GOOGLE_CLIENT_ID
]);

$id_token = $_POST['id_token'] ?? '';

$payload = $client->verifyIdToken($id_token);

if (!$payload) {
  http_response_code(401);
  echo json_encode(["status" => "invalid"]);
  exit;
}

$name = $payload['name'];
$email = $payload['email'];
$google_id = $payload['sub'];

/* 🔹 Check if user exists */
$stmt = $conn->prepare("SELECT * FROM users WHERE email=?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
  // First time login → insert
  $insert = $conn->prepare(
    "INSERT INTO users (google_id, name, email) VALUES (?,?,?)"
  );
  $insert->bind_param("sss", $google_id, $name, $email);
  $insert->execute();

  // Create a row object manually
  $row = [
    "name" => $name,
    "profile_name" => null,
    "profile_image" => null
  ];
} else {
  // Existing user → fetch DB row
  $row = $result->fetch_assoc();
}

/* 🔹 Decide final display name */
$displayName = $row['profile_name'] ?: $row['name'];

echo json_encode([
  "status" => "success",
  "email" => $email,
  "name" => $displayName,
  "profile_image" => $row['profile_image']
]);

