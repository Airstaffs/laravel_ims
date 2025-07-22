document.addEventListener("DOMContentLoaded", function () {
    let lastPrivileges = null; // Store last fetched privileges

    function fetchPrivileges() {
        fetch(privilegesRoute)
            .then((response) => response.json())
            .then((data) => {
                if (data.status === "success") {
                    const privileges = data.data;

                    if (
                        JSON.stringify(lastPrivileges) !==
                        JSON.stringify(privileges)
                    ) {
                        console.log("Privileges updated, applying changes...");

                        updateCheckboxes(privileges);

                        lastPrivileges = privileges;
                    }
                } else {
                    console.error("Error fetching privileges:", data.message);
                }
            })
            .catch((error) => {
                console.error("Error:", error);
            });
    }

    function updateCheckboxes(privileges) {
        const keys = [
            "order",
            "unreceived",
            "receiving",
            "labeling",
            "testing",
            "cleaning",
            "packing",
            "stockroom",
            "validation",
            "fnsku",
            "asinlist",
            "productionarea",
            "returnscanner",
            "fbmorder",
            "notfound",
            "asinoption",
            "houseage",
            "printer",
        ];

        keys.forEach((key) => {
            const checkbox = document.getElementById(key);
            if (checkbox) {
                checkbox.checked = privileges[key] === 1;
            }
        });
    }

    fetchPrivileges();
    // setInterval(fetchPrivileges, 5000);
});
