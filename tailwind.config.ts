import type { Config } from 'tailwindcss';
import typography from '@tailwindcss/typography';
import forms from '@tailwindcss/forms';

export default {
  content: [
    './index.html',
    './src/**/*.{ts,tsx}',
  ],
  darkMode: 'class', // Dark mode support
  theme: {
    extend: {
      colors: {
        noba: {
          orange: '#FF7B29',
          dark: '#1F2933',
          50: '#FFF5EE',
          100: '#FFE5D6',
          200: '#FFD4BA',
          300: '#FFB899',
          400: '#FF9761',
          500: '#FF7B29',
          600: '#E65C0A',
          700: '#C74700',
          800: '#A33800',
          900: '#7A2900',
        },
        dark: {
          primary: '#0a0e1a',
          secondary: '#0f1419',
          tertiary: '#1a1f2e',
          card: 'rgba(15, 23, 42, 0.4)',
          'card-strong': 'rgba(15, 23, 42, 0.7)',
        },
        neon: {
          purple: {
            dark: '#6B21A8',
            DEFAULT: '#8B5CF6',
            bright: '#A78BFA',
            dim: '#7E22CE',
          },
          orange: {
            DEFAULT: '#FF7B29',
            bright: '#FF9761',
            dim: '#E65C0A',
          },
        },
        tech: {
          cyan: {
            400: '#22D3EE',
            500: '#06B6D4',
            600: '#0891B2',
          },
          purple: {
            400: '#C084FC',
            500: '#A855F7',
            600: '#9333EA',
          },
        },
      },
      backgroundImage: {
        'gradient-radial': 'radial-gradient(var(--tw-gradient-stops))',
        'gradient-mesh': 'radial-gradient(at 27% 37%, hsla(215, 98%, 61%, 0.15) 0px, transparent 50%), radial-gradient(at 97% 21%, hsla(125, 98%, 72%, 0.1) 0px, transparent 50%), radial-gradient(at 52% 99%, hsla(354, 98%, 61%, 0.15) 0px, transparent 50%), radial-gradient(at 10% 29%, hsla(256, 96%, 67%, 0.1) 0px, transparent 50%)',
      },
      boxShadow: {
        card: '0 20px 45px -15px rgba(0, 0, 0, 0.7)',
        'glass': '0 8px 32px 0 rgba(0, 0, 0, 0.5)',
        'glass-lg': '0 8px 32px 0 rgba(0, 0, 0, 0.6), 0 0 20px rgba(139, 92, 246, 0.2)',
        'glass-sm': '0 2px 8px rgba(0, 0, 0, 0.4), 0 1px 2px rgba(139, 92, 246, 0.1)',
        'glass-md': '0 4px 16px rgba(0, 0, 0, 0.5), 0 2px 4px rgba(139, 92, 246, 0.15)',
        'neon-purple-dark': '0 0 20px rgba(107, 33, 168, 0.6), 0 0 40px rgba(107, 33, 168, 0.4), 0 0 60px rgba(107, 33, 168, 0.2)',
        'neon-purple': '0 0 20px rgba(139, 92, 246, 0.5), 0 0 40px rgba(139, 92, 246, 0.3), 0 0 60px rgba(139, 92, 246, 0.1)',
        'neon-orange': '0 0 20px rgba(255, 123, 41, 0.5), 0 0 40px rgba(255, 123, 41, 0.3)',
        'glow-purple': '0 0 24px rgba(139, 92, 246, 0.6)',
        'glow-orange': '0 0 24px rgba(255, 123, 41, 0.6)',
        '3d-sm': '0 2px 4px rgba(0, 0, 0, 0.4), 0 8px 16px rgba(0, 0, 0, 0.3)',
        '3d': '0 4px 8px rgba(0, 0, 0, 0.5), 0 12px 24px rgba(0, 0, 0, 0.4)',
        '3d-lg': '0 8px 16px rgba(0, 0, 0, 0.6), 0 20px 40px rgba(0, 0, 0, 0.5)',
      },
      backdropBlur: {
        xs: '2px',
      },
      animation: {
        'float': 'float 6s ease-in-out infinite',
        'pulse-slow': 'pulse 3s cubic-bezier(0.4, 0, 0.6, 1) infinite',
        'fade-in': 'fade-in 0.3s ease-out',
        'fade-in-up': 'fade-in-up 0.4s cubic-bezier(0.16, 1, 0.3, 1)',
        'fade-in-down': 'fade-in-down 0.4s cubic-bezier(0.16, 1, 0.3, 1)',
        'scale-in': 'scale-in 0.2s cubic-bezier(0.16, 1, 0.3, 1)',
        'slide-in-right': 'slide-in-right 0.3s cubic-bezier(0.16, 1, 0.3, 1)',
        'slide-in-left': 'slide-in-left 0.3s cubic-bezier(0.16, 1, 0.3, 1)',
        'slide-up': 'slide-up 0.4s cubic-bezier(0.16, 1, 0.3, 1)',
        'shimmer': 'shimmer 2s linear infinite',
        'shimmer-slide': 'shimmer-slide 2s infinite',
        'ripple': 'ripple 0.6s ease-out',
        'bounce-gentle': 'bounce-gentle 2s ease-in-out infinite',
        'gradient-flow': 'gradient-flow 3s ease infinite',
        'glow-pulse': 'glow-pulse 2s ease-in-out infinite',
      },
      keyframes: {
        float: {
          '0%, 100%': { transform: 'translateY(0px)' },
          '50%': { transform: 'translateY(-10px)' },
        },
        'fade-in': {
          '0%': { opacity: '0' },
          '100%': { opacity: '1' },
        },
        'fade-in-up': {
          '0%': {
            opacity: '0',
            transform: 'translateY(16px)',
          },
          '100%': {
            opacity: '1',
            transform: 'translateY(0)',
          },
        },
        'fade-in-down': {
          '0%': {
            opacity: '0',
            transform: 'translateY(-16px)',
          },
          '100%': {
            opacity: '1',
            transform: 'translateY(0)',
          },
        },
        'scale-in': {
          '0%': {
            opacity: '0',
            transform: 'scale(0.9)',
          },
          '100%': {
            opacity: '1',
            transform: 'scale(1)',
          },
        },
        'slide-in-right': {
          '0%': {
            opacity: '0',
            transform: 'translateX(-20px)',
          },
          '100%': {
            opacity: '1',
            transform: 'translateX(0)',
          },
        },
        'slide-in-left': {
          '0%': {
            opacity: '0',
            transform: 'translateX(20px)',
          },
          '100%': {
            opacity: '1',
            transform: 'translateX(0)',
          },
        },
        shimmer: {
          '0%': { backgroundPosition: '-1000px 0' },
          '100%': { backgroundPosition: '1000px 0' },
        },
        ripple: {
          '0%': {
            transform: 'scale(0)',
            opacity: '1',
          },
          '100%': {
            transform: 'scale(4)',
            opacity: '0',
          },
        },
        'bounce-gentle': {
          '0%, 100%': {
            transform: 'translateY(0)',
            animationTimingFunction: 'cubic-bezier(0.8, 0, 1, 1)',
          },
          '50%': {
            transform: 'translateY(-5%)',
            animationTimingFunction: 'cubic-bezier(0, 0, 0.2, 1)',
          },
        },
        'slide-up': {
          '0%': {
            opacity: '0',
            transform: 'translateY(20px)',
          },
          '100%': {
            opacity: '1',
            transform: 'translateY(0)',
          },
        },
        'shimmer-slide': {
          '0%': { transform: 'translateX(-100%)' },
          '100%': { transform: 'translateX(100%)' },
        },
        'gradient-flow': {
          '0%, 100%': { backgroundPosition: '0% 50%' },
          '50%': { backgroundPosition: '100% 50%' },
        },
        'glow-pulse': {
          '0%, 100%': { 
            boxShadow: '0 0 20px rgba(255, 123, 41, 0.3)',
            transform: 'scale(1)' 
          },
          '50%': { 
            boxShadow: '0 0 30px rgba(255, 123, 41, 0.5)',
            transform: 'scale(1.02)' 
          },
        },
      },
      transitionTimingFunction: {
        'spring': 'cubic-bezier(0.16, 1, 0.3, 1)',
        'bounce': 'cubic-bezier(0.68, -0.55, 0.265, 1.55)',
      },
    },
  },
  plugins: [
    typography,
    forms,
  ],
} satisfies Config;
