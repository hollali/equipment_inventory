function validateForm() {
    const username = document.getElementById("username").value.trim();
    const password = document.getElementById("password").value.trim();

    const userError = document.getElementById("userError");
    const passError = document.getElementById("passError");

    userError.textContent = "";
    passError.textContent = "";

    let isValid = true;

    if (username === "") {
        userError.textContent = "Username is required";
        isValid = false;
    }

    if (password === "") {
        passError.textContent = "Password is required";
        isValid = false;
    } else if (password.length < 6) {
        passError.textContent = "Password must be at least 6 characters";
        isValid = false;
    }

    return isValid;
}

document
    .querySelector(".toggle-password")
    .addEventListener("click", togglePassword);

function togglePassword() {
    const input = document.getElementById("password");
    const icon = document.querySelector(".toggle-password i");

    if (input.type === "password") {
        input.type = "text";
        icon.classList.replace("fa-eye", "fa-eye-slash");
    } else {
        input.type = "password";
        icon.classList.replace("fa-eye-slash", "fa-eye");
    }
}
