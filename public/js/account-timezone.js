document.addEventListener("DOMContentLoaded", function () {
    const form = document.getElementById("timezoneForm");

    form.addEventListener("submit", function (e) {
        e.preventDefault();

        const formData = new FormData(form);

        fetch(updateTimezoneUrl, {
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

// auto-disable logic
document.addEventListener("DOMContentLoaded", function () {
    const checkbox = document.getElementById("auto_sync");
    const tzSelect = document.getElementById("usertimezone");

    function toggleSelect() {
        tzSelect.disabled = checkbox.checked;
    }

    checkbox.addEventListener("change", toggleSelect);
    toggleSelect();
});
