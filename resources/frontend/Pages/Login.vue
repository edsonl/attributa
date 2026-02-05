<template>
    <!-- qualquer componente -->
        <Link :href="route('sobre')">Sobre</Link><br/>
        <Link :href="route('home')" label="Ir pra Home" >Home</Link><br/>
        <a :class="{ active: route().current('sobre') }" :href="route('sobre')">Sobre</a>
</template>
<script setup>
    import MainLayout from "@/Layouts/MainLayout.vue";
    defineOptions({ layout: MainLayout })
</script>
