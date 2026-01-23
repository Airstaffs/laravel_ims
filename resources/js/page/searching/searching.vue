<template>
    <div class="search-wrapper">
        <div class="p-inputgroup search-input">
            <span class="p-inputgroup-addon search-icon">
                <i class="pi pi-search"></i>
            </span>
            <InputText
                ref="searchInput"
                v-model="searchQuery"
                @input="onSearch"
                placeholder="Search inventory..."
                class="p-inputtext-sm"
            />
            <button
                v-if="searchQuery"
                @click="clearSearch"
                class="clear-button"
                type="button"
            >
                <i class="pi pi-times"></i>
            </button>
        </div>
    </div>
</template>

<script>
import InputText from "primevue/inputtext";
import searching from "./searching.js";

export default {
    ...searching,
    components: {
        InputText,
    },
};
</script>

<style scoped>
.search-wrapper {
    width: 100%;
    display: flex;
    justify-content: center;
}

.search-input {
    position: relative;
    width: 100%;
    max-width: 600px;
}

.search-icon {
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);
    z-index: 1;
    background: transparent;
    border: none;
    padding: 0;
}

.search-icon .pi-search {
    color: #6c757d;
    font-size: 16px;
}

.clear-button {
    position: absolute;
    right: 40px;
    top: 50%;
    transform: translateY(-50%);
    z-index: 2;
    background: transparent;
    border: none;
    padding: 4px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    transition: all 0.2s ease;
}

.clear-button:hover {
    background-color: rgba(108, 117, 125, 0.1);
}

.clear-button .pi-times {
    color: #6c757d;
    font-size: 14px;
}

.search-input :deep(.p-inputtext) {
    width: 100%;
    padding: 0.75rem 2.5rem 0.75rem 1rem;
    border-radius: 8px;
    border: 1px solid rgba(255, 255, 255, 0.3);
    background-color: rgba(255, 255, 255, 0.9);
    transition: all 0.3s ease;
}

.search-input :deep(.p-inputtext:focus) {
    border-color: white;
    box-shadow: 0 0 0 0.2rem rgba(255, 255, 255, 0.25);
    background-color: white;
}

.search-input :deep(.p-inputtext::placeholder) {
    color: #6c757d;
}

/* Tablet */
@media (max-width: 992px) {
    .search-input {
        max-width: 400px;
    }

    .search-input :deep(.p-inputtext) {
        padding: 0.65rem 2.5rem 0.65rem 0.75rem;
        font-size: 14px;
    }

    .clear-button {
        right: 36px;
    }
}

/* Mobile */
@media (max-width: 768px) {
    .search-wrapper {
        padding: 0;
    }

    .search-input {
        max-width: 100%;
    }

    .search-input :deep(.p-inputtext) {
        padding: 0.6rem 2.5rem 0.6rem 0.75rem;
        font-size: 14px;
        border-radius: 6px;
    }

    .search-icon .pi-search {
        font-size: 14px;
    }

    .clear-button {
        right: 34px;
    }

    .clear-button .pi-times {
        font-size: 13px;
    }
}

/* Small Mobile */
@media (max-width: 576px) {
    .search-input :deep(.p-inputtext) {
        padding: 0.5rem 2.25rem 0.5rem 0.65rem;
        font-size: 13px;
    }

    .search-icon {
        right: 10px;
    }

    .search-icon .pi-search {
        font-size: 13px;
    }

    .clear-button {
        right: 32px;
    }

    .clear-button .pi-times {
        font-size: 12px;
    }
}
</style>