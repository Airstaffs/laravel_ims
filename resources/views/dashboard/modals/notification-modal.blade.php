<div class="modal fade" id="notifModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content shadow">
            <div class="modal-header">
                <h5 class="modal-title" id="notifModalTitle">Notifications</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="notifExpandedView">
                    <form>
                        <fieldset>
                            <label>Filter by Module:</label>
                            <select id="moduleFilter" class="form-select form-control d-inline-block">
                                <option value="">All</option>
                            </select>
                        </fieldset>
                    </form>

                    <div id="notifExpandedTable"></div>
                </div>

                <!-- Single Notification View -->
                <div id="notifDetailView" class="d-none">
                    <div id="notifDetailContent"></div>
                    <button id="backToExpanded" class="btn btn-secondary btn-sm mt-3">Back</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    let notificationsData = []; // will store fetched notifications
    let currentSort = { key: null, asc: true }; // sort state

    document.addEventListener('DOMContentLoaded', function () {
        const userId = @json(Auth::id());
        const csrfToken = '{{ csrf_token() }}';

        const notifModal = document.getElementById('notifModal');
        const expandedView = document.getElementById('notifExpandedView');
        const detailView = document.getElementById('notifDetailView');
        const notifExpandedTable = document.getElementById('notifExpandedTable');
        const notifDetailContent = document.getElementById('notifDetailContent');

        const notifBadges = [
            document.getElementById('notifBadgeMobile'),
            document.getElementById('notifBadgeDesktop')
        ];

        function updateBadge() {
            fetch(`/notifications/unread-count/${userId}`)
                .then(res => res.json())
                .then(data => {
                    const count = data.unread_count;

                    notifBadges.forEach(badge => {
                        if (!badge) return;
                    });
                });
        }

        function markAsRead(notifId) {
            return fetch(`/notifications/mark-read`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify({ notif_id: notifId, user_id: userId })
            }).then(res => res.json());
        }

        function renderExpandedTable(data) {
            const esc = (s) =>
                String(s ?? "")
                    .replace(/&/g, "&amp;")
                    .replace(/</g, "&lt;")
                    .replace(/>/g, "&gt;")
                    .replace(/"/g, "&quot;")
                    .replace(/'/g, "&#39;");

            const fmtDate = (d) => {
                try { return new Date(d).toLocaleString(); } catch { return esc(d) || "—"; }
            };

            const severityPill = (sev) => {
                const s = String(sev || "").toLowerCase();
                const cls =
                    s === "high" || s === "critical" ? "bg-danger" :
                        s === "medium" ? "bg-warning text-dark" :
                            s === "low" ? "bg-success" :
                                "bg-secondary";
                return `<span class="badge ${cls}">${esc(sev || "—")}</span>`;
            };

            // Build Mobile Cards (visible < md)
            let mobile = `
                <div class="d-md-none">
                ${!data || !data.length ? `
                    <div class="text-center text-muted py-4">No notifications.</div>
                ` : data.map(item => {
                const unread = item.read_status === "unread";
                const dataAttr = esc(JSON.stringify(item));
                return `
                    <div class="notif-card shadow-sm rounded-3 mb-2 p-3 ${unread ? "notif-unread" : ""}" data-item='${dataAttr}' role="button" tabindex="0">
                        <div class="d-flex justify-content-between align-items-start gap-2">
                        <div class="flex-grow-1">
                            <div class="small text-secondary">Module</div>
                            <div class="fw-semibold text-truncate">${esc(item.module)}</div>
                        </div>
                        ${severityPill(item.severity)}
                        </div>

                        <div class="mt-2">
                        <div class="fw-semibold text-truncate">${esc(item.title)}</div>
                        ${item.subtitle ? `<div class="text-secondary small text-truncate">${esc(item.subtitle)}</div>` : ""}
                        </div>

                        ${item.content ? `
                        <div class="mt-2 small notif-clamp">${esc(item.content)}</div>
                        ` : ""}

                        <div class="mt-2 small text-secondary">${fmtDate(item.notif_created_at)}</div>
                    </div>
                    `;
            }).join("")}
                </div>
            `;

            // Build Desktop Table (visible >= md)
            let desktop = `
                <div class="table-responsive d-none d-md-block">
                <table class="table table-bordered table-sm align-middle mb-0">
                    <thead class="table-light sticky-top">
                    <tr>
                        <th>Module</th>
                        <th>Title</th>
                        <th>Subtitle</th>
                        <th>Content</th>
                        <th>Severity</th>
                        <th>Date</th>
                    </tr>
                    </thead>
                    <tbody>
                    ${!data || !data.length ? `
                        <tr><td colspan="6" class="text-center text-muted">No notifications.</td></tr>
                            ` : data.map(item => {
                const unread = item.read_status === "unread";
                const dataAttr = esc(JSON.stringify(item));
                return `
                        <tr class="notif-row ${unread ? "fw-semibold" : ""}" data-item='${dataAttr}' role="button">
                            <td>${esc(item.module)}</td>
                            <td class="text-truncate" style="max-width: 260px;">${esc(item.title)}</td>
                            <td class="text-truncate" style="max-width: 220px;">${esc(item.subtitle || "")}</td>
                            <td class="text-truncate" style="max-width: 360px;">${esc(item.content || "")}</td>
                            <td>${severityPill(item.severity)}</td>
                            <td>${fmtDate(item.notif_created_at)}</td>
                        </tr>
                        `;
            }).join("")}
                    </tbody>
                </table>
                </div>
            `;

            notifExpandedTable.innerHTML = mobile + desktop;

            // Click handlers (cards + rows)
            notifExpandedTable.querySelectorAll(".notif-card, .notif-row").forEach(el => {
                el.addEventListener("click", () => {
                    const raw = el.getAttribute("data-item");
                    if (!raw) return;
                    const item = JSON.parse(raw);
                    markAsRead(item.notif_id).then(() => {
                        updateBadge();
                        showSingleNotification(item);
                    });
                });
                // Make Enter key open on cards (accessibility)
                if (el.classList.contains("notif-card")) {
                    el.addEventListener("keydown", (e) => {
                        if (e.key === "Enter" || e.key === " ") {
                            e.preventDefault();
                            el.click();
                        }
                    });
                }
            });
        }


        function showSingleNotification(item) {
            notifDetailContent.innerHTML = `
            <table class="table table-sm">
                <tbody>
                    <tr><th>Module</th><td>${item.module}</td></tr>
                    <tr><th>Title</th><td>${item.title}</td></tr>
                    <tr><th>Subtitle</th><td>${item.subtitle || ''}</td></tr>
                    <tr><th>Content</th><td>${item.content || ''}</td></tr>
                    <tr><th>Severity</th><td>${item.severity}</td></tr>
                    <tr><th>Date</th><td>${new Date(item.notif_created_at).toLocaleString()}</td></tr>
                </tbody>
            </table>`;
            expandedView.classList.add('d-none');
            detailView.classList.remove('d-none');
        }

        document.getElementById('backToExpanded').addEventListener('click', () => {
            detailView.classList.add('d-none');
            expandedView.classList.remove('d-none');
            // Reload table to update read_status
            fetch(`/notifications/user/${userId}`)
                .then(res => res.json())
                .then(data => renderExpandedTable(data));
        });

        notifModal.addEventListener('shown.bs.modal', () => {
            fetch(`/notifications/user/${userId}`)
                .then(res => res.json())
                .then(data => renderExpandedTable(data));
        });

        notifModal.addEventListener('shown.bs.modal', () => {
            updateBadge();
            fetch(`/notifications/user/${userId}`)
                .then(res => res.json())
                .then(data => renderExpandedTable(data));
        });

        notifModal.addEventListener('hidden.bs.modal', () => {
            detailView.classList.add('d-none');
            expandedView.classList.remove('d-none');
        });


        updateBadge();
        setInterval(updateBadge, 30000);
    });
</script>

<style>
    #notifModal .modal-content {
        width: 800px;
    }

    #notifModal .modal-body {
        padding: 20px;
        display: flex;
        flex-direction: column;
        justify-content: flex-start;
        align-items: stretch;
        gap: 20px;
    }

    #notifExpandedView {
        display: flex;
        flex-direction: column;
        justify-content: flex-start;
        align-items: stretch;
        gap: 20px;
    }

    #notifExpandedView fieldset {
        display: flex;
        justify-content: flex-start;
        align-items: center;
        gap: 10px;
    }

    #notifExpandedView fieldset .form-control {
        width: 120px;
    }

    #notifExpandedTable,
    #notifDetailContent {
        font-size: 14px;
    }
</style>