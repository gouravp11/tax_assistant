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
    $stmt->close(); // CLOSE before re-preparing

    $stmt = $conn->prepare("UPDATE user_details 
        SET age = ?, income_categories = ?, income_amounts = ?, deduction_categories = ?, deduction_amounts = ?
        WHERE email = ?");
    $stmt->bind_param("isssss", $age, $income_categories_str, $income_amounts_str, $deduction_categories_str, $deduction_amounts_str, $user_email);
} else {
    $stmt->close(); // CLOSE before re-preparing

    $stmt = $conn->prepare("INSERT INTO user_details (email, age, income_categories, income_amounts, deduction_categories, deduction_amounts) 
        VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sissss", $user_email, $age, $income_categories_str, $income_amounts_str, $deduction_categories_str, $deduction_amounts_str);
}

if (!$stmt->execute()) {
    echo "SQL Error: " . $stmt->error;
} else {
    echo "Saved successfully!";
}

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
<?php
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Taxify - Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<body class="min-h-screen">
    <!-- Navigation -->
    <nav class="gradient-bg text-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16 items-center">
                <div class="flex items-center space-x-2">
                    <i class="fas fa-calculator text-white text-xl"></i>
                    <span class="text-xl font-bold">Taxify</span>
                </div>
                <div>
                    <a href="logout.php" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-indigo-700 bg-white hover:bg-gray-100 transition duration-150">
                        Logout
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Welcome Section -->
        <div class="text-center mb-10">
            <h1 class="text-3xl font-bold text-gray-800 mb-2">Welcome to Your Tax Dashboard</h1>
            <p class="text-gray-600 max-w-2xl mx-auto">Manage your tax information and get personalized recommendations</p>
        </div>

        <!-- Action Buttons -->
        <div class="flex flex-wrap justify-center gap-4 mb-10">
            <button id="show-add-form-btn" class="<?php echo $user_exists ? 'hidden' : 'inline-flex'; ?> items-center px-6 py-3 border border-transparent text-base font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition duration-150">
                <i class="fas fa-plus-circle mr-2"></i> Add Your Details
            </button>
            
            <button id="open-chatbot-btn" class="inline-flex items-center px-6 py-3 border border-transparent text-base font-medium rounded-md shadow-sm text-white bg-emerald-600 hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-emerald-500 transition duration-150">
                <i class="fas fa-robot mr-2"></i> Tax Assistant
            </button>
            
            <button id="show-update-form-btn" class="<?php echo $user_exists ? 'inline-flex' : 'hidden'; ?> items-center px-6 py-3 border border-transparent text-base font-medium rounded-md shadow-sm text-white bg-amber-500 hover:bg-amber-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-amber-500 transition duration-150">
                <i class="fas fa-edit mr-2"></i> Update Details
            </button>
        </div>

        <!-- User Details Form -->
        <div class="user-details hidden bg-white rounded-lg shadow-card p-6 mb-8 max-w-3xl mx-auto" data-details-filled="<?php echo $user_exists ? 'true' : 'false'; ?>">
            <h2 class="text-xl font-bold text-gray-800 mb-6">
                <?php echo $user_exists ? "Update Your Tax Details" : "Enter Your Tax Details"; ?>
            </h2>
            
            <form class="space-y-6" method="POST" action="index.php" id="user-details-form">
                <!-- Age Field -->
                <div>
                    <label for="age" class="block text-sm font-medium text-gray-700 mb-1">Your Age</label>
                    <input type="number" id="age" name="age" placeholder="Enter your age" 
                        value="<?php echo $user_exists ? $existing_age : ''; ?>"
                        class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500">
                </div>
                
                <!-- Income Sources Section -->
                <div class="income-sources">
                    <div class="flex justify-between items-center mb-3">
                        <label class="block text-sm font-medium text-gray-700">Income Sources</label>
                        <button type="button" class="text-sm font-medium text-indigo-600 hover:text-indigo-800 add-income-btn transition duration-150">
                            <i class="fas fa-plus mr-1"></i> Add Income
                        </button>
                    </div>
                    
                    <?php if ($user_exists): ?>
                        <?php foreach ($income_categories as $index => $category): ?>
                            <div class="income-row flex gap-4 mb-3 items-end">
                                <div class="flex-1">
                                    <input type="text" id="income-category-<?php echo $index + 1; ?>" 
                                        name="income-category-<?php echo $index + 1; ?>" 
                                        placeholder="Income Category" 
                                        value="<?php echo $category; ?>"
                                        class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500">
                                </div>
                                <div class="flex-1">
                                    <div class="relative">
                                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-500">₹</span>
                                        <input type="number" id="income-<?php echo $index + 1; ?>" 
                                            name="income-<?php echo $index + 1; ?>" 
                                            placeholder="Amount" 
                                            value="<?php echo $income_amounts[$index]; ?>"
                                            class="w-full pl-8 pr-4 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500">
                                    </div>
                                </div>
                                <button type="button" class="remove-income-btn text-red-500 hover:text-red-700 mb-2 transition duration-150">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="income-row flex gap-4 mb-3 items-end">
                            <div class="flex-1">
                                <input type="text" id="income-category-1" name="income-category-1" 
                                    placeholder="Income Category"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500">
                            </div>
                            <div class="flex-1">
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-500">₹</span>
                                    <input type="number" id="income-1" name="income-1" 
                                        placeholder="Amount"
                                        class="w-full pl-8 pr-4 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500">
                                </div>
                            </div>
                            <button type="button" class="remove-income-btn text-red-500 hover:text-red-700 mb-2 transition duration-150">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Deductions Section -->
                <div class="deductions">
                    <div class="flex justify-between items-center mb-3">
                        <label class="block text-sm font-medium text-gray-700">Deductions</label>
                        <button type="button" class="text-sm font-medium text-indigo-600 hover:text-indigo-800 add-deduction-btn transition duration-150">
                            <i class="fas fa-plus mr-1"></i> Add Deduction
                        </button>
                    </div>
                    
                    <?php if ($user_exists): ?>
                        <?php foreach ($deduction_categories as $index => $category): ?>
                            <div class="deduction-row flex gap-4 mb-3 items-end">
                                <div class="flex-1">
                                    <input type="text" id="deduction-category-<?php echo $index + 1; ?>" 
                                        name="deduction-category-<?php echo $index + 1; ?>" 
                                        placeholder="Deduction Category" 
                                        value="<?php echo $category; ?>"
                                        class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500">
                                </div>
                                <div class="flex-1">
                                    <div class="relative">
                                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-500">₹</span>
                                        <input type="number" id="deduction-<?php echo $index + 1; ?>" 
                                            name="deduction-<?php echo $index + 1; ?>" 
                                            placeholder="Amount" 
                                            value="<?php echo $deduction_amounts[$index]; ?>"
                                            class="w-full pl-8 pr-4 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500">
                                    </div>
                                </div>
                                <button type="button" class="remove-deduction-btn text-red-500 hover:text-red-700 mb-2 transition duration-150">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="deduction-row flex gap-4 mb-3 items-end">
                            <div class="flex-1">
                                <input type="text" id="deduction-category-1" name="deduction-category-1" 
                                    placeholder="Deduction Category"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500">
                            </div>
                            <div class="flex-1">
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-500">₹</span>
                                    <input type="number" id="deduction-1" name="deduction-1" 
                                        placeholder="Amount"
                                        class="w-full pl-8 pr-4 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500">
                                </div>
                            </div>
                            <button type="button" class="remove-deduction-btn text-red-500 hover:text-red-700 mb-2 transition duration-150">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Submit Button -->
                <div class="pt-4">
                    <button type="submit" name="submit" 
                        class="w-full flex justify-center items-center py-3 px-4 border border-transparent rounded-md shadow-sm text-base font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition duration-150">
                        <i class="fas fa-save mr-2"></i> Save Details
                    </button>
                </div>
            </form>
        </div>

        <!-- Display Submitted Details -->
        <?php if (isset($user_details) && $user_details): ?>
            <div class="bg-white rounded-lg shadow-card overflow-hidden mb-8 max-w-3xl mx-auto">
                <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
                    <h3 class="text-lg font-medium leading-6 text-gray-900">Your Tax Profile</h3>
                </div>
                <div class="px-6 py-4">
                    <dl class="grid grid-cols-1 gap-x-4 gap-y-4 sm:grid-cols-2">
                        <div class="sm:col-span-1">
                            <dt class="text-sm font-medium text-gray-500">Age</dt>
                            <dd class="mt-1 text-sm text-gray-900"><?php echo $user_details['age']; ?></dd>
                        </div>
                    </dl>
                </div>
                
                <!-- Income Sources Table -->
<div class="px-6 py-4 border-t border-gray-200">
    <h4 class="text-md font-medium text-gray-900 mb-3">Income Sources</h4>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Category</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount (₹)</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php foreach ($user_details['income_categories'] as $index => $category): ?>
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo $category ?></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            <?php echo !empty($user_details['income_amounts'][$index]) && is_numeric($user_details['income_amounts'][$index]) 
                                ? number_format(floatval($user_details['income_amounts'][$index])) 
                                : '0' ?>
                        </td>
                    </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Deductions Table -->
<div class="px-6 py-4 border-t border-gray-200">
    <h4 class="text-md font-medium text-gray-900 mb-3">Deductions</h4>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Category</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount (₹)</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php foreach ($user_details['deduction_categories'] as $index => $category): ?>
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo $category ?></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            <?php echo !empty($user_details['deduction_amounts'][$index]) && is_numeric($user_details['deduction_amounts'][$index]) 
                                ? number_format(floatval($user_details['deduction_amounts'][$index])) 
                                : '0' ?>
                        </td>
                    </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    </div>
</div>
            
            <!-- Analyze Button -->
            <div class="text-center mb-8">
                <form method="POST" action="index.php" onsubmit="document.getElementById('loader').classList.remove('hidden');">
                    <button type="submit" name="analyze" 
                        class="inline-flex items-center px-8 py-3 border border-transparent text-base font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition duration-150">
                        <i class="fas fa-chart-pie mr-2"></i> Analyze Tax Situation
                    </button>
                </form>
            </div>
        <?php endif; ?>

        <!-- AI Response -->
        <?php if (isset($aiResponseHtml)) echo $aiResponseHtml; ?>

        <!-- Chatbot Modal -->
        <div id="chatbot-modal" class="hidden fixed inset-0 bg-gray-800 bg-opacity-75 flex items-center justify-center z-50 p-4">
            <div class="bg-white w-full max-w-md h-[80vh] flex flex-col rounded-lg shadow-xl overflow-hidden transform transition-all">
                <div class="bg-indigo-600 px-4 py-3 flex justify-between items-center">
                    <h3 class="text-lg font-medium text-white">Tax Assistant</h3>
                    <button id="close-chatbot-btn" class="text-white hover:text-gray-200 focus:outline-none">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div id="messages" class="flex-1 overflow-y-auto p-4 bg-gray-50 space-y-3">
                    <div class="flex">
                        <div class="bg-gray-200 text-gray-800 p-3 rounded-lg max-w-xs">
                            Hello! I'm your tax assistant. How can I help you with your tax questions today?
                        </div>
                    </div>
                </div>
                <div class="border-t border-gray-200 p-3 bg-white">
                    <div class="flex items-center">
                        <input type="text" id="user-input" placeholder="Type your tax question..." 
                            class="flex-1 border border-gray-300 rounded-l-md px-4 py-2 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                        <button id="send-btn" 
                            class="bg-indigo-600 text-white px-4 py-2 rounded-r-md hover:bg-indigo-700 transition duration-150">
                            <i class="fas fa-paper-plane"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Loader -->
        <div id="loader" class="hidden fixed inset-0 bg-gray-800 bg-opacity-75 flex items-center justify-center z-50">
            <div class="bg-white p-8 rounded-lg shadow-xl flex flex-col items-center">
                <div class="animate-spin rounded-full h-16 w-16 border-t-2 border-b-2 border-indigo-500 mb-4"></div>
                <p class="text-gray-700 font-medium">Analyzing your tax information...</p>
            </div>
        </div>
    </main>

    <!-- JavaScript -->
    <script>
        // Toggle form visibility
        document.getElementById('show-add-form-btn').addEventListener('click', function() {
            document.querySelector('.user-details').classList.remove('hidden');
            this.classList.add('hidden');
            if (document.getElementById('show-update-form-btn')) {
                document.getElementById('show-update-form-btn').classList.add('hidden');
            }
        });

        if (document.getElementById('show-update-form-btn')) {
            document.getElementById('show-update-form-btn').addEventListener('click', function() {
                document.querySelector('.user-details').classList.remove('hidden');
                this.classList.add('hidden');
                if (document.getElementById('show-add-form-btn')) {
                    document.getElementById('show-add-form-btn').classList.add('hidden');
                }
            });
        }

        // Add income/deduction rows
        function addRow(containerClass, rowClass, index) {
    const container = document.querySelector(`.${containerClass}`);
    const newRow = document.createElement('div');
    newRow.className = `${rowClass} flex gap-4 mb-3 items-end`;

    const type = rowClass.includes("income") ? "income" : "deduction";

    newRow.innerHTML = `
        <div class="flex-1">
            <input type="text" id="${type}-category-${index}" name="${type}-category-${index}" 
                placeholder="${type.charAt(0).toUpperCase() + type.slice(1)} Category"
                class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500">
        </div>
        <div class="flex-1">
            <div class="relative">
                <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-500">₹</span>
                <input type="number" id="${type}-${index}" name="${type}-${index}" 
                    placeholder="Amount"
                    class="w-full pl-8 pr-4 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500">
            </div>
        </div>
        <button type="button" class="remove-${type}-btn text-red-500 hover:text-red-700 mb-2 transition duration-150">
            <i class="fas fa-trash"></i>
        </button>
    `;
    container.appendChild(newRow);

    // Add event listener to the new remove button
    newRow.querySelector(`.remove-${type}-btn`).addEventListener('click', function () {
        newRow.remove();
    });
}


        // Income rows
        document.querySelector('.add-income-btn').addEventListener('click', function() {
            const incomeRows = document.querySelectorAll('.income-row');
            addRow('income-sources', 'income-row', incomeRows.length + 1);
        });

        // Deduction rows
        document.querySelector('.add-deduction-btn').addEventListener('click', function() {
            const deductionRows = document.querySelectorAll('.deduction-row');
            addRow('deductions', 'deduction-row', deductionRows.length + 1);
        });

        // Add event listeners to existing remove buttons
        document.querySelectorAll('.remove-income-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                this.closest('.income-row').remove();
            });
        });

        document.querySelectorAll('.remove-deduction-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                this.closest('.deduction-row').remove();
            });
        });

        // Chatbot functionality
        const chatbotModal = document.getElementById("chatbot-modal");
        const openChatbotBtn = document.getElementById("open-chatbot-btn");
        const closeChatbotBtn = document.getElementById("close-chatbot-btn");
        const messagesContainer = document.getElementById("messages");
        const userInput = document.getElementById("user-input");
        const sendBtn = document.getElementById("send-btn");

        openChatbotBtn.addEventListener("click", () => {
            chatbotModal.classList.remove("hidden");
            userInput.focus();
        });

        closeChatbotBtn.addEventListener("click", () => {
            chatbotModal.classList.add("hidden");
        });

        chatbotModal.addEventListener("click", (event) => {
            if (event.target === chatbotModal) {
                chatbotModal.classList.add("hidden");
            }
        });

        function appendMessage(content, sender) {
            const messageDiv = document.createElement("div");
            messageDiv.className = `flex ${sender === 'user' ? 'justify-end' : 'justify-start'}`;
            
            const messageBubble = document.createElement("div");
            messageBubble.className = sender === 'user' 
                ? "bg-indigo-600 text-white p-3 rounded-lg max-w-xs"
                : "bg-gray-200 text-gray-800 p-3 rounded-lg max-w-xs";
            messageBubble.textContent = content;
            
            messageDiv.appendChild(messageBubble);
            messagesContainer.appendChild(messageDiv);
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
        }

        async function sendMessage() {
            const message = userInput.value.trim();
            if (!message) return;

            appendMessage(message, "user");
            userInput.value = "";
            userInput.focus();

            try {
                const response = await fetch("chatbot.php", {
                    method: "POST",
                    headers: { "Content-Type": "application/x-www-form-urlencoded" },
                    body: `message=${encodeURIComponent(message)}`,
                });

                const data = await response.json();
                appendMessage(data.response, "bot");
            } catch (error) {
                appendMessage("Sorry, I'm having trouble connecting. Please try again later.", "bot");
            }
        }

        sendBtn.addEventListener("click", sendMessage);
        userInput.addEventListener("keypress", (e) => {
            if (e.key === "Enter") sendMessage();
        });
    </script>
</body>
</html>