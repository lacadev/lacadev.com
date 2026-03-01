<?php
/**
 * @var $name string
 * @var $value string
 * @var $type string
 * @var $size int
 * @var $unit string
 */

use luckywp\tableOfContents\core\Core;
use luckywp\tableOfContents\core\helpers\Html;

?>
<div class="lwptocFontSizeField">
    <?php echo Html::dropDownList(null, $type, [
        'default' => esc_html__('Default', 'luckywp-table-of-contents'),
        'custom' => esc_html__('Custom Value', 'luckywp-table-of-contents'),
    ], [
        'class' => 'lwptocFontSizeField_typeInput',
    ]) ?>
    <div class="lwptocFontSizeField_custom"<?php echo $type == 'custom' ? '' : ' style="display:none;"' ?>>
        <?php echo Html::textInput(null, $size, [
            'class' => 'lwptocFontSizeField_sizeInput',
        ]) ?>
        <?php echo Html::dropDownList(null, $unit, Core::$plugin->fontSizeUnitsList, [
            'class' => 'lwptocFontSizeField_unitInput',
        ]) ?>
    </div>
    <?php echo Html::hiddenInput($name, $value, ['class' => 'lwptocFontSizeField_input']) ?>
</div>
