<?php

namespace App\PostTypes;

use Carbon_Fields\Container\Container;
use Carbon_Fields\Field;

class Service extends \App\Abstracts\AbstractPostType
{

    public function __construct()
    {
        $this->showThumbnailOnList = true;
        $this->supports            = [
            'title',
            'editor',
            'thumbnail',
            'excerpt',
            'page-attributes',
            'comments',
        ];

        $this->menuIcon         = 'dashicons-admin-generic';
        // $this->menuIcon = get_template_directory_uri() . '/images/custom-icon.png';
        $this->post_type        = 'service';
        $this->singularName     = $this->pluralName = __('Service', 'laca');
        $this->titlePlaceHolder = __('Service', 'laca');
        $this->slug             = 'services';
        parent::__construct();
    }

    public function metaFields()
    {
        // Add meta fields for services here
    }
}
