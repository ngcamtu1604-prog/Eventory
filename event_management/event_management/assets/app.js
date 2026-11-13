function confirmDelete(message = 'Bạn có chắc chắn muốn xóa?') {
    return confirm(message);
}
function validateForm(form) {
    if (!form.checkValidity()) {
        form.classList.add('was-validated');
        return false;
    }
    return true;
}
