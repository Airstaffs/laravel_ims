$(document).ready(function () {
    function fetchAttendanceData(startDate = null, endDate = null) {
        $.ajax({
            url: attendanceFilterRoute,
            type: "POST",
            data: {
                _token: csrfToken,
                start_date: startDate,
                end_date: endDate,
            },
            success: function (response) {
                const tableBody = $("#attendance-table-body");
                const cardContainer = $("#attendance-card-container");
                const totalHoursSpan = $("#total-hours");
                tableBody.empty();
                cardContainer.empty();
                let totalMinutes = 0;

                if (response.employeeClocks.length > 0) {
                    response.employeeClocks.forEach(function (clock, index) {
                        const timeIn = new Date(clock.time_in);
                        const timeOut = clock.time_out
                            ? new Date(clock.time_out)
                            : new Date(
                                  new Date().toLocaleString("en-US", {
                                      timeZone: "America/Los_Angeles",
                                  })
                              );

                        const diffInMinutes = Math.round(
                            (timeOut - timeIn) / 60000
                        );
                        totalMinutes += diffInMinutes;
                        const hours = Math.floor(diffInMinutes / 60);
                        const minutes = diffInMinutes % 60;

                        const timeInStr = timeIn.toLocaleTimeString([], {
                            hour: "2-digit",
                            minute: "2-digit",
                        });
                        const timeOutStr = clock.time_out
                            ? timeOut.toLocaleTimeString([], {
                                  hour: "2-digit",
                                  minute: "2-digit",
                              })
                            : '<span class="text-danger">Not yet timed out</span>';

                        const cardBg =
                            index % 2 === 0 ? "bg-light" : "bg-white";

                        tableBody.append(`
                            <tr>
                                <td><b>${timeIn.toLocaleDateString()}</b></td>
                                <td>${timeInStr}</td>
                                <td>${timeOutStr}</td>
                                <td>${hours} hrs ${minutes} mins</td>
                            </tr>
                        `);

                        cardContainer.append(`
                            <div class="card mb-3 shadow-sm ${cardBg}">
                                <div class="card-body">
                                    <div class="mb-2">
                                        <h6 class="mb-0">Date</h6>
                                        <p class="mb-0"><b>${timeIn.toLocaleDateString()}</b></p>
                                    </div>
                                    <div class="mb-2">
                                        <h6 class="mb-0">Time In</h6>
                                        <p class="mb-0">${timeInStr}</p>
                                    </div>
                                    <div class="mb-2">
                                        <h6 class="mb-0">Time Out</h6>
                                        ${
                                            clock.time_out
                                                ? `<p class="mb-0">${timeOutStr}</p>`
                                                : `<span class="badge bg-danger">Not yet timed out</span>`
                                        }
                                    </div>
                                    <div class="mb-2">
                                        <h6 class="mb-0">Computed Hours</h6>
                                        <p class="mb-0">${hours} hrs ${minutes} mins</p>
                                    </div>
                                </div>
                            </div>
                        `);
                    });

                    const totalHours = Math.floor(totalMinutes / 60);
                    const totalRemainingMinutes = totalMinutes % 60;
                    totalHoursSpan.text(
                        `${totalHours} hrs ${totalRemainingMinutes} mins`
                    );
                } else {
                    tableBody.append(
                        `<tr><td colspan="4" class="text-center">No records found.</td></tr>`
                    );
                    cardContainer.append(`
                        <div class="alert alert-info text-center" role="alert">
                            No records found.
                        </div>
                    `);
                    totalHoursSpan.text("0:00");
                }
            },
            error: function (xhr, status, error) {
                console.error("Error fetching data:", error);
            },
        });
    }

    // Expose CSRF token and route dynamically via Blade template
    const attendanceFilterRoute = $("#filter-form").data("route");
    const csrfToken = $('meta[name="csrf-token"]').attr("content");

    // Default load
    fetchAttendanceData();

    $("#filter-button").on("click", function () {
        const startDate = $("#start-date").val();
        const endDate = $("#end-date").val();
        fetchAttendanceData(startDate, endDate);
    });
});
