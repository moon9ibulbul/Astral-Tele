document.addEventListener('DOMContentLoaded', () => {
    const tg = window.Telegram && window.Telegram.WebApp;
    if (tg) tg.expand();

    let currentTab = 'history';
    let currentPage = 1;

    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.addEventListener('click', (e) => {
            document.querySelectorAll('.tab-btn').forEach(b => {
                b.classList.remove('bg-blue-600', 'text-white');
                b.classList.add('bg-white', 'text-gray-700', 'border');
            });
            e.target.classList.remove('bg-white', 'text-gray-700', 'border');
            e.target.classList.add('bg-blue-600', 'text-white');

            currentTab = e.target.getAttribute('data-tab');
            currentPage = 1;
            loadData();
        });
    });

    function escapeHTML(str) {
        if (!str) return '';
        return str.toString().replace(/[&<>'"]/g, tag => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' }[tag] || tag));
    }

    function renderPagination(totalPages) {
        const paginationControls = document.getElementById('paginationControls');
        if (!paginationControls) return;

        if (totalPages <= 1) {
            paginationControls.innerHTML = '';
            return;
        }

        let html = '';
        if (currentPage > 1) {
            html += `<button onclick="changePage(${currentPage - 1})" class="px-3 py-1 border rounded bg-white">Prev</button>`;
        }
        html += `<span class="px-3 py-1 font-bold">${currentPage} / ${totalPages}</span>`;
        if (currentPage < totalPages) {
            html += `<button onclick="changePage(${currentPage + 1})" class="px-3 py-1 border rounded bg-white">Next</button>`;
        }
        paginationControls.innerHTML = html;
    }

    window.changePage = function(page) {
        currentPage = page;
        loadData();
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function loadData() {
        const list = document.getElementById('libraryList');
        const paginationControls = document.getElementById('paginationControls');
        list.innerHTML = '<p class="text-center text-gray-500 py-10">Loading...</p>';
        if (paginationControls) paginationControls.innerHTML = '';

        const headers = {};
        if (tg && tg.initData) {
            headers['Authorization'] = `Bearer ${tg.initData}`;
            headers['X-Telegram-Init-Data'] = tg.initData;
        }

        fetch(`/api/library.php?tab=${currentTab}&page=${currentPage}`, { headers })
            .then(res => res.json())
            .then(data => {
                if (data.error) {
                    list.innerHTML = `<p class="text-center text-red-500 py-10">${escapeHTML(data.error)}</p>`;
                    return;
                }

                const items = data.data || [];
                if (items.length === 0) {
                    list.innerHTML = '<p class="text-center text-gray-500 py-10">No items found.</p>';
                    return;
                }

                list.innerHTML = items.map(item => {
                    const extraInfo = currentTab === 'bookmarks' ? '' : `<p class="text-xs text-gray-500 mt-1">Ch. ${item.chapter_number} ${item.chapter_title ? '- ' + escapeHTML(item.chapter_title) : ''}</p>`;

                    return `
                    <div class="bg-white rounded-lg p-3 shadow-sm flex gap-3 items-center cursor-pointer hover:bg-gray-50" onclick="window.location.href='detail.html?id=${item.comic_id}'">
                        <img src="${item.thumbnail_url || 'https://via.placeholder.com/150x200?text=No+Image'}" alt="${escapeHTML(item.comic_title)}" class="w-16 h-20 object-cover rounded">
                        <div class="flex-1">
                            <h3 class="font-bold text-sm line-clamp-2">${escapeHTML(item.comic_title)}</h3>
                            ${extraInfo}
                        </div>
                    </div>
                    `;
                }).join('');

                if (data.totalPages) {
                    renderPagination(data.totalPages);
                }
            })
            .catch(err => {
                list.innerHTML = '<p class="text-center text-red-500 py-10">Failed to load data.</p>';
            });
    }

    loadData();
});