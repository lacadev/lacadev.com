const defaultConfig = require('@wordpress/scripts/config/webpack.config');
const path = require('path');
const fs = require('fs');

const blocksDir = path.resolve(__dirname, '../../block-gutenberg');
const blocks = fs.readdirSync(blocksDir).filter(dir => {
  return fs.statSync(path.join(blocksDir, dir)).isDirectory() && fs.existsSync(path.join(blocksDir, dir, 'block.json'));
});

// Create a separate configuration object for each block
module.exports = blocks.map(block => {
  return {
    ...defaultConfig,
    entry: {
      index: path.join(blocksDir, block, 'index.js')
    },
    output: {
      ...defaultConfig.output,
      path: path.join(blocksDir, block, 'build'),
      filename: '[name].js'
    }
  };
});
