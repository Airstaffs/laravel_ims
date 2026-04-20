<template>
    <div class="quick-match-page">
        <br />
        <div class="wrap">
            <!-- Top controls -->
            <div class="toolbar">
                <div style="display: flex; gap: 8px; flex-wrap: wrap">
                    <button class="btn primary" type="button" @click="setLocked(!locked)">
                        {{ locked ? '🔓 Switch to Edit' : '🔒 Switch to Match' }}
                    </button>

                    <button class="btn primary" type="button" @click="toggleMatchType">
                        <span class="icon stroke-light"
                            v-html="matchType === 'absolute' ? absoluteIconSVG('#ffffff') : likeIconSVG('#ffffff')"></span>
                        <span>
                            {{ matchType === 'absolute' ? 'Match: Absolute' : 'Match: Like' }}
                        </span>
                    </button>
                </div>
            </div>

            <!-- EDIT: reference values -->
            <div class="card" :class="{ dim: locked }">
                <div class="toolbar" style="margin-bottom: 8px">
                    <h2 style="margin: 0; font-size: 18px">Reference values</h2>
                    <button class="btn" @click="addRow" :disabled="locked">+ Add</button>
                </div>

                <table class="table">
                    <tbody>
                        <tr v-for="row in refs" :key="row.id">
                            <td>
                                <input type="text" placeholder="Enter value…" v-model="row.value" :disabled="locked" />
                            </td>
                            <td style="width: 110px">
                                <button class="btn" @click="deleteRow(row.id)" :disabled="locked">
                                    Delete
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <p style="color: #64748b; margin: 6px 0 0; font-size: 10px">
                    Add one or more values. Lock to start quick matching.
                </p>
            </div>

            <!-- MATCH -->
            <div class="card" :class="{ dim: !locked }">
                <div class="toolbar" style="margin-bottom: 8px">
                    <h2 style="margin: 0; font-size: 18px">Quick match</h2>
                    <button class="btn primary" @click="toggleTriggerMode">
                        {{ triggerMode === 'auto' ? 'Trigger: Auto-Enter' : 'Trigger: Typing' }}
                    </button>
                </div>

                <div class="row">
                    <input ref="matchInputRef" v-model="matchInput" type="text"
                        :placeholder="triggerMode === 'auto' ? 'Type to check… (auto)' : 'Type to check… (press Check)'"
                        :disabled="!locked" @input="handleMatchInput" @keyup.enter="handleEnter" />
                    <button class="btn check" :disabled="!locked || triggerMode === 'auto'" @click="checkMatch">
                        Check
                    </button>
                    <button class="btn" :disabled="!locked" @click="clearMatch">Clear</button>
                </div>

                <div style="margin-top: 10px; display: flex; flex-wrap: wrap; gap: 8px">
                    <div v-for="chip in uniqueRefs" :key="chip" class="pill">
                        {{ chip }}
                    </div>
                </div>

                <p style="color: #64748b; margin: 6px 0 0; font-size: 10px">
                    Auto-Enter: submits as soon as you type the first character. Typing: press
                    <em>Check</em>.
                </p>
            </div>
        </div>

        <!-- Modal -->
        <div v-if="showModal" class="modal" style="display: flex" @click.self="closeModal">
            <div class="box">
                <div style="font-size: 16px; margin-bottom: 12px">
                    <template v-if="modalOk">
                        ✅ Match found ({{ matchedList.length }})
                        <div style="text-align: left; margin-top: 8px">
                            <div v-for="item in matchedList" :key="item">• {{ item }}</div>
                        </div>
                    </template>
                    <template v-else>
                        ❌ No match
                    </template>
                </div>
                <button class="btn primary" @click="closeModal">OK</button>
            </div>
        </div>

        <!-- Sounds -->
        <audio ref="successSoundRef" src="/sounds/success.mp3"></audio>
        <audio ref="errorSoundRef" src="/sounds/error.mp3"></audio>
    </div>
</template>

