// break-modal.js
(() => {
    const $id = (id) => document.getElementById(id);

    const root = document.getElementById("breakModal");
    if (!root) return;

    // URLs from modal data-attrs
    const statusUrl = root.dataset.statusUrl;
    const startUrl = root.dataset.startUrl;
    const endUrl = root.dataset.endUrl;

    const csrf =
        document
            .querySelector('meta[name="csrf-token"]')
            ?.getAttribute("content") || "";

    // Formatting helpers
    const fmtMin = (m) => {
        const s = Math.max(0, Math.round(m * 60));
        const h = Math.floor(s / 3600);
        const mm = Math.floor((s % 3600) / 60);
        const ss = s % 60;
        return h > 0
            ? `${h}:${mm.toString().padStart(2, "0")}`
            : `${mm}:${ss.toString().padStart(2, "0")}`;
    };

    const badgeClass = (st) =>
        st === "on_break"
            ? "badge bg-warning text-dark"
            : st === "done"
            ? "badge bg-success"
            : st === "idle"
            ? "badge bg-secondary"
            : "badge bg-dark";

    // State
    let snapshot = null;
    let serverOffsetMs = 0;
    let baseUsedMin = 0;
    let baseServerMs = 0;

    let tickTimer = null;
    let pollTimer = null;

    // Errors
    const showErr = (msg) => {
        const el = $id("bk-error");
        if (!el) return;
        el.textContent = msg;
        el.style.display = "block";
        setTimeout(() => (el.style.display = "none"), 5000);
    };

    // Timers
    const stopTick = () => {
        if (tickTimer) {
            clearInterval(tickTimer);
            tickTimer = null;
        }
    };
    const stopPoll = () => {
        if (pollTimer) {
            clearInterval(pollTimer);
            pollTimer = null;
        }
    };

    // Apply UI snapshot
    function applyUI(snap, usedLive = null, remainingLive = null) {
        const st = snap.status ?? "idle";
        const statusBadge = $id("bk-status-badge");
        if (statusBadge) {
            statusBadge.className = badgeClass(st);
            statusBadge.textContent = snap.hasOpenClock
                ? st.replace("_", " ")
                : "no open shift";
        }

        if ($id("bk-allowed"))
            $id("bk-allowed").textContent = fmtMin(snap.allowedMin ?? 0);
        if ($id("bk-used"))
            $id("bk-used").textContent = fmtMin(usedLive ?? snap.usedMin ?? 0);
        if ($id("bk-remaining"))
            $id("bk-remaining").textContent = fmtMin(
                remainingLive ?? snap.remainingMin ?? 0
            );

        const onBreak = st === "on_break";
        const done = st === "done";

        const canStart =
            snap.hasOpenClock &&
            !onBreak &&
            !done &&
            snap.allowedMin - (usedLive ?? snap.usedMin ?? 0) > 0.0001;

        const canEnd = snap.hasOpenClock && onBreak;

        if ($id("bk-start-btn")) $id("bk-start-btn").disabled = !canStart;
        if ($id("bk-end-btn")) $id("bk-end-btn").disabled = !canEnd;
    }

    // Fetch status
    async function fetchStatus() {
        try {
            const res = await fetch(statusUrl, {
                credentials: "same-origin",
                headers: {
                    Accept: "application/json",
                    "X-Requested-With": "XMLHttpRequest",
                },
            });

            if (!res.ok) {
                if (res.status === 419)
                    throw new Error("Session expired. Refresh the page.");
                if (res.status === 401)
                    throw new Error("Unauthorized. Please sign in again.");
                const ct = res.headers.get("content-type") || "";
                if (ct.includes("text/html"))
                    throw new Error("Authentication required.");
            }

            const data = await res.json();

            if (!data.hasOpenClock) {
                snapshot = {
                    hasOpenClock: false,
                    status: "none",
                    allowedMin: 0,
                    usedMin: 0,
                    remainingMin: 0,
                };
                stopTick();
                applyUI(snapshot);
                return;
            }

            const serverNow = new Date(data.serverNow).getTime();
            serverOffsetMs = serverNow - Date.now();
            baseServerMs = serverNow;
            baseUsedMin = data.usedMin ?? 0;

            snapshot = data;
            applyUI(snapshot);

            if (snapshot.status === "on_break") startTick();
            else stopTick();
        } catch (e) {
            showErr(e.message || "Could not load break status.");
        }
    }

    // Live ticking
    function startTick() {
        stopTick();
        tickTimer = setInterval(() => {
            if (!snapshot || snapshot.status !== "on_break") return;
            const nowMs = Date.now() + serverOffsetMs;
            const deltaMin = Math.max(0, (nowMs - baseServerMs) / 1000 / 60);
            const used = Math.min(snapshot.allowedMin, baseUsedMin + deltaMin);
            const rem = Math.max(0, snapshot.allowedMin - used);
            applyUI(snapshot, used, rem);
            if (rem <= 0.001) fetchStatus();
        }, 1000);
    }

    // POST helper
    async function post(url) {
        try {
            const res = await fetch(url, {
                method: "POST",
                credentials: "same-origin",
                headers: {
                    Accept: "application/json",
                    "X-Requested-With": "XMLHttpRequest",
                    "X-CSRF-TOKEN": csrf,
                },
            });

            if (!res.ok) {
                if (res.status === 419)
                    throw new Error("Session expired. Refresh the page.");
                if (res.status === 401)
                    throw new Error("Unauthorized. Please sign in again.");

                const ct = res.headers.get("content-type") || "";
                if (ct.includes("application/json")) {
                    const data = await res.json().catch(() => ({}));
                    throw new Error(
                        data.error ||
                            data.message ||
                            res.statusText ||
                            "Request failed"
                    );
                } else {
                    throw new Error(res.statusText || "Request failed");
                }
            }

            await fetchStatus();
        } catch (e) {
            showErr(e.message || "Request failed");
        }
    }

    // Button events
    const startBtn = $id("bk-start-btn");
    const endBtn = $id("bk-end-btn");
    if (startBtn) startBtn.addEventListener("click", () => post(startUrl));
    if (endBtn) endBtn.addEventListener("click", () => post(endUrl));

    // Modal open/close
    function openBreakModal(ev) {
        if (ev) ev.preventDefault();
        root.classList.add("active");
        root.setAttribute("aria-hidden", "false");

        fetchStatus();
        stopPoll();
        pollTimer = setInterval(fetchStatus, 30000);
    }

    function closeBreakModal() {
        root.classList.remove("active");
        root.setAttribute("aria-hidden", "true");

        stopTick();
        stopPoll();
    }

    // Wire overlay
    const overlay = root.querySelector(".modal-overlay");
    if (overlay && !overlay.hasAttribute("data-wired")) {
        overlay.setAttribute("data-wired", "1");
        overlay.addEventListener("click", closeBreakModal);
    }

    window.openBreakModal = openBreakModal;
    window.closeBreakModal = closeBreakModal;
    window.breakModalCtrl = { open: openBreakModal, close: closeBreakModal };
})();
