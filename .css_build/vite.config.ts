import { defineConfig } from 'vite'
import tailwindcss from '@tailwindcss/vite'
import path from "path"

export default defineConfig({
  plugins: [
    tailwindcss(),
  ],
  optimizeDeps: {
    exclude: ['@tailwindcss/vite']
  },
  build: {
    outDir: '../assets/css',
    emptyOutDir: false,
    rollupOptions: {
      input: path.resolve(__dirname, 'src/import.css'),
      output: {
        assetFileNames: 'tailwind.css'
      }
    },
    minify: false
  }
})