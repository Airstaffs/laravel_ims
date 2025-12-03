<template>
    <Dialog
        :visible="visible"
        @update:visible="handleVisibilityChange"
        modal
        header="Break Time"
        :style="{ width: '500px' }"
        :breakpoints="{ '768px': '90vw' }"
        :closable="!isBreakRunning"
        :draggable="false"
        class="break-modal"
    >
        <div class="break-content">
            <!-- Break Status -->
            <div v-if="!isBreakRunning && !breakCompleted" class="break-idle">
                <div class="break-icon">
                    <i class="pi pi-pause-circle"></i>
                </div>
                <h3>Ready to take a break?</h3>
                <p>You have {{ formattedTime }} remaining for today.</p>

                <div class="break-info">
                    <i class="pi pi-info-circle"></i>
                    <span
                        >Break timer will start once you click "Start
                        Break"</span
                    >
                </div>
            </div>

            <!-- Break Running -->
            <div v-else-if="isBreakRunning" class="break-active">
                <div
                    class="timer-circle"
                    :class="{ warning: remainingSeconds < 120 }"
                >
                    <div class="timer-text">
                        <div class="time-display">{{ formattedTime }}</div>
                        <div class="time-label">Remaining</div>
                    </div>
                </div>

                <div class="break-progress">
                    <ProgressBar
                        :value="progressPercentage"
                        :showValue="false"
                        :class="{ 'warning-progress': remainingSeconds < 120 }"
                    />
                </div>

                <div class="break-message">
                    <i class="pi pi-clock"></i>
                    <span
                        >Break in progress... Please stay within the area</span
                    >
                </div>
            </div>

            <!-- Break Completed -->
            <div v-else-if="breakCompleted" class="break-completed">
                <div class="completed-icon">
                    <i class="pi pi-check-circle"></i>
                </div>
                <h3>Break Time Used</h3>
                <p v-if="remainingSeconds <= 0">
                    You have used all your break time for today.
                </p>
                <p v-else>Your break has ended.</p>

                <div class="break-stats" v-if="breakDuration !== '0m 0s'">
                    <div class="stat-item">
                        <i class="pi pi-clock"></i>
                        <span>Last break duration: {{ breakDuration }}</span>
                    </div>
                </div>
            </div>
        </div>

        <template #footer>
            <div class="break-footer">
                <Button
                    v-if="!isBreakRunning && !breakCompleted"
                    label="Start Break"
                    icon="pi pi-play"
                    @click="startBreak"
                    :loading="loading"
                    severity="success"
                    class="w-full"
                />

                <Button
                    v-else-if="isBreakRunning"
                    label="End Break Early"
                    icon="pi pi-stop"
                    @click="confirmEndBreak"
                    severity="danger"
                    outlined
                    class="w-full"
                />

                <Button
                    v-else-if="breakCompleted"
                    label="Close"
                    icon="pi pi-times"
                    @click="closeModal"
                    severity="secondary"
                    class="w-full"
                />
            </div>
        </template>
    </Dialog>
</template>

<script setup>
import { ref, computed, watch, onUnmounted } from "vue";
import Dialog from "primevue/dialog";
import Button from "primevue/button";
import ProgressBar from "primevue/progressbar";
import Swal from "sweetalert2";

