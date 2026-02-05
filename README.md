# Guia Completo — Laravel 12 + Inertia v2 + Vue 3 + Quasar + Ziggy + Tailwind (prefixo `tw-`)
> Windows • host local **`laravel12.site`** • pasta do frontend: **`resources/frontend`**  
> Este guia foi escrito para você **copiar e colar** os blocos rapidamente.


---

## 0) Pré‑requisitos (Windows)

- **PHP 8.3+**, **Composer 2**
- **Node.js 20.19+ ou 22.12+** (recomendo usar **NVM for Windows**)
- **Git**
- VS Code com extensões **Vue Language Features (Volar)** e **Tailwind CSS IntelliSense**

```powershell
# Node (se necessário)
nvm install 22.12.0
nvm use 22.12.0
node -v
npm -v
```

---

## 1) Host local e Virtual Host

### 1.1 `hosts` do Windows
Edite (como Administrador) o arquivo:
`C:\Windows\System32\drivers\etc\hosts` e adicione:

```
127.0.0.1 laravel12.site
```

### 1.2 Apache (exemplo de VirtualHost)
`httpd-vhosts.conf`:

```apache
<VirtualHost *:80>
  ServerName laravel12.site
  DocumentRoot "C:/www/laravel12/public"

  <Directory "C:/www/laravel12/public">
    AllowOverride All
    Require all granted
    Options Indexes FollowSymLinks
  </Directory>

  ErrorLog "logs/laravel12-error.log"
  CustomLog "logs/laravel12-access.log" combined
</VirtualHost>
```

Reinicie o Apache.

---

## 2) Estrutura recomendada de pastas

```
resources/
  frontend/
    app.js
    Layouts/MainLayout.vue
    Pages/Home.vue
    assets/
      css/
        app.scss
        quasar-variables.sass
resources/views/app.blade.php
```

> Se estiver migrando de `resources/js` para `resources/frontend`, ajuste os caminhos conforme abaixo.

---

## 3) `.env` para desenvolvimento

```env
APP_URL=http://laravel12.site
ASSET_URL=http://laravel12.site

# Vite (dev server)
VITE_HOST=laravel12.site
VITE_PORT=5173
VITE_ORIGIN=http://laravel12.site:5173
VITE_HMR_PROTOCOL=ws
VITE_USE_POLLING=true
VITE_CORS=true
VITE_DEV_SERVER_URL=http://laravel12.site:5173
```

> Dica: se o HTML injetar `localhost:5173`, **apague `public/hot`** e rode `npm run dev` novamente.

---

## 4) `vite.config.js` (Vite + Vue + Quasar + env)

```js
import { defineConfig, loadEnv } from 'vite'
import laravel from 'laravel-vite-plugin'
import vue from '@vitejs/plugin-vue'
import { quasar, transformAssetUrls } from '@quasar/vite-plugin'
import path from 'node:path'

export default defineConfig(({ mode }) => {
  const env = loadEnv(mode, process.cwd(), '')
  const host = env.VITE_HOST ?? 'laravel12.site'
  const port = Number(env.VITE_PORT ?? 5173)
  const origin = env.VITE_ORIGIN ?? `http://${host}:${port}`
  const hmrProtocol = env.VITE_HMR_PROTOCOL ?? 'ws'
  const usePolling = (env.VITE_USE_POLLING ?? 'true') === 'true'
  const cors = (env.VITE_CORS ?? 'true') === 'true'

  return {
    plugins: [
      // Entrada única — o app.js importa o SCSS
      laravel({ input: ['resources/frontend/app.js'], refresh: true }),
      vue({ template: { transformAssetUrls } }),
      quasar({
        sassVariables: 'resources/frontend/assets/css/quasar-variables.sass',
        autoImportComponentCase: 'kebab',
      }),
    ],
    resolve: {
      alias: { '@': path.resolve(__dirname, 'resources/frontend') },
    },
    server: {
      host, port, origin, cors, strictPort: true,
      hmr: { host, port, protocol: hmrProtocol },
      watch: { usePolling },
    },
    css: { devSourcemap: false },
  }
})
```

**Scripts (package.json):**
```json
{
  "scripts": {
    "dev": "vite",
    "build": "vite build"
  }
}
```

---

## 5) Root view — `resources/views/app.blade.php`

```blade
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  @routes
  @vite(['resources/frontend/app.js'])
  @inertiaHead
</head>
<body class="font-sans antialiased">
  @inertia
