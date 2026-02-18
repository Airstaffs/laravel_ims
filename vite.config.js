import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
  plugins: [
    laravel({
      input: ['resources/css/app.css', 'resources/js/app.js', 'resources/js/training.js'],
      refresh: true,
    }),
    vue(),
  ],
  resolve: {
    alias: {
      vue: 'vue/dist/vue.esm-bundler.js',
    },
  },
  build: {
    target: 'esnext',
  },
server: {
    host: '127.0.0.1',
    port: 5173,
    hmr: {
        host: '127.0.0.1',
    },
},
});
