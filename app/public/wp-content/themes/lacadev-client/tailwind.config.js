/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    // Gutenberg blocks — PHP render templates + JS/SCSS
    './block-gutenberg/**/*.{js,jsx,php,scss}',
    // Theme templates (Twig / PHP)
    './app/**/*.php',
    './resources/**/*.{js,jsx,php,twig,html}',
  ],
  theme: {
    extend: {
      colors: {
        // Material Design 3 surface tokens — fallback to CSS vars, default to neutral tones
        'surface':                 'var(--color-surface, #FFFBFF)',
        'surface-variant':         'var(--color-surface-variant, #E7E0EC)',
        'surface-container':       'var(--color-surface-container, #F3EDF7)',
        'surface-container-low':   'var(--color-surface-container-low, #F5F5F5)',
        'surface-container-high':  'var(--color-surface-container-high, #ECE6F0)',
        'surface-container-highest':'var(--color-surface-container-highest, #E6E0E9)',
        'on-surface':              'var(--color-on-surface, #1C1B1F)',
        'on-surface-variant':      'var(--color-on-surface-variant, #49454F)',
        'background':              'var(--color-background, #FFFBFF)',
        'on-background':           'var(--color-on-background, #1C1B1F)',
        'outline':                 'var(--color-outline, #79747E)',
        'primary':                 'var(--color-primary, #6750A4)',
        'on-primary':              'var(--color-on-primary, #FFFFFF)',
      },
    },
  },
  plugins: [],
};

