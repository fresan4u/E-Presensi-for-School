<template>
  <div class="flex h-screen w-full overflow-hidden bg-slate-50">
    <!-- Sisi Kiri: Sidebar navigasi -->
    <layout-sidebar />

    <!-- Sisi Kanan: Topbar + area konten -->
    <div class="flex min-w-0 flex-1 flex-col">
      <layout-topbar />

      <!-- Area konten utama, scroll vertikal -->
      <main class="flex-1 overflow-y-auto p-6">
        <slot />
      </main>
    </div>
  </div>
</template>