const props = defineProps({
    visible: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(["update:visible"]);

// State
const isBreakRunning = ref(false);
const breakCompleted = ref(false);
const remainingSeconds = ref(600); // 10 minutes = 600 seconds (default)
const breakStartTime = ref(null);
const breakEndTime = ref(null);
const breakRecordId = ref(null);
const loading = ref(false);
const timerInterval = ref(null);

// Computed
const formattedTime = computed(() => {
    const minutes = Math.floor(remainingSeconds.value / 60);
    const seconds = remainingSeconds.value % 60;
    return `${String(minutes).padStart(2, "0")}:${String(seconds).padStart(
        2,
        "0"
    )}`;
});

const progressPercentage = computed(() => {
    // Calculate progress based on the total allowed time for the day
    // We need to get the original allowed time from when modal opened
    const totalAllowed = 600; // 10 minutes total for the day
    const used = totalAllowed - remainingSeconds.value;
    return (used / totalAllowed) * 100;
});

const breakDuration = computed(() => {
    if (!breakStartTime.value || !breakEndTime.value) return "0m 0s";

    const start = new Date(breakStartTime.value);
    const end = new Date(breakEndTime.value);
    const diff = Math.floor((end - start) / 1000);

    const minutes = Math.floor(diff / 60);
    const seconds = diff % 60;

    return `${minutes}m ${seconds}s`;
});

// Methods
const handleVisibilityChange = (value) => {
    // Prevent closing if break is running
    if (!value && isBreakRunning.value) {
        Swal.fire({
            icon: "warning",
            title: "Break in Progress",
            text: "You cannot close this window while on break. Please end your break first.",
        });
        return;
    }

    emit("update:visible", value);
};

const loadBreakStatus = async () => {
    try {
        console.log("Loading break status...");
        const response = await fetch("/hr/break/status", {
            credentials: "same-origin",
        });
        console.log("Status response status:", response.status);

        const data = await response.json();
        console.log("Status response data:", data);

        if (data.hasOpenClock && data.status === "on_break") {
            isBreakRunning.value = true;
            breakStartTime.value = data.onBreakSince;

            // Use remaining time from server
            remainingSeconds.value = Math.max(
                0,
                Math.floor(data.remainingMin * 60)
            );

            console.log(
                "Break is running. Remaining seconds:",
                remainingSeconds.value
            );

            if (!timerInterval.value) {
                startTimer();
            }
        } else if (data.hasOpenClock && data.status === "idle") {
            // User is clocked in but not on break
            // Set remaining time based on what's left for the day
            remainingSeconds.value = Math.max(
                0,
                Math.floor(data.remainingMin * 60)
            );
            isBreakRunning.value = false;
            breakCompleted.value = false;

            console.log(
                "Not on break. Remaining break time for today:",
                remainingSeconds.value,
                "seconds"
            );

            // Check if all break time is used up
            if (remainingSeconds.value <= 0) {
                breakCompleted.value = true;
                console.log("No break time remaining for today");
            }
        } else if (data.hasOpenClock && data.status === "done") {
            // Break allowance exhausted
            isBreakRunning.value = false;
            breakCompleted.value = true;
            remainingSeconds.value = 0;
            console.log("Break allowance exhausted");
        } else {
            console.log("No open clock");
            remainingSeconds.value = 600; // Default 10 minutes if not clocked in
        }
    } catch (error) {
        console.error("Error loading break status:", error);
        remainingSeconds.value = 600; // Default fallback
    }
};

const startBreak = async () => {
    loading.value = true;

    try {
        // Check if user is clocked in
        const csrfToken = document.querySelector(
            'meta[name="csrf-token"]'
        )?.content;

        if (!csrfToken) {
            throw new Error("CSRF token not found");
        }

        const xhr = new XMLHttpRequest();
        xhr.open("POST", "/hr/break/start", true);
        xhr.setRequestHeader("Content-Type", "application/json");
        xhr.setRequestHeader("X-CSRF-TOKEN", csrfToken);
        xhr.setRequestHeader("X-Requested-With", "XMLHttpRequest");
        xhr.setRequestHeader("Accept", "application/json");
        xhr.withCredentials = true;

        xhr.onload = function () {
            console.log("Break start response status:", xhr.status);
            console.log("Break start response text:", xhr.responseText);

            if (xhr.status >= 200 && xhr.status < 300) {
                try {
                    const result = JSON.parse(xhr.responseText);
                    console.log("Parsed result:", result);

                    if (result.ok === true || result.success === true) {
                        isBreakRunning.value = true;
                        breakCompleted.value = false;
                        breakStartTime.value = new Date().toISOString();

                        // Don't override remainingSeconds - it's already set from loadBreakStatus
                        // This ensures we use the remaining time for the day
                        console.log(
                            "Break started with remaining time:",
                            remainingSeconds.value,
                            "seconds"
                        );

                        startTimer();

                        const minutes = Math.floor(remainingSeconds.value / 60);
                        const seconds = remainingSeconds.value % 60;

                        Swal.fire({
                            icon: "success",
                            title: "Break Started",
                            text: `Your break has started. ${minutes}m ${seconds}s remaining for today.`,
                            timer: 2000,
                            showConfirmButton: false,
                        });
                    } else {
                        throw new Error(
                            result.error ||
                                result.message ||
                                "Failed to start break"
                        );
                    }
                } catch (parseError) {
                    console.error("Parse error:", parseError);
                    console.error("Response text:", xhr.responseText);
                    Swal.fire({
                        icon: "error",
                        title: "Error",
                        text:
                            "Invalid response from server: " +
                            parseError.message,
                    });
                }
            } else if (xhr.status === 422) {
                try {
                    const error = JSON.parse(xhr.responseText);
                    console.error("422 error:", error);
                    Swal.fire({
                        icon: "error",
                        title: "Cannot Start Break",
                        text:
                            error.error ||
                            "No open shift found. You must clock in first.",
                    });
                } catch (e) {
                    Swal.fire({
                        icon: "error",
                        title: "Cannot Start Break",
                        text: "No open shift found. You must clock in first.",
                    });
                }
            } else if (xhr.status === 409) {
                try {
                    const error = JSON.parse(xhr.responseText);
                    console.error("409 error:", error);
                    Swal.fire({
                        icon: "warning",
                        title: "Already on Break",
                        text: error.error || "You are already on break",
                    });
                } catch (e) {
                    Swal.fire({
                        icon: "warning",
                        title: "Already on Break",
                        text: "You are already on break",
                    });
                }
            } else {
                try {
                    const error = JSON.parse(xhr.responseText);
                    console.error("Other error:", error);
                    Swal.fire({
                        icon: "error",
                        title: "Error",
                        text:
                            error.error ||
                            error.message ||
                            "Failed to start break",
                    });
                } catch (e) {
                    console.error(
                        "Cannot parse error response:",
                        xhr.responseText
                    );
                    Swal.fire({
                        icon: "error",
                        title: "Error",
                        text: "Failed to start break: " + xhr.status,
                    });
                }
            }

            loading.value = false;
        };

        xhr.onerror = function () {
            Swal.fire({
                icon: "error",
                title: "Network Error",
                text: "Failed to connect to server",
            });
            loading.value = false;
        };

        xhr.send(JSON.stringify({}));
    } catch (error) {
        console.error("Error starting break:", error);
        Swal.fire({
            icon: "error",
            title: "Error",
            text: error.message || "Failed to start break",
        });
        loading.value = false;
    }
};

const startTimer = () => {
    stopTimer(); // Clear any existing timer

    timerInterval.value = setInterval(() => {
        remainingSeconds.value--;

        if (remainingSeconds.value <= 0) {
            endBreakAutomatically();
        }
    }, 1000);
};

const stopTimer = () => {
    if (timerInterval.value) {
        clearInterval(timerInterval.value);
        timerInterval.value = null;
    }
};

const endBreakAutomatically = async () => {
    stopTimer();
    await endBreak();

    Swal.fire({
        icon: "info",
        title: "Break Time Over",
        text: "Your break time has ended",
        timer: 3000,
    });
};

const confirmEndBreak = async () => {
    const result = await Swal.fire({
        title: "End Break Early?",
        text: "Are you sure you want to end your break early?",
        icon: "question",
        showCancelButton: true,
        confirmButtonColor: "#dc3545",
        cancelButtonColor: "#6c757d",
        confirmButtonText: "Yes, end break",
        cancelButtonText: "Cancel",
    });

    if (result.isConfirmed) {
        stopTimer();
        await endBreak();

        // Show brief success message
        await Swal.fire({
            icon: "success",
            title: "Break Ended",
            text: "Your break has been ended",
            timer: 1500,
            showConfirmButton: false,
        });

        // Close modal after ending break early
        closeModal();
    }
};

const endBreak = async () => {
    try {
        const csrfToken = document.querySelector(
            'meta[name="csrf-token"]'
        )?.content;

        if (!csrfToken) {
            throw new Error("CSRF token not found");
        }

        const xhr = new XMLHttpRequest();
        xhr.open("POST", "/hr/break/end", true);
        xhr.setRequestHeader("Content-Type", "application/json");
        xhr.setRequestHeader("X-CSRF-TOKEN", csrfToken);
        xhr.setRequestHeader("X-Requested-With", "XMLHttpRequest");
        xhr.setRequestHeader("Accept", "application/json");
        xhr.withCredentials = true;

        xhr.onload = function () {
            if (xhr.status >= 200 && xhr.status < 300) {
                try {
                    const result = JSON.parse(xhr.responseText);

                    if (result.success || result.ok) {
                        breakEndTime.value =
                            result.end_time || new Date().toISOString();
                        isBreakRunning.value = false;
                        breakCompleted.value = true;

                        // Reload status to get updated remaining time
                        loadBreakStatus();
                    }
                } catch (parseError) {
                    console.error("Parse error:", parseError);
                }
            }
        };

        xhr.onerror = function () {
            console.error("Failed to end break");
        };

        xhr.send(JSON.stringify({ break_id: breakRecordId.value }));
    } catch (error) {
        console.error("Error ending break:", error);
    }
};

const closeModal = () => {
    resetBreakState();
    emit("update:visible", false);
};

const resetBreakState = () => {
    isBreakRunning.value = false;
    breakCompleted.value = false;
    remainingSeconds.value = 600;
    breakStartTime.value = null;
    breakEndTime.value = null;
    breakRecordId.value = null;
    stopTimer();
};

// Watch for modal open
watch(
    () => props.visible,
    (newVal) => {
        if (newVal) {
            // Load current break status when modal opens
            loadBreakStatus();
        } else {
            // Don't reset if break is running
            if (!isBreakRunning.value) {
                resetBreakState();
            }
        }
    }
);

// Cleanup on unmount
onUnmounted(() => {
    stopTimer();
});
</script>

<style src="./BreakModalGlobal.css"></style>
<style scoped src="./BreakModal.css"></style>
