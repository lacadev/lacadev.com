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
    extend: {},
  },
  plugins: [],
};
