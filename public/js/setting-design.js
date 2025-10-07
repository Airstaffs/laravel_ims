document.addEventListener("DOMContentLoaded", function () {
    // Load data when page loads
    fetchSystemDesignData();

    // Also load data when design tab is clicked
    const designTab = document.getElementById("design-tab");
    if (designTab) {
        designTab.addEventListener("click", function () {
            fetchSystemDesignData();
        });
    }

    function fetchSystemDesignData() {
        fetch("/get-system-design-data")
            .then((response) => response.json())
            .then((data) => {
                console.log("Loaded data:", data);

                // Populate the form fields with saved data
                const siteTitleInput = document.getElementById("siteTitle");
                const themeColorInput = document.getElementById("themeColor");
                const currentLogoDiv = document.getElementById("currentLogo");

                if (siteTitleInput) {
                    siteTitleInput.value = data.site_title || "";
                }

                if (themeColorInput) {
                    themeColorInput.value = data.theme_color || "#007bff";
                }

                // Show current logo if exists
                if (currentLogoDiv) {
                    if (data.logo) {
                        currentLogoDiv.innerHTML = `<p>Current Logo: <img src="/storage/${data.logo}" alt="Logo" width="100"></p>`;
                    } else {
                        currentLogoDiv.innerHTML = "";
                    }
                }
            })
            .catch((error) => {
                console.log("No saved data found or error:", error);
                // Set defaults if no data
                const siteTitleInput = document.getElementById("siteTitle");
                const themeColorInput = document.getElementById("themeColor");

                if (siteTitleInput && !siteTitleInput.value) {
                    siteTitleInput.value = "";
                }

                if (themeColorInput && !themeColorInput.value) {
                    themeColorInput.value = "#007bff";
                }
            });
    }
});
