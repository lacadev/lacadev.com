<?php

if (!defined('ABSPATH')) {
    exit;
}

class Laca_Bot_Settings {

    public function __construct() {
        add_action('admin_menu', [$this, 'add_settings_page']);
        add_action('admin_init', [$this, 'register_settings']);
    }

    public function add_settings_page() {
        add_menu_page(
            'Laca Bot',
            'Laca Bot',
            'manage_options',
            'laca-bot-settings',
            [$this, 'render_settings_page'],
            'dashicons-smart-phone',
            30
        );
    }

    public function register_settings() {
        $options = [
            'laca_bot_gemini_key', 'laca_bot_groq_key', 'laca_bot_deepseek_key',
            'laca_bot_openai_key', 'laca_bot_anthropic_key',
            'laca_bot_enable_frontend', 'laca_bot_enable_backend',
            'laca_bot_name', 'laca_bot_avatar', 'laca_bot_greeting',
            'laca_bot_company_info', 'laca_bot_contact_phone', 'laca_bot_contact_email',
            'laca_bot_gemini_limit', 'laca_bot_groq_limit', 'laca_bot_deepseek_limit',
            'laca_bot_openai_limit', 'laca_bot_anthropic_limit',
            'laca_bot_position', 'laca_bot_primary_color', 'laca_bot_accent_color',
            'laca_bot_button_style', 'laca_bot_quick_chips'
        ];
        
        foreach ($options as $option) {
            register_setting('laca_bot_settings_group', $option);
        }

        // Custom sanitization for training data to preserve HTML in answers
        register_setting('laca_bot_settings_group', 'laca_bot_training_data', [
            'type' => 'array',
            'sanitize_callback' => function($data) {
                if (!is_array($data)) return [];
                $sanitized = [];
                foreach ($data as $item) {
                    if (empty($item['q']) && empty($item['a'])) continue; // Skip empty rows
                    $sanitized[] = [
                        'q' => sanitize_text_field($item['q'] ?? ''),
                        'k' => sanitize_text_field($item['k'] ?? ''),
                        'a' => wp_kses_post($item['a'] ?? '')
                    ];
                }
                return $sanitized;
            }
        ]);
    }

