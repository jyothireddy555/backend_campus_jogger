<?php
require "db.php";

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

$user_id = $_GET['user_id'] ?? null;

if (!$user_id) {
    http_response_code(400);
    echo json_encode(["status" => "missing_user_id"]);
    exit;
}

$today = date("Y-m-d");
$yesterday = date("Y-m-d", strtotime("-1 day"));

/* =========================
   1️⃣ FETCH USER STREAK DATA
   ========================= */

$userStmt = $conn->prepare(
    "SELECT current_streak, last_jog_date
     FROM users
     WHERE id = ?"
);
$userStmt->bind_param("i", $user_id);
$userStmt->execute();
$user = $userStmt->get_result()->fetch_assoc();

if (!$user) {
    echo json_encode(["status" => "user_not_found"]);
    exit;
}

$currentStreak = (int)($user['current_streak'] ?? 0);
$lastJogDate   = $user['last_jog_date'];

/* =========================
   2️⃣ CHECK IF STREAK BROKEN
   ========================= */

if ($lastJogDate) {
    if ($lastJogDate !== $today && $lastJogDate !== $yesterday) {
        // ❌ Streak broken → reset
        $currentStreak = 0;

        $resetStmt = $conn->prepare(
            "UPDATE users SET current_streak = 0 WHERE id = ?"
        );
        $resetStmt->bind_param("i", $user_id);
        $resetStmt->execute();
    }
}

/* =========================
   3️⃣ FETCH TODAY DISTANCE
   ========================= */

$distStmt = $conn->prepare(
    "SELECT distance_km
     FROM jog_sessions
     WHERE user_id = ? AND session_date = ?"
);
$distStmt->bind_param("is", $user_id, $today);
$distStmt->execute();
$distRow = $distStmt->get_result()->fetch_assoc();

$todayKm = floatval($distRow['distance_km'] ?? 0);

/* =========================
   4️⃣ FINAL RESPONSE
   ========================= */

echo json_encode([
    "status" => "success",
    "current_streak" => $currentStreak,
    "today_km" => $todayKm
]);

