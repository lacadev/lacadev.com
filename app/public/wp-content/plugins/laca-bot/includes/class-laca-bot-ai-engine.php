<?php

if (!defined('ABSPATH')) {
    exit;
}

class Laca_Bot_AI_Engine {

    private $gemini_key;
    private $groq_key;
    private $deepseek_key;
    private $openai_key;
    private $anthropic_key;
    private $chat_context = 'admin';

    public function __construct() {
        $this->gemini_key = get_option('laca_bot_gemini_key');
        $this->groq_key = get_option('laca_bot_groq_key');
        $this->deepseek_key = get_option('laca_bot_deepseek_key');
        $this->openai_key = get_option('laca_bot_openai_key');
        $this->anthropic_key = get_option('laca_bot_anthropic_key');

        add_action('wp_ajax_laca_bot_admin_chat', [$this, 'handle_admin_chat']);
        add_action('wp_ajax_laca_bot_frontend_chat', [$this, 'handle_frontend_chat']);
        add_action('wp_ajax_nopriv_laca_bot_frontend_chat', [$this, 'handle_frontend_chat']);
        add_action('wp_ajax_laca_bot_load_history', [$this, 'handle_load_history']);
        add_action('wp_ajax_nopriv_laca_bot_load_history', [$this, 'handle_load_history']);
    }

    public function handle_load_history() {
        check_ajax_referer('laca_bot_nonce', 'nonce');
        $session_id = sanitize_text_field($_POST['session_id']);
        if (empty($session_id)) wp_send_json_error('Session ID missing');

        $history = Laca_Bot_DB::get_history($session_id, 20);
        wp_send_json_success($history);
    }

