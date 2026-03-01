<?php

namespace luckywp\tableOfContents\integrations;

use luckywp\tableOfContents\core\base\BaseObject;
use luckywp\tableOfContents\core\Core;

use function is_array;
use function is_string;

class Oxygen extends BaseObject
{
    public function init()
    {
        add_action('ct_builder_start', function () {
            Core::$plugin->onTheContentTrue('');
        }, 1);
        add_action('ct_builder_start', function () {
            global $template_content;
            $content = $this->getContent();
            if ($content !== null) {
                $template_content = str_replace(
                    $content,
                    Core::$plugin->shortcode->theContent($content),
                    $template_content
                );
            }
            Core::$plugin->onTheContentFalse('');
        }, 9999);
    }

    /**
     * @return string|null
     */
    private function getContent()
    {
        global $post;

        $meta = get_post_meta($post->ID, '_ct_builder_json', true);
        if (!is_string($meta)) {
            return null;
        }

        $decoded = @json_decode($meta, true);
        if (!is_array($decoded)) {
            return null;
        }

        $content = do_oxygen_elements($decoded);
        if (!is_string($content)) {
            return null;
        }

        return $content;
    }
}
