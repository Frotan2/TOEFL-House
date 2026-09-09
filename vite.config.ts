import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
  plugins: [
    laravel({
      input: [
        'resources/js/app.tsx',
        'resources/js/product-theme.css',
        'resources/js/finance.tsx',
        'resources/js/reporting.tsx',
        'resources/js/hr.tsx',
        'resources/js/payroll.tsx',
        'resources/js/placement.tsx',
        'resources/js/identity.tsx',
        'resources/js/access.tsx',
        'resources/js/organization.tsx',
      ],
      refresh: ['resources/views/**'],
    }),
    react(),
  ],
  server: {
    host: '127.0.0.1',
    strictPort: true,
    allowedHosts: [],
  },
});
