function setupPasswordToggle(toggleId, inputId) {
    const toggleElement = document.getElementById(toggleId);
    const inputElement = document.getElementById(inputId);

    if (!toggleElement || !inputElement) return;

    toggleElement.addEventListener("click", () => {
        const isPasswordVisible = inputElement.type === "text";
        inputElement.type = isPasswordVisible ? "password" : "text";

        toggleElement.classList.toggle("bi-eye", isPasswordVisible);
        toggleElement.classList.toggle("bi-eye-slash", !isPasswordVisible);
    });
}

// Initialize toggles
setupPasswordToggle("toggleNewPassword", "newpassword");
setupPasswordToggle("toggleConfirmPassword", "confirmpassword");

document.addEventListener("DOMContentLoaded", function () {
    const form = document.getElementById("timezoneForm");
    const successBox = document.getElementById("timezoneSuccessBox");
    const successMsg = document.getElementById("timezoneSuccessMsg");

    form.addEventListener("submit", function (e) {
        e.preventDefault();

        const formData = new FormData(form);

        fetch("{{ route('update-timezone') }}", {
            method: "POST",
            headers: {
                "X-CSRF-TOKEN": document.querySelector('input[name="_token"]')
                    .value,
            },
            body: formData,
        })
            .then((response) => response.json())
            .then((data) => {
                if (data.success) {
                    Swal.fire({
                        icon: "success",
                        title: "Success!",
                        text: data.message,
                        timer: 2000,
                        showConfirmButton: true,
                    });
                } else {
                    Swal.fire({
                        icon: "error",
                        title: "Update Failed",
                        text: "Could not update timezone.",
                    });
                }
            })
            .catch((error) => {
                console.error("Error:", error);
                Swal.fire({
                    icon: "error",
                    title: "Something went wrong",
                    text: "Please try again later.",
                });
            });
    });
});

document.addEventListener("DOMContentLoaded", function () {
    const checkbox = document.getElementById("auto_sync");
    const tzSelect = document.getElementById("usertimezone");

    function toggleSelect() {
        tzSelect.disabled = checkbox.checked;
    }

    checkbox.addEventListener("change", toggleSelect);
    toggleSelect();
});
