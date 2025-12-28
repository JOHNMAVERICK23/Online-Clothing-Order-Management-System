document.addEventListener('DOMContentLoaded', () => {
    loadAccountStats();
});

function loadAccountStats() {
    showLoading();

    fetch('../PROCESS/getDashboardData.php')
        .then(res => res.json())
        .then(data => {
            if (!data.success) return;

            animateCounter('totalUsers', data.totalUsers);
            animateCounter('adminCount', data.adminCount);
            animateCounter('staffCount', data.staffCount);
            animateCounter('activeCount', data.activeCount);
        })
        .catch(() => {
            console.error('Failed to load account stats');
        });
}

function showLoading() {
    ['totalUsers', 'adminCount', 'staffCount', 'activeCount'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
    });
}

function animateCounter(id, value) {
    const el = document.getElementById(id);
    if (!el) return;

    let start = 0;
    const duration = 800;
    const step = 20;
    const increment = value / (duration / step);

    const timer = setInterval(() => {
        start += increment;
        if (start >= value) {
            start = value;
            clearInterval(timer);
        }
        el.textContent = Math.round(start);
    }, step);
}
