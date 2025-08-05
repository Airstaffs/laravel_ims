<template>
  <div class="hr-module">
    <ul class="nav nav-tabs">
      <li v-for="tab in tabs" :key="tab.name" class="nav-item">
        <a class="nav-link" :class="{ active: currentTab === tab.name }" @click="currentTab = tab.name">
          {{ tab.label }}
        </a>
      </li>
    </ul>

    <component :is="currentTabComponent"></component>
  </div>
</template>
