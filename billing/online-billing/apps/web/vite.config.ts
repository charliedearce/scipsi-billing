import { defineConfig, loadEnv } from 'vite'
import vue from '@vitejs/plugin-vue'
import path from 'path'
import { fileURLToPath } from 'url'
import vueDevTools from 'vite-plugin-vue-devtools'
import viteCompression from 'vite-plugin-compression'
import Components from 'unplugin-vue-components/vite'
import AutoImport from 'unplugin-auto-import/vite'
import ElementPlus from 'unplugin-element-plus/vite'
import { ElementPlusResolver } from 'unplugin-vue-components/resolvers'
import tailwindcss from '@tailwindcss/vite'
import { VitePWA } from 'vite-plugin-pwa'
// import { visualizer } from 'rollup-plugin-visualizer'

export default ({ mode }: { mode: string }) => {
  const root = process.cwd()
  const env = loadEnv(mode, root)
  const { VITE_VERSION, VITE_PORT, VITE_BASE_URL, VITE_API_URL, VITE_API_PROXY_URL } = env

  console.log(`🚀 API_URL = ${VITE_API_URL}`)
  console.log(`🚀 VERSION = ${VITE_VERSION}`)

  return defineConfig({
    define: {
      __APP_VERSION__: JSON.stringify(VITE_VERSION)
    },
    base: VITE_BASE_URL,
    server: {
      port: Number(VITE_PORT),
      proxy: {
        '/api': {
          target: VITE_API_PROXY_URL,
          changeOrigin: true
        }
      },
      host: true
    },
    // 路径别名
    resolve: {
      alias: {
        '@': fileURLToPath(new URL('./src', import.meta.url)),
        '@views': resolvePath('src/views'),
        '@imgs': resolvePath('src/assets/images'),
        '@icons': resolvePath('src/assets/icons'),
        '@utils': resolvePath('src/utils'),
        '@stores': resolvePath('src/store'),
        '@styles': resolvePath('src/assets/styles')
      }
    },
    build: {
      target: 'es2015',
      outDir: 'dist',
      chunkSizeWarningLimit: 2000,
      minify: 'terser',
      terserOptions: {
        compress: {
          // 生产环境去除 console
          drop_console: true,
          // 生产环境去除 debugger
          drop_debugger: true
        }
      },
      dynamicImportVarsOptions: {
        warnOnError: true,
        exclude: [],
        include: ['src/views/**/*.vue']
      }
    },
    plugins: [
      vue(),
      tailwindcss(),
      // 自动按需导入 API
      AutoImport({
        imports: ['vue', 'vue-router', 'pinia', '@vueuse/core'],
        dts: 'src/types/import/auto-imports.d.ts',
        resolvers: [ElementPlusResolver()],
        eslintrc: {
          enabled: true,
          filepath: './.auto-import.json',
          globalsPropValue: true
        }
      }),
      // 自动按需导入组件
      Components({
        dts: 'src/types/import/components.d.ts',
        resolvers: [ElementPlusResolver()]
      }),
      // 按需定制主题配置
      ElementPlus({
        useSource: true
      }),
      // 压缩
      viteCompression({
        verbose: false, // 是否在控制台输出压缩结果
        disable: false, // 是否禁用
        algorithm: 'gzip', // 压缩算法
        ext: '.gz', // 压缩后的文件名后缀
        threshold: 10240, // 只有大小大于该值的资源会被处理 10240B = 10KB
        deleteOriginFile: false // 压缩后是否删除原文件
      }),
      vueDevTools(),
      // PWA: installable shell with safe offline/update boundary (P1-11 / W33)
      VitePWA({
        // Prompt the user before reloading; never silently skipWaiting
        registerType: 'prompt',
        // Non-blocking script-defer injection
        injectRegister: 'script-defer',
        // Manifest is served from public/manifest.webmanifest — do not auto-generate
        manifest: false,
        // Workbox generateSW strategy
        workbox: {
          // ──────────────────────────────────────────────────────────────────
          // PRECACHE: only versioned/hashed shell assets (JS, CSS, fonts, icons)
          // Private/API/auth/financial data must NEVER appear here (W33 §2)
          // ──────────────────────────────────────────────────────────────────
          globPatterns: ['**/*.{js,css,woff2,woff,ttf,eot,ico,png,svg,webp}'],
          globIgnores: [
            // Exclude anything that could be mistaken for live API data
            '**/api/**',
            '**/uploads/**',
            '**/media/**'
          ],
          // Navigation fallback: serve the offline shell for HTML navigation requests
          // The shell shows an offline message when the API is unreachable.
          navigateFallback: 'index.html',
          navigateFallbackDenylist: [
            // Never intercept API routes — they must be NetworkOnly
            /^\/api\//,
            // Never intercept broadcasting auth
            /^\/broadcasting\//,
            // Never intercept storage/uploads
            /^\/storage\//
          ],
          // ──────────────────────────────────────────────────────────────────
          // RUNTIME ROUTES: all server-authoritative routes are NetworkOnly.
          // Authenticated data, financial state, OTPs, artifacts — never cached.
          // ──────────────────────────────────────────────────────────────────
          runtimeCaching: [
            {
              // API v1 — all endpoints: network-only, never cache responses
              urlPattern: /^\/api\/v1\//,
              handler: 'NetworkOnly',
              options: { cacheName: 'api-never-cache' }
            },
            {
              // Broadcasting / Reverb auth — network-only
              urlPattern: /^\/broadcasting\//,
              handler: 'NetworkOnly',
              options: { cacheName: 'broadcast-never-cache' }
            },
            {
              // Private storage / uploads / media / artifacts — network-only
              urlPattern: /^\/storage\//,
              handler: 'NetworkOnly',
              options: { cacheName: 'storage-never-cache' }
            }
          ],
          // Emit source maps for service worker debugging in staging
          sourcemap: false,
          // Skip waiting is false by default with registerType:'prompt'; be explicit
          skipWaiting: false,
          clientsClaim: false
        },
        // Dev options: disable SW in development to avoid stale cache confusion
        devOptions: {
          enabled: false
        }
      }),
      // 打包分析
      // visualizer({
      //   open: true,
      //   gzipSize: true,
      //   brotliSize: true,
      //   filename: 'dist/stats.html' // 分析图生成的文件名及路径
      // }),
    ],
    // 依赖预构建：避免运行时重复请求与转换，提升首次加载速度
    optimizeDeps: {
      include: [
        'echarts/core',
        'echarts/charts',
        'echarts/components',
        'echarts/renderers',
        'xlsx',
        'xgplayer',
        'crypto-js',
        'file-saver',
        'vue-img-cutter',
        'element-plus/es',
        'element-plus/es/components/*/style/css',
        'element-plus/es/components/*/style/index'
      ]
    },
    css: {
      preprocessorOptions: {
        // sass variable and mixin
        scss: {
          additionalData: `
            @use "@styles/core/el-light.scss" as *; 
            @use "@styles/core/mixin.scss" as *;
          `
        }
      },
      postcss: {
        plugins: [
          {
            postcssPlugin: 'internal:charset-removal',
            AtRule: {
              charset: (atRule) => {
                if (atRule.name === 'charset') {
                  atRule.remove()
                }
              }
            }
          }
        ]
      }
    }
  })
}

function resolvePath(paths: string) {
  return path.resolve(__dirname, paths)
}
