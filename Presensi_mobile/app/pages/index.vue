<script setup lang="ts">
// Akar aplikasi: siswa yang sudah login ke beranda, selain itu ke login.
const { isLoggedIn } = useStudentAuth()

await navigateTo(isLoggedIn.value ? '/siswa' : '/siswa/login', { replace: true })
</script>

<template>
  <div />
</template>
