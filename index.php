<?php
session_start();

// Ensure the user is logged in; otherwise, redirect to the login page
if (!isset($_SESSION["user"])) {
    header("Location: login.php");
    exit();
}
require_once "database.php";
$user_email = $_SESSION["user"];

// Create the user_details table if it doesn't exist
$conn->query("CREATE TABLE IF NOT EXISTS user_details (
    email VARCHAR(255) PRIMARY KEY,
    age INT NOT NULL,
    income_categories TEXT,
    income_amounts TEXT,
    deduction_categories TEXT,
    deduction_amounts TEXT
)");

// Function to fetch user details from the database
function getUserDetails($conn, $user_email) {
    $stmt = $conn->prepare("SELECT age, income_categories, income_amounts, deduction_categories, deduction_amounts FROM user_details WHERE email = ?");
    $stmt->bind_param("s", $user_email);
    $stmt->execute();
    $age = $income_categories = $income_amounts = $deduction_categories = $deduction_amounts = null;
    $stmt->bind_result($age, $income_categories, $income_amounts, $deduction_categories, $deduction_amounts);
    $user_exists = $stmt->fetch();
    $stmt->close();

    if ($user_exists) {
        return [
            "age" => $age,
            "income_categories" => explode(",", $income_categories),
            "income_amounts" => explode(",", $income_amounts),
            "deduction_categories" => explode(",", $deduction_categories),
            "deduction_amounts" => explode(",", $deduction_amounts),
        ];
    }

    return null;
}

// Fetch existing user details or initialize empty arrays
$stmt = $conn->prepare("SELECT age, income_categories, income_amounts, deduction_categories, deduction_amounts FROM user_details WHERE email = ?");
$stmt->bind_param("s", $user_email);
$stmt->execute();
$stmt->bind_result($existing_age, $existing_income_categories, $existing_income_amounts, $existing_deduction_categories, $existing_deduction_amounts);
$user_exists = $stmt->fetch();
$stmt->close();