<script>
export default {
    name: 'QuickMatch',
    data() {
        return {
            CASE_SENSITIVE: false,
            LIKE_REQUIRE_RIGHT_SIDE: true,
            LIKE_RIGHT_START_RATIO: 0.5,

            locked: false,
            triggerMode: 'auto', // auto | typing
            matchType: 'absolute', // absolute | like

            refs: [
                { id: this.uid(), value: 'value1' },
                { id: this.uid(), value: 'value2' },
            ],

            matchInput: '',
            lastAutoFired: '',

            showModal: false,
            modalOk: false,
            matchedList: [],

            modalTimeout: null,
        };
    },

    computed: {
        uniqueRefs() {
            return [...new Set(this.refs.map((r) => r.value).filter((v) => v.trim() !== ''))];
        },
    },

    methods: {
        uid() {
            return Math.random().toString(36).slice(2, 9);
        },

        norm(value) {
            return this.CASE_SENSITIVE ? value : value.toLowerCase();
        },

        likeIconSVG(stroke = '#fff') {
            return `
                <svg viewBox="0 0 24 24" fill="none" stroke="${stroke}" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M3 14c2-3 5-3 7 0s5 3 7 0 4-3 4-3"/>
                </svg>
            `;
        },

        absoluteIconSVG(stroke = '#fff') {
            return `
                <svg viewBox="0 0 24 24" fill="none" stroke="${stroke}" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M4 9h16M4 15h16"/>
                </svg>
            `;
        },

        addRow() {
            this.refs.push({
                id: this.uid(),
                value: '',
            });
        },

        deleteRow(id) {
            this.refs = this.refs.filter((row) => row.id !== id);
        },

        setLocked(state) {
            this.locked = state;

            if (this.locked) {
                const hasValidRef = this.refs.some((r) => r.value.trim() !== '');

                if (!hasValidRef) {
                    alert('Add at least one reference value first.');
                    this.locked = false;
                    return;
                }

                this.lastAutoFired = '';
                this.matchInput = '';

                this.$nextTick(() => {
                    this.$refs.matchInputRef?.focus();
                });
            } else {
                this.$nextTick(() => {
                    const firstInput = this.$el.querySelector('tbody input');
                    if (firstInput) {
                        firstInput.focus();
                        firstInput.select();
                    }
                });
            }
        },

        toggleMatchType() {
            this.matchType = this.matchType === 'absolute' ? 'like' : 'absolute';

            this.$nextTick(() => {
                this.$refs.matchInputRef?.focus();
            });
        },

        toggleTriggerMode() {
            this.triggerMode = this.triggerMode === 'auto' ? 'typing' : 'auto';

            this.$nextTick(() => {
                this.$refs.matchInputRef?.focus();
            });
        },

        handleMatchInput() {
            if (!this.locked || this.triggerMode !== 'auto') return;

            const value = this.matchInput;

            if (value && this.lastAutoFired === '') {
                this.lastAutoFired = value;
                this.checkMatch();
            }
        },

        handleEnter() {
            if (this.locked && this.triggerMode === 'typing') {
                this.checkMatch();
            }
        },

        getMatches(inputVal) {
            const val = inputVal.trim();
            if (!val) return [];

            const nVal = this.norm(val);
            const matches = [];

            const refObjs = this.refs.filter((r) => r.value && r.value.trim() !== '');

            if (this.matchType === 'absolute') {
                const set = new Set(refObjs.map((r) => this.norm(r.value)));

                if (set.has(nVal)) {
                    refObjs.forEach((r) => {
                        if (this.norm(r.value) === nVal) {
                            matches.push(r.value);
                        }
                    });
                }
            } else {
                refObjs.forEach((r) => {
                    const nv = this.norm(r.value);
                    const idx = nv.indexOf(nVal);

                    if (idx === -1) return;

                    if (!this.LIKE_REQUIRE_RIGHT_SIDE) {
                        matches.push(r.value);
                        return;
                    }

                    const minStart = Math.floor(nv.length * this.LIKE_RIGHT_START_RATIO);
                    if (idx >= minStart) {
                        matches.push(r.value);
                    }
                });
            }

            return [...new Set(matches)];
        },

        checkMatch() {
            const value = this.matchInput.trim();
            if (!value) return;

            const matched = this.getMatches(value);
            const ok = matched.length > 0;

            this.showResult(ok, matched);

            this.matchInput = '';
            this.lastAutoFired = '';
        },

        clearMatch() {
            this.matchInput = '';
            this.lastAutoFired = '';

            this.$nextTick(() => {
                this.$refs.matchInputRef?.focus();
            });
        },

        showResult(ok, matchedList = []) {
            this.modalOk = ok;
            this.matchedList = matchedList;
            this.showModal = true;

            if (ok) {
                this.$refs.successSoundRef?.play?.();
            } else {
                this.$refs.errorSoundRef?.play?.();
            }

            if (this.modalTimeout) {
                clearTimeout(this.modalTimeout);
            }

            this.modalTimeout = setTimeout(() => {
                this.closeModal();
            }, 900);
        },

        closeModal() {
            this.showModal = false;

            if (this.modalTimeout) {
                clearTimeout(this.modalTimeout);
                this.modalTimeout = null;
            }

            if (this.locked) {
                this.$nextTick(() => {
                    this.$refs.matchInputRef?.focus();
                    this.$refs.matchInputRef?.select?.();
                });
            }
        },
    },

    beforeUnmount() {
        if (this.modalTimeout) {
            clearTimeout(this.modalTimeout);
        }
    },
};
</script>

