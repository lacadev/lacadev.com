import Swal from 'sweetalert2';

document.addEventListener('DOMContentLoaded', () => {
    // 1. Logs & Alerts Functionality
    const logsContainer = document.querySelector('.laca-logs-container');
    if (logsContainer) {
        const postId = logsContainer.dataset.projectId;
        const nonceInput = document.getElementById('laca_pm_nonce');
        const nonce = nonceInput ? nonceInput.value : (typeof ajaxurl_params !== 'undefined' ? ajaxurl_params.nonce : '');
        const ajaxurl = window.ajaxurl;

        function toastSuccess(message) {
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: 'success',
                    title: message || 'Thành công',
                    showConfirmButton: false,
                    timer: 1600,
                    timerProgressBar: true,
                });
                return;
            }

            alert(message || 'Thành công');
        }

        function toastError(message) {
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: 'error',
                    title: message || 'Có lỗi xảy ra',
                    showConfirmButton: false,
                    timer: 2200,
                    timerProgressBar: true,
                });
                return;
            }

            alert(message || 'Có lỗi xảy ra');
        }

        function confirmAction(message) {
            if (typeof Swal !== 'undefined') {
                return Swal.fire({
                    title: 'Xác nhận',
                    text: message || 'Bạn chắc chắn muốn thực hiện thao tác này?',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Đồng ý',
                    cancelButtonText: 'Hủy',
                    reverseButtons: true,
                }).then((r) => Boolean(r.isConfirmed));
            }

            return Promise.resolve(confirm(message || 'Bạn chắc chắn muốn thực hiện thao tác này?'));
        }

        function ajaxRequest(action, data, onSuccess, onError) {
            data.action = action;
            data.nonce = nonce;
            data.project_id = postId;

            jQuery.post(ajaxurl, data, function(res) {
                if (res.success) {
                    if (typeof onSuccess === 'function') onSuccess(res);
                } else {
                    if (typeof onError === 'function') onError(res);
                    toastError((res && res.data && res.data.message) ? res.data.message : 'Có lỗi xảy ra');
                }
            }).fail(function() {
                if (typeof onError === 'function') onError();
                toastError('Lỗi kết nối máy chủ');
            });
        }

        // Giải quyết Alert
        jQuery('.laca-resolve-btn').on('click', function(e) {
            e.preventDefault();
            const $btn = jQuery(this);
            const alertId = $btn.data('id');

            confirmAction('Đánh dấu cảnh báo này là đã xử lý?').then((ok) => {
                if (!ok) return;

                $btn.addClass('disabled').css({ pointerEvents: 'none', opacity: 0.6 });
                ajaxRequest('laca_resolve_alert', { alert_id: alertId }, function(res){
                    toastSuccess((res && res.data && res.data.message) ? res.data.message : 'Đã đánh dấu xử lý');
                    const $item = jQuery('#alert-' + alertId);
                    if ($item.length) $item.slideUp(160, () => $item.remove());
                }, function(){
                    $btn.removeClass('disabled').css({ pointerEvents: '', opacity: '' });
                });
            });
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
            }, function(res){
                toastSuccess((res && res.data && res.data.message) ? res.data.message : 'Đã gửi cảnh báo');
                btn.prop('disabled', false).text('Gửi cảnh báo');
                jQuery('#new_alert_msg').val('');
                // Hiện tại API không trả về ID/alert HTML → reload để đồng bộ danh sách.
                location.reload();
            }, function(){
                btn.prop('disabled', false).text('Gửi cảnh báo');
            });
        });

        // ---- TASK CHECKLIST ----

        function updateProgress(tasks) {
            const total = tasks.length;
            const done  = tasks.filter(t => t.done).length;
            const pct   = total > 0 ? Math.round(done / total * 100) : 0;
            const label = document.getElementById('task_progress_label');
            const bar   = document.getElementById('task_progress_bar');
            const pctEl = document.getElementById('task_progress_pct');
            if (label) label.textContent = `${done}/${total} task hoàn thành`;
            if (pctEl)  pctEl.textContent  = `${pct}%`;
            if (bar)    bar.style.width     = `${pct}%`;
        }

        const categoryIcon = {
            bug:     '🐛',
            page:    '🖼️',
            content: '📝',
            seo:     '🔍',
            feature: '⭐',
            other:   '📌',
        };

        function buildTaskRow(task) {
            const cat      = task.category || (task.source === 'page' ? 'page' : 'other');
            const icon     = categoryIcon[cat] || '📌';
            const demoLink = task.demo_url ? `<a href="${task.demo_url}" target="_blank" style="font-size:11px;color:#0073aa;margin-left:6px;" title="Mẫu giao diện">↗ mẫu</a>` : '';
            const row = document.createElement('div');
            row.className = `laca-task-item ${task.done ? 'task-done' : ''}`;
            row.dataset.id = task.id;
            row.innerHTML = `
                <input type="checkbox" class="task-checkbox" data-id="${task.id}" ${task.done ? 'checked' : ''}>
                <div style="flex:1;min-width:0;">
                    <span class="task-name">${icon} ${task.name}</span>${demoLink}
                </div>
                <a class="task-delete-btn" data-id="${task.id}" title="Xoá task">✕</a>
            `;
            return row;
        }

        // Helper: rebuild logs list từ response
        function renderLogs(logs) {
            const $list = jQuery('.laca-pm-col .laca-pm-list').last();
            if (!$list.length || !logs) return;
            if (!logs.length) {
                $list.html('<p style="color:#888;">Chưa có nhật ký nào.</p>');
                return;
            }
            $list.empty();
            logs.forEach(l => {
                const typeLabels = {
                    note: '📝 Ghi chú', task_done: '✅ Hoàn thành task',
                    bug_fix: '🐛 Sửa lỗi', client_request: '👤 Yêu cầu',
                    deployment: '🚀 Deploy', theme_switch: '🎨 Thiết kế',
                };
                const label = typeLabels[l.log_type] || l.log_type;
                const dateStr = new Date(l.log_date).toLocaleDateString('vi-VN', {day:'2-digit',month:'2-digit',year:'2-digit'});
                $list.append(`
                    <div class="laca-pm-item" id="log-${l.id}">
                        <div class="laca-pm-meta">
                            <span style="font-weight:600;color:#0073aa;">${label}${l.is_auto ? ' <span style="color:#e67e22;font-size:10px;">(Auto)</span>' : ''}</span>
                            <span>${dateStr} bởi ${l.log_by}</span>
                        </div>
                        <div style="margin:6px 0;">${l.log_content.replace(/\n/g, '<br>')}</div>
                    </div>
                `);
            });
        }

        // Delegate: Toggle task
        jQuery(document).on('change', '.task-checkbox', function() {
            const taskId   = jQuery(this).data('id');
            const $cb      = jQuery(this);
            $cb.prop('disabled', true);
            ajaxRequest('laca_toggle_task', { task_id: taskId }, function(res) {
                $cb.prop('disabled', false);
                updateProgress(res.data.tasks);
                // Update visual state
                const $row = $cb.closest('.laca-task-item');
                const isDone = res.data.tasks.find(t => t.id === taskId)?.done;
                $row.toggleClass('task-done', isDone);
                $row.find('.task-name').css({ textDecoration: isDone ? 'line-through' : '', color: isDone ? '#999' : '' });
                // Auto-refresh logs nếu server trả về (khi vừa done)
                if (res.data.logs) renderLogs(res.data.logs);
                toastSuccess(isDone ? '✅ Đánh dấu hoàn thành' : '↩ Đã mở lại task');
            }, function() {
                $cb.prop('disabled', false);
                $cb.prop('checked', !$cb.prop('checked')); // revert
            });
        });

        // Delegate: Delete task
        jQuery(document).on('click', '.task-delete-btn', function(e) {
            e.preventDefault();
            const taskId = jQuery(this).data('id');
            confirmAction('Xoá task này?').then((ok) => {
                if (!ok) return;
                ajaxRequest('laca_delete_task', { task_id: taskId }, function(res) {
                    jQuery(`.laca-task-item[data-id="${taskId}"]`).slideUp(160, function() { jQuery(this).remove(); });
                    updateProgress(res.data.tasks);
                    toastSuccess('Đã xoá task');
                });
            });
        });

        // Add task manually
        jQuery('#btn_add_task').on('click', function(e) {
            e.preventDefault();
            const name     = jQuery('#new_task_name').val().trim();
            const category = jQuery('#new_task_category').val() || 'other';
            if (!name) { toastError('Vui lòng nhập tên task'); return; }
            const $btn = jQuery(this);
            $btn.prop('disabled', true).text('Đang thêm...');
            ajaxRequest('laca_add_task', { task_name: name, task_category: category }, function(res) {
                $btn.prop('disabled', false).text('+ Thêm');
                jQuery('#new_task_name').val('');
                const $container = jQuery('#task_list_container');
                // Remove empty placeholder
                $container.find('p').remove();
                const row = buildTaskRow(res.data.task);
                $container.append(row);
                toastSuccess('Đã thêm task');
            }, function() {
                $btn.prop('disabled', false).text('+ Thêm');
            });
        });

        // Sync pages from design_pages
        jQuery('#btn_sync_pages').on('click', function(e) {
            e.preventDefault();
            const $btn = jQuery(this);
            $btn.prop('disabled', true).text('Đang sync...');
            ajaxRequest('laca_sync_pages', {}, function(res) {
                $btn.prop('disabled', false).text('🔄 Sync trang');
                toastSuccess(res.data.message || 'Đã sync xong');
                // Rebuild task list
                const $container = jQuery('#task_list_container');
                $container.empty();
                if (res.data.tasks && res.data.tasks.length) {
                    res.data.tasks.forEach(t => $container.append(buildTaskRow(t)));
                } else {
                    $container.html('<p style="color:#888;font-size:13px;">Chưa có task nào.</p>');
                }
                updateProgress(res.data.tasks || []);
            }, function() {
                $btn.prop('disabled', false).text('🔄 Sync trang');
            });
        });

        // Enter key in task input
        jQuery('#new_task_name').on('keydown', function(e) {
            if (e.key === 'Enter') { e.preventDefault(); jQuery('#btn_add_task').trigger('click'); }
        });

        // ---- END TASK CHECKLIST ----

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
                    toastError((res && res.data && res.data.message) ? res.data.message : 'Lỗi');
                }
                $btn.text(originalText).prop('disabled', false);
            }).fail(function() {
                toastError('Lỗi kết nối máy chủ');
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
                // Format theo kiểu VN (dấu chấm), tránh lệch với PHP number_format.
                val = parseInt(val, 10).toLocaleString('vi-VN');
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

        // Payment Status đã được xử lý bằng script footer trong PHP (MutationObserver + delegation).
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

    // Tránh polling liên tục: chạy 1 lần + dùng MutationObserver để bắt CF render thêm fields.
    setupCopyableFields();
    const copyObserverTarget = document.querySelector('#post-body') || document.body;
    let copyObserverTimer;
    const copyObserver = new MutationObserver(() => {
        clearTimeout(copyObserverTimer);
        copyObserverTimer = setTimeout(setupCopyableFields, 120);
    });
    // ---- REMOTE UPDATE ----
    jQuery('#btn_remote_update').on('click', function (e) {
        e.preventDefault();
        const $btn    = jQuery(this);
        const action  = jQuery('#remote_update_action').val();
        const slug    = jQuery('#remote_update_slug').val().trim();
        const $msg    = jQuery('#remote_update_msg');

        if ((action === 'update_plugin' || action === 'update_theme') && !slug) {
            $msg.show().html('<div class="notice notice-error inline" style="margin:0;padding:8px 12px;"><p>⚠️ Vui lòng nhập slug trước khi gửi lệnh.</p></div>');
            return;
        }

        $btn.prop('disabled', true).text('Đang gửi...');
        $msg.hide();

        jQuery.post(ajaxurl, {
            action:        'laca_remote_update',
            nonce:         nonce,
            project_id:    postId,
            update_action: action,
            update_slug:   slug,
        }, function (res) {
            $btn.prop('disabled', false).text('🚀 Gửi lệnh');
            if (res.success) {
                $msg.show().html('<div class="notice notice-success inline" style="margin:0;padding:8px 12px;"><p>✅ ' + (res.data.message || 'Thành công') + '</p></div>');
                toastSuccess(res.data.message || 'Cập nhật thành công');
            } else {
                $msg.show().html('<div class="notice notice-error inline" style="margin:0;padding:8px 12px;"><p>❌ ' + (res.data.message || 'Lỗi không xác định') + '</p></div>');
            }
        }).fail(function (xhr) {
            $btn.prop('disabled', false).text('🚀 Gửi lệnh');
            $msg.show().html('<div class="notice notice-error inline" style="margin:0;padding:8px 12px;"><p>❌ Lỗi kết nối AJAX (HTTP ' + xhr.status + ')</p></div>');
        });
    });

    // Hide slug input when update_core selected
    jQuery('#remote_update_action').on('change', function () {
        const $slug = jQuery('#remote_update_slug');
        if (jQuery(this).val() === 'update_core') {
            $slug.val('').prop('disabled', true).attr('placeholder', '(không cần slug với update_core)');
        } else {
            $slug.prop('disabled', false).attr('placeholder', 'slug (vd: woocommerce/woocommerce.php)');
        }
    });

    // ---- LOAD PENDING PLUGIN UPDATES ----
    jQuery('#btn_load_pending').on('click', function () {
        const $btn   = jQuery(this);
        const $list  = jQuery('#pending_plugins_list');
        const $empty = jQuery('#pending_plugins_empty');
        const $tbody = jQuery('#pending_plugins_tbody');

        $btn.prop('disabled', true).text('⏳ Đang tải...');

        jQuery.post(ajaxurl, {
            action:     'laca_get_pending_updates',
            nonce:      nonce,
            project_id: postId,
        }, function (res) {
            $btn.prop('disabled', false).text('🔄 Tải danh sách plugin chờ update');
            if (!res.success) return;

            const plugins = res.data.plugins || [];
            if (plugins.length === 0) {
                $list.hide();
                $empty.show();
                return;
            }

            $empty.hide();
            $tbody.empty();
            plugins.forEach(function (p) {
                const slug = p.slug || '';
                const row  = `<tr data-slug="${slug}" style="border-bottom:1px solid #eee;">
                    <td style="padding:7px 10px;">${p.name || slug}</td>
                    <td style="padding:7px 10px; text-align:center; color:#888; font-size:12px;">${p.current_version || '?'}</td>
                    <td style="padding:7px 10px; text-align:center; color:#0073aa; font-weight:600; font-size:12px;">${p.new_version || '?'}</td>
                    <td style="padding:7px 10px; text-align:center;">
                        <button type="button" class="button js-update-plugin" data-slug="${slug}" style="font-size:12px; padding:3px 8px;">
                            ⬆️ Cập nhật
                        </button>
                    </td>
                </tr>`;
                $tbody.append(row);
            });
            $list.show();
        }).fail(function () {
            $btn.prop('disabled', false).text('🔄 Tải danh sách plugin chờ update');
        });
    });

    // ---- UPDATE SINGLE PLUGIN từ danh sách pending ----
    jQuery(document).on('click', '.js-update-plugin', function () {
        const $btn  = jQuery(this);
        const slug  = $btn.data('slug');
        const $msg  = jQuery('#remote_update_msg');

        $btn.prop('disabled', true).text('⏳ Đang update...');
        $msg.hide();

        jQuery.post(ajaxurl, {
            action:        'laca_remote_update',
            nonce:         nonce,
            project_id:    postId,
            update_action: 'update_plugin',
            update_slug:   slug,
        }, function (res) {
            if (res.success) {
                $btn.closest('tr').css('opacity', '0.5');
                $btn.prop('disabled', true).text('✅ Đã cập nhật');
                $msg.show().html('<div class="notice notice-success inline" style="margin:0;padding:8px 12px;"><p>✅ ' + (res.data.message || 'Thành công') + '</p></div>');
                toastSuccess(res.data.message || 'Cập nhật plugin thành công');
            } else {
                $btn.prop('disabled', false).text('⬆️ Cập nhật');
                $msg.show().html('<div class="notice notice-error inline" style="margin:0;padding:8px 12px;"><p>❌ ' + (res.data.message || 'Lỗi không xác định') + '</p></div>');
            }
        }).fail(function (xhr) {
            $btn.prop('disabled', false).text('⬆️ Cập nhật');
            $msg.show().html('<div class="notice notice-error inline" style="margin:0;padding:8px 12px;"><p>❌ Lỗi kết nối AJAX (HTTP ' + xhr.status + ')</p></div>');
        });
    });

    copyObserver.observe(copyObserverTarget, { childList: true, subtree: true });
});
