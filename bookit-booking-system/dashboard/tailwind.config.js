/** @type {import('tailwindcss').Config} */
export default {
  content: [
    './app/**/*.php',
    './src/**/*.{vue,js,ts,jsx,tsx}',
  ],
  theme: {
    extend: {
      colors: {
        primary: {
          50: 'var(--bookit-primary-50)',
          100: 'var(--bookit-primary-100)',
          500: 'var(--bookit-primary-500)',
          600: 'var(--bookit-primary-600)',
          700: 'var(--bookit-primary-700)',
        }
      }
    },
  },
  plugins: [],
}