<style scoped>
:root {
    --card: #fff;
    --muted: #64748b;
    --primary: #2563eb;
    --ring: #cbd5e1;
}

* {
    box-sizing: border-box;
}

.quick-match-page {
    margin: 0;
    font-family: system-ui, -apple-system, Segoe UI, Roboto, Inter, Arial;
    background: #f1f5f9;
    color: #0f172a;
    min-height: 100vh;
}

.wrap {
    max-width: 720px;
    margin: 0 auto;
    padding: 16px;
}

.card {
    background: var(--card);
    border-radius: 16px;
    box-shadow: 0 8px 24px rgba(2, 6, 23, 0.08);
    padding: 16px;
    margin-bottom: 12px;
}

.toolbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
    margin-bottom: 12px;
}

.btn {
    appearance: none;
    border: 1px solid #e2e8f0;
    background: #fff;
    border-radius: 12px;
    padding: 10px 14px;
    font-size: 14px;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.btn.primary {
    background: var(--primary);
    color: #fff;
    border-color: var(--primary);
}

.row {
    display: flex;
    gap: 8px;
    align-items: center;
}

input[type='text'] {
    width: 100%;
    padding: 12px;
    border: 1px solid var(--ring);
    border-radius: 12px;
    font-size: 16px;
}

input:focus {
    outline: 2px solid var(--primary);
    outline-offset: 1px;
}

.table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0 8px;
}

.table td {
    background: #f8fafc;
    padding: 8px;
    border-radius: 10px;
}

.dim {
    opacity: 0.55;
    pointer-events: none;
    filter: grayscale(0.05);
}

.pill {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 10px;
    background: #eef2ff;
    border-radius: 999px;
    font-size: 12px;
}

.modal {
    position: fixed;
    inset: 0;
    align-items: center;
    justify-content: center;
    background: rgba(15, 23, 42, 0.35);
    padding: 16px;
    z-index: 50;
}

.modal .box {
    background: #fff;
    border-radius: 16px;
    min-width: 240px;
    max-width: 90vw;
    padding: 16px;
    box-shadow: 0 10px 30px rgba(2, 6, 23, 0.25);
    text-align: center;
}

.check {
    background: #3fc82a;
    color: #fff;
    border-color: #3fc82a;
}

.icon {
    width: 16px;
    height: 16px;
    display: inline-block;
}

.icon svg {
    width: 16px;
    height: 16px;
    display: block;
}

.icon.stroke-dark svg {
    stroke: #0f172a;
}

.icon.stroke-light svg {
    stroke: #ffffff;
}
</style>