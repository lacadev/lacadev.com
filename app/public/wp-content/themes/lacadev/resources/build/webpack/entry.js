/**
 * The internal dependencies.
 */
const utils = require('../lib/utils');

module.exports = {
  'theme': utils.srcScriptsPath('theme/index.js'),
  'admin': utils.srcScriptsPath('admin/index.js'),
  'login': utils.srcScriptsPath('login/index.js'),
  'editor': utils.srcScriptsPath('editor/index.js'),
  // Laca SEO Admin Bundle
  'laca-seo': [
    utils.srcScriptsPath('admin/laca-seo/index.js'),
    utils.srcStylesPath('admin/laca-seo/index.scss'),
  ],
  // Laca SEO Editor Bundle
  'laca-seo-editor': [
    utils.srcScriptsPath('admin/laca-seo/editor.js'),
    utils.srcStylesPath('admin/laca-seo/editor.scss'),
  ],
};
