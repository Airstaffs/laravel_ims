<template>
    <div class="msku-creator">
        <h2 class="page-title">Create MSKU</h2>

        <!-- Step 1: Store Selection -->
        <div class="step">
            <h4 class="section-title">Step 1: Select Store</h4>
            <label class="input-label">Store</label>
            <select
                v-model="selectedStore"
                @change="handleStoreChange"
                class="search-input"
            >
                <option disabled value="">Select Store</option>
                <option
                    v-for="store in storeOptions"
                    :key="store"
                    :value="store"
                >
                    {{ store }}
                </option>
            </select>
        </div>

        <!-- Step 2: ASIN Search -->
        <div class="step" v-if="selectedStore">
            <h4 class="section-title">Step 2: Search and Select ASIN</h4>
            <label for="asin-search" class="input-label"
                >Search ASIN or Title</label
            >
            <div class="input-group">
                <input
                    id="asin-search"
                    v-model="asinSearch"
                    @input="fetchAsins"
                    placeholder="e.g. B08XYZ1234 or keywords"
                    class="search-input"
                />
                <span
                    v-if="selectedAsin"
                    class="clear-icon"
                    @click="clearAsinSelection"
                    title="Clear Selection"
                    >✖</span
                >
            </div>

            <div
                class="dropdown"
                v-if="filteredAsins.length > 0"
                ref="dropdownList"
            >
                <div
                    v-for="asin in filteredAsins"
                    :key="asin.ASIN"
                    class="dropdown-item"
                    :class="{
                        'selected-dropdown-item':
                            selectedAsin && asin.ASIN === selectedAsin.ASIN,
                    }"
                    @click="selectAsin(asin)"
                >
                    <div class="asin-title">
                        <strong>{{ asin.ASIN }}</strong> – {{ asin.title }}
                    </div>
                    <div class="asin-meta">
                        <span v-if="asin.storename"
                            >Used by: {{ asin.storename }}</span
                        >
                        <span
                            v-if="
                                selectedAsin && asin.ASIN === selectedAsin.ASIN
                            "
                            class="checkmark"
                            >✔ Selected</span
                        >
                    </div>
                </div>
            </div>

            <div v-if="selectedAsin" class="selected-summary">
                <strong>Selected ASIN:</strong>
                {{ selectedAsin.ASIN }} – {{ selectedAsin.title }}
            </div>
        </div>

        <!-- Step 3: MSKU Generation -->
        <div class="step" v-if="selectedAsin">
            <h4 class="section-title">Step 3: Generate MSKU</h4>

            <label class="input-label">Condition</label>
            <select v-model="selectedCondition" class="search-input">
                <option
                    v-for="(label, key) in conditionMap"
                    :value="key"
                    :key="key"
                >
                    {{ label }}
                </option>
            </select>

            <button
                @click="generateMSKU"
                :disabled="
                    !selectedAsin || !selectedCondition || !selectedStore
                "
                class="button-primary"
            >
                Generate MSKU
            </button>

            <div v-if="generatedMsku" class="generated-box">
                <p><strong>Generated MSKU:</strong></p>
                <div class="generated-msku">{{ generatedMsku }}</div>
                <button @click="saveMsku" class="button-secondary">
                    Save MSKU
                </button>
            </div>

            <div v-if="mskuList.length > 0" class="pending-box">
                <h4>Pending MSKUs for {{ selectedAsin.ASIN }}:</h4>
                <ul class="pending-list">
                    <li v-for="(msku, index) in mskuList" :key="index">
                        {{ msku.msku }} ({{ conditionMap[msku.condition] }})
                        <button
                            @click="removeMsku(index)"
                            class="button-danger"
                        >
                            Remove
                        </button>
                    </li>
                </ul>
                <button @click="submitAllMskus" class="button-primary">
                    Submit All MSKUs
                </button>
            </div>
        </div>
    </div>
</template>

<script>
import CreationMSKU from "./creation_msku.js";
export default CreationMSKU;
</script>
