<?php

use luckywp\tableOfContents\admin\Rate;
use luckywp\tableOfContents\core\admin\helpers\AdminHtml;
use luckywp\tableOfContents\core\Core;

?>
<div class="notice notice-info lwptocRate">
    <p>
        <?php echo esc_html__('Hello!', 'luckywp-table-of-contents') ?>
        <br>
        <?php echo sprintf(
        /* translators: %s: LuckyWP Table of Contents */
            esc_html__('We are very pleased that you by now have been using the %s plugin a few days.', 'luckywp-table-of-contents'),
            '<b>' . Core::$plugin->getName() . '</b>'
        ) ?>
        <br>
        <?php echo esc_html__('Please rate plugin. It will help us a lot.', 'luckywp-table-of-contents') ?>
    </p>
    <p>
        <?php echo AdminHtml::buttonLink(esc_html__('Rate the plugin', 'luckywp-table-of-contents'), Rate::LINK, [
            'attrs' => [
                'data-action' => 'lwptoc_rate',
                'target' => '_blank',
            ],
            'theme' => AdminHtml::BUTTON_THEME_PRIMARY,
        ]) ?>
        <?php echo AdminHtml::button(esc_html__('Remind later', 'luckywp-table-of-contents'), [
            'attrs' => [
                'data-action' => 'lwptoc_rate_show_later',
            ],
            'theme' => AdminHtml::BUTTON_THEME_LINK,
        ]) ?>
        <?php echo AdminHtml::button(esc_html__('Don\'t show again', 'luckywp-table-of-contents'), [
            'attrs' => [
                'data-action' => 'lwptoc_rate_hide',
            ],
            'theme' => AdminHtml::BUTTON_THEME_LINK,
        ]) ?>
    </p>
    <p>
        <b><?php echo esc_html__('Thank you very much!', 'luckywp-table-of-contents') ?></b>
    </p>
</div>
