<?php
/**
 * Theme Options.
 *
 * Here, you can register Theme Options using the Carbon Fields library.
 *
 * @link    https://carbonfields.net/docs/containers-theme-options/
 *
 * @package WPEmergeCli
 */

use Carbon_Fields\Container\Container;
use Carbon_Fields\Field\Field;

$optionsPage = Container::make('theme_options', __('Laca Theme', 'laca'))
	->set_page_file('app-theme-options.php')
	->set_page_menu_position(3)
	->add_tab(__('Branding | Thương hiệu', 'laca'), [
		Field::make('color', 'primary_color', __('Primary color', 'laca'))
			->set_width(33.33),
		Field::make('color', 'secondary_color', __('Secondary color', 'laca'))
			->set_width(33.33),
		Field::make('color', 'bg_color', __('Background color', 'laca'))
			->set_width(33.33),

		Field::make('color', 'primary_color_dark', __('Primary color dark', 'laca'))
			->set_width(33.33),
		Field::make('color', 'secondary_color_dark', __('Secondary color dark', 'laca'))
			->set_width(33.33),
		Field::make('color', 'bg_color_dark', __('Background color dark', 'laca'))
			->set_width(33.33),

		Field::make('image', 'logo', __('Logo', 'laca'))
			->set_width(33.33),
		Field::make('image', 'logo_dark', __('Logo Dark', 'laca'))
			->set_width(33.33),
		Field::make('image', 'default_image', __('Default image | Hình ảnh mặc định', 'laca'))
			->set_width(33.33),
	])

	->add_tab(__('Contact | Liên hệ', 'laca'), [
		Field::make('html', 'info', __('', 'laca'))
			->set_html('----<i> Information | Thông tin </i>----'),
		Field::make('text', 'company' . currentLanguage(), __('', 'laca'))->set_width(50)
			->set_attribute('placeholder', 'Company | Công ty'),
		Field::make('text', 'address' . currentLanguage(), __('', 'laca'))->set_width(50)
			->set_attribute('placeholder', 'Address | Địa chỉ'),
		Field::make('textarea', 'googlemap' . currentLanguage(), __('', 'laca'))
			->set_attribute('placeholder', 'Google map'),
		Field::make('text', 'email' . currentLanguage(), __('', 'laca'))->set_width(33.33)
			->set_attribute('placeholder', 'Email'),
		Field::make('text', 'phone_number' . currentLanguage(), __('', 'laca'))->set_width(33.33)
			->set_attribute('placeholder', 'Phone number | Số điện thoại'),
		Field::make('text', 'hour_working' . currentLanguage(), __('', 'laca'))->set_width(33.33)
			->set_attribute('placeholder', 'Hour working | Giờ làm việc'),
		Field::make('html', 'socials', __('', 'laca'))
			->set_html('----<i> Socials | Mạng xã hội </i>----'),
		Field::make('text', 'facebook' . currentLanguage(), __('', 'laca'))->set_width(50)
			->set_attribute('placeholder', 'facebook'),
		Field::make('text', 'instagram' . currentLanguage(), __('', 'laca'))->set_width(50)
			->set_attribute('placeholder', 'instagram'),
		Field::make('text', 'tiktok' . currentLanguage(), __('', 'laca'))->set_width(50)
			->set_attribute('placeholder', 'tiktok'),
		Field::make('text', 'youtube' . currentLanguage(), __('', 'laca'))->set_width(50)
			->set_attribute('placeholder', 'youtube'),
	])

	->add_tab(__('Header  |  Footer', 'laca'), [
		Field::make('html', 'header', __('', 'laca'))
			->set_html('----<i> Header </i>----'),
		Field::make('text', 'header_label' . currentLanguage(), __('', 'laca'))->set_width(50),

		Field::make('html', 'footer', __('', 'laca'))
			->set_html('----<i> Footer </i>----'),
		Field::make('text', 'contact_label' . currentLanguage(), __('', 'laca'))->set_width(50)
			->set_attribute('placeholder', 'Contact label | Nhãn liên hệ'),
		Field::make('text', 'contact_url' . currentLanguage(), __('', 'laca'))->set_width(50)
			->set_attribute('placeholder', 'Contact URL | Liên kết liên hệ'),
		Field::make('textarea', 'contact_message' . currentLanguage(), __('', 'laca'))
			->set_attribute('placeholder', 'Contact description | Mô tả liên hệ'),
	])

	->add_tab(__('Scripts', 'laca'), [
		Field::make('header_scripts', 'crb_header_script', __('Header Script', 'laca')),
		Field::make('footer_scripts', 'crb_footer_script', __('Footer Script', 'laca')),
	]);