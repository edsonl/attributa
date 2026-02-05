<template>
    <div>
        <h1 class="text-h5 q-mb-md">Bem-vindo 🎉</h1>
        <q-btn label="Abrir diálogo" @click="open" />
    </div>
</template>

<script setup>
    /* global route */
    import { Dialog } from 'quasar'
    function open() {
        Dialog.create({ title: 'Olá', message: 'Inertia + Quasar OK!' })
    }
    function teste(){
        console.log(route('home'));
    }

  import MainLayout from "@/Layouts/MainLayout.vue";
  defineOptions({ layout: MainLayout })
</script>
