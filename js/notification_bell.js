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

    // Fetch profile for admin role check
    function fetchProfileForAdmin() {
        const navMenu = document.querySelector('nav.fixed.bottom-4');
        if (!navMenu) return; // If we are not on a page with floating menu

        fetch('/api/profile.php', { headers })
            .then(res => res.json())
            .then(data => {
                if (data.role === 'admin') {
                    // Inject Admin icon to floating menu
                    const isReader = window.location.pathname.includes('reader.html');
                    const textClass = isReader ? 'text-gray-400 hover:text-white' : 'text-gray-500 hover:text-blue-600';

                    const adminHtml = `
                        <a href="admin.php" class="flex flex-col items-center ${textClass}">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                            <span class="text-[10px] mt-1 font-medium">Admin</span>
                        </a>
                    `;
                    navMenu.insertAdjacentHTML('beforeend', adminHtml);
                }
            })
            .catch(err => console.error("Error fetching profile", err));
    }

    fetchProfileForAdmin();
});
