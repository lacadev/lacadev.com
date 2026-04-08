/* global laca2faConfig, QRCode, jQuery */
(function ($) {
    'use strict';

    const cfg = window.laca2faConfig;
    if (!cfg) return;

    // ── Setup flow ──────────────────────────────────────────────────────────────
    $('#laca-2fa-setup-btn').on('click', function () {
        const panel = $('#laca-2fa-setup-panel');
        panel.show();
        $(this).hide();

        $.post(cfg.ajaxUrl, { action: 'laca_2fa_get_secret', nonce: cfg.nonce }, function (res) {
            if (!res.success) { panel.html('<p style="color:red;">Lỗi: ' + res.data + '</p>'); return; }
            const secret  = res.data.secret;
            const otpauth = res.data.otpauth;

            $('#laca-secret-display').text(secret);
            $('#laca-2fa-secret-val').val(secret);

            try {
                new QRCode(document.getElementById('laca-qrcode'), {
                    text: otpauth, width: 144, height: 144, correctLevel: QRCode.CorrectLevel.M
                });
            } catch (e) {
                $('#laca-qrcode').html('<p style="font-size:11px;color:#666;">Không thể tạo QR.<br>Nhập secret thủ công.</p>');
            }
        });
    });

    // Xác nhận mã OTP sau khi quét QR
    $('#laca-2fa-confirm-btn').on('click', function () {
        const code = $('#laca-2fa-verify-code').val().trim();
        const $msg = $('#laca-2fa-verify-msg');
        if (!/^\d{6}$/.test(code)) { $msg.css('color', 'red').text('Nhập đúng 6 chữ số.'); return; }

        $(this).prop('disabled', true).text('Đang xác nhận...');
        $.post(cfg.ajaxUrl, { action: 'laca_2fa_verify_setup', nonce: cfg.nonce, code }, function (res) {
            $('#laca-2fa-confirm-btn').prop('disabled', false).text('Xác nhận');
            if (!res.success) { $msg.css('color', 'red').text('Lỗi: ' + res.data); return; }
            $msg.css('color', 'green').text('Kích hoạt thành công!');

            // Show backup codes
            const $wrap = $('#laca-2fa-backup-first');
            const $list = $('#laca-2fa-backup-codes').empty();
            (res.data.backup_codes || []).forEach(function (c) {
                $list.append('<code style="font-size:14px;background:#fff;padding:4px 8px;border:1px solid #fde047;border-radius:4px;">' + c + '</code>');
            });
            $wrap.show();

            // Reload after 8s
            setTimeout(function () { location.reload(); }, 8000);
        });
    });

    // ── Active state ─────────────────────────────────────────────────────────────
    $('#laca-2fa-disable-btn').on('click', function () {
        if (!confirm('Tắt 2FA sẽ giảm bảo mật tài khoản. Bạn chắc chắn?')) return;
        $(this).prop('disabled', true).text('Đang tắt...');
        $.post(cfg.ajaxUrl, { action: 'laca_2fa_disable', nonce: cfg.nonce }, function (res) {
            if (!res.success) { alert('Lỗi: ' + res.data); $('#laca-2fa-disable-btn').prop('disabled', false).text('Tắt 2FA'); return; }
            location.reload();
        });
    });

    $('#laca-2fa-regen-backup').on('click', function () {
        if (!confirm('Tạo mã dự phòng mới sẽ xoá mã cũ. Tiếp tục?')) return;
        $(this).prop('disabled', true).text('Đang tạo...');
        $.post(cfg.ajaxUrl, { action: 'laca_2fa_regen_backup', nonce: cfg.nonce }, function (res) {
            $('#laca-2fa-regen-backup').prop('disabled', false).text('Tạo lại mã dự phòng');
            if (!res.success) { alert('Lỗi: ' + res.data); return; }
            const $list = $('#laca-2fa-codes-list').empty();
            (res.data.backup_codes || []).forEach(function (c) {
                $list.append('<code style="font-size:14px;background:#fff;padding:4px 8px;border:1px solid #fde047;border-radius:4px;">' + c + '</code>');
            });
            $('#laca-2fa-backup-display').show();
        });
    });
}(jQuery));
