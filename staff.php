<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Parliamentary Service of Ghana Inventory Management System</title>
    <link href="images/logo.png" rel="icon" type="image/x-icon">
    <link rel="stylesheet" href="./css/login.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
</head>

<body>
    <div class="container">
        <div class="loginHeader">
            <h1>Parliamentary Service of Ghana</h1>
            <h3>Inventory Management System</h3>
        </div>
        <div class="loginBody">
            <form action="" method="POST" onsubmit="return validateForm()">
                <div class="loginInputsContainer">
                    <h1>Staff Login panel</h1>
                    <label for="username">Username</label>
                    <input id="username" placeholder="Enter Your Username" type="text" name="username"
                        required="required" autocomplete="off" value="">
                    <small id="userError" class="error"></small>
                </div>
                <div class="loginInputsContainer">
                    <label>Password</label>
                    <div class="password-wrapper">
                        <input id="password" placeholder="Enter Your Password" type="password" name="password" required
                            autocomplete="off" />
                        <span class="toggle-password" onclick="togglePassword()">
                            <i class="fa-solid fa-eye"></i>
                        </span>
                    </div>
                    <small id="passError" class="error"></small>
                </div>
                <!--<div class="selectorContainer">
                    <label for="">Select Role</label>
                    <select name="selector" id="selector" required="required">
                        <option value="admin">ADMIN</option>
                        <option value="staff">STAFF</option>
                    </select>
                </div>-->
                <div class="loginButtonContainer">
                    <button type="submit">LOGIN</button>
                    <p>Admin Login Panel <a href="index.php">Click Here</a></p>
                </div>
            </form>
        </div>
    </div>
    <script src="./scripts/login.js" defer></script>
</body>

</html>