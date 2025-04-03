<?php
// Start the session to manage user login state
session_start();

// Redirect logged-in users to the dashboard
if (isset($_SESSION["user"])) {
   header("Location: index.php");
}

// Include the database connection
require_once "database.php";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <!-- Metadata and Tailwind CSS for styling -->
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Form</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mx-auto h-screen flex items-center">
        <!-- Application title -->
        <div class="absolute top-0 m-4 text-center mb-6">
            <h1 class="text-4xl font-bold text-indigo-500">Taxify</h1>
        </div>

        <?php
        if (isset($_POST["login"])) {
            // Handle login form submission
            $email = $_POST["email"];
            $password = $_POST["password"];
            require_once "database.php";
            $sql = "SELECT * FROM users WHERE email = '$email'";
            $result = mysqli_query($conn, $sql);
            $user = mysqli_fetch_array($result, MYSQLI_ASSOC);
            echo "<div class='errors absolute top-0 my-2 w-full flex flex-col items-center justify-center'>";
            if ($user) {
                if (password_verify($password, $user["password"])) {
                    session_start();
                    $_SESSION["user"] = $email;
                    header("Location: index.php");
                    die();
                } else {
                    echo "<div class='bg-red-100 text-red-700 px-4 py-1 rounded w-1/4  border border-red-400'>Password does not match</div>";
                }
            } else {
                echo "<div class='bg-red-100 text-red-700 px-4 py-1 rounded w-1/4  border border-red-400'>Email does not match</div>";
            }
            echo "</div>";
        }
        ?>

        <!-- Login form -->
        <div class="container mx-auto py-8">
        <h1 class="text-2xl font-bold text-center">Login</h1>
        <form action="login.php" method="post" class="w-full max-w-sm mx-auto bg-white p-8 rounded-md shadow-md">
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2" for="email">Email</label>
                <input class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500"
                    type="email" id="email" name="email" placeholder="john@example.com">
            </div>
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2" for="password">Password</label>
                <input class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500"
                    type="password" id="password" name="password" placeholder="********">
            </div>
            <button
                class="w-full bg-indigo-500 text-white text-sm font-bold py-2 px-4 rounded-md hover:bg-indigo-600 transition duration-300"
                type="submit" name="login">Login</button>
        </form>
        <div class="text-center mt-4">
            <p>Not registered yet? <a href="registration.php" class="text-indigo-500 hover:underline">Register Here</a></p>
        </div>
        </div>

        <!-- Link to registration page -->
    </div>
</body>
</html>