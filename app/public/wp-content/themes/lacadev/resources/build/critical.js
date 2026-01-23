/* eslint-disable no-console */
/**
 * Critical CSS Generator
 * Run via: npm run critical
 */
// Critical module will be imported dynamically
const path = require('path');
const config = require('../../config.json');

// Get dev URL from config or fallback
const targetUrl = (config.development && config.development.url) ? config.development.url : 'http://lacadev.local';

console.log(`Starting Critical CSS generation from: ${targetUrl}`);

// Resolve paths
// theme/dist is where we want to save
const distPath = path.resolve(__dirname, '../../dist/');

// Dimensions to check
const dimensions = [
    {
        height: 640,
        width: 360, // Mobile
    },
    {
        height: 1024,
        width: 768, // Tablet
    },
    {
        height: 900,
        width: 1200, // Desktop
    },
];

(async () => {
    try {
        const { generate } = await import('critical');
        const { css, html, uncritical } = await generate({
            base: distPath,
            src: targetUrl,
            target: 'styles/critical.css',
            inline: false,
            extract: false,
            dimensions: dimensions,
            // Ignore strict font loading in critical path to avoid FOUT issues
            ignore: {
                atrule: ['@font-face'],
            },

        });

        console.log('✅ Critical CSS generated successfully at: dist/styles/critical.css');
    } catch (err) {
        console.error('❌ Critical CSS Generation Failed:', err);
        process.exit(1);
    }
})();
