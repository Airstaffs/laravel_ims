<template>
    <div class="item-checker-page">
        <audio ref="successSound" :src="successSoundSrc"></audio>
        <audio ref="errorSound" :src="errorSoundSrc"></audio>
        <audio ref="workingSound" :src="workingSoundSrc"></audio>
        <audio ref="notWorkingSound" :src="notWorkingSoundSrc"></audio>

        <div class="checker-container">
            <div class="form-block">
                <h2>Enter Serial Number or PCN</h2>

                <label class="checkbox-row">
                    <input v-model="autoSubmit" type="checkbox" />
                    Auto-submit after typing
                </label>

                <input
                    ref="serialInput"
                    v-model="serialnumber"
                    type="text"
                    placeholder="Enter Serial Number"
                    @input="handleInput"
                    @keyup.enter="handleManualSubmit"
                />

                <button v-if="!autoSubmit" @click="handleManualSubmit" :disabled="loading">
                    {{ loading ? 'Checking...' : 'Check Item Status' }}
                </button>
            </div>

            <template v-if="canUpdate">
                <label class="checkbox-row update-toggle">
                    <input v-model="updateMode" type="checkbox" />
                    Update mode
                </label>

                <div v-if="updateMode" class="update-panel">
                    <label class="field-label">
                        Update status To
                        <select v-model="targetStatus">
                            <option value="Not Working">Not Working</option>
                            <option value="Working">Working</option>
                        </select>
                    </label>

                    <label class="field-label">
                        Reason (optional)
                        <input
                            v-model="updateReason"
                            type="text"
                            placeholder="e.g., Failed QA beep"
                        />
                    </label>

                    <label class="checkbox-row">
                        <input v-model="oneShot" type="checkbox" />
                        One-shot (turn off update mode after this update)
                    </label>
                </div>
            </template>

            <div v-if="showConfirmBar" class="confirm-bar">
                <div class="confirm-text">
                    Change {{ pendingUpdate?.serial }} from
                    {{ lastLookup?.currentStatus }} → {{ pendingUpdate?.newStatus }}?
                </div>

                <div class="confirm-actions">
                    <button class="confirm-btn" @click="confirmUpdate">
                        Confirm (Enter)
                    </button>
                    <button class="cancel-btn" @click="cancelUpdate">
                        Cancel (Esc)
                    </button>
                </div>
            </div>

            <div class="message-box">
                <div v-if="message.text">
                    <div class="status-icon" :class="message.isWorking ? 'working-icon' : 'not-working-icon'">
                        {{ message.icon }}
                    </div>
                    <div
                        class="status-message"
                        :style="{ color: message.isWorking ? 'green' : 'red' }"
                    >
                        {{ message.text }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import axios from 'axios';

export default {
    name: 'ItemChecker',
    props: {
        canUpdate: {
            type: Boolean,
            default: false,
        },
        apiBase: {
            type: String,
            default: '/api/utils/scanner',
        },
        successSoundSrc: {
            type: String,
            default: '/assets/sound/succ.mp3',
        },
        errorSoundSrc: {
            type: String,
            default: '/assets/sound/error.mp3',
        },
        workingSoundSrc: {
            type: String,
            default: '/media/Working.mp3',
        },
        notWorkingSoundSrc: {
            type: String,
            default: '/media/Not_Working.mp3',
        },
    },
    data() {
        return {
            serialnumber: '',
            autoSubmit: true,
            typingTimer: null,
            doneTypingInterval: 1000,
            loading: false,

            updateMode: false,
            targetStatus: 'Not Working',
            updateReason: '',
            oneShot: true,

            lastLookup: null,
            pendingUpdate: null,
            showConfirmBar: false,

            message: {
                text: '',
                isWorking: false,
                icon: '',
            },
        };
    },
    mounted() {
        if (this.$refs.serialInput) {
            this.$refs.serialInput.focus();
        }

        document.addEventListener('keydown', this.handleKeydown);
    },
    beforeUnmount() {
        document.removeEventListener('keydown', this.handleKeydown);

        if (this.typingTimer) {
            clearTimeout(this.typingTimer);
        }
    },
    methods: {
        showMessage(text, isWorking, icon) {
            this.message = {
                text,
                isWorking,
                icon,
            };
        },

        playAudio(type) {
            try {
                if (type === 'success' && this.$refs.successSound) this.$refs.successSound.play();
                if (type === 'error' && this.$refs.errorSound) this.$refs.errorSound.play();
                if (type === 'working' && this.$refs.workingSound) this.$refs.workingSound.play();
                if (type === 'notWorking' && this.$refs.notWorkingSound) this.$refs.notWorkingSound.play();
            } catch (e) {
                console.warn('Audio play blocked:', e);
            }
        },

        handleInput() {
            if (this.typingTimer) {
                clearTimeout(this.typingTimer);
            }

            if (!this.autoSubmit) return;

            this.typingTimer = setTimeout(() => {
                const serial = this.serialnumber.trim();
                this.fetchOrder(serial);
            }, this.doneTypingInterval);
        },

        handleManualSubmit() {
            const serial = this.serialnumber.trim();
            this.fetchOrder(serial);
        },

        async fetchOrder(serial) {
            if (!serial) {
                this.showMessage('Please enter a serial number.', false, '❌');
                this.playAudio('error');
                return;
            }

            this.loading = true;
            this.showConfirmBar = false;
            this.pendingUpdate = null;

            try {
                const response = await axios.post(`${this.apiBase}/check`, {
                    serialnumber: serial,
                });

                const parsed = response.data;
                this.serialnumber = '';

                if (parsed.success) {
                    const itemStatus = parsed.itemstatus || '';

                    if (itemStatus === 'Working') {
                        this.playAudio('working');
                        this.showMessage('Working', true, '✅');
                    } else if (itemStatus === 'Not Working') {
                        this.playAudio('notWorking');
                        this.showMessage('Not Working', false, '❌');
                    } else {
                        this.playAudio('success');
                        this.showMessage(`Status: ${itemStatus}`, true, 'ℹ️');
                    }

                    this.lastLookup = {
                        serial,
                        currentStatus: itemStatus,
                    };

                    if (this.updateMode && itemStatus) {
                        if (this.targetStatus && this.targetStatus !== itemStatus) {
                            this.pendingUpdate = {
                                serial,
                                newStatus: this.targetStatus,
                                reason: this.updateReason.trim(),
                            };
                            this.showConfirmBar = true;
                        } else {
                            this.showMessage(`Already ${itemStatus}. No change.`, true, 'ℹ️');
                        }
                    }
                } else {
                    this.playAudio('error');
                    this.showMessage(parsed.message || 'Lookup failed.', false, '❌');
                }
            } catch (error) {
                this.serialnumber = '';
                this.playAudio('error');
                this.showMessage(
                    error?.response?.data?.message || 'Request failed.',
                    false,
                    '❌'
                );
            } finally {
                this.loading = false;
                this.$nextTick(() => {
                    if (this.$refs.serialInput) {
                        this.$refs.serialInput.focus();
                    }
                });
            }
        },

        async confirmUpdate() {
            if (!this.pendingUpdate) return;

            try {
                const response = await axios.post(`${this.apiBase}/update`, {
                    serialnumber: this.pendingUpdate.serial,
                    status: this.pendingUpdate.newStatus,
                    reason: this.pendingUpdate.reason,
                });

                const parsed = response.data;
                this.showConfirmBar = false;

                if (parsed.success) {
                    this.playAudio('success');
                    this.showMessage(`Updated to ${parsed.newstatus || this.pendingUpdate.newStatus}`, true, '✅');

                    this.lastLookup = {
                        serial: this.pendingUpdate.serial,
                        currentStatus: parsed.newstatus || this.pendingUpdate.newStatus,
                    };

                    this.pendingUpdate = null;

                    if (this.oneShot) {
                        this.updateMode = false;
                    }
                } else {
                    this.playAudio('error');
                    this.showMessage(parsed.message || 'Update failed.', false, '❌');
                }
            } catch (error) {
                this.playAudio('error');
                this.showMessage(
                    error?.response?.data?.message || 'Request failed.',
                    false,
                    '❌'
                );
            }
        },

        cancelUpdate() {
            this.showConfirmBar = false;
            this.pendingUpdate = null;
            this.showMessage('Update canceled.', false, '❌');
        },

        handleKeydown(e) {
            const key = e.key.toLowerCase();

            if (this.canUpdate && key === 'u') {
                this.updateMode = !this.updateMode;
                if (!this.updateMode) {
                    this.showConfirmBar = false;
                    this.pendingUpdate = null;
                }
            }

            if (!this.updateMode) return;

            if (key === 'n') {
                this.targetStatus = 'Not Working';
            }

            if (key === 'w') {
                this.targetStatus = 'Working';
            }

            if (this.showConfirmBar) {
                if (e.key === 'Enter') {
                    this.confirmUpdate();
                }

                if (e.key === 'Escape') {
                    this.cancelUpdate();
                }
            }
        },
    },
};
</script>

<style scoped>
.item-checker-page {
    margin: 0;
    padding: 1.5rem;
    font-family: Arial, sans-serif;
    background-color: #f2f2f2;
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: 100vh;
}

.checker-container {
    width: 100%;
    max-width: 400px;
    background-color: #ffffff;
    padding: 2rem;
    border-radius: 10px;
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
}

.form-block {
    margin-bottom: 1rem;
}

h2 {
    font-size: 1.5rem;
    margin-bottom: 1rem;
    text-align: center;
}

input[type="text"],
select {
    width: 100%;
    padding: 0.75rem;
    font-size: 1rem;
    border: 1px solid #ccc;
    border-radius: 6px;
    margin-bottom: 1rem;
    box-sizing: border-box;
}

button {
    width: 100%;
    padding: 0.75rem;
    font-size: 1rem;
    background-color: #007bff;
    color: white;
    border: none;
    border-radius: 6px;
    cursor: pointer;
}

button:hover {
    background-color: #0056b3;
}

button:disabled {
    opacity: 0.7;
    cursor: not-allowed;
}

.checkbox-row {
    display: block;
    margin-bottom: 10px;
}

.update-toggle {
    margin: 10px 0;
}

.update-panel {
    display: block;
    margin: 10px 0;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 6px;
}

.field-label {
    display: block;
    margin-bottom: 6px;
}

.confirm-bar {
    margin-top: 10px;
    padding: 10px;
    border-radius: 6px;
    background: #fff3cd;
    border: 1px solid #ffeeba;
}

.confirm-text {
    margin-bottom: 8px;
    font-weight: bold;
}

.confirm-actions {
    display: flex;
    gap: 8px;
}

.confirm-actions button {
    width: 100%;
}

.confirm-btn {
    background-color: #28a745;
}

.confirm-btn:hover {
    background-color: #218838;
}

.cancel-btn {
    background-color: #dc3545;
}

.cancel-btn:hover {
    background-color: #c82333;
}

.message-box {
    background-color: #fff;
    border-radius: 7px;
    max-width: 400px;
    box-shadow: 0 0 10px rgba(0, 0, 0, 0.2);
    padding: 10px;
    margin-top: 20px;
    overflow: visible;
    max-height: none;
    min-width: 290px;
    text-align: center;
    min-height: 110px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.status-icon {
    font-size: 3rem;
    margin: 10px 0;
}

.working-icon {
    color: green;
}

.not-working-icon {
    color: red;
}

.status-message {
    font-size: 1.2rem;
    font-weight: bold;
    margin: 10px 0;
}

@media (max-width: 480px) {
    .checker-container {
        padding: 1.5rem;
    }

    h2 {
        font-size: 1.25rem;
    }
}
</style>