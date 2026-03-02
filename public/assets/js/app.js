/* PostgreManager — Global JS */

// Auto-dismiss flash messages after 4s
document.addEventListener('DOMContentLoaded', function () {
    setTimeout(function () {
        document.querySelectorAll('.alert.alert-success, .alert.alert-danger').forEach(function (el) {
            if (el.querySelector('.close')) el.querySelector('.close').click();
        });
    }, 4000);
});
