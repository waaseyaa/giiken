import type { Config } from 'tailwindcss'

export default {
  content: ['./resources/js/**/*.{vue,ts}'],
  theme: {
    extend: {
      colors: {
        indigo: {
          DEFAULT: '#3d35c8',
          dark: '#1a1a2e',
          light: '#f0f0ff',
          mid: '#6e66ff',
        },
        muted: '#9090b0',
        border: '#e8eaf0',
        bg: '#f8f8fd',
      },
    },
  },
} satisfies Config
