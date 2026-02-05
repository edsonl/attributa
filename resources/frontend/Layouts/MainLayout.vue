<template>
    <q-layout view="hHh lpR fFf">
        <q-header elevated class="bg-primary text-white">
            <q-toolbar>
                <q-toolbar-title>Minha App</q-toolbar-title>
                <Link href="/" class="q-mr-md">Home</Link>
                <Link href="/sobre">Sobre</Link>
            </q-toolbar>
        </q-header>

        <q-page-container>
            <q-page class="q-pa-md">
                <slot />
            </q-page>
        </q-page-container>

        <q-footer class="bg-grey-10 text-white">
            <div class="q-pa-sm">© {{ new Date().getFullYear() }}</div>
        </q-footer>
    </q-layout>
</template>

<script setup>
// nada aqui por enquanto
</script>
