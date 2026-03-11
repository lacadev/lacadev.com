<?php
/**
 * Gutenberg Blocks Registration
 * 
 * Register ReactJS-based Gutenberg blocks
 * 
 * @package LacaDev
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register Gutenberg blocks scripts and styles
 */
function lacadev_register_gutenberg_blocks_assets() {
    // Theme root is one level above `theme/` folder (see APP_DIR constant in `theme/functions.php`)
    if (!defined('APP_DIR')) {
        return;
    }

    // Legacy/global bundle (only used for blocks that don't have their own build folder)
    $asset_file = trailingslashit(APP_DIR) . 'dist/gutenberg/index.asset.php';
    
    if (!file_exists($asset_file)) {
        return;
    }
    
    $asset = require $asset_file;
    
    // URL root of theme (one level above `theme/`)
    $theme_root_uri = dirname(get_stylesheet_directory_uri());
    
    // Register block editor script
    wp_register_script(
        'lacadev-gutenberg-blocks',
        $theme_root_uri . '/dist/gutenberg/index.js',
        $asset['dependencies'],
        $asset['version'],
        false
    );
}
add_action('init', 'lacadev_register_gutenberg_blocks_assets', 5);

/**
 * Register all custom blocks
 */
function lacadev_register_custom_blocks() {
    // First, register assets
    lacadev_register_gutenberg_blocks_assets();
    
    // Get all block directories - use file path, not URL
    if (!defined('APP_DIR')) {
        return;
    }

    $blocks_dir = trailingslashit(APP_DIR) . 'block-gutenberg';
    $theme_root_uri = dirname(get_stylesheet_directory_uri());
    
    if (!is_dir($blocks_dir)) {
        return;
    }
    
    $blocks = scandir($blocks_dir);
    $registered_count = 0;
    
    foreach ($blocks as $block) {
        // Skip . and .. and index.js
        if ($block === '.' || $block === '..' || $block === 'index.js' || $block === 'debug.js') {
            continue;
        }
        
        $block_json = $blocks_dir . '/' . $block . '/block.json';
        
        if (file_exists($block_json)) {
            // Check if block has render.php for dynamic rendering
            $render_php = $blocks_dir . '/' . $block . '/render.php';
            
            // Check if block has a specific build folder (self-contained block)
            $has_individual_build = is_dir($blocks_dir . '/' . $block . '/build');
            
            $block_args = [];
            
            if ($has_individual_build) {
                $asset_file = $blocks_dir . '/' . $block . '/build/index.asset.php';
                $asset = [
                    'dependencies' => [],
                    'version' => null,
                ];

                if (file_exists($asset_file)) {
                    $asset = require $asset_file;
                }

                $editor_script_handle = 'lacadev-block-' . $block . '-editor';
                $editor_style_handle = 'lacadev-block-' . $block . '-editor';
                $style_handle = 'lacadev-block-' . $block;

                wp_register_script(
                    $editor_script_handle,
                    $theme_root_uri . '/block-gutenberg/' . $block . '/build/index.js',
                    $asset['dependencies'] ?? [],
                    $asset['version'] ?? null,
                    true
                );

                wp_register_style(
                    $editor_style_handle,
                    $theme_root_uri . '/block-gutenberg/' . $block . '/build/index.css',
                    [],
                    $asset['version'] ?? null
                );

                wp_register_style(
                    $style_handle,
                    $theme_root_uri . '/block-gutenberg/' . $block . '/build/style-index.css',
                    [],
                    $asset['version'] ?? null
                );

                $block_args['editor_script'] = $editor_script_handle;
                $block_args['editor_style'] = $editor_style_handle;
                $block_args['style'] = $style_handle;
            } else {
                // Backward compatibility for blocks that haven't been refactored
                $block_args['editor_script'] = 'lacadev-gutenberg-blocks';
            }
            
            // Add render callback if render.php exists
            if (file_exists($render_php)) {
                $block_args['render_callback'] = function($attributes, $content) use ($render_php) {
                    ob_start();
                    require $render_php;
                    return ob_get_clean();
                };
            }
            
            $result = register_block_type_from_metadata($block_json, $block_args);
            
            if ($result) {
                $registered_count++;
            }
        }
    }
    
}
add_action('init', 'lacadev_register_custom_blocks', 10);

/**
 * Register custom block category
 */
function lacadev_register_block_category($categories, $post) {
    return array_merge(
        [
            [
                'slug'  => 'lacadev-blocks',
                'title' => __('La Cà Blocks', 'laca'),
                'icon'  => 'admin-customizer',
            ],
        ],
        $categories
    );
}
add_filter('block_categories_all', 'lacadev_register_block_category', 10, 2);
