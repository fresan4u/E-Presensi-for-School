<template>
  <div class="flex h-screen w-full overflow-hidden bg-slate-50">
    <!-- Sidebar navigasi admin -->
    <layout-sidebar variant="gold" />

    <!-- Topbar + area konten -->
    <div class="flex min-w-0 flex-1 flex-col">
      <layout-topbar />

      <!-- Area konten utama -->
      <main class="flex-1 overflow-y-auto p-6">
        <slot />
      </main>
    </div>
  </div>
</template>