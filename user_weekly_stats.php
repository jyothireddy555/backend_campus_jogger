<?php
require "db.php";
header("Content-Type: application/json");

$user_id = $_GET['user_id'] ?? null;

if (!$user_id) {
  echo json_encode(["error" => "user_id missing"]);
  exit;
}

/*
  Get last 7 days data
  - start_time = first session of the day
  - distance = sum of all sessions that day
*/
$query = "
SELECT 
  DATE(created_at) as day,
  MIN(TIME(created_at)) as start_time,
  SUM(distance_km) as total_distance
FROM jog_sessions
WHERE user_id = ?
  AND created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
GROUP BY DATE(created_at)
";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

/* Build map from DB */
$dataMap = [];
while ($row = $result->fetch_assoc()) {
  $day = $row['day'];
  $startTime = $row['start_time'];

  // convert HH:MM:SS → decimal hour
  if ($startTime) {
    [$h, $m] = explode(":", $startTime);
    $decimalTime = round($h + ($m / 60), 2);
  } else {
    $decimalTime = 0;
  }

  $dataMap[$day] = [
    "start_time" => $decimalTime,
    "distance" => round((float)$row['total_distance'], 2)
  ];
}

/* Generate last 7 days (even if empty) */
$days = [];
$startTimes = [];
$distances = [];

for ($i = 6; $i >= 0; $i--) {
  $date = date("Y-m-d", strtotime("-$i days"));
  $days[] = date("D", strtotime($date));

  if (isset($dataMap[$date])) {
    $startTimes[] = $dataMap[$date]['start_time'];
    $distances[] = $dataMap[$date]['distance'];
  } else {
    $startTimes[] = 0;
    $distances[] = 0;
  }
}

echo json_encode([
  "days" => $days,
  "start_times" => $startTimes,
  "distances" => $distances
]);

