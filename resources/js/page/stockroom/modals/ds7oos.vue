<template>

    <!-- 
    
    
    -->
  <teleport to="body">
    <div v-if="show" class="overlay" @click.self="$emit('close')">
      <div class="modal">
        <header class="head">
          <h3>DS7 & OOS Settings</h3>
          <button class="x" @click="$emit('close')">✕</button>
        </header>

        <section class="body">
          <!-- Put your DS7/OOS content/filters/table here -->
          <div class="row">
            <label>Days threshold</label>
            <input type="number" v-model.number="days" min="1" />
          </div>
          <small class="hint">Philosophy: simple inputs, clear actions.</small>
        </section>

        <footer class="foot">
          <button @click="$emit('close')">Close</button>
          <button class="primary" @click="$emit('save', { days })">Save</button>
        </footer>
      </div>
    </div>
  </teleport>
</template>

<script>
export default {
  name: 'Ds7OosModal',
  props: { show: { type: Boolean, default: false } },
  data(){ return { days: 30 }; }
}
</script>

<style scoped>
.overlay { position: fixed; inset: 0; background: rgba(0,0,0,.35); display:grid; place-items:center; z-index:1000; }
.modal { width:min(720px, calc(100vw - 24px)); background:#fff; border-radius:12px; box-shadow:0 10px 30px rgba(0,0,0,.25); overflow:hidden; }
.head, .foot { display:flex; align-items:center; justify-content:space-between; padding:12px 16px; border-bottom:1px solid #eee; }
.foot { border-top:1px solid #eee; border-bottom:none; justify-content:flex-end; gap:8px; }
.body { padding:16px; display:grid; gap:12px; }
.row { display:grid; gap:6px; }
.x { background:transparent; border:0; font-size:18px; cursor:pointer; }
.primary { background:#2b6cb0; color:#fff; border:0; border-radius:6px; padding:8px 12px; }
</style>
