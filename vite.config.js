import { defineConfig, loadEnv  } from 'vite'
import laravel from 'laravel-vite-plugin'
import vue from '@vitejs/plugin-vue'
import { quasar, transformAssetUrls } from '@quasar/vite-plugin'
import path from 'path'

// util: normaliza path e tira extensão
const normalize = (id) => id.replaceAll(path.sep, '/')
const basenameNoExt = (p) => p.replace(/\.[^/.]+$/, '')

// Deriva um nome legível e em minúsculas a partir do caminho do módulo
function deriveLowerChunkName(facadeId) {
    if (!facadeId) return null
    const id = normalize(facadeId)

    // case: páginas e componentes do seu app
    // ajusta se sua estrutura for diferente
    const m = id.match(/resources\/js\/(Pages|Components)\/(.+)\.(vue|js|ts|tsx)$/)
    if (m) {
        const group = m[1].toLowerCase()         // pages|components
        const rel   = m[2]                       // ex.: Admin/Users/Index
        const parts = rel.split('/')

        // pega última (arquivo) e penúltima (pasta) pra evitar colisões
        const file  = parts.pop()                // ex.: Index
        const prev  = parts.pop() || ''          // ex.: Users

        // se o arquivo for Index, usa também a pasta anterior
        const base  = file.toLowerCase()
        const name  = (base === 'index' && prev) ? `${prev.toLowerCase()}-index` : base

        return `${group}-${name}`                // ex.: pages-home, pages-about, pages-users-index
    }

    // caso não bata com a sua árvore, tenta o basename do arquivo
    const last = basenameNoExt(id.split('/').pop() || '')
    return last ? last.toLowerCase() : null
}

export default defineConfig(({ mode }) => {

    const env = loadEnv(mode, process.cwd(), '') // '' = pega todas (não só as que começam com VITE_)
    const host = env.VITE_HOST ?? 'localhost'
    const port = Number(env.VITE_PORT ?? 5173)
    const origin = env.VITE_ORIGIN ?? `http://${host}:${port}`
    const hmrProtocol = env.VITE_HMR_PROTOCOL ?? 'ws'
    const usePolling = (env.VITE_USE_POLLING ?? 'true') === 'true'
    const enableCors = (env.VITE_CORS ?? 'true') === 'true'

    return {
        resolve: {
            alias: {
                'ziggy-js': path.resolve('vendor/tightenco/ziggy'),
                '@': path.resolve(__dirname, 'resources/frontend'), // 👈 alias novo
            },
        },
        plugins: [
            laravel({
                input: [
                    'resources/frontend/app.js',
                    'resources/frontend/assets/css/app.scss',
                ],
                //refresh: true,
                refresh: [
                    'resources/frontend/**',
                    'resources/views/**/*.blade.php',  // se você usa Blade
                ],

                buildDirectory: 'dist',
            }),
            vue({ template: { transformAssetUrls } }),
            quasar({
                //sassVariables: '@/assets/css/quasar-variables.sass',
                autoImportComponentCase: 'kebab',
                //autoImportComponentCase: 'combined',
            }),
        ],
        /*
        server: {
            host: 'localhost',
            port: 5173,
            strictPort: true,
            watch: { usePolling: true },
        },
        */
        server: {
            host,
            port,
            origin,
            hmr: { host, port, protocol: hmrProtocol },
            strictPort: true,
            watch: { usePolling:usePolling },
            cors: enableCors,
            // Ignora o que não precisa observar
            ignored: [
                '**/node_modules/**',
                '**/vendor/**',
                '**/.git/**',
                '**/.idea/**',
                '**/.vscode/**',
                '**/storage/**',
            ],
         },
        // opcional: reduzir “path absoluto” nos sourcemaps em dev
        css: { devSourcemap: false },
        build: {
            chunkSizeWarningLimit: 900,
            emptyOutDir: true,
            rollupOptions: {
                output: {
                    // nome dos CHUNKS (lazy/dinâmicos)
                    chunkFileNames: (chunkInfo) => {
                        // tenta derivar a partir do arquivo de origem
                        const custom = deriveLowerChunkName(chunkInfo.facadeModuleId || '')
                        if (custom) {
                            return `assets/chunks/${custom}-[hash].js`
                        }
                        // fallback
                        return 'assets/chunks/[name]-[hash].js'
                    },
                    // nome dos ENTRY (ex.: app.js)
                    entryFileNames: (assetInfo) => {
                        const nm = (assetInfo.name || '').toLowerCase()
                        return `assets/${nm || 'entry'}-[hash].js`
                    },
                    // (opcional) agrupar vendors em nomes estáveis
                    manualChunks(id) {
                        if (id.includes('node_modules')) {
                            if (id.includes('quasar')) return 'vendor-quasar'
                            return 'vendor'
                        }
                        // exemplo: agrupar suas páginas de admin num bundle separado
                        // if (id.includes('/Pages/Admin/')) return 'admin'
                    },
                },
            },
        }
    }

})
