<?php
require "db.php";

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

$user_id   = $_POST['user_id'] ?? null;
$distance  = $_POST['distance'] ?? null;   // km
$duration  = $_POST['duration'] ?? null;   // seconds
$calories  = $_POST['calories'] ?? 0;
$avg_speed = $_POST['avg_speed'] ?? 0;

if (!$user_id || !$distance || !$duration) {
    http_response_code(400);
    echo json_encode(["status" => "missing_fields"]);
    exit;
}

$today = date("Y-m-d");

/* =========================
   1️⃣ MERGE DAILY SESSION
   ========================= */

$check = $conn->prepare(
    "SELECT distance_km, duration_sec 
     FROM jog_sessions 
     WHERE user_id = ? AND session_date = ?"
);
$check->bind_param("is", $user_id, $today);
$check->execute();
$result = $check->get_result();

if ($row = $result->fetch_assoc()) {

    // Update existing daily row
    $newDistance = $row['distance_km'] + $distance;
    $newDuration = $row['duration_sec'] + $duration;
    $newAvgSpeed = $newDistance / ($newDuration / 3600);

    $update = $conn->prepare(
        "UPDATE jog_sessions
         SET distance_km = ?, 
             duration_sec = ?, 
             calories = calories + ?, 
             avg_speed = ?
         WHERE user_id = ? AND session_date = ?"
    );

    $update->bind_param(
        "diidis",
        $newDistance,
        $newDuration,
        $calories,
        $newAvgSpeed,
        $user_id,
        $today
    );

    if (!$update->execute()) {
        http_response_code(500);
        echo json_encode(["status" => "db_error"]);
        exit;
    }

} else {

    // Insert new row for today
    $insert = $conn->prepare(
        "INSERT INTO jog_sessions
        (user_id, distance_km, duration_sec, calories, avg_speed, session_date)
        VALUES (?,?,?,?,?,?)"
    );

    $insert->bind_param(
        "idiids",
        $user_id,
        $distance,
        $duration,
        $calories,
        $avg_speed,
        $today
    );

    if (!$insert->execute()) {
        http_response_code(500);
        echo json_encode(["status" => "db_error"]);
        exit;
    }
}

/* =========================
   2️⃣ CALCULATE TODAY TOTAL
   ========================= */

$totalStmt = $conn->prepare(
    "SELECT distance_km 
     FROM jog_sessions 
     WHERE user_id = ? AND session_date = ?"
);
$totalStmt->bind_param("is", $user_id, $today);
$totalStmt->execute();
$totalRow = $totalStmt->get_result()->fetch_assoc();

$totalKm = floatval($totalRow['distance_km'] ?? 0);

/* =========================
   3️⃣ 600 METERS RULE
   ========================= */

if ($totalKm < 0.01) {
    echo json_encode([
        "status" => "saved",
        "streak_updated" => false,
        "today_km" => $totalKm
    ]);
    exit;
}

/* =========================
   4️⃣ FETCH USER STREAK
   ========================= */

$userStmt = $conn->prepare(
    "SELECT current_streak, last_jog_date
     FROM users WHERE id = ?"
);
$userStmt->bind_param("i", $user_id);
$userStmt->execute();
$user = $userStmt->get_result()->fetch_assoc();

/* =========================
   5️⃣ PREVENT DOUBLE COUNT
   ========================= */

if ($user['last_jog_date'] === $today) {
    echo json_encode([
        "status" => "saved",
        "streak_updated" => false,
        "today_km" => $totalKm,
        "current_streak" => $user['current_streak']
    ]);
    exit;
}

/* =========================
   6️⃣ UPDATE STREAK
   ========================= */

$yesterday = date("Y-m-d", strtotime("-1 day"));

if (!$user['last_jog_date']) {
    $newStreak = 1;
} elseif ($user['last_jog_date'] === $yesterday) {
    $newStreak = $user['current_streak'] + 1;
} else {
    $newStreak = 1;
}

$updateStreak = $conn->prepare(
    "UPDATE users
     SET current_streak = ?, last_jog_date = ?
     WHERE id = ?"
);
$updateStreak->bind_param("isi", $newStreak, $today, $user_id);
$updateStreak->execute();

/* =========================
   7️⃣ FINAL RESPONSE
   ========================= */

echo json_encode([
    "status" => "saved",
    "streak_updated" => true,
    "today_km" => $totalKm,
    "current_streak" => $newStreak
]);

