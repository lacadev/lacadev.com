<?php

namespace luckywp\tableOfContents\admin\widgets;

use luckywp\tableOfContents\core\base\Widget;

class OverrideColorBadge extends Widget
{
    /**
     * @var string
     */
    public $color;

    public function run()
    {
        if ($this->color) {
            $color = esc_html($this->color);
            $ico = '<b style="background:' . $color . '"></b>';
            return '<span class="lwptocColorBadge">' . (is_rtl() ? ($color . $ico) : ($ico . $color)) . '</span>';
        }
        return esc_html__('from scheme', 'luckywp-table-of-contents');
    }
}
