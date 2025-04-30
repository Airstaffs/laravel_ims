<template>
    <div>
        <input type="text" v-model="searchQuery" @input="onSearch" placeholder="Search inventory..." />
    </div>
</template>
<script>
    import searching from "./searching.js";
    export default searching;
</script>