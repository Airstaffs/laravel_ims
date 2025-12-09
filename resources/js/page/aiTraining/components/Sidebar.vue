<template>
    <aside class="w-64 bg-gray-900 text-gray-100 p-4">
        <!-- <img alt="Vue logo" class="logo mb-2" src="" width="40" height="40" /> -->
        <h2 class="text-lg font-semibold mb-4"><i class="fas fa-robot"></i>AI Training</h2>
        <hr class="mb-2" />
        <nav class="space-y-2">
            <RouterLink to="/aiTraining" class="block p-2 rounded hover:bg-indigo-500 transition">
                Training Model
            </RouterLink>
            <RouterLink to="/dataset-management" class="block p-2 rounded hover:bg-indigo-500 transition">
                Dataset Management
            </RouterLink>
            <RouterLink to="/testing-training" class="block p-2 rounded hover:bg-indigo-500 transition">
                Testing Model
            </RouterLink>
        </nav>
    </aside>
</template>

<script setup>
import { RouterLink } from 'vue-router'
</script>
<!-- Sidebar -->