    public function handle_admin_chat() {
        check_ajax_referer('laca_bot_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }

        $this->chat_context = 'admin';
        $message = sanitize_text_field($_POST['message']);
        $session_id = sanitize_text_field($_POST['session_id'] ?? 'admin-default');
        $user_id = get_current_user_id();

        // Save User message
        Laca_Bot_DB::add_message($session_id, 'user', $message, 'admin', $user_id);
        
        $dna = Laca_Bot_Tools::get_site_dna();
        $system_prompt = "Bạn là trợ lý AI cao cấp dành cho Quản trị viên (Admin). Tên bạn là " . (get_option('laca_bot_name') ?: 'Laca Bot') . ".\n" .
                         "Website này chạy theme " . $dna['theme'] . " và thuộc loại " . $dna['type'] . ".\n" .
                         "NHIỆM VỤ: Hỗ trợ admin quản trị nội dung, tìm bài viết, hướng dẫn kỹ thuật, kiểm tra SEO và tối ưu hóa website.\n" .
                         "QUY TẮC CHUNG VỀ NGUỒN DỮ LIỆU: Chỉ được sử dụng thông tin xuất phát từ: nội dung bài viết lấy qua công cụ 'search_site_content', cấu trúc site lấy từ 'get_site_guide', dữ liệu 'get_site_dna', các options trong WordPress (bao gồm cấu hình Laca Bot) và lịch sử hội thoại. Nếu không tìm thấy thông tin trong các nguồn này thì PHẢI nói rõ là không có đủ dữ liệu để trả lời, không được tự bịa.\n" .
                         "QUY TẮC CỤ THỂ: \n" .
                         "1. Sử dụng công cụ 'search_site_content' để tìm nội dung khi Admin yêu cầu hoặc khi cần tham chiếu bài viết/trang cụ thể.\n" .
                         "2. Sử dụng 'get_site_guide' để nắm toàn bộ cấu trúc CPTs/Plugin của web và giải thích cho admin khi cần.\n" .
                         "3. Luôn đưa ra Edit link bài viết khi tìm thấy nội dung cho Admin.\n" .
                         "4. KHÔNG được bịa ra URL, slug, tên post type, tên plugin, cấu hình hay đoạn code nếu không có trong dữ liệu hoặc không hợp lý với WordPress. Nếu không chắc chắn, hãy nói rõ với Admin và đề xuất cách kiểm tra trong Dashboard.\n" .
                         "5. ĐA NGÔN NGỮ: Phản hồi theo ngôn ngữ Admin sử dụng.";

        $reply = $this->route_request($message, $system_prompt, $session_id, $user_id);
        
        // Save Assistant message
        Laca_Bot_DB::add_message($session_id, 'assistant', $reply, 'admin', $user_id);

        wp_send_json_success(['reply' => $reply]);
    }

    public function handle_frontend_chat() {
        check_ajax_referer('laca_bot_nonce', 'nonce');

        $this->chat_context = 'user';
        $message = sanitize_text_field($_POST['message']);
        $session_id = sanitize_text_field($_POST['session_id'] ?? 'frontend-default');

        // Save User message
        Laca_Bot_DB::add_message($session_id, 'user', $message, 'user', 0);
        
        $company_info = get_option('laca_bot_company_info') ?: 'Chúng tôi là cửa hàng trực tuyến bán các sản phẩm chất lượng cao.';
        $phone = get_option('laca_bot_contact_phone');
        $email = get_option('laca_bot_contact_email');
        
        $contact_str = "";
        if ($phone) {
            $contact_str .= "SĐT: $phone. ";
        }
        if ($email) {
            $contact_str .= "Email: $email. ";
        }

        $training_data = get_option('laca_bot_training_data');
        $training_str = "";
        if (is_array($training_data) && !empty($training_data)) {
            $training_str .= "\n\nDưới đây là bối cảnh thông tin / FAQs bạn PHẢI ưu tiên sử dụng để trả lời nếu khách hàng hỏi trúng từ khoá hoặc ý nghĩa tương tự:\n";
            foreach ($training_data as $data) {
                if (!empty($data['q']) || !empty($data['a'])) {
                    $training_str .= "- Tình huống/Hỏi: " . ($data['q'] ?? '') . "\n";
                    if (!empty($data['k'])) $training_str .= "  Từ khoá nhận diện: " . $data['k'] . "\n";
                    $training_str .= "  => CÂU TRẢ LỜI CỦA BẠN: " . ($data['a'] ?? '') . "\n\n";
                }
            }
        }

        $dna = Laca_Bot_Tools::get_site_dna();
        $site_nature = "Đây là website thuộc chủ đề: " . $dna['type'] . ". ";
        if (!empty($dna['strengths'])) {
            $site_nature .= "Thế mạnh: " . implode(', ', $dna['strengths']) . ". ";
        }

        $system_prompt = "Bạn là trợ lý AI chuyên nghiệp của website này. Tên bạn là " . (get_option('laca_bot_name') ?: 'Laca Bot') . ". \n" .
                         $site_nature . "\n" .
                         "QUY TẮC PHONG CÁCH & BÁN HÀNG BẮT BUỘC: \n" .
                         "1. Bạn là tư vấn viên con người, tư vấn CHUYÊN NGHIỆP, THÂN THIỆN, NĂNG ĐỘNG. Hãy dùng biểu tượng cảm xúc (emoji) hợp lý. \n" .
                         "2. Trả lời tự nhiên, linh hoạt. KHÔNG BAO GIỜ nói kiểu máy móc như 'Dưới đây là kết quả tìm kiếm cho...' hoặc 'Không tìm thấy kết quả...'. \n" .
                         "3. NGAY TỪ ĐẦU (hoặc khi khách hỏi website này làm gì/bán gì/cung cấp dịch vụ gì, sản phẩm nào giá rẻ, địa chỉ cửa hàng, thông tin thương hiệu), hãy GỌI CÔNG CỤ 'get_business_profile' MỘT LẦN để nắm rõ bức tranh tổng quan về mô hình kinh doanh, nhóm sản phẩm/dịch vụ chính, ví dụ sản phẩm (bao gồm giá nếu có) và thông tin liên hệ/địa chỉ cửa hàng. KHÔNG ĐƯỢC TRẢ LỜI CÁC CÂU HỎI NÀY DỰA TRÊN KIẾN THỨC CHUNG NẾU CHƯA GỌI TOOL. \n" .
                         "4. Nếu khách hỏi dịch vụ/bài viết cụ thể, GỌI CÔNG CỤ 'search_site_content' NGAY. Dựa vào nội dung (excerpt) nhận được, hãy XUYÊN SUỐT VÀ TÓM TẮT LẠI GIÁ TRỊ, ĐIỂM NỔI BẬT của dịch vụ để khách thấy hấp dẫn, rồi kết hợp khéo léo link vào câu nói. \n" .
                         "5. Nếu tìm không ra, hãy xin lỗi nhẹ nhàng, hỏi thêm nhu cầu hoặc chủ động gợi ý các dịch vụ phổ biến khác dựa trên dữ liệu bạn biết từ 'get_business_profile'. \n" .
                         "6. THÔNG TIN LIÊN HỆ: KHÔNG ĐƯỢC TỰ BỊA SỐ ĐIỆN THOẠI, EMAIL, LINK LIÊN HỆ NẾU KHÔNG ĐƯỢC CẤU HÌNH SẴN. Nếu không có dữ liệu liên hệ trong cấu hình, bạn phải nói rõ với khách là hiện tại chưa có thông tin liên hệ chính thức trong hệ thống. \n" .
                         "7. Nếu có thông tin liên hệ trong cấu hình, CHỈ ĐƯỢC DÙNG CHÍNH XÁC CÁC GIÁ TRỊ ĐÓ, KHÔNG ĐƯỢC THÊM THẮT HAY THAY ĐỔI. \n" .
                         "8. Cuối mỗi câu trả lời, thường xuyên đặt câu hỏi mở (Ví dụ: Bạn có muốn tôi tư vấn sâu hơn về gói này không? Bác cần hỗ trợ mảng nào cụ thể...) để GIỮ CHÂN KHÁCH HÀNG tiếp tục trò chuyện. \n" .
                         "9. TRẢ LỜI dựa trên sự thật, không bịa đặt. \n" .
                         "10. TRÁNH LẶP LẠI NGUYÊN VĂN CÙNG MỘT CÂU TRẢ LỜI NHIỀU LẦN TRONG CÙNG PHIÊN TRÒ CHUYỆN. Nếu đã từng xin lỗi vì thiếu dữ liệu cho một chủ đề mà khách vẫn hỏi lại, hãy: (a) giải thích rõ hơn lý do bạn không thể trả lời chính xác, (b) đề xuất cụ thể cách khách có thể mô tả chi tiết nhu cầu hoặc (c) gợi ý một hướng hỏi khác dựa trên những gì bạn biết, thay vì lặp lại y nguyên câu trước. \n" .
                         "11. ĐA NGÔN NGỮ: Tự động phản hồi bằng ngôn ngữ của khách. \n\n" .
                         "Thông tin liên hệ (dùng khi cần thiết, có thể rỗng nếu chưa cấu hình): ". $contact_str . "\n\n" .
                         "Nhiệm vụ cốt lõi: Nắm bất tâm lý khách, tư vấn nhiệt tình, tư vấn chốt đơn thay vì chỉ cung cấp link." . 
                         $training_str;

        $reply = $this->route_request($message, $system_prompt, $session_id, 0);
        
        // Save Assistant message
        Laca_Bot_DB::add_message($session_id, 'assistant', $reply, 'user', 0);

        wp_send_json_success(['reply' => $reply]);
    }

    private function get_tool_definitions() {
        $all_tools = [
            'search_site_content' => [
                'name' => 'search_site_content',
                'description' => 'Tìm kiếm bài viết, trang, dịch vụ, dự án trên website. Trả về tiêu đề, link và đoạn nội dung ngắn (excerpt) để bạn đọc và tư vấn.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'keyword' => [
                            'type' => 'string',
                            'description' => 'Từ khoá cần tìm kiếm'
                        ],
                        'post_type' => [
                            'type' => 'string',
                            'description' => 'Mã loại bài viết (post, page, service...). Mặc định search tất cả.',
                        ]
                    ],
                    'required' => ['keyword']
                ]
            ],
            'get_business_profile' => [
                'name' => 'get_business_profile',
                'description' => 'Phân tích cấu trúc dữ liệu để tóm tắt website này đang làm gì, đang bán gì hoặc cung cấp dịch vụ gì (dựa trên WooCommerce, trang services, danh mục bài viết...).',
                'parameters' => [ 'type' => 'object', 'properties' => (object)[] ]
            ],
            'get_site_guide' => [
                'name' => 'get_site_guide',
                'description' => 'Lấy cấu trúc tổng quan của website (CPTs, plugins...). CHỈ DÀNH CHO ADMIN.',
                'parameters' => [ 'type' => 'object', 'properties' => (object)[] ]
            ],
            'admin_find_seo_issues' => [
                'name' => 'admin_find_seo_issues',
                'description' => 'Tra cứu lỗi SEO. CHỈ DÀNH CHO ADMIN.',
                'parameters' => [ 'type' => 'object', 'properties' => (object)[] ]
            ]
        ];

        $allowed = ['search_site_content', 'get_business_profile'];
        if ($this->chat_context === 'admin') {
            $allowed = array_keys($all_tools);
        }

        $funcs = [];
        foreach ($allowed as $key) {
            $funcs[] = $all_tools[$key];
        }
        return $funcs;
    }

    private function get_anthropic_tools() {
        $funcs = $this->get_tool_definitions();
        $anthropic_tools = [];
        foreach ($funcs as $f) {
            $anthropic_tools[] = [
                'name' => $f['name'],
                'description' => $f['description'],
                'input_schema' => $f['parameters']
            ];
        }
        return $anthropic_tools;
    }

    private function route_request($message, $system_prompt, $session_id = '', $user_id = 0) {
        $providers = [
            'gemini' => [$this, 'call_gemini'],
            'groq' => [$this, 'call_groq'],
            'deepseek' => [$this, 'call_deepseek'],
            'openai' => [$this, 'call_openai'],
            'anthropic' => [$this, 'call_anthropic']
        ];

        $errors = [];
        
        // Get History
        $history = Laca_Bot_DB::get_history($session_id, 10);
        
        foreach ($providers as $name => $func) {
            try {
                // Bỏ qua hoàn toàn provider nếu chưa cấu hình API key
                if (
                    ($name === 'gemini' && empty($this->gemini_key)) ||
                    ($name === 'groq' && empty($this->groq_key)) ||
                    ($name === 'deepseek' && empty($this->deepseek_key)) ||
                    ($name === 'openai' && empty($this->openai_key)) ||
                    ($name === 'anthropic' && empty($this->anthropic_key))
                ) {
                    continue;
                }

                // Check quota limit
                $usage = Laca_Bot_Usage::get_today_usage();
                $current_total = ($usage[$name]['admin'] ?? 0) + ($usage[$name]['user'] ?? 0);
                $limit = (int) get_option("laca_bot_{$name}_limit", 50000);

                if ($current_total >= $limit) {
                    throw new Exception("Đã hết hạn mức sử dụng gói miễn phí trong ngày.");
                }

                $response = call_user_func($func, $message, $system_prompt, $history);
                if (is_array($response) && isset($response['text'])) {
                    // Log usage if returned as array
                    if (isset($response['usage'])) {
                        Laca_Bot_Usage::log($name, $response['usage'], $this->chat_context);
                    }
                    return $response['text']; 
                }
                if ($response && is_string($response)) {
                    return $response; 
                }
            } catch (Exception $e) {
                // Log and continue to next
                $errors[] = strtoupper($name) . ": " . $e->getMessage();
                error_log("LacaBot Fallback: $name failed -> " . $e->getMessage());
            }
        }

        return "Hệ thống quá tải. Chi tiết lỗi: " . implode(' | ', $errors);
    }

    private function format_history($history, $provider) {
        $formatted = [];
        foreach ($history as $msg) {
            $role = $msg['role'];
            $content = $msg['content'];

            if ($provider === 'gemini') {
                $formatted[] = [
                    'role' => ($role === 'assistant' ? 'model' : 'user'),
                    'parts' => [['text' => $content]]
                ];
            } elseif ($provider === 'anthropic') {
                $formatted[] = [
                    'role' => ($role === 'assistant' ? 'assistant' : 'user'),
                    'content' => $content
                ];
            } else {
                // OpenAI, Groq, DeepSeek
                $formatted[] = [
                    'role' => ($role === 'assistant' ? 'assistant' : 'user'),
                    'content' => $content
                ];
            }
        }
        return $formatted;
    }

    private function call_gemini($message, $system_prompt, $history = [], $depth = 0) {
        if ($depth > 3) throw new Exception("Max tool recursion depth reached");
        if (empty($this->gemini_key)) throw new Exception("API Key trống");

        // Sử dụng endpoint v1 ổn định với model gemini-1.5-flash
        // Tham khảo: https://ai.google.dev/gemini-api/docs/models/gemini#gemini-1.5-flash
        $url = 'https://generativelanguage.googleapis.com/v1/models/gemini-1.5-flash:generateContent?key=' . $this->gemini_key;

        // If history is already formatted (recursive call), use it. Otherwise format it.
        $contents = (isset($history[0]['parts'])) ? $history : $this->format_history($history, 'gemini');
        
        // Append user message if it's the first call or a direct followup
        if ($depth === 0) {
            $contents[] = [
                "role" => "user",
                "parts" => [["text" => $message]]
            ];
        }

        $body = [
            "system_instruction" => [
                "parts" => [["text" => $system_prompt]]
            ],
            "contents" => $contents,
            "tools" => [["function_declarations" => $this->get_tool_definitions()]],
            "generationConfig" => [
                "temperature" => 0.1,
                "maxOutputTokens" => 800,
            ]
        ];

        $response = wp_remote_post($url, [
            'headers' => ['Content-Type' => 'application/json'],
            'body'    => wp_json_encode($body),
            'timeout' => 20,
            'sslverify' => false
        ]);

        if (is_wp_error($response)) throw new Exception($response->get_error_message());

        $status = wp_remote_retrieve_response_code($response);
        $body_response = wp_remote_retrieve_body($response);
        $data = json_decode($body_response, true);

        if ($status !== 200) throw new Exception("Gemini Error: $status - $body_response");

        $parts = $data['candidates'][0]['content']['parts'] ?? [];
        
        foreach ($parts as $part) {
            // Kiểm tra xem Gemini có gọi Tool không
            if (isset($part['functionCall'])) {
                $fn_call = $part['functionCall'];
                $fn_name = $fn_call['name'];
                $fn_args = $fn_call['args'] ?? [];

                // Thực thi code WordPress
                $result = "No method found";
                if (method_exists('Laca_Bot_Tools', $fn_name)) {
                    $result = call_user_func(['Laca_Bot_Tools', $fn_name], $fn_args);
                }

                // Lưu history cho lần gọi tiếp theo
                $new_history = $contents;
                $new_history[] = [
                    "role" => "model",
                    "parts" => [$part]
                ];
                $new_history[] = [
                    "role" => "function",
                    "parts" => [
                        [
                            "functionResponse" => [
                                "name" => $fn_name,
                                "response" => ["name" => $fn_name, "content" => $result]
                            ]
                        ]
                    ]
                ];

                return $this->call_gemini("Hãy dựa trên các dữ liệu trên để trả lời người dùng.", $system_prompt, $new_history, $depth + 1);
            }

            if (isset($part['text'])) {
                $usage = $data['usageMetadata']['totalTokenCount'] ?? 0;
                return [
                    'text' => $part['text'],
                    'usage' => $usage
                ];
            }
        }

        throw new Exception("Unexpected Gemini response format");
    }

    private function call_groq($message, $system_prompt, $history = [], $depth = 0) {
        if ($depth > 3) throw new Exception("Max tool recursion depth reached");
        if (empty($this->groq_key)) throw new Exception("API Key trống");

        $url = 'https://api.groq.com/openai/v1/chat/completions';

        // Check if $history is already formatted OpenAI messages
        $messages = (isset($history[0]['role'])) ? $history : array_merge([["role" => "system", "content" => $system_prompt]], $this->format_history($history, 'groq'));
        
        if ($depth === 0) {
            $messages[] = ["role" => "user", "content" => $message];
        }

        $funcs = $this->get_tool_definitions();
        $openai_tools = [];
        foreach ($funcs as $f) {
            $openai_tools[] = [
                'type' => 'function',
                'function' => $f
            ];
        }

        $args = [
            'model' => 'llama-3.3-70b-versatile',
            'messages' => $messages,
            'temperature' => 0.1,
            'max_tokens' => 800,
            'tools' => $openai_tools,
            'tool_choice' => 'auto'
        ];

        $response = wp_remote_post($url, [
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $this->groq_key
            ],
            'body'    => wp_json_encode($args),
            'timeout' => 15,
            'sslverify' => false
        ]);

        if (is_wp_error($response)) throw new Exception($response->get_error_message());

        $status = wp_remote_retrieve_response_code($response);
        $body_response = wp_remote_retrieve_body($response);
        $data = json_decode($body_response, true);

        if ($status !== 200) throw new Exception("Groq Error: $status - $body_response");

        $choice = $data['choices'][0]['message'] ?? null;

        if ($choice && isset($choice['tool_calls'])) {
            $tool_calls = $choice['tool_calls'];
            $tool_outputs = [];
            foreach ($tool_calls as $tool_call) {
                $fn_name = $tool_call['function']['name'];
                $fn_args = json_decode($tool_call['function']['arguments'], true);

                $result = "No method found";
                if (method_exists('Laca_Bot_Tools', $fn_name)) {
                    $result = call_user_func(['Laca_Bot_Tools', $fn_name], $fn_args);
                }
                $tool_outputs[] = [
                    "tool_call_id" => $tool_call['id'],
                    "output" => $result
                ];
            }

            // Recursive call with tool results
            $new_messages = $messages;
            $new_messages[] = $choice; // Add the tool call message from the model
            foreach ($tool_outputs as $output) {
                $content_str = is_string($output['output']) ? $output['output'] : wp_json_encode($output['output']);
                $new_messages[] = [
                    "role" => "tool",
                    "tool_call_id" => $output['tool_call_id'],
                    "content" => $content_str ?: "{}"
                ];
            }

            // Call Groq again with the tool output
            return $this->call_groq("Hãy dựa trên các dữ liệu trên để trả lời người dùng.", $system_prompt, $new_messages, $depth + 1);

        } elseif (isset($choice['content'])) {
            $usage = $data['usage']['total_tokens'] ?? 0;
            return [
                'text' => $choice['content'],
                'usage' => $usage
            ];
        }

        throw new Exception("Unexpected Groq response format");
    }

    private function call_deepseek($message, $system_prompt, $history = [], $depth = 0) {
        if ($depth > 3) throw new Exception("Max tool recursion depth reached");
        if (empty($this->deepseek_key)) throw new Exception("API Key trống");

        $url = 'https://api.deepseek.com/chat/completions';

        // Check if already formatted
        $messages = (isset($history[0]['role'])) ? $history : array_merge([["role" => "system", "content" => $system_prompt]], $this->format_history($history, 'deepseek'));
        
        if ($depth === 0) {
            $messages[] = ["role" => "user", "content" => $message];
        }

        // DeepSeek dùng schema tools tương thích OpenAI => cần bao bọc với type:function
        $funcs = $this->get_tool_definitions();
        $openai_style_tools = [];
        foreach ($funcs as $f) {
            $openai_style_tools[] = [
                'type' => 'function',
                'function' => $f
            ];
        }

        $args = [
            "model" => "deepseek-chat",
            "messages" => $messages,
            "temperature" => 0.1,
            "max_tokens" => 800,
            'tools' => $openai_style_tools,
            'tool_choice' => 'auto'
        ];

        $response = wp_remote_post($url, [
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $this->deepseek_key
            ],
            'body'    => wp_json_encode($args),
            'timeout' => 15,
            'sslverify' => false
        ]);

        if (is_wp_error($response)) throw new Exception($response->get_error_message());

        $status = wp_remote_retrieve_response_code($response);
        $body_response = wp_remote_retrieve_body($response);
        $data = json_decode($body_response, true);

        if ($status !== 200) throw new Exception("DeepSeek Error: $status - $body_response");

        $choice = $data['choices'][0]['message'] ?? null;

        if ($choice && isset($choice['tool_calls'])) {
            $tool_calls = $choice['tool_calls'];
            $tool_outputs = [];
            foreach ($tool_calls as $tool_call) {
                $fn_name = $tool_call['function']['name'];
                $fn_args = json_decode($tool_call['function']['arguments'], true);

                $result = "No method found";
                if (method_exists('Laca_Bot_Tools', $fn_name)) {
                    $result = call_user_func(['Laca_Bot_Tools', $fn_name], $fn_args);
                }
                $tool_outputs[] = [
                    "tool_call_id" => $tool_call['id'],
                    "output" => $result
                ];
            }

            // Recursive call with tool results
            $new_messages = $messages;
            $new_messages[] = $choice; // Add the tool call message from the model
            foreach ($tool_outputs as $output) {
                $content_str = is_string($output['output']) ? $output['output'] : wp_json_encode($output['output']);
                $new_messages[] = [
                    "role" => "tool",
                    "tool_call_id" => $output['tool_call_id'],
                    "content" => $content_str ?: "{}"
                ];
            }

            // Call DeepSeek again with the tool output
            return $this->call_deepseek("Hãy dựa trên các dữ liệu trên để trả lời người dùng.", $system_prompt, $new_messages, $depth + 1);

        } elseif (isset($choice['content'])) {
            $usage = $data['usage']['total_tokens'] ?? 0;
            return [
                'text' => $choice['content'],
                'usage' => $usage
            ];
        }

        throw new Exception("Unexpected DeepSeek response format");
    }

    private function call_openai($message, $system_prompt, $history = [], $depth = 0) {
        if ($depth > 3) throw new Exception("Max tool recursion depth reached");
        if (empty($this->openai_key)) throw new Exception("API Key trống");

        $url = 'https://api.openai.com/v1/chat/completions';

        // Check if already formatted
        $messages = (isset($history[0]['role'])) ? $history : array_merge([["role" => "system", "content" => $system_prompt]], $this->format_history($history, 'openai'));
        
        if ($depth === 0) {
            $messages[] = ["role" => "user", "content" => $message];
        }

        $args = [
            'model' => 'gpt-4o-mini',
            'messages' => $messages,
            'temperature' => 0.1,
            'max_tokens' => 800,
            'tools' => $this->get_tool_definitions(),
            'tool_choice' => 'auto'
        ];

        $response = wp_remote_post($url, [
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $this->openai_key
            ],
            'body'    => wp_json_encode($args),
            'timeout' => 15,
            'sslverify' => false
        ]);

        if (is_wp_error($response)) throw new Exception($response->get_error_message());

        $status = wp_remote_retrieve_response_code($response);
        $body_response = wp_remote_retrieve_body($response);
        $data = json_decode($body_response, true);

        if ($status !== 200) throw new Exception("OpenAI Error: $status - $body_response");

        $choice = $data['choices'][0]['message'] ?? null;

        if ($choice && isset($choice['tool_calls'])) {
            $tool_calls = $choice['tool_calls'];
            $tool_outputs = [];
            foreach ($tool_calls as $tool_call) {
                $fn_name = $tool_call['function']['name'];
                $fn_args = json_decode($tool_call['function']['arguments'], true);

                $result = "No method found";
                if (method_exists('Laca_Bot_Tools', $fn_name)) {
                    $result = call_user_func(['Laca_Bot_Tools', $fn_name], $fn_args);
                }
                $tool_outputs[] = [
                    "tool_call_id" => $tool_call['id'],
                    "output" => $result
                ];
            }

            $new_messages = $messages;
            $new_messages[] = $choice;
            foreach ($tool_outputs as $output) {
                $content_str = is_string($output['output']) ? $output['output'] : wp_json_encode($output['output']);
                $new_messages[] = [
                    "role" => "tool",
                    "tool_call_id" => $output['tool_call_id'],
                    "content" => $content_str ?: "{}"
                ];
            }
            return $this->call_openai("Hãy dựa trên các dữ liệu trên để trả lời người dùng.", $system_prompt, $new_messages, $depth + 1);

        } elseif (isset($choice['content'])) {
            $usage = $data['usage']['total_tokens'] ?? 0;
            return [
                'text' => $choice['content'],
                'usage' => $usage
            ];
        }
        throw new Exception("OpenAI unexpected response: " . $body_response);
    }

    private function call_anthropic($message, $system_prompt, $history = [], $depth = 0) {
        if ($depth > 3) throw new Exception("Max tool recursion depth reached");
        if (empty($this->anthropic_key)) throw new Exception("API Key trống");

        $url = 'https://api.anthropic.com/v1/messages';
        
        // Anthropic system is a top-level field, not in messages.
        // Format messages if not already formatted.
        $messages = (isset($history[0]['role'])) ? $history : $this->format_history($history, 'anthropic');
        
        if ($depth === 0) {
            $messages[] = ["role" => "user", "content" => $message];
        }

        $args = [
            'model' => 'claude-3-5-haiku-20241022',
            'system' => $system_prompt,
            'messages' => $messages,
            'max_tokens' => 800,
            'temperature' => 0.1,
            'tools' => $this->get_anthropic_tools()
        ];

        $response = wp_remote_post($url, [
            'headers' => [
                'Content-Type' => 'application/json',
                'x-api-key'    => $this->anthropic_key,
                'anthropic-version' => '2023-06-01'
            ],
            'body'    => wp_json_encode($args),
            'timeout' => 20,
            'sslverify' => false
        ]);

        if (is_wp_error($response)) throw new Exception("Anthropic Network Error: " . $response->get_error_message());

        $status = wp_remote_retrieve_response_code($response);
        $body_response = wp_remote_retrieve_body($response);
        $data = json_decode($body_response, true);

        if ($status !== 200) throw new Exception("Anthropic Error: $status - $body_response");

        $content = $data['content'] ?? [];
        $text_output = "";
        $tool_calls = [];

        foreach ($content as $item) {
            if ($item['type'] === 'text') $text_output .= $item['text'];
            if ($item['type'] === 'tool_use') $tool_calls[] = $item;
        }

        if (!empty($tool_calls)) {
            $new_messages = $messages;
            $new_messages[] = ["role" => "assistant", "content" => $content];

            foreach ($tool_calls as $tool) {
                $fn_name = $tool['name'];
                $fn_args = $tool['input'];
                $result = "No method found";
                if (method_exists('Laca_Bot_Tools', $fn_name)) {
                    $result = call_user_func(['Laca_Bot_Tools', $fn_name], $fn_args);
                }
                $new_messages[] = [
                    "role" => "user",
                    "content" => [
                        [
                            "type" => "tool_result",
                            "tool_use_id" => $tool['id'],
                            "content" => is_string($result) ? $result : (wp_json_encode($result) ?: "{}")
                        ]
                    ]
                ];
            }
            return $this->call_anthropic("Hãy dựa trên các dữ liệu trên để trả lời người dùng.", $system_prompt, $new_messages, $depth + 1);
        }

        return [
            'text' => $text_output,
            'usage' => ($data['usage']['input_tokens'] ?? 0) + ($data['usage']['output_tokens'] ?? 0)
        ];
    }
}
