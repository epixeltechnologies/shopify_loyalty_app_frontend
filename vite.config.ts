import { defineConfig } from 'vitest/config';
import react from '@vitejs/plugin-react';
import path from 'path';

// https://vitejs.dev/config/
export default defineConfig({
  plugins: [react()],
  resolve: {
    alias: {
      '@': path.resolve(__dirname, './src'),
    },
  },
  server: {
    port: 5173,
    strictPort: true,
    cors: true,
    // Allows the Laravel-served embedded app shell (app.blade.php) to
    // pull the dev server's HMR client/assets during local development.
    origin: 'http://localhost:5173',
  },
  build: {
    manifest: true,
    outDir: '../backend/public/build',
    emptyOutDir: true,
    rollupOptions: {
      input: 'src/main.tsx',
    },
  },
  test: {
    environment: 'jsdom',
    globals: true,
    setupFiles: ['./src/test/setup.ts'],
  },
});
