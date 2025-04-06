<?php
session_start();

if (!isset($_SESSION["user"])) {
    header("Location: login.php");
    exit();
}

require_once "database.php";
require_once "gemini_api.php";

$user_email = $_SESSION["user"];

// Fetch user details
$stmt = $conn->prepare("SELECT age, income_categories, income_amounts, deduction_categories, deduction_amounts FROM user_details WHERE email = ?");
$stmt->bind_param("s", $user_email);
$stmt->execute();
$stmt->bind_result($age, $income_categories, $income_amounts, $deduction_categories, $deduction_amounts);
$stmt->fetch();
$stmt->close();

// Convert comma-separated values to arrays
$income_cat_arr = explode(",", $income_categories);
$income_amt_arr = explode(",", $income_amounts);
$deduction_cat_arr = explode(",", $deduction_categories);
$deduction_amt_arr = explode(",", $deduction_amounts);

// Combine categories with their amounts
$income_details = [];
foreach ($income_cat_arr as $i => $cat) {
    $amt = $income_amt_arr[$i] ?? 0;
    $income_details[] = "$cat (₹$amt)";
}

$deduction_details = [];
foreach ($deduction_cat_arr as $i => $cat) {
    $amt = $deduction_amt_arr[$i] ?? 0;
    $deduction_details[] = "$cat (₹$amt)";
}

// Build the context string
$user_context = "User details: Age: $age. ";
$user_context .= "Income sources: " . implode(", ", $income_details) . ". ";
$user_context .= "Deductions: " . implode(", ", $deduction_details) . ".";

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["message"])) {
    $user_message = $_POST["message"];
    $prompt = "$user_context User question: $user_message";
    $ai_response = getGeminiResponse($prompt);

    echo json_encode(["response" => $ai_response]);
    exit();
}
?>
