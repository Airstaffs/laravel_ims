document.addEventListener("DOMContentLoaded", function () {
    initTimeRecordScript(); // <-- you forgot to call this

    function initTimeRecordScript() {
        const selectUser = document.getElementById("selectUserDrop");
        const startDate = document.getElementById("start_date");
        const endDate = document.getElementById("end_date");
        const filterBtn = document.getElementById("filterRecords");
        const tbody = document.getElementById("timeRecordsBody");
        const mobileContainer = document.getElementById("timeRecordsMobile");

        const currentUserId =
            typeof CURRENT_USER_ID !== "undefined" ? CURRENT_USER_ID : null;

        function formatDate(date) {
            return new Date(date).toLocaleDateString("en-US", {
                month: "short",
                day: "numeric",
                year: "numeric",
            });
        }
        function formatTime(date) {
            return new Date(date).toLocaleTimeString("en-US", {
                hour: "2-digit",
                minute: "2-digit",
                hour12: true,
            });
        }
        function calculateHours(timeIn, timeOut) {
            const diff = timeOut - timeIn;
            const hours = Math.max(0, Math.floor(diff / (1000 * 60 * 60)));
            const minutes = Math.max(
                0,
                Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60))
            );
            return `${hours}h ${minutes}m`;
        }

        function renderRecord(record, index) {
            const timeIn = new Date(record.TimeIn);
            const timeOut = record.TimeOut ? new Date(record.TimeOut) : null;
            const totalHours = timeOut
                ? calculateHours(timeIn, timeOut)
                : "Active";
            const notes = record.Notes || "-";
            const timeOutStr = timeOut
                ? formatTime(timeOut)
                : "Not clocked out";
            const formattedDate = formatDate(timeIn);
            const cardBg = index % 2 === 0 ? "bg-light" : "bg-white";

            if (tbody) {
                tbody.insertAdjacentHTML(
                    "beforeend",
                    `
                    <tr>
                        <td>
                            <ul class="list-unstyled m-0">
                                <li><strong>${formattedDate}</strong></li>
                                <li><strong>IN:</strong> ${formatTime(
                                    timeIn
                                )}</li>
                                <li><strong>OUT:</strong> ${timeOutStr}</li>
                            </ul>
                        </td>
                        <td>${totalHours}</td>
                        <td>${notes}</td>
                    </tr>
                `
                );
            }

            if (mobileContainer) {
                mobileContainer.insertAdjacentHTML(
                    "beforeend",
                    `
                    <div class="card mb-3 shadow-sm ${cardBg}">
                        <div class="card-body">
                            <h6 class="mb-1"><strong>${formattedDate}</strong></h6>
                            <p class="mb-1"><strong>Time In:</strong> ${formatTime(
                                timeIn
                            )}</p>
                            <p class="mb-1"><strong>Time Out:</strong> ${timeOutStr}</p>
                            <p class="mb-1"><strong>Total Hours:</strong> ${totalHours}</p>
                            <p class="mb-0"><strong>Notes:</strong> ${
                                notes !== "-"
                                    ? `<i class="bi bi-sticky me-1"></i>${notes}`
                                    : "-"
                            }</p>
                        </div>
                    </div>
                `
                );
            }
        }

        async function fetchTimeRecords() {
            const userId = selectUser?.value || currentUserId;
            if (!userId) {
                await Swal.fire({
                    icon: "error",
                    title: "No user selected",
                    text: "Please select a user.",
                    confirmButtonText: "OK",
                });
                return;
            }

            const today = new Date().toISOString().split("T")[0];
            const start = startDate?.value || "2025-01-01";
            const end = endDate?.value || today;

            if (startDate && !startDate.value) startDate.value = start;
            if (endDate && !endDate.value) endDate.value = end;

            if (new Date(start) > new Date(end)) {
                await Swal.fire({
                    icon: "warning",
                    title: "Invalid date range",
                    text: "Please select a valid date range.",
                    confirmButtonText: "OK",
                });
                return;
            }

            if (tbody)
                tbody.innerHTML = `<tr><td colspan="3" class="text-center">Loading records...</td></tr>`;
            if (mobileContainer)
                mobileContainer.innerHTML = `<div class="alert alert-info text-center">Loading records...</div>`;

            const params = new URLSearchParams({
                start_date: start,
                end_date: end,
            });

            try {
                const res = await fetch(
                    `/get-time-records/${encodeURIComponent(
                        userId
                    )}?${params.toString()}`,
                    {
                        headers: {
                            Accept: "application/json",
                            "X-Requested-With": "XMLHttpRequest",
                        },
                    }
                );

                const ct = res.headers.get("content-type") || "";
                const data = ct.includes("application/json")
                    ? await res.json()
                    : [];

                if (tbody) tbody.innerHTML = "";
                if (mobileContainer) mobileContainer.innerHTML = "";

                if (!Array.isArray(data) || data.length === 0) {
                    if (tbody)
                        tbody.innerHTML = `<tr><td colspan="3" class="text-center">No logs found</td></tr>`;
                    if (mobileContainer)
                        mobileContainer.innerHTML = `<div class="alert alert-info text-center">No logs found</div>`;
                    return;
                }

                data.forEach((record, index) => renderRecord(record, index));
            } catch (error) {
                console.error("Error fetching time records:", error);
                if (tbody)
                    tbody.innerHTML = `<tr><td colspan="3" class="text-danger text-center">Error loading records</td></tr>`;
                if (mobileContainer)
                    mobileContainer.innerHTML = `<div class="alert alert-danger text-center">Error loading records</div>`;
                await Swal.fire({
                    icon: "error",
                    title: "Load failed",
                    text: "An error occurred while loading records.",
                    confirmButtonText: "OK",
                });
            }
        }

        selectUser?.addEventListener("change", fetchTimeRecords);
        filterBtn?.addEventListener("click", fetchTimeRecords);

        // initial load
        fetchTimeRecords();
    }
});
