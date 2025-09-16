document.addEventListener("DOMContentLoaded", function () {
    initUserLogsScript(); // <-- make sure we actually run it

    function initUserLogsScript() {
        const selectUser = document.getElementById("selectUserDrop_logs");
        const startDate = document.getElementById("start_date_logs");
        const endDate = document.getElementById("end_date_logs");
        const filterButton = document.getElementById("filter_logs");
        const tbody = document.getElementById("userlogsData");
        const cardContainer = document.getElementById("userlogsCardView");

        const currentUserId =
            typeof CURRENT_USER_ID !== "undefined" ? CURRENT_USER_ID : null;

        function formatDate(dateTime) {
            return new Date(dateTime).toLocaleDateString("en-US", {
                month: "short",
                day: "numeric",
                year: "numeric",
            });
        }

        async function fetchUserLogs() {
            const userId = selectUser?.value || currentUserId;
            if (!userId) {
                await Swal.fire({
                    icon: "error",
                    title: "No user selected",
                    text: "Please select a user to view logs.",
                    confirmButtonText: "OK",
                });
                return;
            }

            const today = new Date().toISOString().split("T")[0];
            const start = startDate?.value || "2025-01-01";
            const end = endDate?.value || today;

            // set defaults into inputs once
            if (startDate && !startDate.value) startDate.value = start;
            if (endDate && !endDate.value) endDate.value = end;

            // validate dates
            if (new Date(start) > new Date(end)) {
                await Swal.fire({
                    icon: "warning",
                    title: "Invalid date range",
                    text: "Start date cannot be after end date.",
                    confirmButtonText: "OK",
                });
                return;
            }

            const params = new URLSearchParams({
                user_id: String(userId),
                start_date_logs: start,
                end_date_logs: end,
            });

            if (tbody)
                tbody.innerHTML = `<tr><td colspan="3" class="text-center">Loading logs...</td></tr>`;
            if (cardContainer)
                cardContainer.innerHTML = `<div class="alert alert-info text-center">Loading logs...</div>`;

            try {
                const res = await fetch(`/get-user-logs?${params.toString()}`, {
                    headers: {
                        Accept: "application/json",
                        "X-Requested-With": "XMLHttpRequest",
                    },
                });

                const ct = res.headers.get("content-type") || "";
                const data = ct.includes("application/json")
                    ? await res.json().catch(() => [])
                    : [];

                // clear containers
                if (tbody) tbody.innerHTML = "";
                if (cardContainer) cardContainer.innerHTML = "";

                if (!Array.isArray(data) || data.length === 0) {
                    if (tbody)
                        tbody.innerHTML = `<tr><td colspan="3" class="td-notes text-center">No logs found</td></tr>`;
                    if (cardContainer)
                        cardContainer.innerHTML = `<div class="alert alert-info text-center">No logs found</div>`;
                    return;
                }

                data.forEach((log, index) => {
                    const formattedDate = formatDate(log.datetimelogs);
                    const actions = log.actions || "-";
                    const cardBg = index % 2 === 0 ? "bg-light" : "bg-white";

                    if (tbody) {
                        tbody.insertAdjacentHTML(
                            "beforeend",
                            `
                            <tr class="tr-notes">
                                <td class="td-notes">${log.username}</td>
                                <td class="td-notes notes-column">${actions}</td>
                                <td class="td-notes">${formattedDate}</td>
                            </tr>
                        `
                        );
                    }

                    if (cardContainer) {
                        cardContainer.insertAdjacentHTML(
                            "beforeend",
                            `
                            <div class="card mb-3 shadow-sm ${cardBg}">
                                <div class="card-body">
                                    <h6 class="mb-1"><strong>User:</strong> ${
                                        log.username
                                    }</h6>
                                    <p class="mb-1"><strong>Action:</strong> ${
                                        log.actions
                                            ? `<i class="bi bi-sticky me-1"></i>${log.actions}`
                                            : "-"
                                    }</p>
                                    <p class="mb-0"><strong>Date:</strong> ${formattedDate}</p>
                                </div>
                            </div>
                        `
                        );
                    }
                });
            } catch (error) {
                console.error("Error fetching user logs:", error);
                if (tbody)
                    tbody.innerHTML = `<tr><td colspan="3" class="td-notes text-center text-danger">Error loading logs</td></tr>`;
                if (cardContainer)
                    cardContainer.innerHTML = `<div class="alert alert-danger text-center">Error loading logs</div>`;
                await Swal.fire({
                    icon: "error",
                    title: "Load failed",
                    text: "An error occurred while loading user logs.",
                    confirmButtonText: "OK",
                });
            }
        }

        selectUser?.addEventListener("change", fetchUserLogs);
        filterButton?.addEventListener("click", fetchUserLogs);

        // initial load
        fetchUserLogs();
    }
});
