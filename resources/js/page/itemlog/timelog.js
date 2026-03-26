import {
    Button,
    InputText,
    Calendar,
    DataTable,
    Column,
    Dialog,
} from "primevue";

const API_BASE_URL = window.location.origin;

export default {
    name: "ItemLogs",
    components: { Button, InputText, Calendar, DataTable, Column, Dialog },

    data() {
        return {
            logs: [],
            selectedRows: [],
            selectAll: false,
            loading: false,
            showLogDialog: false,
            selectedLog: null,
            filters: {
                serial: "",
                asin: "",
                from: null,
                to: null,
            },
        };
    },

    mounted() {
        this.fetchLogs();
    },

    methods: {
        async fetchLogs() {
            this.loading = true;
            try {
                const params = {};
                if (this.filters.serial) params.serial = this.filters.serial;
                if (this.filters.asin) params.asin = this.filters.asin;
                if (this.filters.from)
                    params.from = this.formatDate(this.filters.from);
                if (this.filters.to)
                    params.to = this.formatDate(this.filters.to);

                const response = await axios.get(
                    `${API_BASE_URL}/api/received/checklist-logs`,
                    { params },
                );
                this.logs = response.data.data ?? response.data;
            } catch (error) {
                console.error("Error fetching checklist logs:", error);
            } finally {
                this.loading = false;
            }
        },

        clearFilters() {
            this.filters = { serial: "", asin: "", from: null, to: null };
            this.fetchLogs();
        },

        formatDate(date) {
            if (!date) return null;
            return new Date(date).toISOString().split("T")[0];
        },

        onSelectAllChange(event) {
            this.selectAll = event.checked;
            this.selectedRows = event.checked ? [...this.logs] : [];
        },

        viewFullLog(row) {
            this.selectedLog = row;
            this.showLogDialog = true;
        },

        parsedSerials(log) {
            return [
                log.serialnumber,
                log.serialnumberb,
                log.serialnumberc,
                log.serialnumberd,
                log.serialnumbere,
            ].filter(Boolean);
        },

        printLog(log) {
            const serials = this.parsedSerials(log);
            const serialRows = serials
                .map(
                    (sn, i) =>
                        `<div class="wl-field">
                    <span class="wl-field-label">Serial Number${i > 0 ? " " + (i + 1) : ""}:</span>
                    <span class="wl-field-value">${sn}</span>
                </div>`,
                )
                .join("");

            const win = window.open("", "_blank");
            win.document.write(`
                <html>
                <head>
                    <title>Workflow Log Report</title>
                    <style>
                        @page { size: 8.5in 11in portrait; margin: 0.75in; }
                        body { font-family: Arial, sans-serif; font-size: 13px; color: #111; }
                        .wl-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 16px; }
                        .wl-title { font-size: 22px; font-weight: bold; margin: 0 0 4px; }
                        .wl-subtitle { color: #666; margin: 0; font-size: 13px; }
                        .wl-serial-badge { background: #4f46e5; color: #fff; padding: 10px 18px; border-radius: 8px; text-align: center; min-width: 130px; }
                        .wl-serial-label { font-size: 11px; margin-bottom: 4px; }
                        .wl-serial-value { font-size: 18px; font-weight: bold; }
                        .wl-meta-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 0; margin-bottom: 10px; }
                        .wl-meta-label { font-size: 11px; color: #888; margin-bottom: 3px; }
                        .wl-meta-value { font-weight: bold; font-size: 13px; }
                        hr { border: none; border-top: 1px solid #ddd; margin: 16px 0; }
                        .wl-section-header { padding: 8px 12px; font-weight: bold; font-size: 13px; margin-bottom: 0; margin-top: 16px; }
                        .wl-section-header--received { background: #e8eaf6; border-left: 4px solid #4f46e5; }
                        .wl-section-header--labelling { background: #f3e8ff; border-left: 4px solid #7c3aed; }
                        .wl-field { display: flex; padding: 5px 0; border-bottom: 1px solid #f1f5f9; }
                        .wl-field-label { width: 260px; color: #555; }
                        .wl-field-value { font-weight: 500; }
                    </style>
                </head>
                <body>
                    <div class="wl-header">
                        <div>
                            <div class="wl-title">WORKFLOW LOG REPORT</div>
                            <div class="wl-subtitle">Complete Item Processing History</div>
                        </div>
                        <div class="wl-serial-badge">
                            <div class="wl-serial-label">Serial Number</div>
                            <div class="wl-serial-value">${log.serialnumber || "—"}</div>
                        </div>
                    </div>

                    <div class="wl-meta-grid">
                        <div><div class="wl-meta-label">ASIN</div><div class="wl-meta-value">${log.asin || "—"}</div></div>
                        <div><div class="wl-meta-label">FNSKU</div><div class="wl-meta-value">${log.fnsku || log.fnsku_changed || "—"}</div></div>
                        <div><div class="wl-meta-label">Product</div><div class="wl-meta-value">${log.product_name || "—"}</div></div>
                    </div>
                    <div class="wl-meta-grid" style="margin-top:8px;">
                        <div><div class="wl-meta-label">Date Received</div><div class="wl-meta-value">${log.date_received || "—"}</div></div>
                        <div><div class="wl-meta-label">Date Labelled</div><div class="wl-meta-value">${log.date_labelled || "—"}</div></div>
                        <div><div class="wl-meta-label">RT#</div><div class="wl-meta-value">${log.rtcounter || "—"}</div></div>
                    </div>

                    <hr/>

                    <div class="wl-section-header wl-section-header--received">📦 1. RECEIVED MODULE</div>
                    <div class="wl-field"><span class="wl-field-label">Date Received:</span><span class="wl-field-value">${log.date_received || "—"}</span></div>
                    <div class="wl-field"><span class="wl-field-label">Tracking Number:</span><span class="wl-field-value">${log.trackingnumber || "—"}</span></div>
                    ${serialRows || `<div class="wl-field"><span class="wl-field-label">Serial Number:</span><span class="wl-field-value">—</span></div>`}
                    <div class="wl-field"><span class="wl-field-label">Working / Not Working:</span><span class="wl-field-value">${log.pass_fail_result === "pass" ? "Working" : "Not Working"}</span></div>
                    <div class="wl-field"><span class="wl-field-label">Received By:</span><span class="wl-field-value">${log.received_by || "—"}</span></div>
                    <div class="wl-field"><span class="wl-field-label">Item received correct on order:</span><span class="wl-field-value">${log.correct_on_order === "yes" ? "Yes ✓" : "No ✗"}</span></div>
                    <div class="wl-field"><span class="wl-field-label">Condition on Arrival:</span><span class="wl-field-value" style="text-transform:capitalize;">${log.condition_on_arrival || "—"}${log.condition_on_arrival === "good" ? " ✓" : ""}</span></div>
                    ${log.condition_notes ? `<div class="wl-field"><span class="wl-field-label">Condition Notes:</span><span class="wl-field-value">${log.condition_notes}</span></div>` : ""}

                    <div class="wl-section-header wl-section-header--labelling">🏷️ 2. LABELLING MODULE</div>
                    <div class="wl-field"><span class="wl-field-label">Date Labelled:</span><span class="wl-field-value">${log.date_labelled || "—"}</span></div>
                    <div class="wl-field"><span class="wl-field-label">Labelled By:</span><span class="wl-field-value">${log.labelled_by || "—"}</span></div>
                    <div class="wl-field"><span class="wl-field-label">FNSKU:</span><span class="wl-field-value">${log.fnsku || log.fnsku_changed || "—"}</span></div>
                    <div class="wl-field"><span class="wl-field-label">ASIN:</span><span class="wl-field-value">${log.asin || "—"}</span></div>
                    <div class="wl-field"><span class="wl-field-label">RPN:</span><span class="wl-field-value">${log.rpn || "—"}</span></div>
                    <div class="wl-field"><span class="wl-field-label">PRD:</span><span class="wl-field-value">${log.prd || "—"}</span></div>
                    <div class="wl-field"><span class="wl-field-label">Grading:</span><span class="wl-field-value">${log.grading || "—"}</span></div>
                    <div class="wl-field"><span class="wl-field-label">Priority Rank:</span><span class="wl-field-value">${log.priority_rank || "—"}</span></div>
                    ${log.sticker_note ? `<div class="wl-field"><span class="wl-field-label">Sticker Notes:</span><span class="wl-field-value">${log.sticker_note}</span></div>` : ""}
                    ${log.employee_note ? `<div class="wl-field"><span class="wl-field-label">Employee Notes:</span><span class="wl-field-value">${log.employee_note}</span></div>` : ""}
                    <div class="wl-field"><span class="wl-field-label">Current Location:</span><span class="wl-field-value">${log.current_location || "—"}</span></div>
                </body>
                </html>`);
            win.document.close();
            win.print();
        },

        printSelected() {
            this.triggerPrint(this.selectedRows);
        },

        printAll() {
            this.triggerPrint(this.logs);
        },

        triggerPrint(rows) {
            const win = window.open("", "_blank");
            const rows_html = rows
                .map(
                    (r) => `
                <tr>
                    <td>${r.trackingnumber || "—"}</td>
                    <td>${r.serialnumber || "—"}</td>
                    <td>${r.asin || "—"}</td>
                    <td>${r.product_name || "—"}</td>
                    <td>${r.pcn_number || "—"}</td>
                    <td>${r.basket_number || "—"}</td>
                    <td>${r.pass_fail_result === "pass" ? "✓ Pass" : "✗ Fail"}</td>
                    <td>${r.date_received || "—"}</td>
                    <td>${r.received_by || "—"}</td>
                </tr>`,
                )
                .join("");

            win.document.write(`
                <html><head><title>Item Log Print</title>
                <style>
                    body { font-family: Arial, sans-serif; font-size: 12px; }
                    table { width: 100%; border-collapse: collapse; }
                    th, td { border: 1px solid #ccc; padding: 6px 8px; text-align: left; }
                    th { background: #f0f0f0; }
                </style>
                </head><body>
                <h2>Item Log Report</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Tracking #</th><th>Serial</th><th>ASIN</th>
                            <th>Product</th><th>PCN</th><th>Basket</th>
                            <th>Status</th><th>Date</th><th>Received By</th>
                        </tr>
                    </thead>
                    <tbody>${rows_html}</tbody>
                </table>
                </body></html>`);
            win.document.close();
            win.print();
        },
    },
};
