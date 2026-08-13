// Notifications Bell Logic
document.addEventListener('DOMContentLoaded', () => {
    const tg = window.Telegram && window.Telegram.WebApp;
    const headers = {};
    if (tg && tg.initData) headers['Authorization'] = `Bearer ${tg.initData}`;

    function fetchNotificationCount() {
        const notifBadge = document.getElementById('notifBadge');
        if (!notifBadge) return;

        fetch('/api/notifications.php?action=count', { headers })
            .then(res => res.json())
            .then(data => {
                if (data.count && data.count > 0) {
                    notifBadge.innerText = data.count;
                    notifBadge.classList.remove('hidden');
                } else {
                    notifBadge.classList.add('hidden');
                }
            })
            .catch(err => console.error("Error fetching notification count", err));
    }

    // Call it initially
    fetchNotificationCount();

    // Also attach to window so other scripts could trigger it if needed
    window.refreshNotificationCount = fetchNotificationCount;
});
