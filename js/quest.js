document.addEventListener('DOMContentLoaded', () => {
    const tg = window.Telegram && window.Telegram.WebApp;
    if (tg) tg.expand();

    const headers = {};
    if (tg && tg.initData) {
        headers['Authorization'] = `Bearer ${tg.initData}`;
        headers['X-Telegram-Init-Data'] = tg.initData;
    }

    function getRankHtml(role, pts) {
        pts = parseInt(pts) || 0;
        if (role === 'admin') {
            return '<span class="px-2 py-1 rounded text-sm font-bold bg-yellow-100 text-yellow-700 border border-yellow-200">Admin</span>';
        }
        if (pts > 2500) {
            return '<span class="px-2 py-1 rounded text-sm font-bold bg-gradient-to-r from-red-500 via-yellow-500 to-blue-500 text-white shadow-sm">Aeon</span>';
        } else if (pts >= 1000) {
            return '<span class="px-2 py-1 rounded text-sm font-bold bg-red-100 text-red-700 border border-red-200">Emanator</span>';
        } else if (pts >= 501) {
            return '<span class="px-2 py-1 rounded text-sm font-bold bg-yellow-50 text-yellow-600 border border-yellow-200">The Nameless</span>';
        } else if (pts >= 251) {
            return '<span class="px-2 py-1 rounded text-sm font-bold bg-blue-100 text-blue-700 border border-blue-200">Passenger</span>';
        } else if (pts >= 101) {
            return '<span class="px-2 py-1 rounded text-sm font-bold bg-green-100 text-green-700 border border-green-200">Pathstrider</span>';
        } else {
            return '<span class="px-2 py-1 rounded text-sm font-bold bg-gray-100 text-gray-800 border border-gray-300">NPC</span>';
        }
    }

    function loadQuests() {
        let userRole = 'user'; // We need to fetch role, let's just fetch profile first then quests

        fetch('/api/profile.php', { headers })
            .then(res => res.json())
            .then(profile => {
                if (profile.role) userRole = profile.role;

                fetch('/api/quests.php', { headers })
                    .then(res => res.json())
                    .then(data => {
                        if (data.error) {
                            document.getElementById('questsList').innerHTML = `<p class="text-red-500">${data.error}</p>`;
                            return;
                        }

                        document.getElementById('questPts').innerText = data.pts;
                        document.getElementById('questRank').innerHTML = getRankHtml(userRole, data.pts);

                        const list = document.getElementById('questsList');
                        if (!data.quests || data.quests.length === 0) {
                            list.innerHTML = '<p class="text-sm text-gray-500">No active quests found.</p>';
                            return;
                        }

                        list.innerHTML = data.quests.map(q => {
                            let actionHtml = '';
                            if (q.completed) {
                                actionHtml = `<span class="bg-gray-100 text-gray-500 px-3 py-1.5 rounded-lg text-sm font-bold">Selesai</span>`;
                            } else if (q.type === 'login') {
                                actionHtml = `<button onclick="doLoginQuest()" class="bg-blue-600 text-white px-3 py-1.5 rounded-lg text-sm font-bold hover:bg-blue-700">Login</button>`;
                            } else {
                                actionHtml = `<button onclick="window.location.href='index.html'" class="bg-white border-2 border-blue-600 text-blue-600 px-3 py-1.5 rounded-lg text-sm font-bold hover:bg-blue-50">Kerjakan</button>`;
                            }

                            return `
                                <div class="bg-white border rounded-lg p-3 flex justify-between items-center shadow-sm">
                                    <div>
                                        <h3 class="font-bold text-sm">${q.title}</h3>
                                        <p class="text-xs text-gray-500 capitalize">${q.period} &bull; +${q.reward_pts} PTS</p>
                                    </div>
                                    <div>
                                        ${actionHtml}
                                    </div>
                                </div>
                            `;
                        }).join('');
                    });
            });
    }

    window.doLoginQuest = function() {
        const fd = new FormData();
        fd.append('action', 'login');
        fetch('/api/quests.php', { method: 'POST', headers, body: fd })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    loadQuests();
                } else {
                    alert('Gagal menyelesaikan quest. Pastikan Anda belum menyelesaikannya hari ini.');
                }
            });
    };

    loadQuests();
});
