<?php
require "config.php";
require "db.php";
require 'vendor/autoload.php';

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

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

/* 🔹 Check user */
$stmt = $conn->prepare(
  "SELECT id, name, profile_name, profile_image FROM users WHERE email=?"
);
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
  // First login
  $insert = $conn->prepare(
    "INSERT INTO users (google_id, name, email) VALUES (?,?,?)"
  );
  $insert->bind_param("sss", $google_id, $name, $email);
  $insert->execute();

  $user_id = $insert->insert_id;

  $row = [
    "id" => $user_id,
    "name" => $name,
    "profile_name" => null,
    "profile_image" => null
  ];
} else {
  $row = $result->fetch_assoc();
}

/* 🔹 Decide final display name */
$displayName = $row['profile_name'] ?: $row['name'];

echo json_encode([
  "status" => "success",
  "user_id" => $row['id'],
  "email" => $email,
  "name" => $displayName,
  "profile_image" => $row['profile_image']
]);

