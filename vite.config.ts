import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
  plugins: [
    laravel({
      input: ['resources/js/app.tsx', 'resources/js/finance.tsx', 'resources/js/reporting.tsx', 'resources/js/hr.tsx', 'resources/js/payroll.tsx'],
      refresh: ['resources/views/**'],
    }),
    react(),
  ],
  server: {
    host: '0.0.0.0',
    strictPort: true,
    allowedHosts: true,
  },
});
