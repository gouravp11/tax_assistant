<?php
// Start the session to access user data
session_start();

// Redirect users who are not logged in to the login page
if (!isset($_SESSION["user"])) {
    header("Location: login.php");
    exit();
}

// Include database connection and Gemini API integration
require_once "database.php";
require_once "gemini_api.php";

$user_email = $_SESSION["user"];

// Fetch user details from the database
$stmt = $conn->prepare("SELECT age, income_categories, income_amounts, deduction_categories, deduction_amounts FROM user_details WHERE email = ?");
$stmt->bind_param("s", $user_email);
$stmt->execute();
$stmt->bind_result($age, $income_categories, $income_amounts, $deduction_categories, $deduction_amounts);
$stmt->fetch();
$stmt->close();

// Prepare user context for the chatbot
$user_context = "User details: Age: $age. Income sources: " . implode(", ", explode(",", $income_categories)) . ". Deductions: " . implode(", ", explode(",", $deduction_categories)) . ".";

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["message"])) {
    // Handle user message and generate a response using the Gemini API
    $user_message = $_POST["message"];
    $prompt = "$user_context User question: $user_message";
    $ai_response = getGeminiResponse($prompt);

    // Return the AI response as JSON
    echo json_encode(["response" => $ai_response]);
    exit();
}
?>