</body>
</html>
```

---

## 6) Inertia no Laravel 12 (middleware)

```powershell
composer require inertiajs/inertia-laravel
php artisan inertia:middleware
```

**Registrar o middleware no Laravel 12 — `bootstrap/app.php`:**
```php
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
  ->withRouting(
    web: __DIR__.'/../routes/web.php',
    commands: __DIR__.'/../routes/console.php',
    health: '/up',
  )
  ->withMiddleware(function (Middleware $middleware) {
    $middleware->web(append: [
      \App\Http\Middleware\HandleInertiaRequests::class,
    ]);
  })
  ->create();
```

---

## 7) Frontend — `resources/frontend/app.js`

```js
// importa seu SCSS (Tailwind + Quasar)
import '@/assets/css/app.scss'

import { createApp, h } from 'vue'
import { createInertiaApp, Head, Link } from '@inertiajs/vue3'
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers'
import { InertiaProgress } from '@inertiajs/progress'
import { ZiggyVue } from 'ziggy-js'
import MainLayout from '@/Layouts/MainLayout.vue'

InertiaProgress.init({ color: '#4B5563', showSpinner: false })

createInertiaApp({
  title: (title) => (title ? `${title} - Minha App` : 'Minha App'),
  resolve: (name) =>
    resolvePageComponent(`./Pages/${name}.vue`, import.meta.glob('@/Pages/**/*.vue'))
      .then((mod) => {
        const page = mod.default
        page.layout ??= MainLayout
        return page
      }),
  setup({ el, App, props, plugin }) {
    const app = createApp({ render: () => h(App, props) })
    app.use(plugin)
    app.use(ZiggyVue, window.Ziggy) // route() global
    app.component('Head', Head)
    app.component('Link', Link)
    app.mount(el)
  },
})
```

---

## 8) Tailwind com prefixo `tw-`

```powershell
npm i -D tailwindcss@^3 postcss@^8 autoprefixer@^10
npx -y tailwindcss@^3 init -p
```

**Como seu projeto pode usar ESM, prefira CommonJS para configs:**

`tailwind.config.cjs`
```js
/** @type {import('tailwindcss').Config} */
module.exports = {
  prefix: 'tw-',
  content: [
    './resources/frontend/**/*.{vue,js,ts,jsx,tsx}',
    './resources/views/**/*.blade.php',
    './resources/frontend/assets/css/**/*.{css,scss,sass}',
  ],
  theme: {
    extend: {
      colors: {
        brand: {
          primary  : 'rgb(var(--color-primary) / <alpha-value>)',
          secondary: 'rgb(var(--color-secondary) / <alpha-value>)',
          accent   : 'rgb(var(--color-accent) / <alpha-value>)',
          dark     : 'rgb(var(--color-dark) / <alpha-value>)',
          positive : 'rgb(var(--color-positive) / <alpha-value>)',
          negative : 'rgb(var(--color-negative) / <alpha-value>)',
          info     : 'rgb(var(--color-info) / <alpha-value>)',
          warning  : 'rgb(var(--color-warning) / <alpha-value>)',
        },
      },
    },
  },
  plugins: [],
  // Se o reset do Tailwind atrapalhar o Quasar:
  // corePlugins: { preflight: false },
}
```

`postcss.config.cjs`
```js
module.exports = {
  plugins: {
    tailwindcss: {},
    autoprefixer: {},
  },
}
```

---

## 9) Paleta **Purple** unificada (Quasar + Tailwind)

`resources/frontend/assets/css/quasar-variables.sass`
```sass
$primary   : #7c3aed
$secondary : #a78bfa
$accent    : #c084fc
$dark      : #0b1220

$positive  : #22c55e
$negative  : #ef4444
$info      : #3b82f6
$warning   : #f59e0b
```

`resources/frontend/assets/css/app.scss`
```scss
/* Tailwind */
@tailwind base;
@tailwind components;
@tailwind utilities;

/* CSS vars sincronizadas com Quasar */
:root {
  --color-primary   : 124 58 237;   /* #7c3aed */
  --color-secondary : 167 139 250;  /* #a78bfa */
  --color-accent    : 192 132 252;  /* #c084fc */
  --color-dark      : 11 18 32;

  --color-positive  : 34 197 94;    /* #22c55e */
  --color-negative  : 239 68 68;    /* #ef4444 */
  --color-info      : 59 130 246;   /* #3b82f6 */
  --color-warning   : 245 158 11;   /* #f59e0b */
}
.dark {
  --color-primary   : 139 92 246;   /* #8b5cf6 */
  --color-secondary : 196 181 253;  /* #c4b5fd */
  --color-accent    : 216 180 254;  /* #d8b4fe */
  --color-dark      : 7 12 22;
}

/* Quasar base */
@import "@quasar/extras/material-icons/material-icons.css";
@import "quasar/src/css/index.sass";

