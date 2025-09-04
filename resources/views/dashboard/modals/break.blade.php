<div class="modal modal-break" id="breakModal" aria-hidden="true" data-status-url="{{ route('hr.break.status') }}"
    data-start-url="{{ route('hr.break.start') }}" data-end-url="{{ route('hr.break.end') }}">

    <div class="modal-overlay"></div>

    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title">Break Center</h2>
            <span id="bk-status-badge" class="badge bg-secondary">Loading…</span>

            <!-- Close button -->
            <button type="button" class="btn-close" aria-label="Close" onclick="closeBreakModal()"></button>
        </div>

        <div class="modal-body" id="break-controls">
            <div class="remaining">
                <h2 id="bk-remaining" class="m-0">0:00</h2>
                <span>Remaining</span>
            </div>
            <div class="used">
                <h5 id="bk-used" class="m-0">0:00</h5>
                <span>Used</span>
            </div>

            <div class="alert alert-info" role="alert">
                <p>Allowance (unpaid): <span id="bk-allowed">0:00</span></p>
                <small class="text-muted d-block">
                    Server-enforced in <b>America/Los_Angeles</b>.
                </small>
            </div>

            <!-- Error placeholder (hidden by default) -->
            {{-- <div id="bk-error" class="alert alert-danger" role="alert" style="display:none"></div> --}}

            <div class="d-flex gap-2">
                <button type="button" class="btn btn-outline-primary" id="bk-start-btn">Start Break</button>
                <button type="button" class="btn btn-outline-secondary" id="bk-end-btn">End Break</button>
            </div>
        </div>
    </div>
</div>


<style scoped>
    .modal-break {
        display: none;
        position: fixed;
        inset: 0;
        z-index: 1050;
    }

    .modal-break.active {
        display: block;
    }

    .modal-overlay {
        position: absolute;
        inset: 0;
        background: rgba(0, 0, 0, 0.5);
    }

    .modal-break .modal-content {
        width: min(600px, 100%);
        background: var(--white);
        border: 1px solid var(--border);
        box-shadow: 0 10px 30px rgba(13, 53, 120, .08);
        padding: clamp(16px, 3.5vw, 28px);
        position: relative;
        background: #fff;
        margin: 100px auto;
        border-radius: 8px;
        z-index: 2;
    }

    .modal-break .modal-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        background: transparent;
        border: 0;
        height: unset;
        padding: 0;
        margin-bottom: 20px;
    }

    .modal-break .modal-body {
        display: flex;
        flex-direction: column;
        justify-content: flex-start;
        align-items: stretch;
        gap: 5px;
        text-align: center;
    }

    .modal-break .modal-body .small {
        text-align: center;
        font-weight: 600;
    }

    @media (max-width: 767px) {
        .modal-break .modal-content {
            margin: 0;
        }
    }
</style>