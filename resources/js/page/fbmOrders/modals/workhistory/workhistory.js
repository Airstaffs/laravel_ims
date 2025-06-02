const API_BASE_URL = import.meta.env.VITE_API_URL;

export default {
    props: {
        show: {
            type: Boolean,
            required: true,
        },
    },
    data() {
        return {
            workHistory: null,
            loading: false,
            error: null,
        };
    },
    watch: {
        show(newVal) {
            if (newVal) {
                this.fetchWorkHistory();
            }
        },
    },
    methods: {
        async fetchWorkHistory() {
            this.loading = true;
            this.error = null;
            try {
                const response = await axios.get(
                    "/fbmorders/work-history-test-post"
                );
                this.workHistory = response.data;
            } catch (err) {
                this.error = "Failed to load work history.";
                console.error(err);
            } finally {
                this.loading = false;
            }
        },
    },
};
