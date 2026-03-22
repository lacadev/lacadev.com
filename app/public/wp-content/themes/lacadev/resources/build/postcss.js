/**
 * The internal dependencies.
 */

/**
 * Setup PostCSS plugins.
 * Tailwind CSS v4: dùng @tailwindcss/postcss thay vì tailwindcss(config)
 */
const plugins = [
  require('tailwindcss'),
  require('autoprefixer'),
  require('cssnano')({ preset: 'default' })
];

/**
 * Prepare the configuration.
 */
const config = {
  plugins,
};

module.exports = config;
