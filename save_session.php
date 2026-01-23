<?php
require "db.php";

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

$user_id   = $_POST['user_id'] ?? null;
$distance  = $_POST['distance'] ?? null;
$duration  = $_POST['duration'] ?? null;
$calories  = $_POST['calories'] ?? null;
$avg_speed = $_POST['avg_speed'] ?? null;

if (!$user_id || !$distance || !$duration) {
  http_response_code(400);
  echo json_encode(["status" => "missing_fields"]);
  exit;
}

$stmt = $conn->prepare(
  "INSERT INTO jog_sessions 
   (user_id, distance_km, duration_sec, calories, avg_speed)
   VALUES (?,?,?,?,?)"
);

$stmt->bind_param(
  "ididi",
  $user_id,
  $distance,
  $duration,
  $calories,
  $avg_speed
);

if ($stmt->execute()) {
  echo json_encode(["status" => "saved"]);
} else {
  http_response_code(500);
  echo json_encode(["status" => "db_error"]);
}

