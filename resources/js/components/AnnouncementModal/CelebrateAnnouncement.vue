<template>
    <Dialog
        v-model:visible="visible"
        modal
        :closable="false"
        :style="{ width: '38rem' }"
        :breakpoints="{ '960px': '75vw', '641px': '90vw' }"
        :pt="{ header: { style: 'display:none' } }"
    >
        <div class="celebrate-wrapper text-center">
            <!-- Banner -->
            <div class="celebrate-banner" :style="bannerStyle">
                <div class="celebrate-emoji">{{ emoji }}</div>
                <h2 class="celebrate-title">{{ announcement.title }}</h2>
            </div>

            <!-- Message -->
            <div class="celebrate-body">
                <p style="white-space: pre-line">{{ announcement.content }}</p>
            </div>
        </div>

        <template #footer>
            <div class="text-center w-full">
                <Button
                    :label="
                        acknowledging ? 'Acknowledging...' : 'Acknowledge 🎉'
                    "
                    :loading="acknowledging"
                    severity="success"
                    class="w-full"
                    @click="acknowledge"
                    autofocus
                />
            </div>
        </template>
    </Dialog>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from "vue";
import Dialog from "primevue/dialog";
import Button from "primevue/button";

// ── State ──────────────────────────────────────────
const visible = ref(false);
const acknowledging = ref(false);
const announcement = ref({});
const lastShownId = ref(null);

// ── Computed ───────────────────────────────────────
const isBirthday = computed(() => announcement.value?.type === "birthday");

const emoji = computed(() => (isBirthday.value ? "🎂" : "🏆"));

const bannerStyle = computed(() => ({
    background: isBirthday.value
        ? "linear-gradient(135deg, #fff3e0, #ffe0b2)"
        : "linear-gradient(135deg, #e8f5e9, #c8e6c9)",
    borderRadius: "8px",
    padding: "1.5rem",
    marginBottom: "1rem",
}));

const duration = computed(() => {
    const { start_at, end_at } = announcement.value;
    if (!start_at && !end_at) return "";
    const fmt = (d) => new Date(d).toLocaleDateString();
    if (start_at && end_at) return `${fmt(start_at)} — ${fmt(end_at)}`;
    if (start_at) return `From ${fmt(start_at)}`;
    return `Until ${fmt(end_at)}`;
});

// ── Polling ────────────────────────────────────────
const check = async () => {
    if (visible.value) return;
    if (document.hidden) return;

    try {
        const res = await fetch("/hr/dash/announcements", {
            credentials: "same-origin",
        });
        const list = await res.json();

        if (!Array.isArray(list)) return;

        // Only pick birthday or anniversary types
        const found = list.find(
            (a) =>
                ["birthday", "anniversary"].includes(a.type) &&
                a.id !== lastShownId.value,
        );

        if (found) {
            announcement.value = found;
            lastShownId.value = found.id;
            visible.value = true;
        }
    } catch (err) {
        console.error("CelebrateAnnouncement check error:", err);
    }
};

// ── Acknowledge ────────────────────────────────────
const acknowledge = async () => {
    const annId = announcement.value?.id;
    if (!annId) {
        visible.value = false;
        return;
    }

    acknowledging.value = true;
    try {
        const csrfToken = document.querySelector(
            'meta[name="csrf-token"]',
        )?.content;

        const res = await fetch("/hr/dash/announcements/acknowledge", {
            method: "POST",
            credentials: "same-origin",
            headers: {
                "Content-Type": "application/json",
                "X-CSRF-TOKEN": csrfToken,
                "X-Requested-With": "XMLHttpRequest",
                Accept: "application/json",
            },
            body: JSON.stringify({
                announcement_id: annId,
                username:
                    window.userName ||
                    document.querySelector('meta[name="user-name"]')?.content,
            }),
        });

        const data = await res.json();

        if (data?.success) {
            visible.value = false;
        } else {
            Swal.fire({
                icon: "error",
                title: "Error",
                text: data?.message || "Failed to acknowledge",
            });
        }
    } catch (err) {
        console.error("Acknowledge error:", err);
        Swal.fire({
            icon: "error",
            title: "Network Error",
            text: "Could not acknowledge announcement",
        });
    } finally {
        acknowledging.value = false;
    }
};

// ── Lifecycle ──────────────────────────────────────
let pollInterval;

onMounted(() => {
    check();
    pollInterval = setInterval(check, 60000);
    document.addEventListener("visibilitychange", () => {
        if (!document.hidden) check();
    });
});

onUnmounted(() => {
    clearInterval(pollInterval);
});
</script>

<style scoped>
.celebrate-wrapper {
    padding: 0.5rem;
}
.celebrate-banner {
    display: flex;
    flex-direction: column;
    align-items: center;
}
.celebrate-emoji {
    font-size: 4rem;
    line-height: 1;
    margin-bottom: 0.5rem;
}
.celebrate-title {
    font-size: 1.3rem;
    font-weight: 700;
    margin: 0;
}
.celebrate-body {
    padding: 0 0.5rem;
}
</style>