$income_categories = $user_exists ? explode(",", $existing_income_categories) : [];
$income_amounts = $user_exists ? explode(",", $existing_income_amounts) : [];
$deduction_categories = $user_exists ? explode(",", $existing_deduction_categories) : [];
$deduction_amounts = $user_exists ? explode(",", $existing_deduction_amounts) : [];

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["submit"])) {
    // Handle form submission to save or update user details
    $age = intval($_POST["age"]);

    $income_categories = [];
    $income_amounts = [];
    $deduction_categories = [];
    $deduction_amounts = [];

    foreach ($_POST as $key => $value) {
        if (strpos($key, "income-category-") !== false && !empty($value)) {
            $index = str_replace("income-category-", "", $key);
            $income_categories[] = $conn->real_escape_string($value);
            $income_amounts[] = intval($_POST["income-$index"] ?? 0);
        }

        if (strpos($key, "deduction-category-") !== false && !empty($value)) {
            $index = str_replace("deduction-category-", "", $key);
            $deduction_categories[] = $conn->real_escape_string($value);
            $deduction_amounts[] = intval($_POST["deduction-$index"] ?? 0);
        }
    }

    $income_categories_str = implode(",", $income_categories);
    $income_amounts_str = implode(",", $income_amounts);
    $deduction_categories_str = implode(",", $deduction_categories);
    $deduction_amounts_str = implode(",", $deduction_amounts);

    // Check if user details already exist
    $stmt = $conn->prepare("SELECT email FROM user_details WHERE email = ?");
    $stmt->bind_param("s", $user_email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        // Update existing record
        $stmt = $conn->prepare("UPDATE user_details 
                                SET age = ?, income_categories = ?, income_amounts = ?, deduction_categories = ?, deduction_amounts = ?
                                WHERE email = ?");
        $stmt->bind_param("ssssss", $age, $income_categories_str, $income_amounts_str, $deduction_categories_str, $deduction_amounts_str, $user_email);
    } else {
        // Insert new record
        $stmt = $conn->prepare("INSERT INTO user_details (email, age, income_categories, income_amounts, deduction_categories, deduction_amounts)
                                VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssss", $user_email, $age, $income_categories_str, $income_amounts_str, $deduction_categories_str, $deduction_amounts_str);
    }

    $stmt->execute();
    $stmt->close();

    // Fetch updated user details
    $user_details = getUserDetails($conn, $user_email);
} else {
    // Fetch user details for GET requests
    $user_details = getUserDetails($conn, $user_email);
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["analyze"])) {
    // Analyze user details using the Gemini API
    $user_details = getUserDetails($conn, $user_email);

    if ($user_details) {
        require_once "gemini_api.php";
        $prompt = "Calculate tax for a person aged {$user_details['age']} with the following details: 
            Income sources: " . implode(", ", array_map(fn($c, $a) => "$c: ₹$a", $user_details['income_categories'], $user_details['income_amounts'])) . ". 
            Deductions: " . implode(", ", array_map(fn($c, $a) => "$c: ₹$a", $user_details['deduction_categories'], $user_details['deduction_amounts'])) . ". 
            Provide the estimated tax liability as a single number first, followed by very concise tax-saving suggestions using simple vocabulary.";

        $aiResponse = getGeminiResponse($prompt);

        // Extract the estimated tax liability and suggestions
        list($estimatedTax, $taxSuggestions) = explode("\n", $aiResponse, 2);

        $aiResponseHtml = "
            <div class='ai-response mt-6 p-4 border border-gray-300 rounded-md bg-gray-50'>
                <h2 class='text-lg font-bold'>AI Response:</h2>
                <p class='text-gray-700'><strong>Estimated Tax Liability:</strong> ₹$estimatedTax</p>
                <p class='text-gray-700'><strong>Tax-Saving Suggestions:</strong> $taxSuggestions</p>
            </div>";
    } else {
        $aiResponseHtml = "
            <div class='ai-response mt-6 p-4 border border-red-300 rounded-md bg-red-50'>
                <h2 class='text-lg font-bold text-red-500'>Error:</h2>
                <p class='text-gray-700'>No user details found. Please update your details first.</p>
            </div>";
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <!-- Metadata and Tailwind CSS for styling -->
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <title>User Dashboard</title>
</head>

<body>
    <!-- Main container -->
    <div class="container w-screen">
        <!-- Navigation bar -->
        <nav class="bg-gray-100 p-4 flex items-center justify-between">
            <h1 class="text-xl font-bold text-indigo-500">Taxify</h1>
            <button id="show-update-form-btn"
                    class="bg-indigo-500 <?php echo $user_exists ? "inline-block" : "hidden"; ?> text-white text-sm font-bold py-2 px-4 rounded-md hover:bg-indigo-600 transition duration-300">
                    Update your details
            </button>
            <a href="logout.php"
                class="ml-auto inline-block self-end bg-red-500 text-white text-sm font-bold py-1 px-4  rounded-md hover:bg-red-600 transition duration-300">Logout</a>
        </nav>

        <!-- Main content -->
        <main class="p-4">
            <!-- Welcome message -->
            <h1 class="text-3xl font-bold opacity-90 mb-10">Welcome to Dashboard</h1>
            <div class="flex gap-4">
                <button id="show-add-form-btn"
                    class="bg-indigo-500 <?php echo $user_exists ? "hidden" : "inline-block"; ?> text-white text-sm font-bold py-2 px-4 rounded-md hover:bg-indigo-600 transition duration-300">
                    Click to add your details
                </button>
                <button id="open-chatbot-btn"
                    class="bg-green-500 text-white text-sm font-bold py-2 px-4 rounded-md hover:bg-green-600 transition duration-300">
                    Open Chatbot
                </button>
            </div>

            <!-- User details form -->
            <div class="user-details w-1/2 hidden" data-details-filled="<?php echo $user_exists ? 'true' : 'false'; ?>">
                <h1 class="text-lg font-bold">
                    <?php echo $user_exists ? "Update your details:" : "Enter your details:"; ?></h1>
                <form class="w-full" method="POST" action="index.php" id="user-details-form">
                    <h6 class="font-bold">Your age:</h6>
                    <input
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500"
                        type="number" id="age" name="age" placeholder="00"
                        value="<?php echo $user_exists ? $existing_age : ''; ?>">
                    <div class="income-sources mt-6">
                        <h6 class="font-bold">Income sources:</h6>
                        <?php
                        if ($user_exists) {
                            foreach ($income_categories as $index => $category) {
                                echo '<div class="income-' . ($index + 1) . ' flex gap-2 mb-2">
                                <input class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500"
                                    type="text" id="income-category-' . ($index + 1) . '" name="income-category-' . ($index + 1) . '" placeholder="Income Category" value="' . $category . '">
                                <input class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500"
                                    type="number" id="income-' . ($index + 1) . '" name="income-' . ($index + 1) . '" placeholder="00000" value="' . $income_amounts[$index] . '">
                            </div>';
                            }
                        } else {
                            echo '<div class="income-1 flex gap-2 mb-2">
                            <input class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500"
                                type="text" id="income-category-1" name="income-category-1" placeholder="Income Category">
                            <input class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500"
                                type="number" id="income-1" name="income-1" placeholder="00000">
                        </div>';
                        }
                        ?>
                        <button class="text-indigo-500 hover:underline">Add more</button>
                    </div>
                    <div class="deductions mt-4">
                        <h6 class="font-bold">Deductions:</h6>
                        <?php
                        if ($user_exists) {
                            foreach ($deduction_categories as $index => $category) {
                                echo '<div class="deduction-' . ($index + 1) . ' flex gap-2 mb-2">
                                <input class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500"
                                    type="text" id="deduction-category-' . ($index + 1) . '" name="deduction-category-' . ($index + 1) . '" placeholder="Deduction Category" value="' . $category . '">
                                <input class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500"
                                    type="number" id="deduction-' . ($index + 1) . '" name="deduction-' . ($index + 1) . '" placeholder="00000" value="' . $deduction_amounts[$index] . '">
                            </div>';
                            }
                        } else {
                            echo '<div class="deduction-1 flex gap-2 mb-2">
                            <input class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500"
                                type="text" id="deduction-category-1" name="deduction-category-1" placeholder="Deduction Category">
                            <input class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500"
                                type="number" id="deduction-1" name="deduction-1" placeholder="00000">
                        </div>';
                        }
                        ?>
                        <button class="text-indigo-500 hover:underline">Add more</button>
                    </div>
                    <button
                        class="bg-indigo-500 text-white text-sm font-bold py-2 px-4 rounded-md hover:bg-indigo-600 transition duration-300"
                        type="submit" name="submit">Submit</button>
                </form>
            </div>

            <!-- Display submitted user details -->
            <?php if (isset($user_details) && $user_details): ?>
                <div class="mt-6">
                    <h2 class="text-xl font-bold mb-4">Your Submitted Details:</h2>
                    <table class="table-auto w-full border-collapse border border-gray-300">
                        <thead>
                            <tr class="bg-gray-100">
                                <th class="border border-gray-300 px-4 py-2 text-left">Category</th>
                                <th class="border border-gray-300 px-4 py-2 text-left">Details</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td class="border border-gray-300 px-4 py-2">Age</td>
                                <td class="border border-gray-300 px-4 py-2"><?php echo $user_details['age']; ?></td>
                            </tr>
                            <tr class="bg-gray-50">
                                <th class="border border-gray-300 px-4 py-2 text-left">Income Sources</th>
                                <th class="border border-gray-300 px-4 py-2 text-left">Income Amount</th>
                            </tr>
                            <?php foreach ($user_details['income_categories'] as $index => $category): ?>
                                <tr>
                                    <td class="border border-gray-300 px-4 py-2"><?php echo $category ?></td>
                                    <td class="border border-gray-300 px-4 py-2"><?php echo $user_details['income_amounts'][$index] ?></td>
                                </tr>
                            <?php endforeach ?>
                            <tr class="bg-gray-50">
                                <th class="border border-gray-300 px-4 py-2 text-left">Deduction Sources</th>
                                <th class="border border-gray-300 px-4 py-2 text-left">Deduction Amount</th>
                            </tr>
                            <?php foreach ($user_details['deduction_categories'] as $index => $category): ?>
                                <tr>
                                    <td class="border border-gray-300 px-4 py-2"><?php echo $category ?></td>
                                    <td class="border border-gray-300 px-4 py-2"><?php echo $user_details['deduction_amounts'][$index] ?></td>
                                </tr>
                            <?php endforeach ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <!-- Analyze button -->
            <form method="POST" action="index.php" onsubmit="document.getElementById('loader').classList.remove('hidden');">
                <button
                    class="bg-indigo-500 text-white text-sm font-bold py-2 px-4 rounded-md hover:bg-indigo-600 transition duration-300"
                    type="submit" name="analyze">Analyze</button>
            </form>
            <?php if (isset($aiResponseHtml))
                echo $aiResponseHtml; ?>

            <!-- Chatbot modal -->
            <div id="chatbot-modal" class="hidden fixed inset-0 bg-gray-800 bg-opacity-50 flex items-center justify-center z-50">
                <div class="bg-white w-full max-w-lg h-3/4 flex flex-col border border-gray-300 rounded-lg overflow-hidden">
                    <div id="messages" class="flex-1 overflow-y-auto p-4 bg-gray-50">
                        <!-- Chat messages will appear here -->
                    </div>
                    <div class="flex items-center border-t border-gray-300 p-2 bg-white">
                        <input type="text" id="user-input" placeholder="Type your message..." 
                            class="flex-1 border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring focus:ring-indigo-300" />
                        <button id="send-btn" 
                            class="ml-2 bg-indigo-500 text-white px-4 py-2 rounded-md hover:bg-indigo-600 transition duration-300">
                            Send
                        </button>
                    </div>
                </div>
            </div>

            <!-- Loader for processing -->
            <div id="loader"
                class="hidden fixed inset-0 bg-gray-800 bg-opacity-50 flex items-center justify-center z-50">
                <div class="animate-spin rounded-full h-12 w-12 border-4 border-t-4 border-gray-200 border-t-blue-500">
                </div>
            </div>
        </main>
    </div>

    <!-- JavaScript for interactivity -->
    <script>
    const chatbotModal = document.getElementById("chatbot-modal");
    const openChatbotBtn = document.getElementById("open-chatbot-btn");
    const messagesContainer = document.getElementById("messages");
    const userInput = document.getElementById("user-input");
    const sendBtn = document.getElementById("send-btn");

    openChatbotBtn.addEventListener("click", () => {
        chatbotModal.classList.remove("hidden");
    });

    // Close chatbot when clicking outside the modal
    chatbotModal.addEventListener("click", (event) => {
        if (event.target === chatbotModal) {
            chatbotModal.classList.add("hidden");
        }
    });

    function appendMessage(content, sender) {
        const messageDiv = document.createElement("div");
        messageDiv.classList.add("mb-3", "p-3", "rounded-lg", "max-w-xs", "w-fit");
        if (sender === "user") {
            messageDiv.classList.add("bg-indigo-500", "text-white", "ml-auto");
        } else {
            messageDiv.classList.add("bg-gray-200", "text-gray-800", "mr-auto");
        }
        messageDiv.textContent = content;
        messagesContainer.appendChild(messageDiv);
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
    }

    sendBtn.addEventListener("click", async () => {
        const message = userInput.value.trim();
        if (!message) return;

        appendMessage(message, "user");
        userInput.value = "";

        const response = await fetch("chatbot.php", {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body: `message=${encodeURIComponent(message)}`,
        });

        const data = await response.json();
        appendMessage(data.response, "bot");
    });
    </script>
    <script src="script.js"></script>
</body>

</html>