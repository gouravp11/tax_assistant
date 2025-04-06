<?php
// Start the session to manage user registration state
session_start();

// Redirect logged-in users to the dashboard
if (isset($_SESSION["user"])) {
   header("Location: index.php");
}

// Include the database connection
require_once "database.php";

// Create the users table if it doesn't exist
$conn->query("CREATE TABLE IF NOT EXISTS users (
    id INT(11) NOT NULL AUTO_INCREMENT,
    full_name VARCHAR(128) NOT NULL,
    email VARCHAR(255) NOT NULL,
    password VARCHAR(255) NOT NULL,
    PRIMARY KEY (id)
)");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <!-- Metadata and Tailwind CSS for styling -->
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registration Form</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container mx-auto h-screen flex items-center">
        <!-- Application title -->
        <div class="absolute top-0 m-4 text-center mb-6">
        <div class="flex items-center space-x-2">
                    <i class="fas fa-calculator text-xl"></i>
                    <span class="text-xl font-bold">Taxify</span>
                </div>
        </div>
        <?php
        if (isset($_POST["submit"])) {
            // Handle registration form submission
           $fullName = $_POST["fullname"];
           $email = $_POST["email"];
           $password = $_POST["password"];
           $passwordRepeat = $_POST["repeat_password"];
           
           $passwordHash = password_hash($password, PASSWORD_DEFAULT);

           $errors = array();
           
           if (empty($fullName) OR empty($email) OR empty($password) OR empty($passwordRepeat)) {
            array_push($errors,"All fields are required");
           }
           if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            array_push($errors, "Email is not valid");
           }
           if (strlen($password)<8) {
            array_push($errors,"Password must be at least 8 charactes long");
           }
           if ($password!==$passwordRepeat) {
            array_push($errors,"Password does not match");
           }

           $sql = "SELECT * FROM users WHERE email = '$email'";
           $result = mysqli_query($conn, $sql);
           $rowCount = mysqli_num_rows($result);
           if ($rowCount>0) {
            array_push($errors,"Email already exists!");
           }
           if (count($errors)>0) {
            echo "<div class='errors absolute top-0 my-2 w-full flex flex-col items-center justify-center'>";
            foreach ($errors as $error) {
                echo "<div class='w-1/4 bg-red-100 border border-red-400 text-red-700 mt-1 text-center px-4 rounded' role='alert'>
                        <span class='block sm:inline'>$error</span>
                      </div>";
            }
            echo "</div>";
                     
           }else{
            
            $sql = "INSERT INTO users (full_name, email, password) VALUES ( ?, ?, ? )";
            $stmt = mysqli_stmt_init($conn);
            $prepareStmt = mysqli_stmt_prepare($stmt,$sql);
            if ($prepareStmt) {
                mysqli_stmt_bind_param($stmt,"sss",$fullName, $email, $passwordHash);
                mysqli_stmt_execute($stmt);
                echo "<div class='absolute top-0 my-2 w-full flex flex-col items-center justify-center'>";
                echo "<div class='w-1/4 bg-green-100 border border-green-400 text-green-700 mt-1 text-center px-4 rounded' role='alert'>
                        <span class='block sm:inline'>You are registered successfully.</span>
                      </div>";
                echo "</div>";
            }else{
                die("Something went wrong");
            }
           }
          

        }
        ?>
        <div class="container mx-auto py-8">
            <h1 class="text-2xl font-bold text-center">Sign Up</h1>
            <form action="registration.php" method="post" class="w-full max-w-sm mx-auto bg-white p-8 rounded-md shadow-md">
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="fullname">Name</label>
                    <input class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500"
                        type="text" id="fullname" name="fullname" placeholder="John Doe">
                </div>
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
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="repeat_password">Confirm Password</label>
                    <input class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500"
                        type="password" id="repeat_password" name="repeat_password" placeholder="********">
                </div>
                <button
                    class="w-full bg-indigo-500 text-white text-sm font-bold py-2 px-4 rounded-md hover:bg-indigo-600 transition duration-300"
                    type="submit" name="submit">Sign Up</button>
            </form>
            <div class="text-center mt-4">
                <p>Already Registered? <a href="login.php" class="text-indigo-500 hover:underline">Login Here</a></p>
            </div>
        </div>
    </div>
</body>
</html>