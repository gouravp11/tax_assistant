<?php
// Include Composer autoloader and configuration file
require_once __DIR__ . '/vendor/autoload.php';
require_once "config.php";

use GeminiAPI\Client;
use GeminiAPI\Resources\ModelName;
use GeminiAPI\Resources\Parts\TextPart;

// Function to get a response from the Gemini API
function getGeminiResponse($prompt){
    // Initialize the Gemini API client with the API key
    $client = new Client(GEMINI_API_KEY);

    // Generate content using the Gemini API
    $response = $client->withV1BetaVersion()
        ->generativeModel(ModelName::GEMINI_1_5_FLASH)
        ->withSystemInstruction('You are a tax advisor who keeps responses constant on same data.')
        ->generateContent(
            new TextPart($prompt), // Use the $prompt parameter
        );

    // Return the generated text response
    return $response->text();
}
?>

