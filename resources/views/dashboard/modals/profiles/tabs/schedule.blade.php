<div class="tab-pane fade" id="myschedule" role="tabpanel" aria-labelledby="myschedule-tab">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <div class="btn-group">
            <button class="btn btn-outline-secondary" id="schedPrevBtn" type="button" title="Previous month">
                <i class="bi bi-chevron-left"></i>
            </button>

            <!-- Month dropdown with mini calendar -->
            <div class="dropdown">
                <button class="btn btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown" type="button"
                    id="schedMonthBtn">
                    <i class="bi bi-calendar-event me-2"></i><span id="schedMonthLabel">—</span>
                </button>
                <div class="dropdown-menu p-3" style="min-width: 280px;">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <button class="btn btn-sm btn-light" id="miniPrev"><i class="bi bi-chevron-left"></i></button>
                        <div class="fw-semibold" id="miniCaption">—</div>
                        <button class="btn btn-sm btn-light" id="miniNext"><i class="bi bi-chevron-right"></i></button>
                    </div>

                    <!-- Mini calendar grid -->
                    <div id="miniCal" class="mini-grid"></div>
                </div>
            </div>

            <button class="btn btn-outline-secondary" id="schedNextBtn" type="button" title="Next month">
                <i class="bi bi-chevron-right"></i>
            </button>
        </div>

        <div class="small text-muted d-flex align-items-center flex-wrap gap-3">
            <span class="d-inline-flex align-items-center"><span class="status-dot present me-1"></span>Present</span>
            <span class="d-inline-flex align-items-center"><span class="status-dot late me-1"></span>Late</span>
            <span class="d-inline-flex align-items-center"><span class="status-dot absent me-1"></span>Absent</span>
            <span class="d-inline-flex align-items-center"><span class="swatch swatch-today me-1"></span>Today</span>
            <span class="d-inline-flex align-items-center"><span
                    class="swatch swatch-selected me-1"></span>Selected</span>
            <button id="closeAllDayWindows" type="button" class="btn btn-outline-danger btn-sm d-none">Close all day
                windows</button>
        </div>
    </div>

    <!-- Main month calendar -->
    <div id="scheduleCalendar" class="cal-grid" tabindex="0" aria-label="Monthly schedule calendar"></div>

    <!-- Per-day details (optional, shows when a day is clicked) -->
    <div class="mt-3" id="dayDetails" style="display:none;">
        <h6 class="mb-2" id="dayDetailsTitle">Schedule</h6>
        <div id="dayDetailsBody" class="list-group small"></div>
    </div>
</div>

