<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");
file_put_contents("debug.txt", print_r($_FILES, true));


require "db.php";

$email = $_POST['email'] ?? null;
$profile_name = $_POST['profile_name'] ?? null;

if (!$email) {
    echo json_encode(["status" => "error", "message" => "Email required"]);
    exit;
}

// Fetch existing image (if any)
$oldImage = null;
$check = $conn->prepare("SELECT profile_image FROM users WHERE email=?");
$check->bind_param("s", $email);
$check->execute();
$res = $check->get_result();
if ($res->num_rows > 0) {
    $row = $res->fetch_assoc();
    $oldImage = $row['profile_image'];
}

$newImagePath = $oldImage;

// Handle image upload (optional)
if (isset($_FILES['profile_image'])) {

    $targetDir = "uploads/";
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0777, true);
    }

    $fileName = time() . "_" . basename($_FILES["profile_image"]["name"]);
    $targetFile = $targetDir . $fileName;

    if (move_uploaded_file($_FILES["profile_image"]["tmp_name"], $targetFile)) {

        // Delete old image if exists
        if ($oldImage && file_exists($oldImage)) {
            unlink($oldImage);
        }

        $newImagePath = $fileName;
    }
}

// Update DB
$stmt = $conn->prepare(
    "UPDATE users SET profile_name=?, profile_image=? WHERE email=?"
);
$stmt->bind_param("sss", $profile_name, $newImagePath, $email);
$stmt->execute();

echo json_encode([
    "status" => "success",
    "profile_name" => $profile_name,
    "profile_image" => $newImagePath
]);

