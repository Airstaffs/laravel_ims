<!-- Manage Announcements Modal -->
<div class="modal fade" id="annManageModal" tabindex="-1" aria-hidden="true">
    <div class="ann-modal-underlay" id="annManageUnderlay" aria-hidden="true"></div>

    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <div class="has-button">
                    <h5 class="modal-title">Announcements</h5>
                    <button type="button" class="btn btn-primary btn-sm" onclick="ANN.openCompose()">
                        <i class="bi bi-plus-lg"></i> New
                    </button>
                </div>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">
                <form onsubmit="event.preventDefault(); ANN.refreshManage();">
                    <fieldset>
                        <label>Status</label>
                        <select id="annFilterStatus" class="form-control form-select form-select-sm"
                            onchange="ANN.refreshManage()">
                            <option value="all">All</option>
                            <option value="active">Active</option>
                            <option value="draft">Draft</option>
                        </select>
                    </fieldset>

                    <fieldset>
                        <label>Search (title/message)</label>
                        <input id="annFilterQ" type="search" class="form-control form-control-sm" placeholder="Search…"
                            oninput="ANN.debouncedRefresh()" />
                    </fieldset>

                    <button class="btn btn-outline-secondary btn-sm" type="button"
                        onclick="ANN.refreshManage()">Refresh</button>
                </form>

                <div class="table-responsive mt-3">
                    <table class="table table-sm align-middle">
                        <thead>
                            <tr>
                                <th style="width:70px;">#</th>
                                <th>Title</th>
                                <th>Status</th>
                                <th>Window</th>
                                <th>Recipients</th>
                                <th style="width:180px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="annManageTbody">
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">Loading…</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="modal-footer">
                <button class="btn btn-light" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>

    <!-- Compose/Edit Announcement Modal -->
    <div class="modal fade" id="annComposeModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <form id="annComposeForm" onsubmit="ANN.submitCompose(); return false;">
                    <div class="modal-header">
                        <h5 class="modal-title" id="annComposeTitle">New Announcement</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"
                            onclick="ANN.resetCompose()"></button>
                    </div>


                    <!-- 👇 modal-body will scroll if too tall -->
                    <div class="modal-body" style="max-height: 75vh; overflow-y: auto;">
                        <input type="hidden" id="annId" value="">

                        <div class="mb-2">
                            <label class="form-label">Title<span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="annTitle" maxlength="255" required>
                        </div>

                        <div class="mb-2">
                            <label class="form-label">Message</label>
                            <textarea class="form-control" id="annMessage" rows="5"></textarea>
                        </div>

                        <div class="row g-2">
                            <div class="col">
                                <label class="form-label">Start (local)</label>
                                <input type="datetime-local" class="form-control" id="annStartAt">
                            </div>
                            <div class="col">
                                <label class="form-label">End (local)</label>
                                <input type="datetime-local" class="form-control" id="annEndAt">
                            </div>
                        </div>

                        <div class="mt-3">
                            <label class="form-label">Status</label>
                            <div class="btn-group" role="group" aria-label="Status">
                                <input type="radio" class="btn-check" name="annStatus" id="annStatusDraft"
                                    autocomplete="off" checked>
                                <label class="btn btn-outline-secondary" for="annStatusDraft">Draft</label>

                                <input type="radio" class="btn-check" name="annStatus" id="annStatusActive"
                                    autocomplete="off">
                                <label class="btn btn-outline-success" for="annStatusActive">Active</label>
                            </div>
                        </div>

                        <hr>

                        <div class="mb-2">
                            <label class="form-label">Recipients</label>

                            <div class="d-flex flex-wrap gap-3 align-items-center mb-2">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="annGroupPH"
                                        onchange="ANN.applyGroupSelection()">
                                    <label class="form-check-label" for="annGroupPH">PH group</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="annGroupUS"
                                        onchange="ANN.applyGroupSelection()">
                                    <label class="form-check-label" for="annGroupUS">US group</label>
                                </div>

                                <div class="ms-auto">
                                    <button type="button" class="btn btn-sm btn-outline-secondary me-1"
                                        onclick="ANN.checkAll(true)">Check all</button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary"
                                        onclick="ANN.checkAll(false)">Uncheck all</button>
                                </div>
                            </div>

                            <input id="annRecipientsFilter" type="search" class="form-control form-control-sm mb-2"
                                placeholder="Filter recipients…" oninput="ANN.filterRecipients()">

                            <div id="annRecipientsList" class="list-group" style="max-height: 250px; overflow-y: auto;">
                            </div>
                            <small class="text-muted">Leave empty to send to everyone.</small>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button class="btn btn-light" type="button" data-bs-dismiss="modal"
                            onclick="ANN.resetCompose()">Cancel</button>
                        <button class="btn btn-primary" type="submit" id="annSaveBtn">Save</button>
                        <button class="btn btn-success" type="button" onclick="ANN.submitCompose('active')"
                            id="annPublishBtn">Save & Activate</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    window.ANN = window.ANN || {};

    document.addEventListener('DOMContentLoaded', function () {

        const API = {
            employees: '/hr/employees',
            adminList: '/hr/announcements/admin',
            save: '/hr/announcements/save',
            toggle: '/hr/announcements/toggle-active',
        };

        // State
        let employees = [];
        let manageRows = [];

        // Modals
        let manageModal = null, composeModal = null;

        // Utils
        const qs = (s, r = document) => r.querySelector(s);
        const qsa = (s, r = document) => Array.from(r.querySelectorAll(s));
        const escapeHtml = (s) => String(s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;')
            .replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
        const fmtWindow = (r) => {
            const s = r.start_at || null, e = r.end_at || null;
            if (!s && !e) return '—';
            if (s && e) return `${s} — ${e}`;
            if (s) return `from ${s}`;
            return `until ${e}`;
        };
        const badge = (ok) => ok
            ? '<span class="badge bg-success">Active</span>'
            : '<span class="badge bg-secondary">Draft</span>';
        const debounce = (fn, t = 300) => { let to; return (...a) => { clearTimeout(to); to = setTimeout(() => fn(...a), t); }; };

        function ensureModals() {
            const m1 = qs('#annManageModal');
            const m2 = qs('#annComposeModal');
            if (m1) manageModal = bootstrap.Modal.getOrCreateInstance(m1, { backdrop: 'static' });
            if (m2) composeModal = bootstrap.Modal.getOrCreateInstance(m2, { backdrop: 'static' });
        }

        function getCheckedRecipientIds() {
            return qsa('input[name="annRecipient"]:checked')
                .map(cb => Number(cb.value));
        }

        function setCheckedRecipientIds(ids) {
            const want = new Set((ids || []).map(Number));
            qsa('input[name="annRecipient"]').forEach(cb => {
                cb.checked = want.has(Number(cb.value));
            });
        }

        function renderRecipientsList() {
            const box = qs('#annRecipientsList');
            if (!box) return;

            // full list for filtering
            box.innerHTML = employees.map(e => {
                const id = String(e.id);
                const name = (e.name || e.username || ('#' + id));
                const acct = e.accounttype ? ` (${e.accounttype})` : '';
                const dataName = (name + ' ' + (e.accounttype || '')).toLowerCase();

                return `
      <label class="list-group-item d-flex align-items-center gap-2"
             data-name="${escapeHtml(dataName)}"
             data-acct="${escapeHtml(e.accounttype || '')}">
        <input class="form-check-input m-0" type="checkbox"
               name="annRecipient" value="${id}">
        <span>${escapeHtml(name)}${escapeHtml(acct)}</span>
      </label>`;
            }).join('');
        }

        // ===== Manage view =====
        function onOpenManage() { refreshManage(); }

        function refreshManage() {
            const tbody = qs('#annManageTbody');
            if (!tbody) return;
            const status = (qs('#annFilterStatus')?.value || 'all');
            const q = (qs('#annFilterQ')?.value || '').trim();

            const params = new URLSearchParams();
            if (status !== 'all') params.set('status', status);
            if (q) params.set('q', q);

            tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">Loading…</td></tr>';

            fetch(`${API.adminList}${params.toString() ? `?${params.toString()}` : ''}`)
                .then(r => r.json())
                .then(rows => { manageRows = Array.isArray(rows) ? rows : []; renderManageTable(); })
                .catch(() => { tbody.innerHTML = '<tr><td colspan="6" class="text-center text-danger py-4">Failed to load.</td></tr>'; });
        }

        function renderManageTable() {
            const tbody = qs('#annManageTbody');
            if (!tbody) return;

            if (!manageRows.length) {
                tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">No announcements.</td></tr>';
                return;
            }
            const toName = id => (employees.find(e => String(e.id) === String(id))?.name) || `#${id}`;
            const toNames = arr => (Array.isArray(arr) ? arr.map(toName).slice(0, 3).join(', ') + (arr.length > 3 ? '…' : '') : 'everyone');

            tbody.innerHTML = manageRows.map(r => `
      <tr>
        <td>${r.id}</td>
        <td>${escapeHtml(r.title || '')}</td>
        <td>${badge(!!r.is_active)}</td>
        <td>${fmtWindow(r)}</td>
        <td>${Array.isArray(r.recipients) && r.recipients.length ? toNames(r.recipients) : 'Everyone'}</td>
        <td class="text-nowrap">
          <button class="btn btn-sm btn-outline-primary me-1" onclick="ANN.openCompose(${r.id})">
            <i class="bi bi-pencil"></i> Edit
          </button>
          <button class="btn btn-sm ${r.is_active ? 'btn-outline-warning' : 'btn-outline-success'}"
                  onclick="ANN.toggleActive(${r.id}, ${r.is_active ? 'false' : 'true'})">
            ${r.is_active ? 'Deactivate' : 'Activate'}
          </button>
        </td>
      </tr>
    `).join('');
        }

        // ===== Compose form =====
        function resetCompose() {
            const ttl = qs('#annComposeTitle'); if (ttl) ttl.textContent = 'New Announcement';

            const id = qs('#annId'); if (id) id.value = '';
            const t = qs('#annTitle'); if (t) t.value = '';
            const m = qs('#annMessage'); if (m) m.value = '';
            const s = qs('#annStartAt'); if (s) s.value = '';
            const e = qs('#annEndAt'); if (e) e.value = '';

            const rd = qs('#annStatusDraft'); if (rd) rd.checked = true;
            const ra = qs('#annStatusActive'); if (ra) ra.checked = false;

            const gph = qs('#annGroupPH'); if (gph) gph.checked = false;
            const gus = qs('#annGroupUS'); if (gus) gus.checked = false;

            qsa('#annRecipients option').forEach(o => o.selected = false);
        }

        function openCompose(id = null) {
            resetCompose();

            // always (re)render list before we select items
            renderRecipientsList();

            if (id != null) {
                const row = manageRows.find(r => String(r.id) === String(id));
                if (row) {
                    qs('#annComposeTitle').textContent = `Edit #${row.id}`;
                    qs('#annId').value = row.id;
                    qs('#annTitle').value = row.title || '';
                    qs('#annMessage').value = row.message || '';
                    qs('#annStartAt').value = row.start_at ? row.start_at.replace(' ', 'T').slice(0, 16) : '';
                    qs('#annEndAt').value = row.end_at ? row.end_at.replace(' ', 'T').slice(0, 16) : '';
                    if (row.is_active) {
                        qs('#annStatusActive').checked = true;
                        qs('#annStatusDraft').checked = false;
                    }
                    // recipients (checkboxes)
                    const ids = Array.isArray(row.recipients) ? row.recipients.map(Number) : [];
                    setCheckedRecipientIds(ids);
                }
            }

            // close Manage first to avoid double focustrap
            const manageEl = qs('#annManageModal');
            const showCompose = () => composeModal && composeModal.show();

            if (manageEl && manageEl.classList.contains('show') && manageModal) {
                manageEl.addEventListener('hidden.bs.modal', showCompose, { once: true });
                manageModal.hide();
            } else {
                showCompose();
            }
        }

        function submitCompose(mode = null) {
            const id = qs('#annId').value || null;
            const title = qs('#annTitle').value.trim();
            const msg = qs('#annMessage').value || '';
            const start = qs('#annStartAt').value || null;
            const end = qs('#annEndAt').value || null;
            const active = qs('#annStatusActive').checked;
            const save_mode = mode ? mode : (active ? 'active' : 'draft');

            if (!title) { alert('Title is required.'); return; }
            if (start && end && (new Date(start) > new Date(end))) { alert('Start must be before End.'); return; }

            const recipients = getCheckedRecipientIds();

            fetch(API.save, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify({ id: id ? Number(id) : null, title, message: msg, start_at: start, end_at: end, save_mode, recipients }),
                credentials: 'include'
            })
                .then(r => r.json())
                .then(d => {
                    if (!d || d.success === false) throw new Error(d?.error || 'Save failed');
                    refreshManage();
                    composeModal && composeModal.hide();
                })
                .catch(e => alert(e.message || 'Save failed.'));
        }

        function checkAll(flag) {
            qsa('input[name="annRecipient"]').forEach(cb => cb.checked = !!flag);
        }

        function filterRecipients(q) {
            const term = (q || '').toLowerCase();
            document.querySelectorAll('#annRecipientsGrid > label').forEach(el => {
                const name = el.getAttribute('data-name') || '';
                el.style.display = name.includes(term) ? '' : 'none';
            });
        }

        // Quick group toggle
        function applyGroupSelection() {
            const ph = !!qs('#annGroupPH')?.checked;
            const us = !!qs('#annGroupUS')?.checked;

            if (!ph && !us) return; // nothing chosen -> leave manual selection as-is

            const want = new Set(
                employees
                    .filter(e =>
                        (ph && e.accounttype === 'PH') ||
                        (us && e.accounttype === 'US'))
                    .map(e => Number(e.id))
            );

            qsa('input[name="annRecipient"]').forEach(cb => {
                cb.checked = want.has(Number(cb.value));
            });
        }

        // Filter by text (exposed on ANN)
        function filterRecipients() {
            const term = (qs('#annRecipientsFilter')?.value || '').trim().toLowerCase();
            qsa('#annRecipientsList .list-group-item').forEach(li => {
                const hay = (li.getAttribute('data-name') || '').toLowerCase();
                li.style.display = hay.includes(term) ? '' : 'none';
            });
        }

        function toggleActive(id, makeActive) {
            fetch(API.toggle, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify({ id: Number(id), make_active: !!makeActive }),
                credentials: 'include'
            })
                .then(r => r.json())
                .then(d => {
                    if (!d || d.success === false) throw new Error(d?.error || 'Toggle failed');
                    refreshManage();
                })
                .catch(e => alert(e.message || 'Toggle failed'));
        }

        function renderRecipients() {
            const grid = document.getElementById('annRecipientsGrid');
            if (!grid) return;

            grid.innerHTML = employees.map(e => `
    <label class="form-check d-flex align-items-center gap-2" data-name="${(e.name || e.username || '').toLowerCase()}"
           data-type="${(e.accounttype || '').toUpperCase()}">
      <input class="form-check-input ann-rec" type="checkbox" value="${e.id}">
      <span>${escapeHtml(e.name || e.username || ('#' + e.id))}${e.accounttype ? ` <small class="text-muted">(${e.accounttype})</small>` : ''}</span>
    </label>
  `).join('');
        }

        function getSelectedRecipientIds() {
            return Array.from(document.querySelectorAll('.ann-rec:checked'))
                .map(cb => Number(cb.value));
        }

        function init() {
            ensureModals();

            // prefetch employees then render checkbox list
            fetch(API.employees)
                .then(r => r.json())
                .then(data => { employees = Array.isArray(data) ? data : []; renderRecipientsList(); })
                .catch(() => { employees = []; renderRecipientsList(); });

            // wire filter input
            const f = qs('#annRecipientsFilter');
            if (f) f.addEventListener('input', filterRecipients);

            window.ANN.debouncedRefresh = debounce(refreshManage, 300);
        }

        // Expose API to HTML handlers
        window.ANN = {
            ...(window.ANN || {}),
            filterRecipients,
            applyGroupSelection,
            checkAll,
            init,
            onOpenManage,
            refreshManage,
            debouncedRefresh: () => { },
            openCompose,
            resetCompose,
            submitCompose,
            toggleActive,

            filterRecipients,
            applyGroupSelection,
            checkAll,
        };

        // auto-init once DOM is ready
        init();
    });

    // 
    (() => {
        const ANN_ENDPOINT = 'hr/dash/announcements';
        const ACK_ENDPOINT = 'hr/dash/announcements/acknowledge';
        const POLL_MS = 60_000; // every minute

        let lastShownId = null;
        let isFetching = false;
        let controller = null;

        function isModalOpen() {
            const el = document.getElementById('announcementModal');
            return el && el.classList.contains('show');
        }

        function renderAnnouncement(ann) {
            document.getElementById("announcementTitle").innerText = ann.title ?? 'Announcement';
            document.getElementById("announcementMessage").innerText = ann.message ?? '';
            const start = ann.start_at || '';
            const end = ann.end_at || '';
            document.getElementById("announcementDuration").innerText =
                (start || end) ? `Duration: ${start} → ${end}` : '';
            const readbyText = (Array.isArray(ann.readby) && ann.readby.length) ? ann.readby.join(", ") : "None";
            document.getElementById("announcementReadBy").innerText = readbyText;

            window.__currentAnnouncementId = ann.id;
        }

        function showAnnouncement(ann) {
            renderAnnouncement(ann);
            lastShownId = ann.id;
            openAnnouncement(); // uses .show class
        }

        async function fetchAnnouncements() {
            if (isFetching) return [];
            isFetching = true;
            controller?.abort();
            controller = new AbortController();
            try {
                const res = await fetch(ANN_ENDPOINT, { credentials: 'same-origin', signal: controller.signal });
                const list = await res.json();
                return Array.isArray(list) ? list : [];
            } catch (err) {
                if (err.name !== 'AbortError') console.error('Error loading announcements:', err);
                return [];
            } finally {
                isFetching = false;
            }
        }

        async function checkAndShow() {
            if (isModalOpen()) return;   // don't interrupt the user
            if (document.hidden) return; // pause when tab not visible

            const list = await fetchAnnouncements();
            if (!list.length) return;

            const ann = list[0]; // your API already filters by time & acknowledgements
            if (ann && ann.id !== lastShownId) {
                showAnnouncement(ann);
            }
        }

        // Expose open/close helpers (use .show class)
        window.openAnnouncement = function () {
            document.getElementById('announcementModal').classList.add('show');
        };
        window.closeAnnouncement = function () {
            document.getElementById('announcementModal').classList.remove('show');
        };

        // Expose acknowledge with success close + debounce next show
        window.acknowledgeAnnouncement = async function () {
            const annId = window.__currentAnnouncementId;
            if (!annId) return closeAnnouncement();

            try {
                const res = await fetch(ACK_ENDPOINT, {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                        "X-CSRF-TOKEN": "{{ csrf_token() }}"
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({ announcement_id: annId })
                });
                const resp = await res.json();
                if (resp && resp.success) {
                    closeAnnouncement();
                    lastShownId = annId; // don't reshow the same one on the next tick
                } else {
                    alert(resp?.message || 'Failed to acknowledge.');
                }
            } catch {
                alert('Network error.');
            }
        };

        // Boot + poll
        document.addEventListener("DOMContentLoaded", () => {
            // initial check
            checkAndShow();

            // avoid duplicate intervals (e.g., hot reload)
            if (window.__announcementPollHandle) clearInterval(window.__announcementPollHandle);

            // poll every minute
            window.__announcementPollHandle = setInterval(checkAndShow, POLL_MS);

            // also check when the tab becomes visible again
            document.addEventListener('visibilitychange', () => {
                if (!document.hidden) checkAndShow();
            });
        });
    })();
</script>

<style>
    /* Ensure the dialog is above the underlay */
    #annManageModal .modal-dialog {
        position: relative;
        z-index: 2;
    }

    /* The underlay sits inside the modal, below the dialog */
    #annManageModal .ann-modal-underlay {
        position: fixed;
        /* follow viewport like Bootstrap backdrop */
        inset: 0;
        background: rgba(0, 0, 0, 0.45);
        backdrop-filter: blur(2px);
        opacity: 0;
        /* hidden by default */
        pointer-events: none;
        /* never block clicks to the dialog */
        transition: opacity .18s ease-in-out;
        z-index: 1;
        /* below .modal-dialog (z-index:2 above) */
    }

    /* When modal is shown, fade the underlay in */
    #annManageModal.show .ann-modal-underlay {
        opacity: 1;
    }

    .modal-header .has-button {
        display: flex;
        justify-content: flex-start;
        align-items: center;
        gap: 10px;
    }

    #annManageModal .modal-body {
        padding: 20px;
    }


    #annManageModal .modal-body form {
        display: flex;
        justify-content: flex-start;
        align-items: flex-end;
        gap: 10px;
    }

    #annManageModal .modal-body form button {
        height: 40px;
    }
</style>