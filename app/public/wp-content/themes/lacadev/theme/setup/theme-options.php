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
		Field::make('html', 'branding_intro', '')
			->set_html('<div style="background:#f0f9ff;border:1px solid #bae6fd;border-radius:6px;padding:14px 16px;margin:8px 0"><p style="margin:0 0 8px;font-weight:600;color:#0369a1">🔧 Thương hiệu</p><p style="margin:0;font-size:13px;color:#374151">Các trường ở đây thiết lập màu sắc và logo của website, được áp dụng đồng bộ trên toàn bộ theme (cả giao diện sáng và tối).</p></div>'),
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
		Field::make('html', 'contact_intro', '')
			->set_html('<div style="background:#f0f9ff;border:1px solid #bae6fd;border-radius:6px;padding:14px 16px;margin:8px 0"><p style="margin:0 0 8px;font-weight:600;color:#0369a1">🔧 Liên hệ</p><p style="margin:0;font-size:13px;color:#374151">Đây là thông tin liên hệ của công ty (địa chỉ, số điện thoại, mạng xã hội...) hiển thị ở footer và các block liên hệ. Chỉnh sửa ở đây sẽ cập nhật trên toàn bộ website.</p></div>'),
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
		Field::make('text', 'linkedin' . currentLanguage(), __('', 'laca'))->set_width(50)
			->set_attribute('placeholder', 'linkedin'),
		Field::make('text', 'instagram' . currentLanguage(), __('', 'laca'))->set_width(50)
			->set_attribute('placeholder', 'instagram'),
		Field::make('text', 'tiktok' . currentLanguage(), __('', 'laca'))->set_width(50)
			->set_attribute('placeholder', 'tiktok'),
		Field::make('text', 'youtube' . currentLanguage(), __('', 'laca'))->set_width(50)
			->set_attribute('placeholder', 'youtube'),
		Field::make('text', 'zalo' . currentLanguage(), __('', 'laca'))->set_width(50)
			->set_attribute('placeholder', 'zalo'),
	])

	->add_tab(__('Archive pages | List bài viết CPT', 'laca'), [
		Field::make('html', 'archive_intro', '')
			->set_html('<div style="background:#f0f9ff;border:1px solid #bae6fd;border-radius:6px;padding:14px 16px;margin:8px 0"><p style="margin:0 0 8px;font-weight:600;color:#0369a1">🔧 Trang danh sách (Archive)</p><p style="margin:0;font-size:13px;color:#374151">Các trường này quy định tiêu đề và mô tả hiển thị trên trang danh sách (archive) của từng loại nội dung, ví dụ trang liệt kê Dịch vụ hoặc Dự án.</p></div>'),
		Field::make('html', 'service', __('', 'laca'))
			->set_html('----<i> Service </i>----'),
		Field::make('text', 'service_page_title' . currentLanguage(), __('', 'laca'))
			->set_attribute('placeholder', 'Service page title | Tiêu đề trang dịch vụ'),
		Field::make('text', 'service_page_description' . currentLanguage(), __('', 'laca'))
			->set_attribute('placeholder', 'Service page description | Mô tả trang dịch vụ'),

		Field::make('html', 'project', __('', 'laca'))
			->set_html('----<i> Project </i>----'),
		Field::make('text', 'project_page_title' . currentLanguage(), __('', 'laca'))
			->set_attribute('placeholder', 'Project page title | Tiêu đề trang dự án'),
		Field::make('text', 'project_page_description' . currentLanguage(), __('', 'laca'))
			->set_attribute('placeholder', 'Project page description | Mô tả trang dự án'),
	])

	->add_tab(__('Scripts', 'laca'), [
		Field::make('html', 'scripts_intro', '')
			->set_html('<div style="background:#f0f9ff;border:1px solid #bae6fd;border-radius:6px;padding:14px 16px;margin:8px 0"><p style="margin:0 0 8px;font-weight:600;color:#0369a1">🔧 Scripts (Header/Footer)</p><p style="margin:0;font-size:13px;color:#374151">Dùng để chèn các mã theo dõi như Google Analytics, Facebook Pixel... Lưu ý: mã không hợp lệ ở đây có thể làm hỏng toàn bộ website, chỉ nên dán script từ nguồn đáng tin cậy.</p></div>'),
		Field::make('header_scripts', 'crb_header_script', __('Header Script', 'laca')),
		Field::make('footer_scripts', 'crb_footer_script', __('Footer Script', 'laca')),
	])

	->add_tab(__('AI Translation | Dịch thuật AI', 'laca'), [
		Field::make('html', 'ai_intro', __('', 'laca'))
			->set_html('Cấu hình API Key để kích hoạt tính năng tự động dịch nội dung bằng trí tuệ nhân tạo. Bạn nên ưu tiên dùng Gemini hoặc Groq vì có gói miễn phí rất tốt.'),
		
		Field::make('text', 'ai_gemini_key', __('Gemini API Key', 'laca'))
			->set_help_text('Model: Gemini 1.5 Pro/Flash. Lấy tại: <a href="https://aistudio.google.com/app/apikey" target="_blank">Google AI Studio</a>'),
		
		Field::make('text', 'ai_groq_key', __('Groq API Key', 'laca'))
			->set_help_text('Model: Llama 3/3.1. Lấy tại: <a href="https://console.groq.com/keys" target="_blank">Groq Console</a>'),

		Field::make('text', 'ai_deepseek_key', __('DeepSeek API Key', 'laca'))
			->set_help_text('Model: DeepSeek Chat. Lấy tại: <a href="https://platform.deepseek.com/" target="_blank">DeepSeek Platform</a>'),

		Field::make('text', 'ai_openai_key', __('OpenAI API Key', 'laca'))
			->set_help_text('Model: GPT-4o, GPT-4o-mini. Lấy tại: <a href="https://platform.openai.com/" target="_blank">OpenAI Platform</a>'),

		Field::make('text', 'ai_anthropic_key', __('Anthropic API Key', 'laca'))
			->set_help_text('Model: Claude 3.5 Sonnet/Haiku. Lấy tại: <a href="https://console.anthropic.com/" target="_blank">Anthropic Console</a>'),

		Field::make('select', 'ai_default_provider', __('Bô xử lý ưu tiên', 'laca'))
			->set_options([
				'gemini' => 'Google Gemini (Khuyên dùng)',
				'groq'   => 'Groq (Llama 3 - Tốc độ cực nhanh)',
				'deepseek' => 'DeepSeek (Giá rẻ/Chất lượng cao)',
				'openai' => 'OpenAI GPT',
				'anthropic' => 'Anthropic Claude',
			])
			->set_default_value('gemini'),
	]);