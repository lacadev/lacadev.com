<?php
/**
 * @var $title string
 * @var $titleTag string
 * @var $toggle bool
 * @var $labelShow string
 * @var $labelHide string
 * @var $hideItems bool
 * @var $containerOptions array
 * @var $innerContainerOptions array
 * @var $headerStyles array
 * @var $titleStyles array
 * @var $itemsStyles array
 * @var $items array
 * @var $before string
 * @var $after string
 */

use luckywp\tableOfContents\core\helpers\Html;

echo $before . Html::beginTag('div', $containerOptions) . Html::beginTag('div', $innerContainerOptions);
?>
<?php if ($toggle || $title) { ?>
    <div class="lwptoc_header"<?php echo $headerStyles ? ' style="' . implode('', $headerStyles) . '"' : '' ?>>
        <?php
        if ($title) {
            echo '<'
                . $titleTag
                . ' class="lwptoc_title"'
                . ($titleStyles ? ' style="' . esc_attr(implode('', $titleStyles)) . '"' : '')
                . '>'
                . esc_html($title)
                . '</'
                . $titleTag
                . '>';
        }
        ?>
        <?php if ($toggle) { ?>
            <span class="lwptoc_toggle">
                <?php echo '<a href="#" class="lwptoc_toggle_label" data-label="'
                    . htmlspecialchars(esc_html($hideItems ? $labelHide : $labelShow), ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8')
                    . '">'
                    . esc_html($hideItems ? $labelShow : $labelHide)
                    . '</a>'
                ?>
            </span>
        <?php } ?>
    </div>
<?php } ?>
<div class="lwptoc_items<?php echo $hideItems ? '' : ' lwptoc_items-visible' ?>"<?php echo $itemsStyles ? ' style="' . implode('', $itemsStyles) . '"' : '' ?>>
    <?php lwptoc_items($items) ?>
</div>
<?php echo '</div></div>' . $after ?>