/* estilos seus... */
```

---

## 10) Ziggy e uso de `route()` global

```powershell
composer require tightenco/ziggy
```

No Blade já há `@routes`. No `app.js` usamos:
```js
import { ZiggyVue } from 'ziggy-js'
// ...
app.use(ZiggyVue, window.Ziggy) // agora route() é global, inclusive em <script setup>
```

Exemplo de uso:
```vue
<template>
  <Link :href="route('home')">Home</Link>
</template>
```

**ESLint** (global `route`): `.eslintrc.cjs`
```js
module.exports = {
  root: true,
  env: { browser: true, es2022: true },
  extends: ['eslint:recommended', 'plugin:vue/vue3-recommended'],
  parserOptions: { ecmaVersion: 'latest', sourceType: 'module' },
  overrides: [{ files: ['resources/frontend/**/*.{js,vue,ts}'], globals: { route: 'readonly' } }],
}
```

---

## 11) Rotas e páginas de exemplo

`routes/web.php`
```php
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', fn () => Inertia::render('Home'))->name('home');
Route::get('/sobre', fn () => Inertia::render('About'))->name('sobre');
```

`resources/frontend/Layouts/MainLayout.vue`
```vue
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
  </q-layout>
</template>

<script setup>
import { Link } from '@inertiajs/vue3'
</script>
```

`resources/frontend/Pages/Home.vue`
```vue
<template>
  <div class="tw-p-4 tw-bg-brand-primary/10 tw-rounded-lg">
    <h1 class="tw-text-brand-primary tw-font-semibold tw-mb-3">Bem-vindo 🎉</h1>
    <q-btn label="QUASAR + TAILWIND" color="warning" class="q-mb-md" />
    <q-btn label="ACCENT (tw-)" class="tw-bg-brand-accent tw-text-white q-ml-sm" />
  </div>
</template>
```

---

## 12) Code Splitting e Layout lazy (opcional)

O `resolvePageComponent` já cria **um chunk por página**. Para deixar **Layout lazy**:

```js
resolve: async (name) => {
  const pages = import.meta.glob('@/Pages/**/*.vue')
  const mod = await pages[`./Pages/${name}.vue`]()
  const page = mod.default
  page.layout ??= (await import('@/Layouts/MainLayout.vue')).default
  return page
}
```

Prefetch leve (carrega próximo chunk ao passar o mouse):
```vue
<script setup>
import { Link } from '@inertiajs/vue3'
const pages = import.meta.glob('@/Pages/**/*.vue')
function prefetch(page){ pages[`./Pages/${page}.vue`]?.() }
</script>

<template>
  <Link href="/sobre" @mouseenter="prefetch('About')">Sobre</Link>
</template>
```

---

## 13) Desenvolvimento e Build

### Dev
```powershell
php artisan optimize:clear
del public\hot 2>$null   # se existir
npm run dev
# abra http://laravel12.site
```

### Build (produção)
```powershell
npm run build
php artisan optimize:clear
# configure o servidor para apontar para /public
```

**CDN opcional:**  
`.env` → `ASSET_URL=https://cdn.seu-dominio` para prefixar assets do build.

---

## 14) Troubleshooting rápido

- **Node < 20.19** → Vite quebra com `crypto.hash` → atualize Node.
- **CORS em `@vite/client`/`app.js`** → host do Vite diferente do site.  
  Use as variáveis do `.env`, ajuste `server.host/origin/hmr` e **apague `public/hot`**.
- **`View [app] not found`** → falta `resources/views/app.blade.php` (ou ajuste `config/inertia.php: root_view`).
- **PostCSS/Tailwind com `"type":"module"`** → use `.cjs` para `postcss.config.cjs`/`tailwind.config.cjs`, ou troque para `export default`.
- **Build falha `Could not resolve entry ... app.scss`** → caminho errado.  
  Recomendo **entrada única** (SCSS importado no `app.js`).

---

## 15) Dicas extras

### Dark mode sincronizado (Quasar + CSS vars)
```vue
<script setup>
import { Dark } from 'quasar'
function toggleDark () {
  Dark.set(!Dark.isActive)
  document.body.classList.toggle('dark', Dark.isActive)
}
</script>

<template>
  <q-btn :label="Dark.isActive ? 'Light' : 'Dark'" @click="toggleDark" />
</template>
```

### Alterar cor primária em runtime (Quasar)
```js
import { setCssVar } from 'quasar'
setCssVar('primary', '#8b5cf6')
```

---

**Pronto!**  
Com este guia você tem um baseline sólido para **Laravel 12 + Inertia v2 + Vue 3 + Quasar + Ziggy + Tailwind (tw-)** usando host local `laravel12.site`. Copie e cole à vontade. 😉
