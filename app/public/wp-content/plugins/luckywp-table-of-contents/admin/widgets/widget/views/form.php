<?php
/**
 * @var $id string
 * @var $inputName string
 * @var $value string
 * @var $attrs array
 */

use luckywp\tableOfContents\admin\widgets\widget\Widget;
use luckywp\tableOfContents\core\admin\helpers\AdminHtml;
use luckywp\tableOfContents\core\helpers\Html;

?>
<div class="lwptocWidget lwptocWidget-<?php echo $id ?>" data-id="<?php echo $id ?>">
    <div class="lwptocWidget_override">
        <?php echo Widget::overrideHtml($attrs) ?>
    </div>
    <p>
        <?php echo AdminHtml::button(__('Customize', 'luckywp-table-of-contents'), [
            'class' => 'lwptocWidget_customize',
        ]) ?>
    </p>
    <?php echo Html::hiddenInput($inputName, $value, ['class' => 'lwptocWidget_input']) ?>
</div>
<script>
    jQuery.lwptocWidget.init();
</script>
