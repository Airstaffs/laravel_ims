<template>
    <teleport to="body">
        <div v-if="show" class="ds7oos-overlay" @click.self="$emit('close')">
            <div class="ds7oos-dialog">
                <header class="ds7oos-head">
                    <h3>DS7 & OOS Settings</h3>
                    <button class="ds7oos-close" @click="$emit('close')">
                        ✕
                    </button>
                </header>

                <section class="ds7oos-body">
                    <div class="row">
                        <label>Days threshold</label>
                        <input type="number" v-model.number="days" min="1" />
                    </div>
                    <small class="hint"
                        >Philosophy: simple inputs, clear actions.</small
                    >
                </section>

                <footer class="ds7oos-foot">
                    <button @click="$emit('close')">Close</button>
                    <button class="primary" @click="$emit('save', { days })">
                        Save
                    </button>
                </footer>
            </div>
        </div>
    </teleport>
</template>

<script>
export default {
    name: "Ds7OosModal",
    props: {
        show: { type: Boolean, default: false },
    },
    data() {
        return {
            days: 30,
        };
    },
};
</script>

<style scoped>
/* Overlay layer */
.ds7oos-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.45);
    display: grid;
    place-items: center;
    z-index: 4000; /* Higher than Bootstrap modals */
}

/* Dialog box */
.ds7oos-dialog {
    width: min(720px, calc(100vw - 24px));
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.25);
    overflow: hidden;
    z-index: 4010;
    animation: fadeIn 0.2s ease-out;
}

/* Header */
.ds7oos-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px 16px;
    border-bottom: 1px solid #eee;
    background: #f8f9fa;
}

.ds7oos-head h3 {
    font-size: 18px;
    margin: 0;
    font-weight: 600;
}

.ds7oos-close {
    background: transparent;
    border: 0;
    font-size: 20px;
    cursor: pointer;
    color: #444;
    line-height: 1;
}

/* Body */
.ds7oos-body {
    padding: 16px;
    display: grid;
    gap: 12px;
}

.row {
    display: grid;
    gap: 6px;
}

label {
    font-weight: 600;
    color: #374151;
}

input {
    width: 100%;
    padding: 8px 10px;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    font-size: 14px;
}

.hint {
    color: #6b7280;
    font-size: 13px;
}

/* Footer */
.ds7oos-foot {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    padding: 12px 16px;
    border-top: 1px solid #eee;
    gap: 8px;
    background: #fafafa;
}

button {
    background: #e5e7eb;
    color: #111827;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    padding: 8px 12px;
    font-size: 14px;
    cursor: pointer;
}

button:hover {
    background: #d1d5db;
}

button.primary {
    background: #2b6cb0;
    color: #fff;
    border: 1px solid #2c5282;
}

button.primary:hover {
    background: #2c5282;
}

/* Animation */
@keyframes fadeIn {
    from {
        opacity: 0;
        transform: scale(0.97);
    }
    to {
        opacity: 1;
        transform: scale(1);
    }
}
</style>
