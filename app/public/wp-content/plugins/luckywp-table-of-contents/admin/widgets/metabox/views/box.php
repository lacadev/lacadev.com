<?php
/**
 * @var $post WP_Post
 * @var $settings \luckywp\tableOfContents\plugin\PostSettings
 */

use luckywp\tableOfContents\core\admin\helpers\AdminHtml;
use luckywp\tableOfContents\core\Core;

?>
<div class="lwptocMetabox" data-post-id="<?php echo $post->ID ?>">
    <?php if ($settings->enabled) { ?>
        <p>
            <?php echo __('The table of contents will be automatic added to this post.', 'luckywp-table-of-contents') ?>
        </p>
    <?php } elseif ($settings->processHeadings) { ?>
        <p>
            <?php echo __('The headings will be processing in this post for use in table of contents in widget or custom shortcode.', 'luckywp-table-of-contents') ?>
        </p>
    <?php } ?>

    <?php if ($settings->enabled || $settings->processHeadings) { ?>
        <?php
        $rows = Core::$plugin->admin->overrideSettingsToRows($settings);
        if ($rows) { ?>
            <p>
                <?php echo __('Overridden settings:', 'luckywp-table-of-contents') ?>
            </p>
            <div class="lwptocMetabox_settings">
                <?php foreach ($rows as $row) { ?>
                    <div class="lwptocMetabox_settings_item">
                        <b><?php echo $row[0] ?>:</b>
                        <?php echo $row[1] === null ? '<i>' . __('empty', 'luckywp-table-of-contents') . '</i>' : $row[1] ?>
                    </div>
                <?php } ?>
            </div>
        <?php } ?>
        <p>
            <?php echo AdminHtml::button(__('Customize', 'luckywp-table-of-contents'), [
                'class' => 'lwptocMetabox_customize',
            ]) ?>
            <?php
            if ($settings->enabled) {
                echo AdminHtml::button(__('Disable TOC', 'luckywp-table-of-contents'), [
                    'theme' => AdminHtml::BUTTON_THEME_LINK_DELETE,
                    'class' => 'lwptocMetabox_disable',
                ]);
            } elseif ($settings->processHeadings) {
                echo AdminHtml::button(__('Disable Processing', 'luckywp-table-of-contents'), [
                    'theme' => AdminHtml::BUTTON_THEME_LINK_DELETE,
                    'class' => 'lwptocMetabox_disableProcessing',
                ]);
            }
            ?>
        </p>
    <?php } ?>


    <?php if (!$settings->processHeadings && !$settings->enabled) { ?>
        <p>
            <?php echo __('Click "Enable Processing" for headings processing in this post.', 'luckywp-table-of-contents') ?>
        </p>
        <p>
            <?php echo AdminHtml::button(__('Enable Processing', 'luckywp-table-of-contents'), [
                'class' => 'lwptocMetabox_enableProcessing',
            ]) ?>
        </p>
    <?php } ?>

    <?php if (!$settings->enabled) { ?>
        <p>
            <?php echo __('Click "Enable TOC" for automatic add table of contents to this post.', 'luckywp-table-of-contents') ?>
        </p>
        <p>
            <?php echo AdminHtml::button(__('Enable TOC', 'luckywp-table-of-contents'), [
                'class' => 'lwptocMetabox_enable',
            ]) ?>
        </p>
    <?php } ?>
</div>
