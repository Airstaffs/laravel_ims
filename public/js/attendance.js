document.addEventListener("DOMContentLoaded", function () {
    // ========== SOUND ELEMENTS ==========
    const clockinSound = document.getElementById("clockin-question-sound");
    const clockoutSound = document.getElementById("clockout-question-sound");

    // ========== GENERIC AJAX CLOCK FUNCTION ==========
    function sendAjaxClock(route, successCallback) {
        fetch(route, {
            method: "POST",
            headers: {
                "X-CSRF-TOKEN": document
                    .querySelector('meta[name="csrf-token"]')
                    .getAttribute("content"),
                Accept: "application/json",
            },
            credentials: "same-origin",
        })
            .then((response) => response.json())
            .then((data) => {
                alert(
                    data.message ||
                        (data.success
                            ? "Action successful."
                            : "Something went wrong.")
                );
                if (data.success && typeof successCallback === "function") {
                    successCallback();
                }
            })
            .catch((error) => {
                console.error("Error:", error);
                alert("Server error. Please try again.");
            });
    }

    // ========== CLOCK IN / CLOCK OUT CONFIRMATION ==========
    window.confirmClockIn = function () {
        clockinSound?.play();
        if (confirm("Are you sure you want to Clock In?")) {
            const route = document
                .getElementById("clockin-button")
                ?.getAttribute("data-route");
            if (route) sendAjaxClock(route, () => location.reload());
        }
    };

    window.confirmClockOut = function () {
        clockoutSound?.play();
        if (confirm("Are you sure you want to Clock Out?")) {
            const route = document
                .getElementById("clockout-button")
                ?.getAttribute("data-route");
            if (route) sendAjaxClock(route, () => location.reload());
        }
    };

    // ========== REAL-TIME CLOCK (PACIFIC TIME) ==========
    function updateTime() {
        const currentTimeElement = document.getElementById("current-time");
        const currentDayElement = document.getElementById("current-day");
        const currentDateElement = document.getElementById("current-date");

        if (currentTimeElement && currentDayElement && currentDateElement) {
            const now = new Date();

            const timeParts = new Intl.DateTimeFormat("en-US", {
                timeZone: "America/Los_Angeles",
                hour: "2-digit",
                minute: "2-digit",
                second: "2-digit",
                hour12: true,
            }).formatToParts(now);

            const formattedTime = `${
                timeParts.find((p) => p.type === "hour").value
            }:${timeParts.find((p) => p.type === "minute").value}:${
                timeParts.find((p) => p.type === "second").value
            } ${timeParts.find((p) => p.type === "dayPeriod").value}`;

            const pacificDay = new Intl.DateTimeFormat("en-US", {
                timeZone: "America/Los_Angeles",
                weekday: "long",
            }).format(now);

            const pacificDate = new Intl.DateTimeFormat("en-US", {
                timeZone: "America/Los_Angeles",
                year: "numeric",
                month: "long",
                day: "numeric",
            }).format(now);

            currentTimeElement.textContent = formattedTime;
            currentDayElement.textContent = `${pacificDay} , ${pacificDate}`;
            currentDateElement.textContent = pacificDate;
        }
    }
    updateTime();
    setInterval(updateTime, 1000);

    // ========== AUTO CLOCK OUT ==========
    function autoClockOut() {
        const lastRecordTimeIn = document
            .querySelector('meta[name="last-record-timein"]')
            ?.getAttribute("content");
        if (!lastRecordTimeIn) return;

        const timeInDate = new Date(lastRecordTimeIn);
        const currentDate = new Date(
            new Date().toLocaleString("en-US", {
                timeZone: "America/Los_Angeles",
            })
        );

        const isNotToday =
            timeInDate.toLocaleDateString() !==
            currentDate.toLocaleDateString();
        const eightHoursAgo = new Date(
            currentDate.getTime() - 8 * 60 * 60 * 1000
        );
        const isMoreThan8HoursAgo = timeInDate < eightHoursAgo;

        if (isNotToday || isMoreThan8HoursAgo) {
            console.log(
                "Auto Clocking Out: TimeIn is not today or more than 8 hours ago."
            );
            fetch("/your-auto-clockout-route", {
                method: "POST",
                headers: {
                    "X-CSRF-TOKEN": document
                        .querySelector('meta[name="csrf-token"]')
                        .getAttribute("content"),
                    "Content-Type": "application/json",
                },
                body: JSON.stringify({}),
            })
                .then((response) => response.json())
                .then((data) => {
                    if (data.success) setTimeout(() => location.reload(), 1000);
                })
                .catch((error) =>
                    console.error("Error during auto clock-out:", error)
                );
        }
    }

    setTimeout(autoClockOut, 30000);
    autoClockOut();

    // ========== NOTES MODAL BEHAVIOR ==========
    const editNotesModal = document.getElementById("editNotesModal");
    const profileModal = document.getElementById("profileModal");

    if (editNotesModal && profileModal) {
        editNotesModal.addEventListener("hidden.bs.modal", function () {
            document.querySelector(".modal-backdrop")?.remove();
            const profileModalInstance = new bootstrap.Modal(profileModal);
            profileModalInstance.show();
            document.querySelector("#attendance-tab")?.click();
        });

        editNotesModal.addEventListener("show.bs.modal", function () {
            const profileModalInstance =
                bootstrap.Modal.getInstance(profileModal);
            profileModalInstance?.hide();
        });
    }

    // ========== NOTES MODAL ACTIONS ==========
    window.populateNotesModal = function (recordId, notes) {
        const modal = new bootstrap.Modal(editNotesModal);
        document.getElementById("recordId").value = recordId;
        document.getElementById("notes").value = notes;
        modal.show();
    };

    window.updateNotes = function () {
        const recordId = document.getElementById("recordId").value;
        const notes = document.getElementById("notes").value;
        const modalInstance = bootstrap.Modal.getInstance(editNotesModal);

        fetch(`/update-notes/${recordId}`, {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "X-CSRF-TOKEN": document
                    .querySelector('meta[name="csrf-token"]')
                    .getAttribute("content"),
            },
            body: JSON.stringify({ notes }),
        })
            .then((response) => response.json())
            .then((data) => {
                if (data.success) {
                    modalInstance.hide();
                    document.querySelector(".modal-backdrop")?.remove();
                    alert(data.message);
                    location.reload();
                } else {
                    alert("Failed to update notes.");
                }
            })
            .catch((error) => {
                console.error("Error updating notes:", error);
                alert("An error occurred. Please try again.");
            });
    };

    function calculateAndDisplayHours(recordId, timeInStr, timeOutStr) {
        if (!timeInStr || !timeOutStr) return;

        const timeIn = new Date(timeInStr);
        const timeOut = new Date(timeOutStr);

        if (isNaN(timeIn.getTime()) || isNaN(timeOut.getTime())) return;

        const diffMs = timeOut - timeIn;
        const diffHours = Math.floor(diffMs / (1000 * 60 * 60));
        const diffMinutes = Math.floor((diffMs / (1000 * 60)) % 60);

        const displayText = `${diffHours}:${diffMinutes
            .toString()
            .padStart(2, "0")} hrs`;
        const container = document.getElementById(`computed-hours-${recordId}`);
        if (container) container.innerHTML = `<strong>${displayText}</strong>`;
    }

    // Loop through hidden update buttons to extract data and compute hours
    document.querySelectorAll(".update-computed-hours").forEach((btn) => {
        const timeIn = btn.dataset.timein;
        const timeOut = btn.dataset.timeout;
        const recordId = btn.dataset.id;

        if (timeIn && timeOut) {
            calculateAndDisplayHours(recordId, timeIn, timeOut);
        }
    });
});
