<template>
    <div class="d-block d-md-none">
        <button
            v-show="showBackToTop"
            class="fab-btn back-top"
            @click="scrollToTop"
        >
            <i class="fas fa-chevron-up"></i>
        </button>

        <button
            v-show="showGoToBottom"
            class="fab-btn go-bottom"
            @click="scrollToBottom"
        >
            <i class="fas fa-chevron-down"></i>
        </button>
    </div>
</template>

<script>
export default {
    name: "ScrollFab",
    props: {
        targetSelector: {
            type: String,
            required: true,
        },
        bottomSelector: {
            type: String,
            default: null,
        },
        offsetTop: {
            type: Number,
            default: 100,
        },
        offsetBottom: {
            type: Number,
            default: 100,
        },
    },
    data() {
        return {
            showBackToTop: false,
            showGoToBottom: true,
        };
    },
    mounted() {
        this.bindScrollListener();
    },
    beforeUnmount() {
        const target = document.querySelector(this.targetSelector);
        if (target) target.removeEventListener("scroll", this.onScroll);
    },
    methods: {
        bindScrollListener() {
            const target = document.querySelector(this.targetSelector);
            if (target) {
                target.addEventListener("scroll", this.onScroll);
                this.onScroll();
            }
        },
        onScroll() {
            const target = document.querySelector(this.targetSelector);
            if (!target) return;

            const scrollTop = target.scrollTop;
            const scrollHeight = target.scrollHeight;
            const clientHeight = target.clientHeight;

            this.showBackToTop = scrollTop > this.offsetTop;
            this.showGoToBottom =
                scrollTop + clientHeight < scrollHeight - this.offsetBottom;
        },
        scrollToTop() {
            const target = document.querySelector(this.targetSelector);
            if (target) {
                target.scrollTo({ top: 0, behavior: "smooth" });
            }
        },
        scrollToBottom() {
            const target = document.querySelector(this.targetSelector);
            const bottomEl = this.bottomSelector
                ? document.querySelector(this.bottomSelector)
                : null;

            if (target) {
                const scrollTo = bottomEl
                    ? bottomEl.offsetTop
                    : target.scrollHeight;
                target.scrollTo({ top: scrollTo, behavior: "smooth" });
            }
        },
    },
};
</script>

<style scoped>
.fab-btn {
    position: fixed;
    right: 16px;
    z-index: 9999;
    width: 56px;
    height: 56px;
    border-radius: 50%;
    border: none;
    background-color: #1e88e5;
    color: white;
    font-size: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.3);
    transition: opacity 0.3s ease-in-out, transform 0.3s;
    cursor: pointer;
}
.fab-btn:active {
    transform: scale(0.96);
}
.fab-btn.back-top {
    bottom: 90px;
}
.fab-btn.go-bottom {
    bottom: 20px;
}
</style>
