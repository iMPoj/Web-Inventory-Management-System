 <?php
session_start();

// Check if the user is already logged in
if (isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in'] === true) {
    // If they are, redirect them to the main application page
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Inventory Management</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body class="bg-stone-100">

    <div class="min-h-screen flex items-center justify-center p-4">
        <div class="w-full max-w-md bg-white p-8 rounded-xl shadow-lg">
            
            <div class="text-center mb-8">
                <img src="Reckitt-Logo.png" alt="Reckitt Logo" class="h-14 w-auto mx-auto">
                
                <h1 class="text-2xl sm:text-3xl font-bold text-stone-900 mt-4">Inventory System Login</h1>
                <p class="text-stone-600">Please enter your credentials to proceed.</p>
            </div>

            <form id="login-form" class="space-y-6">
                <div>
                    <label for="username" class="block text-sm font-medium text-stone-700">Username</label>
                    <input type="text" id="username" name="username" required 
                           class="mt-1 block w-full px-3 py-2 bg-white border border-stone-300 rounded-md shadow-sm placeholder-stone-400 focus:outline-none focus:ring-rose-500 focus:border-rose-500 sm:text-sm">
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium text-stone-700">Password</label>
                    <input type="password" id="password" name="password" required
                           class="mt-1 block w-full px-3 py-2 bg-white border border-stone-300 rounded-md shadow-sm placeholder-stone-400 focus:outline-none focus:ring-rose-500 focus:border-rose-500 sm:text-sm">
                </div>

                <div>
                    <p id="error-message" class="text-sm text-red-600 text-center hidden"></p>
                </div>

                <div>
                    <button type="submit" 
                            class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-rose-600 hover:bg-rose-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-rose-500">
                        Login
                    </button>
                </div>
            </form>
            <div class="mt-6 relative">
                <div class="absolute inset-0 flex items-center">
                    <div class="w-full border-t border-stone-300"></div>
                </div>
                <div class="relative flex justify-center text-sm">
                    <span class="px-2 bg-white text-stone-500">Or</span>
                </div>
            </div>

            <div class="mt-6">
                <a href="index.php" class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-slate-600 hover:bg-slate-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-slate-500">
                    Proceed as Viewer
                </a>
            </div>
        </div>
    </div>

    <script src="login.js"></script>
</body>
</html