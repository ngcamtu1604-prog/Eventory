
// =====================================================
// EVENTORY - JAVASCRIPT
// 1. Xác nhận trước khi xóa dữ liệu
// 2. Kiểm tra dữ liệu biểu mẫu trước khi gửi
// 3. Kiểm tra các trường bắt buộc
// 4. Kiểm tra định dạng email và giới hạn dữ liệu
// =====================================================


// -----------------------------------------------------
// 1. XÁC NHẬN XÓA
// -----------------------------------------------------

function confirmDelete(message = 'Bạn có chắc chắn muốn xóa?') {
    return window.confirm(message);
}


// -----------------------------------------------------
// 2. KIỂM TRA DỮ LIỆU BIỂU MẪU
// -----------------------------------------------------

function validateForm(form) {

    if (!form || typeof form.checkValidity !== 'function') {
        return true;
    }

    const fields = form.querySelectorAll(
        'input, textarea, select'
    );

    fields.forEach((field) => {

        // Bỏ qua các trường không cần kiểm tra
        if (
            field.disabled ||
            field.type === 'hidden' ||
            field.type === 'file'
        ) {
            return;
        }

        // Kiểm tra trường bắt buộc
        if (
            field.required &&
            typeof field.value === 'string' &&
            field.value.trim() === ''
        ) {
            field.setCustomValidity(
                'Vui lòng nhập thông tin hợp lệ.'
            );
        } else {
            field.setCustomValidity('');
        }

    });


    // Kiểm tra tính hợp lệ của biểu mẫu
    if (!form.checkValidity()) {

        form.classList.add('was-validated');

        // Tìm trường dữ liệu bị lỗi đầu tiên
        const invalidField = form.querySelector(':invalid');

        if (invalidField) {
            invalidField.focus();
        }

        return false;
    }

    return true;
}


// -----------------------------------------------------
// 3. KIỂM TRA EMAIL
// -----------------------------------------------------

function validateEmail(email) {

    const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

    return emailPattern.test(email);
}


// -----------------------------------------------------
// 4. KIỂM TRA SỐ
// -----------------------------------------------------

function validateNumber(value, min = 0, max = Infinity) {

    const number = Number(value);

    return (
        Number.isFinite(number) &&
        number >= min &&
        number <= max
    );
}


// -----------------------------------------------------
// 5. KIỂM TRA ĐỘ DÀI DỮ LIỆU
// -----------------------------------------------------

function validateLength(value, min = 1, max = 255) {

    const text = String(value).trim();

    return (
        text.length >= min &&
        text.length <= max
    );
}


// -----------------------------------------------------
// 6. XỬ LÝ KHI TRANG WEB ĐƯỢC TẢI
// -----------------------------------------------------

document.addEventListener('DOMContentLoaded', () => {

    // Lấy các biểu mẫu cần kiểm tra
    const forms = document.querySelectorAll(
        'form[method="post"], form[method="POST"], form[data-validate]'
    );


    forms.forEach((form) => {

        // Kiểm tra dữ liệu khi gửi biểu mẫu
        form.addEventListener('submit', (event) => {

            if (!validateForm(form)) {
                event.preventDefault();
            }

        });


        // Xóa thông báo lỗi khi người dùng nhập email
        const emailFields = form.querySelectorAll(
            'input[type="email"]'
        );

        emailFields.forEach((field) => {

            field.addEventListener('input', () => {
                field.setCustomValidity('');
            });

        });


        // Xóa thông báo lỗi khi người dùng nhập lại dữ liệu
        const requiredFields = form.querySelectorAll(
            '[required]'
        );

        requiredFields.forEach((field) => {

            field.addEventListener('input', () => {
                field.setCustomValidity('');
            });

            field.addEventListener('change', () => {
                field.setCustomValidity('');
            });

        });

    });

});
