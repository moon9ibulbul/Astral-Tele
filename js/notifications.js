document.addEventListener('DOMContentLoaded', () => {
    const tg = window.Telegram && window.Telegram.WebApp;
    if (tg) tg.expand();

    function escapeHTML(str) {
        if (!str) return '';
        return str.toString().replace(/[&<>'"]/g, tag => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' }[tag] || tag));
    }

    const headers = {};
    if (tg && tg.initData) headers['Authorization'] = `Bearer ${tg.initData}`;

    const list = document.getElementById('notificationsList');

    function loadNotifications() {
        fetch('/api/notifications.php?action=list', { headers })
            .then(res => res.json())
            .then(data => {
                if (data.error) {
                    list.innerHTML = `<p class="text-center text-red-500 py-10">${escapeHTML(data.error)}</p>`;
                    return;
                }

                const notifs = data.data || [];
                if (notifs.length === 0) {
                    list.innerHTML = '<p class="text-center text-gray-500 py-10">No notifications found.</p>';
                    return;
                }

                list.innerHTML = notifs.map(n => {
                    const bgClass = n.is_read ? 'bg-white' : 'bg-blue-50 border-blue-200';
                    return `
                    <div class="${bgClass} border rounded-lg p-3 shadow-sm flex gap-3 cursor-pointer hover:bg-gray-100 transition" onclick="openNotification(${n.id}, ${n.comic_id})">
                        <div class="flex-1">
                            <p class="text-sm">
                                <span class="font-bold">${escapeHTML(n.username || 'Someone')}</span> replied to your comment:
                            </p>
                            <p class="text-sm text-gray-700 italic mt-1 line-clamp-2">"${escapeHTML(n.content)}"</p>
                            <p class="text-xs text-gray-500 mt-2">${new Date(n.created_at).toLocaleString()}</p>
                        </div>
                    </div>
                    `;
                }).join('');
            })
            .catch(err => {
                list.innerHTML = '<p class="text-center text-red-500 py-10">Failed to load notifications.</p>';
            });
    }

    window.openNotification = function(notifId, comicId) {
        const fd = new FormData();
        fd.append('action', 'read');
        fd.append('id', notifId);

        fetch('/api/notifications.php', { method: 'POST', headers, body: fd })
            .then(() => {
                window.location.href = `detail.html?id=${comicId}`;
            })
            .catch(() => {
                window.location.href = `detail.html?id=${comicId}`;
            });
    }

    loadNotifications();
});