<!-- Minimal styles -->
<style>
    .cal-grid {
        display: grid;
        grid-template-columns: repeat(7, 1fr);
        gap: .5rem;
        user-select: none;
    }

    .cal-grid .dow {
        font-size: .8rem;
        color: var(--bs-secondary);
        text-align: center;
    }

    .cal-grid .cell {
        border: 1px solid var(--bs-border-color);
        border-radius: .75rem;
        padding: .5rem;
        min-height: 92px;
        position: relative;
        background: var(--bs-body-bg);
    }

    .cal-grid .date {
        font-weight: 600;
        font-size: .95rem;
    }

    .cal-grid .meta {
        margin-top: .25rem;
        font-size: .78rem;
        line-height: 1.1;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        /* at most 2 lines */
        -webkit-box-orient: vertical;
        overflow: hidden;
        text-overflow: ellipsis;
        min-height: 1.6em;
        /* keeps row from jittering */
    }

    .cal-grid .is-today {
        outline: 2px solid var(--bs-indigo);
        outline-offset: 2px;
    }

    .cal-grid .is-selected {
        background: #e8f0ff;
    }

    .mini-grid {
        display: grid;
        grid-template-columns: repeat(7, 1fr);
        gap: .25rem;
    }

    .mini-grid .dow {
        font-size: .75rem;
        text-align: center;
        color: var(--bs-secondary);
    }

    .mini-grid button.day {
        border: none;
        background: transparent;
        padding: .3rem .25rem;
        border-radius: .5rem;
    }

    .mini-grid button.day.is-today {
        outline: 2px solid var(--bs-indigo);
        outline-offset: 2px;
    }

    .mini-grid button.day.is-selected {
        background: #e8f0ff;
    }

    .mini-grid button.day:focus {
        outline: none;
        box-shadow: 0 0 0 2px var(--bs-primary);
    }

    .swatch {
        display: inline-block;
        width: 10px;
        height: 10px;
        border-radius: 2px;
        margin-right: .35rem;
        vertical-align: middle;
    }

    .swatch-today {
        background: var(--bs-indigo);
    }

    .swatch-selected {
        background: #e8f0ff;
        border: 1px solid #bcd0ff;
    }

    .badge-holiday {
        font-size: .7rem;
        border: 1px solid var(--bs-border-color);
        background: var(--bs-light);
        padding: .1rem .35rem;
        border-radius: .4rem;
    }

    .floating-day-modal .modal-dialog {
        position: fixed !important;
        margin: 0 !important;
        width: 480px;
        max-width: 92vw;
        top: 80px;
        /* default drop-in */
        left: 80px;
    }

    .floating-day-modal .modal-header {
        cursor: move;
        /* indicate draggable */
    }

    .status-dot {
        display: inline-block;
        width: 8px;
        height: 8px;
        border-radius: 50%;
        vertical-align: middle;
        margin-left: .35rem;
    }

    .status-dot.present {
        background: var(--bs-success);
    }

    .status-dot.late {
        background: var(--bs-warning);
    }

    .status-dot.absent {
        background: var(--bs-danger);
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        // ---- Helpers ----
        const pad = n => (n < 10 ? '0' + n : '' + n);
        const fmtYm = (y, m) => `${y}-${pad(m)}`; // YYYY-MM
        const fmtISO = d => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;

        const today = new Date(); today.setHours(0, 0, 0, 0);

        // State
        let viewYear = today.getFullYear();
        let viewMonth = today.getMonth(); // 0..11
        let selectedDate = new Date(today); // default today
        let monthData = null; // cache from API for current month

        // DOM
        const schedMonthLabel = document.getElementById('schedMonthLabel');
        const schedPrevBtn = document.getElementById('schedPrevBtn');
        const schedNextBtn = document.getElementById('schedNextBtn');
        const miniCaption = document.getElementById('miniCaption');
        const miniPrev = document.getElementById('miniPrev');
        const miniNext = document.getElementById('miniNext');
        const miniCal = document.getElementById('miniCal');
        const cal = document.getElementById('scheduleCalendar');
        const dayDetails = document.getElementById('dayDetails');
        const dayDetailsTitle = document.getElementById('dayDetailsTitle');
        const dayDetailsBody = document.getElementById('dayDetailsBody');
        const closeAllBtn = document.getElementById('closeAllDayWindows');

        const DOW = ['M', 'T', 'W', 'T', 'F', 'S', 'S'];

        function monthName(y, m) {
            return new Date(y, m, 1).toLocaleString(undefined, { month: 'long', year: 'numeric' });
        }
        function startOfMonth(y, m) { return new Date(y, m, 1); }
        function endOfMonth(y, m) { return new Date(y, m + 1, 0); }
        function jsToIsoDow(js) { return js === 0 ? 7 : js; } // Sun(0) -> 7

        // ===== floating Day windows =====
        const openDayModals = new Map();  // id -> { modal, el }
        let zBase = 1060;                  // stack above main modal

        function updateCloseAllBtn() {
            if (!closeAllBtn) return;
            const count = openDayModals.size;
            closeAllBtn.classList.toggle('d-none', !(count > 2));
        }
        if (closeAllBtn) {
            closeAllBtn.addEventListener('click', () => {
                for (const [id, obj] of openDayModals.entries()) {
                    try { obj.modal.hide(); } catch (e) { }
                    obj.el.remove();
                }
                openDayModals.clear();
                updateCloseAllBtn();
            });
        }

        function makeDraggable(modalEl) {
            const dialog = modalEl.querySelector('.modal-dialog');
            const header = modalEl.querySelector('.modal-header');
            let sx = 0, sy = 0, ox = 0, oy = 0, dragging = false;

            function onDown(e) {
                dragging = true;
                const rect = dialog.getBoundingClientRect();
                ox = rect.left;
                oy = rect.top;
                sx = e.clientX;
                sy = e.clientY;
                dialog.style.top = oy + 'px';
                dialog.style.left = ox + 'px';
                dialog.style.userSelect = 'none';
            }
            function onMove(e) {
                if (!dragging) return;
                const nx = ox + (e.clientX - sx);
                const ny = oy + (e.clientY - sy);
                dialog.style.left = Math.max(8, nx) + 'px';
                dialog.style.top = Math.max(8, ny) + 'px';
            }
            function onUp() {
                dragging = false;
                dialog.style.userSelect = '';
            }
            header.addEventListener('mousedown', onDown);
            window.addEventListener('mousemove', onMove);
            window.addEventListener('mouseup', onUp);
        }

        function formatLong(dateObj) {
            return dateObj.toLocaleDateString(undefined,
                { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' });
        }

        function spawnDayModal(dateObj, info, originRect = null) {
            const iso = fmtISO(dateObj);
            const id = `daywin-${iso}-${Date.now()}`;

            // schedule list (AM/PM is already provided by API)
            let schedHtml = '';
            if (info?.entries?.length) {
                schedHtml = info.entries.map(e => `
        <div class="list-group-item d-flex justify-content-between align-items-center">
          <div>
            <div class="fw-semibold">${e.name || 'Shift'}</div>
            ${e.notes ? `<div class="text-muted small">${e.notes}</div>` : ''}
          </div>
          <div>${e.start} – ${e.end}</div>
        </div>`).join('');
            } else {
                schedHtml = `<div class="list-group-item">No schedule</div>`;
            }

            // holidays (full title + holidate)
            let holHtml = '';
            if (info?.holidays?.length) {
                holHtml = `
        <div class="list-group-item">
          <div class="fw-semibold mb-1">Holiday</div>
          <ul class="m-0 ps-3">
            ${info.holidays.map(h => `<li>${h.title} — ${h.date}${h.status ? ` (${h.status})` : ''}</li>`).join('')}
          </ul>
        </div>`;
            }

            const el = document.createElement('div');
            el.className = 'modal fade floating-day-modal';
            el.id = id;
            el.tabIndex = -1;
            el.innerHTML = `
      <div class="modal-dialog shadow-none" role="document" style="z-index:${zBase += 2};position:fixed;top:80px;left:80px;width:480px;max-width:92vw;margin:0;">
        <div class="modal-content">
          <div class="modal-header" style="cursor:move;">
            <h6 class="modal-title">${formatLong(dateObj)}</h6>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body p-0">
            <div class="list-group list-group-flush">
              ${schedHtml}
              ${holHtml}
            </div>
          </div>
        </div>
      </div>
    `;
            document.body.appendChild(el);

            // position near cell if available
            const dialog = el.querySelector('.modal-dialog');
            if (originRect) {
                const top = Math.max(8, originRect.top + window.scrollY + 12);
                const left = Math.max(8, originRect.left + window.scrollX + 12);
                dialog.style.top = top + 'px';
                dialog.style.left = left + 'px';
            }

            const instance = new bootstrap.Modal(el, { backdrop: false, focus: false, keyboard: true });
            instance.show();
            makeDraggable(el);

            el.addEventListener('hidden.bs.modal', () => {
                openDayModals.delete(id);
                el.remove();
                updateCloseAllBtn();
            });

            openDayModals.set(id, { modal: instance, el });
            updateCloseAllBtn();
        }
        // ===== end floating Day windows =====

        // ---- Rendering ----
        function renderMini() {
            miniCaption.textContent = monthName(viewYear, viewMonth);
            miniCal.innerHTML = '';

            // headers
            DOW.forEach(ch => {
                const h = document.createElement('div');
                h.className = 'dow text-center';
                h.textContent = ch;
                miniCal.appendChild(h);
            });

            const first = startOfMonth(viewYear, viewMonth);
            const last = endOfMonth(viewYear, viewMonth);

            // leading blanks align Monday first
            let lead = jsToIsoDow(first.getDay()) - 1; // 0..6
            for (let i = 0; i < lead; i++) miniCal.appendChild(document.createElement('div'));

            for (let d = 1; d <= last.getDate(); d++) {
                const dateObj = new Date(viewYear, viewMonth, d);
                const btn = document.createElement('button');
                btn.className = 'day';
                btn.textContent = d;

                if (dateObj.getTime() === today.getTime()) btn.classList.add('is-today');
                if (fmtISO(dateObj) === fmtISO(selectedDate)) btn.classList.add('is-selected');

                btn.addEventListener('click', () => {
                    selectedDate = new Date(viewYear, viewMonth, d);
                    renderMini();
                    renderMonth();
                    showDay(selectedDate);
                });

                miniCal.appendChild(btn);
            }
        }

        function renderMonth() {
            schedMonthLabel.textContent = monthName(viewYear, viewMonth);
            cal.innerHTML = '';

            // DOW headers
            DOW.forEach(ch => {
                const h = document.createElement('div');
                h.className = 'dow';
                h.textContent = ch;
                cal.appendChild(h);
            });

            const first = startOfMonth(viewYear, viewMonth);
            const last = endOfMonth(viewYear, viewMonth);

            let lead = jsToIsoDow(first.getDay()) - 1; // 0..6
            for (let i = 0; i < lead; i++) {
                const blank = document.createElement('div');
                cal.appendChild(blank);
            }

            for (let d = 1; d <= last.getDate(); d++) {
                const dateObj = new Date(viewYear, viewMonth, d);
                const iso = fmtISO(dateObj);
                const info = monthData?.byDate?.[iso];

                const cell = document.createElement('div');
                cell.className = 'cell';
                if (dateObj.getTime() === today.getTime()) cell.classList.add('is-today');
                if (fmtISO(selectedDate) === iso) cell.classList.add('is-selected');

                // date row with number on left, status dot on right
                const dateEl = document.createElement('div');
                dateEl.className = 'date d-flex align-items-center justify-content-between';
                const num = document.createElement('span');
                num.textContent = d;
                dateEl.appendChild(num);

                // status dot (present | late | absent)
                if (info?.status) {
                    const dot = document.createElement('span');
                    dot.className = `status-dot ${info.status}`;
                    dot.title = info.status.charAt(0).toUpperCase() + info.status.slice(1);
                    dateEl.appendChild(dot);
                }

                // compact meta line
                const metaEl = document.createElement('div');
                metaEl.className = 'meta';

                let compact = (info?.entries && info.entries.length)
                    ? (info.entries.length === 1
                        ? `${info.entries[0].start}–${info.entries[0].end}`
                        : `${info.entries.length} shifts`)
                    : '—';

                if (info?.holidays?.length) {
                    const holidate = dateObj.toLocaleDateString(undefined, { month: 'short', day: 'numeric' });
                    compact = compact === '—' ? `Holiday: ${holidate}` : `${compact} • Holiday: ${holidate}`;
                }

                metaEl.textContent = compact;
                if (info?.holiday_full) metaEl.title = info.holiday_full; // tooltip with full text

                cell.appendChild(dateEl);
                cell.appendChild(metaEl);

                cell.addEventListener('click', () => {
                    selectedDate = dateObj;
                    renderMonth();
                    showDay(dateObj);

                    // open floating day modal at the cell position
                    const rect = cell.getBoundingClientRect();
                    const i = monthData?.byDate?.[fmtISO(dateObj)];
                    spawnDayModal(dateObj, i, rect);
                });

                cal.appendChild(cell);
            }
        }
        function showDay(dateObj) {
            const iso = fmtISO(dateObj);
            const info = monthData?.byDate?.[iso];

            dayDetails.style.display = 'block';
            dayDetailsTitle.textContent = dateObj.toLocaleDateString(
                undefined, { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' });

            dayDetailsBody.innerHTML = '';

            // Optional: show holiday summary line
            if (info?.holidays?.length) {
                const h = document.createElement('div');
                h.className = 'list-group-item';
                h.innerHTML = `<div class="fw-semibold">Holiday</div>
                     <div class="text-muted">${info.holidays.map(x => ((x.status ? x.status + ': ' : '') + x.title)).join(' / ')}</div>`;
                dayDetailsBody.appendChild(h);
            }

            if (!info || !info.entries || info.entries.length === 0) {
                dayDetailsBody.innerHTML += `<div class="list-group-item">No schedule</div>`;
                return;
            }

            info.entries.forEach(e => {
                const item = document.createElement('div');
                item.className = 'list-group-item d-flex justify-content-between align-items-center';
                item.innerHTML = `
        <div>
          <div class="fw-semibold">${e.name || 'Shift'}</div>
          <div class="text-muted">${e.notes || ''}</div>
        </div>
        <div>${e.start} – ${e.end}</div>
      `;
                dayDetailsBody.appendChild(item);
            });
        }

        // ---- Data loading ----
        async function loadMonth(y, m) {
            const ym = fmtYm(y, m + 1); // 1-based month for API
            const url = `/schedule/month?ym=${encodeURIComponent(ym)}`;
            try {
                const response = await fetch(url, {
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });
                const data = response.ok ? await response.json() : null;
                monthData = data || { byDate: {} };
            } catch (e) {
                console.error('Load schedule failed', e);
                monthData = { byDate: {} };
            }
        }

        async function refreshAll() {
            await loadMonth(viewYear, viewMonth);
            renderMini();
            renderMonth();
            showDay(selectedDate);
        }

        function shiftMonth(delta) {
            viewMonth += delta;
            if (viewMonth < 0) { viewMonth = 11; viewYear--; }
            if (viewMonth > 11) { viewMonth = 0; viewYear++; }
            selectedDate = new Date(
                viewYear, viewMonth,
                Math.min(selectedDate.getDate(), endOfMonth(viewYear, viewMonth).getDate())
            );
            refreshAll();
        }

        // ---- Events ----
        schedPrevBtn.addEventListener('click', () => shiftMonth(-1));
        schedNextBtn.addEventListener('click', () => shiftMonth(1));
        miniPrev.addEventListener('click', () => shiftMonth(-1));
        miniNext.addEventListener('click', () => shiftMonth(1));

        // Mouse wheel on main calendar
        cal.addEventListener('wheel', (evt) => {
            evt.preventDefault();
            const delta = evt.deltaY > 0 ? 1 : -1;
            shiftMonth(delta);
        }, { passive: false });

        // Keyboard arrows (left/right) when calendar is focused
        cal.addEventListener('keydown', (evt) => {
            if (evt.key === 'ArrowLeft') shiftMonth(-1);
            if (evt.key === 'ArrowRight') shiftMonth(1);
        });

        // Init
        refreshAll();
    });
</script>
