<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Parliamentary Service of Ghana Inventory Management System </title>
    <link rel="stylesheet" href="./css/login.css ">
</head>

<body>
    <div class="container">
        <div class="loginHeader">
            <h1>Parliamentary Service of Ghana</h1>
            <h3>Inventory Management System </h3>
        </div>
        <div class="loginBody">
            <form action="" method="POST" onsubmit="return validateForm()">
                <div class="loginInputsContainer">
                    <label for="username">Username</label>
                    <input placeholder="Enter Your Username" type="text" name="username" required="required"
                        autocomplete="off">
                    <small id="userError" class="error"></small>
                </div>
                <div class="loginInputsContainer">
                    <label for="">Password</label>
                    <input placeholder="Enter Your Password" type="password" name="password" required="required"
                        autocomplete="off">
                    <small id="passError" class="error"></small>
                </div>
                <div class="loginButtonContainer">
                    <button type="submit">LOGIN</button>
                </div>
            </form>
        </div>
    </div>
    <!-- External JavaScript -->
    <script src="./scripts/login.js"></script>
</body>

</html>