import Swal from 'sweetalert2';

document.addEventListener('DOMContentLoaded', () => {
    // 1. Logs & Alerts Functionality
    const logsContainer = document.querySelector('.laca-logs-container');
    if (logsContainer) {
        const postId = logsContainer.dataset.projectId;
        const nonceInput = document.getElementById('laca_pm_nonce');
        const nonce = nonceInput ? nonceInput.value : (typeof ajaxurl_params !== 'undefined' ? ajaxurl_params.nonce : '');
        const ajaxurl = window.ajaxurl;

        function ajaxRequest(action, data, onSuccess) {
            data.action = action;
            data.nonce = nonce;
            data.project_id = postId;
            
            jQuery.post(ajaxurl, data, function(res) {
                if (res.success) {
                    onSuccess();
                    location.reload(); // Reload for simplicity to show new data
                } else {
                    alert(res.data.message || 'Có lỗi xảy ra');
                }
            }).fail(function() {
                alert('Lỗi kết nối máy chủ');
            });
        }

        // Giải quyết Alert
        jQuery('.laca-resolve-btn').on('click', function(e) {
            e.preventDefault();
            if(confirm('Xác nhận đã xử lý?')) {
                ajaxRequest('laca_resolve_alert', { alert_id: jQuery(this).data('id') }, function(){});
            }
        });

        // Xoá Log
        jQuery('.delete-log').on('click', function(e) {
            e.preventDefault();
            if(confirm('Xác nhận xoá log này?')) {
                ajaxRequest('laca_delete_log', { log_id: jQuery(this).data('id') }, function(){});
            }
        });

        // Thêm Log
        jQuery('#btn_add_log').on('click', function(e) {
            e.preventDefault();
            var btn = jQuery(this);
            btn.prop('disabled', true).text('Đang lưu...');
            ajaxRequest('laca_add_log', {
                log_type: jQuery('#new_log_type').val(),
                log_content: jQuery('#new_log_msg').val()
            }, function(){});
        });

        // Thêm Alert
        jQuery('#btn_add_alert').on('click', function(e) {
            e.preventDefault();
            var btn = jQuery(this);
            btn.prop('disabled', true).text('Đang gửi...');
            ajaxRequest('laca_add_alert', {
                alert_type: jQuery('#new_alert_type').val(),
                alert_level: jQuery('#new_alert_level').val(),
                alert_msg: jQuery('#new_alert_msg').val()
            }, function(){});
        });

        // Lấy mã Auto Tracker và Download
        jQuery('#btn_download_tracker, #btn_view_tracker_code').on('click', function(e) {
            e.preventDefault();
            let isDownload = jQuery(this).attr('id') === 'btn_download_tracker';
            let $btn = jQuery(this);
            
            let originalText = $btn.text();
            $btn.text('Đang lấy...').prop('disabled', true);
            
            jQuery.post(ajaxurl, {
                action: 'laca_get_tracker_code',
                nonce: nonce,
                project_id: postId
            }, function(res) {
                if (res.success) {
                    if (isDownload) {
                        let blob = new Blob([res.data.code], {type: "text/php;charset=utf-8"});
                        let url = window.URL.createObjectURL(blob);
                        let a = document.createElement("a");
                        a.style.display = "none";
                        a.href = url;
                        a.download = "laca-tracker.php";
                        document.body.appendChild(a);
                        a.click();
                        window.URL.revokeObjectURL(url);
                    } else {
                        if (typeof Swal !== 'undefined') {
                            Swal.fire({
                                title: 'Mã PHP Tracker',
                                html: `<textarea id="swal_tracker_code" readonly style="width:100%; height:250px; font-family:monospace; font-size:12px; background:#f4f4f4; padding:10px; border:1px solid #ddd;">${res.data.code}</textarea>
                                       <div style="margin-top:10px;text-align:right;">
                                           <button class="button button-primary" onclick="var copyText = document.getElementById('swal_tracker_code'); copyText.select(); navigator.clipboard.writeText(copyText.value); Swal.showValidationMessage('Đã copy!'); setTimeout(()=>Swal.resetValidationMessage(), 2000);">📋 Copy Code</button>
                                       </div>`,
                                width: '600px',
                                showConfirmButton: false,
                                showCloseButton: true
                            });
                        } else {
                            // Fallback nếu không có Swal
                            prompt("Copy đoạn mã bên dưới:", res.data.code);
                        }
                    }
                } else {
                    alert(res.data.message || 'Lỗi');
                }
                $btn.text(originalText).prop('disabled', false);
            }).fail(function() {
                alert('Lỗi kết nối máy chủ');
                $btn.text(originalText).prop('disabled', false);
            });
        });
    }

    // 2. Project Payment Auto Calculation
    const bodyIndex = document.body.classList.contains('post-type-project') || document.querySelector('.laca-price-build');
    if (bodyIndex) {
        // Tự động thêm dấu phẩy (separator) khi nhập vào input kiểu tiền tệ
        jQuery('body').on('input', 'input[data-type="currency"]', function() {
            let val = jQuery(this).val();
            val = val.replace(/[^0-9]/g, ''); // chỉ lấy số
            if (val !== '') {
                val = parseInt(val, 10).toLocaleString('en-US'); // format string có dấu phẩy
                jQuery(this).val(val);
            } else {
                jQuery(this).val('');
            }
        });
        
        // Format lại nội dung nếu có update react qua DOM NodeInserted (hoặc timeout)
        setTimeout(function() {
            jQuery('input[data-type="currency"]').each(function() {
                if (jQuery(this).val()) {
                    jQuery(this).trigger('input');
                }
            });
        }, 1000);

        // Tự động tính Payment Status trực tiếp trên giao diện
        function calcPaymentStatus() {
            let $buildInput = jQuery('.laca-price-build input[type="text"]');
            if ($buildInput.length === 0) return; // Chưa kịp render
            
            let valStr = $buildInput.val() || '';
            let buildPrice = parseInt(valStr.replace(/[^0-9]/g, ''), 10) || 0;

            let totalPaid = 0;
            jQuery('.laca-pay-amount input[type="text"]').each(function() {
                let amountStr = jQuery(this).val() || '0';
                totalPaid += parseInt(amountStr.replace(/[^0-9]/g, ''), 10) || 0;
            });

            let newStatus = 'pending';
            if (buildPrice > 0) {
                if (totalPaid <= 0) newStatus = 'pending';
                else if (totalPaid < buildPrice) newStatus = 'partial';
                else newStatus = 'paid';
            }

            let $select = jQuery('.laca-payment-status select');
            if ($select.length && $select.val() !== newStatus) {
                // Kích hoạt React change event
                let nativeInputValueSetter = Object.getOwnPropertyDescriptor(window.HTMLSelectElement.prototype, "value").set;
                nativeInputValueSetter.call($select[0], newStatus);
                $select[0].dispatchEvent(new Event('change', { bubbles: true }));
            }
        }

        // Lắng nghe thay đổi input và sau đó tính toán lại
        jQuery(document).on('input blur change', '.laca-price-build input, .laca-pay-amount input', function() {
            setTimeout(calcPaymentStatus, 100);
        });

        // Do Carbon Fields render DOM lazy, chạy interval cẩn thận
        setInterval(calcPaymentStatus, 1500);
    }

    // 3. Password Toggle & Copy
    function setupCopyableFields() {
        const fields = document.querySelectorAll('.laca-password-input:not(.processed), .laca-copyable-input:not(.processed)');
        fields.forEach(fieldWrapper => {
            fieldWrapper.classList.add('processed');
            
            const input = fieldWrapper.querySelector('input');
            if (!input) return;

            const wrapper = document.createElement('div');
            wrapper.className = 'laca-input-with-actions';
            input.parentNode.insertBefore(wrapper, input);
            wrapper.appendChild(input);

            const actions = document.createElement('div');
            actions.className = 'laca-input-actions';
            wrapper.appendChild(actions);

            // Toggle Password
            if (fieldWrapper.classList.contains('laca-password-input')) {
                const toggleBtn = document.createElement('button');
                toggleBtn.type = 'button';
                toggleBtn.className = 'laca-toggle-pwd-btn';
                toggleBtn.innerHTML = '👁️';
                toggleBtn.title = 'Hiện/Ẩn mật khẩu';
                toggleBtn.onclick = (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    input.type = input.type === 'password' ? 'text' : 'password';
                    toggleBtn.innerHTML = input.type === 'password' ? '👁️' : '🔒';
                };
                actions.appendChild(toggleBtn);
            }

            // Copy Button
            const copyBtn = document.createElement('button');
            copyBtn.type = 'button';
            copyBtn.className = 'laca-copy-btn';
            copyBtn.innerHTML = '📋';
            copyBtn.title = 'Copy';
            copyBtn.onclick = (e) => {
                e.preventDefault();
                e.stopPropagation();
                const val = input.value;
                if (!val) return;

                const doCopy = () => {
                    const originalText = copyBtn.innerHTML;
                    copyBtn.innerHTML = '✅';
                    setTimeout(() => copyBtn.innerHTML = originalText, 2000);
                };

                if (navigator.clipboard && window.isSecureContext) {
                    navigator.clipboard.writeText(val).then(doCopy);
                } else {
                    // Fallback
                    input.select();
                    document.execCommand('copy');
                    doCopy();
                }
            };
            actions.appendChild(copyBtn);
        });
    }

    setInterval(setupCopyableFields, 1000);
});