    public function render_settings_page() {
        $active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'api';
        ?>
        <?php wp_enqueue_editor(); ?>
        <div class="wrap">
            <h1>Laca Bot - Cài đặt Trợ lý AI</h1>
            
            <?php settings_errors(); ?>

            <h2 class="nav-tab-wrapper" id="laca-bot-tabs">
                <a href="#api" class="nav-tab nav-tab-active" data-tab="api">Cài đặt API AI</a>
                <a href="#ui" class="nav-tab" data-tab="ui">Nhân vật & Giao diện</a>
                <a href="#context" class="nav-tab" data-tab="context">Ngữ cảnh Doanh nghiệp</a>
                <a href="#train" class="nav-tab" data-tab="train">Đào tạo (Bộ nhớ tĩnh)</a>
            </h2>
            
            <form method="post" action="options.php" id="laca-bot-settings-form">
                <?php settings_fields('laca_bot_settings_group'); ?>
                
                <!-- Tab API -->
                <div id="tab-api" class="laca-bot-tab-content">
                    <table class="form-table">
                        <tr><td colspan="2"><p>Hệ thống sẽ chạy ưu tiên theo thứ tự: Gemini -> Groq -> DeepSeek. Hãy lấy API Key miễn phí từ các nhà cung cấp này.</p></td></tr>
                        <tr>
                            <th scope="row">Google Gemini API Key</th>
                            <td>
                                <input type="password" name="laca_bot_gemini_key" value="<?php echo esc_attr(get_option('laca_bot_gemini_key')); ?>" class="regular-text" />
                                <input type="number" name="laca_bot_gemini_limit" value="<?php echo esc_attr(get_option('laca_bot_gemini_limit', 50000)); ?>" class="small-text" /> <small>Limit/Day</small>
                                <br><small>Lấy tại: https://aistudio.google.com/app/apikey</small>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Groq API Key</th>
                            <td>
                                <input type="password" name="laca_bot_groq_key" value="<?php echo esc_attr(get_option('laca_bot_groq_key')); ?>" class="regular-text" />
                                <input type="number" name="laca_bot_groq_limit" value="<?php echo esc_attr(get_option('laca_bot_groq_limit', 50000)); ?>" class="small-text" /> <small>Limit/Day</small>
                                <br><small>Lấy tại: https://console.groq.com/keys</small>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">DeepSeek API Key</th>
                            <td>
                                <input type="password" name="laca_bot_deepseek_key" value="<?php echo esc_attr(get_option('laca_bot_deepseek_key')); ?>" class="regular-text" />
                                <input type="number" name="laca_bot_deepseek_limit" value="<?php echo esc_attr(get_option('laca_bot_deepseek_limit', 100000)); ?>" class="small-text" /> <small>Limit/Day</small>
                                <br><small>Lấy tại: https://platform.deepseek.com/</small>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">OpenAI (ChatGPT) API Key</th>
                            <td>
                                <input type="password" name="laca_bot_openai_key" value="<?php echo esc_attr(get_option('laca_bot_openai_key')); ?>" class="regular-text" />
                                <input type="number" name="laca_bot_openai_limit" value="<?php echo esc_attr(get_option('laca_bot_openai_limit', 50000)); ?>" class="small-text" /> <small>Limit/Day</small>
                                <br><small>Sử dụng model GPT-4o mini. Lấy tại: https://platform.openai.com/</small>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Anthropic (Claude) API Key</th>
                            <td>
                                <input type="password" name="laca_bot_anthropic_key" value="<?php echo esc_attr(get_option('laca_bot_anthropic_key')); ?>" class="regular-text" />
                                <input type="number" name="laca_bot_anthropic_limit" value="<?php echo esc_attr(get_option('laca_bot_anthropic_limit', 50000)); ?>" class="small-text" /> <small>Limit/Day</small>
                                <br><small>Sử dụng model Claude 3.5 Haiku. Lấy tại: https://console.anthropic.com/</small>
                            </td>
                        </tr>
                    </table>

                    <div style="margin-top: 40px; background: #fff; border: 1px solid #ccd0d4; padding: 20px;">
                        <h3 style="margin-top: 0;">📊 Thống kê sử dụng Token (Hôm nay)</h3>
                        <p><small>Dữ liệu sẽ tự động reset sau 24:00 mỗi ngày.</small></p>
                        <table class="widefat fixed striped" style="margin-top: 15px;">
                            <thead>
                                <tr>
                                    <th>Nhà cung cấp</th>
                                    <th>Admin dùng</th>
                                    <th>Khách dùng</th>
                                    <th>Tổng cộng</th>
                                    <th>Số dư (Dự tính)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $usage_stats = Laca_Bot_Usage::get_formatted_usage();
                                foreach ($usage_stats as $stat): 
                                    $limit = (int) get_option('laca_bot_' . $stat['slug'] . '_limit', 50000);
                                    $remaining = $limit - $stat['total'];
                                    $color = $remaining < 1000 ? '#d63638' : ($remaining < 5000 ? '#dba617' : '#2271b1');
                                ?>
                                <tr>
                                    <td><strong><?php echo esc_html($stat['name']); ?></strong></td>
                                    <td><?php echo number_format($stat['admin']); ?></td>
                                    <td><?php echo number_format($stat['user']); ?></td>
                                    <td><?php echo number_format($stat['total']); ?> / <?php echo number_format($limit); ?></td>
                                    <td style="color: <?php echo $color; ?>; font-weight: bold;"><?php echo number_format(max(0, $remaining)); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Tab UI -->
                <div id="tab-ui" class="laca-bot-tab-content" style="display: none;">
                    <table class="form-table">
                        <tr>
                            <th scope="row">Bật Bot cho khách (Frontend)</th>
                            <td><input type="checkbox" name="laca_bot_enable_frontend" value="1" <?php checked(1, get_option('laca_bot_enable_frontend', 1)); ?> /></td>
                        </tr>
                        <tr>
                            <th scope="row">Bật Bot cho Admin (Backend)</th>
                            <td><input type="checkbox" name="laca_bot_enable_backend" value="1" <?php checked(1, get_option('laca_bot_enable_backend', 1)); ?> /></td>
                        </tr>
                        <tr>
                            <th scope="row">Tên Robot</th>
                            <td><input type="text" name="laca_bot_name" value="<?php echo esc_attr(get_option('laca_bot_name', 'Laca Bot')); ?>" class="regular-text" /></td>
                        </tr>
                        <tr>
                            <th scope="row">Ảnh đại diện Bot (URL)</th>
                            <td><input type="text" name="laca_bot_avatar" value="<?php echo esc_attr(get_option('laca_bot_avatar')); ?>" class="regular-text" /></td>
                        </tr>
                        <tr>
                            <th scope="row">Lời chào đầu tiên (Frontend)</th>
                            <td><textarea name="laca_bot_greeting" rows="3" class="large-text"><?php echo esc_textarea(get_option('laca_bot_greeting', 'Xin chào! Tôi là trợ lý AI thông minh của LacaDev. Tôi có thể giúp gì cho bạn hôm nay?')); ?></textarea></td>
                        </tr>
                        <tr>
                            <th scope="row">Vị trí hiển thị</th>
                            <td>
                                <select name="laca_bot_position">
                                    <option value="right" <?php selected('right', get_option('laca_bot_position', 'right')); ?>>Góc dưới bên phải</option>
                                    <option value="left" <?php selected('left', get_option('laca_bot_position', 'right')); ?>>Góc dưới bên trái</option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Màu chủ đạo (Primary Color)</th>
                            <td><input type="color" name="laca_bot_primary_color" value="<?php echo esc_attr(get_option('laca_bot_primary_color', '#2271b1')); ?>" /> <small>Dành cho các nút và tin nhắn người dùng.</small></td>
                        </tr>
                        <tr>
                            <th scope="row">Màu nhấn (Accent Color)</th>
                            <td><input type="color" name="laca_bot_accent_color" value="<?php echo esc_attr(get_option('laca_bot_accent_color', '#1d2327')); ?>" /> <small>Dành cho Header của Widget.</small></td>
                        </tr>
                        <tr>
                            <th scope="row">Kiểu nút Widget</th>
                            <td>
                                <select name="laca_bot_button_style">
                                    <option value="rounded" <?php selected('rounded', get_option('laca_bot_button_style', 'rounded')); ?>>Bo góc (Rounded)</option>
                                    <option value="circle" <?php selected('circle', get_option('laca_bot_button_style', 'rounded')); ?>>Hình tròn (Circle)</option>
                                    <option value="square" <?php selected('square', get_option('laca_bot_button_style', 'rounded')); ?>>Hình vuông (Square)</option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Câu hỏi mẫu (Suggestion Chips)</th>
                            <td>
                                <textarea name="laca_bot_quick_chips" rows="4" class="large-text" placeholder="Dịch vụ SEO, Lên đỉnh Google ngay hôm nay | Tôi muốn tư vấn dịch vụ SEO cho website, thiết kế Website | Mô tả ngắn gọn dịch vụ thiết kế kèm link https://domain.com/web"><?php echo esc_textarea(get_option('laca_bot_quick_chips', 'Dịch vụ SEO, Báo giá, Liên hệ tư vấn')); ?></textarea>
                                <br><small>Nhập các câu hỏi, phân cách bằng dấu phẩy (,).<br>💡 <b>Mẹo nâng cao:</b> Bạn có thể dùng dấu gạch đứng (<code>|</code>) để lồng ghép <strong>ngữ cảnh</strong> phía sau nút gắn trên màn hình (Định dạng: <code>Tên nút | Lệnh ẩn cho Bot</code>). <br>Ví dụ: <code>Website WordPress | Hãy mô tả ngắn gọn về dịch vụ WP và gửi kèm link tham khảo: https://laca.dev</code></small>
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- Tab Context -->
                <div id="tab-context" class="laca-bot-tab-content" style="display: none;">
                    <table class="form-table">
                        <tr><td colspan="2"><p>Cung cấp thông tin tổng quan, sứ mệnh, chính sách... của công ty. AI sẽ dùng thông tin chung này để đóng vai chuẩn hơn.</p></td></tr>
                        <tr>
                            <th scope="row">Giới thiệu chung</th>
                            <td><textarea name="laca_bot_company_info" rows="5" class="large-text"><?php echo esc_textarea(get_option('laca_bot_company_info')); ?></textarea><br><small>Ví dụ: Đây là website bán máy pha cà phê cao cấp tại Việt Nam. Chúng tôi cam kết bảo hành 2 năm...</small></td>
                        </tr>
                        <tr>
                            <th scope="row">Số điện thoại chốt sale</th>
                            <td><input type="text" name="laca_bot_contact_phone" value="<?php echo esc_attr(get_option('laca_bot_contact_phone')); ?>" class="regular-text" /></td>
                        </tr>
                        <tr>
                            <th scope="row">Email liên hệ</th>
                            <td><input type="text" name="laca_bot_contact_email" value="<?php echo esc_attr(get_option('laca_bot_contact_email')); ?>" class="regular-text" /></td>
                        </tr>
                    </table>
                </div>

                <!-- Tab Training -->
                <div id="tab-train" class="laca-bot-tab-content" style="display: none;">
                    <p class="description">Định nghĩa những câu hỏi thường gặp (FAQ) hoặc thông tin ưu tiên trả lời. Việc định nghĩa này giúp tiết kiệm Token bởi AI sẽ dùng các câu trả lời này để match nhanh chóng thay vì "tự biên tự diễn" (hallucination).</p>
                    
                    <div id="laca-bot-repeater-container" style="margin-top: 20px;">
                        <?php
                        $training_data = get_option('laca_bot_training_data');
                        if (!is_array($training_data)) $training_data = [];
                        
                        // Layout 1 item rỗng để clone bằng JS
                        echo '<div id="laca-bot-repeater-template" style="display:none;" class="laca-bot-repeater-item">';
                        $this->render_repeater_item_html();
                        echo '</div>';

                        // Render dữ liệu đã lưu
                        if (empty($training_data)) {
                            // Nếu chưa có gì, render sẵn 1 hộp rỗng
                            echo '<div class="laca-bot-repeater-item">';
                            $this->render_repeater_item_html();
                            echo '</div>';
                        } else {
                            foreach ($training_data as $index => $data) {
                                echo '<div class="laca-bot-repeater-item">';
                                $this->render_repeater_item_html($data['q'] ?? '', $data['k'] ?? '', $data['a'] ?? '', $index);
                                echo '</div>';
                            }
                        }
                        ?>
                    </div>

                    <button type="button" class="button button-secondary" id="laca-bot-add-row" style="margin-top: 15px;">+ Thêm trường huấn luyện</button>

                    <style>
                        .laca-bot-repeater-item {
                            background: #fff;
                            border: 1px solid #ccd0d4;
                            padding: 15px;
                            margin-bottom: 15px;
                            position: relative;
                            display: flex;
                            flex-direction: column;
                            gap: 10px;
                        }
                        .laca-bot-repeater-item .remove-row {
                            position: absolute;
                            top: 10px;
                            right: 10px;
                            color: #d63638;
                            cursor: pointer;
                            font-weight: bold;
                            border: none; background: none;
                        }
                        .laca-bot-repeater-item .remove-row:hover { color: #b32d2e; }
                        .laca-bot-repeater-item label { font-weight: 600; display: block; margin-bottom: 5px; }
                        .laca-bot-repeater-item input[type="text"] { width: 100%; max-width: 800px; }
                        .laca-bot-repeater-item .wp-editor-wrap { max-width: 800px; margin-top: 5px; }
                        .laca-bot-repeater-item .wp-editor-container textarea { width: 100% !important; min-height: 150px; }
                    </style>
                </div>
                
                <input type="hidden" name="active_tab" id="active_tab_input" value="api" />
                <p class="submit" style="margin-top: 30px;">
                    <?php submit_button('', 'primary', 'submit', false); ?>
                </p>
            </form>
        </div>

        <script>
        document.addEventListener('DOMContentLoaded', function() {
            // -- XỬ LÝ TAB --
            var tabs = document.querySelectorAll('#laca-bot-tabs .nav-tab');
            var contents = document.querySelectorAll('.laca-bot-tab-content');
            var activeTabInput = document.getElementById('active_tab_input');
            
            var savedTab = localStorage.getItem('laca_bot_active_tab') || 'api';
            switchTab(savedTab);

            tabs.forEach(function(tab) {
                tab.addEventListener('click', function(e) {
                    e.preventDefault();
                    var targetTab = this.getAttribute('data-tab');
                    switchTab(targetTab);
                });
            });

            function switchTab(tabId) {
                tabs.forEach(function(t) { t.classList.remove('nav-tab-active'); });
                var activeTabEl = document.querySelector('#laca-bot-tabs .nav-tab[data-tab="' + tabId + '"]');
                if (activeTabEl) activeTabEl.classList.add('nav-tab-active');

                contents.forEach(function(c) { c.style.display = 'none'; });
                var targetContent = document.getElementById('tab-' + tabId);
                if (targetContent) targetContent.style.display = 'block';

                if (activeTabInput) activeTabInput.value = tabId;
                localStorage.setItem('laca_bot_active_tab', tabId);
            }

            // -- XỬ LÝ REPEATER --
            var container = document.getElementById('laca-bot-repeater-container');
            var template = document.getElementById('laca-bot-repeater-template');
            var addBtn = document.getElementById('laca-bot-add-row');

            // Set index property cho các name trong mảng và ID cho editor
            function reindexRepeater() {
                var items = container.querySelectorAll('.laca-bot-repeater-item:not(#laca-bot-repeater-template)');
                items.forEach(function(item, index) {
                    var qInput = item.querySelector('.input-q');
                    var kInput = item.querySelector('.input-k');
                    var aInput = item.querySelector('.input-a');
                    if(qInput) qInput.name = 'laca_bot_training_data[' + index + '][q]';
                    if(kInput) kInput.name = 'laca_bot_training_data[' + index + '][k]';
                    if(aInput) {
                        aInput.name = 'laca_bot_training_data[' + index + '][a]';
                        if (!aInput.id) {
                            aInput.id = 'laca_bot_a_' + index + '_' + Math.floor(Math.random() * 10000);
                        }
                    }
                });
            }
            
            function initEditors() {
                if (typeof wp !== 'undefined' && wp.editor) {
                    var textareas = container.querySelectorAll('.laca-bot-repeater-item:not(#laca-bot-repeater-template) .input-a');
                    textareas.forEach(function(ta) {
                        if (!tinymce.get(ta.id)) {
                            wp.editor.initialize(ta.id, {
                                tinymce: {
                                    toolbar1: 'formatselect,bold,italic,bullist,numlist,link,unlink,undo,redo'
                                },
                                quicktags: true
                            });
                        }
                    });
                }
            }

            // Reindex và init ngay khi load
            reindexRepeater();
            initEditors();

            addBtn.addEventListener('click', function() {
                var clone = template.cloneNode(true);
                clone.removeAttribute('id');
                clone.style.display = 'flex';
                
                // Trống các ô input
                var inputs = clone.querySelectorAll('input, textarea');
                inputs.forEach(i => {
                    i.value = '';
                    if (i.tagName.toLowerCase() === 'textarea') {
                        i.removeAttribute('id'); // Remove id to generate a new one
                        // Remove wp-editor wrappers if cloned from an initialized one (though template shouldn't have it)
                    }
                });

                container.appendChild(clone);
                reindexRepeater();
                initEditors();
            });

            container.addEventListener('click', function(e) {
                if (e.target.classList.contains('remove-row') || e.target.closest('.remove-row')) {
                    e.preventDefault();
                    var row = e.target.closest('.laca-bot-repeater-item');
                    if (confirm('Bạn có chắc xoá cụm thông tin huấn luyện này?')) {
                        var ta = row.querySelector('.input-a');
                        if (ta && typeof wp !== 'undefined' && wp.editor) {
                            wp.editor.remove(ta.id);
                        }
                        row.remove();
                        reindexRepeater();
                    }
                }
            });

            // Trigger save before submit
            document.getElementById('laca-bot-settings-form').addEventListener('submit', function() {
                if (typeof tinyMCE !== 'undefined') {
                    tinyMCE.triggerSave();
                }
            });
        });
        </script>
        <?php
    }

    private function render_repeater_item_html($q = '', $k = '', $a = '', $index = 'TEMPLATE') {
        ?>
        <div>
            <label>Câu hỏi / Tình huống mẫu</label>
            <input type="text" class="input-q regular-text" value="<?php echo esc_attr($q); ?>" placeholder="VD: Mua hàng free ship không?">
        </div>
        <div>
            <label>Từ khoá bắt dính (Keywords)</label>
            <input type="text" class="input-k regular-text" value="<?php echo esc_attr($k); ?>" placeholder="VD: freeship, miễn phí vận chuyển, phí ship, giao hàng">
        </div>
        <div>
            <label>Câu trả lời (Đáp án tiêu chuẩn của Bot)</label>
            <textarea class="input-a large-text laca-bot-editor" rows="5" placeholder="Ghi đáp án chốt sale ở đây..."><?php echo esc_textarea($a); ?></textarea>
        </div>
        <button type="button" class="remove-row" title="Xóa">✕ Xóa cụm này</button>
        <?php
    }
}
