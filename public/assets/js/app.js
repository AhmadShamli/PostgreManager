/* PostgreManager — Global JS */

// Auto-dismiss flash messages after 4s
document.addEventListener('DOMContentLoaded', function () {
    setTimeout(function () {
        document.querySelectorAll('.alert.alert-success, .alert.alert-danger').forEach(function (el) {
            if (el.querySelector('.close')) el.querySelector('.close').click();
        });
    }, 4000);

    // Theme toggle button
    var toggleBtn = document.getElementById('theme-toggle');
    if (toggleBtn) {
        toggleBtn.addEventListener('click', function () {
            fetch('/theme/toggle', { method: 'POST' })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    var body = document.body;
                    var icon = toggleBtn.querySelector('i');
                    if (data.theme === 'light') {
                        body.classList.add('pm-light');
                        icon.classList.replace('fa-sun', 'fa-moon');
                    } else {
                        body.classList.remove('pm-light');
                        icon.classList.replace('fa-moon', 'fa-sun');
                    }
                });
        });
    }
});

