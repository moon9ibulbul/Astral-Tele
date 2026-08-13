document.addEventListener('DOMContentLoaded', () => {
    // Initialize Telegram WebApp
    const tg = window.Telegram.WebApp;
    tg.expand();
    const initData = tg.initData || '';

    // Global state
    let currentPage = 1;
    let currentCategory = '';
    let currentSearch = '';

    const comicGrid = document.getElementById('comicGrid');
    const paginationControls = document.getElementById('paginationControls');
    
    // Check if we are on index page
    if (comicGrid) {
        loadComics();

        // Search Handlers
        document.getElementById('searchBtn').addEventListener('click', () => {
            currentSearch = document.getElementById('searchInput').value;
            currentPage = 1;
            loadComics();
        });

        // Category Handlers
        document.querySelectorAll('.category-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                // Update active state
                document.querySelectorAll('.category-btn').forEach(b => {
                    b.classList.remove('bg-blue-600', 'text-white');
                    b.classList.add('bg-gray-200', 'text-gray-700');
                });
                e.target.classList.remove('bg-gray-200', 'text-gray-700');
                e.target.classList.add('bg-blue-600', 'text-white');

                currentCategory = e.target.getAttribute('data-category');
                currentPage = 1;
                loadComics();
            });
        });
    }

    function loadComics() {
        comicGrid.innerHTML = '<p class="col-span-full text-center text-gray-500 py-10">Loading...</p>';
        
        let url = `/api/comics.php?page=${currentPage}&limit=10`;
        if (currentSearch) url += `&search=${encodeURIComponent(currentSearch)}`;
        if (currentCategory) url += `&category=${encodeURIComponent(currentCategory)}`;

        fetch(url)
            .then(res => res.json())
            .then(data => {
                renderComics(data.data);
                renderPagination(data.totalPages);
            })
            .catch(err => {
                console.error(err);
                comicGrid.innerHTML = '<p class="col-span-full text-center text-red-500 py-10">Failed to load comics.</p>';
            });
    }

    function renderComics(comics) {
        if (!comics || comics.length === 0) {
            comicGrid.innerHTML = '<p class="col-span-full text-center text-gray-500 py-10">No comics found.</p>';
            return;
        }

        comicGrid.innerHTML = comics.map(comic => `
            <div class="bg-white rounded-lg shadow overflow-hidden flex flex-col cursor-pointer" onclick="window.location.href='detail.html?id=${comic.id}'">
                <div class="aspect-[3/4] bg-gray-200">
                    <img src="${comic.thumbnail_url || 'https://via.placeholder.com/300x400?text=No+Image'}" alt="${comic.title}" class="w-full h-full object-cover">
                </div>
                <div class="p-3 flex-1 flex flex-col">
                    <h3 class="font-bold text-sm line-clamp-2 mb-2">${comic.title}</h3>
                    <div class="mt-auto space-y-1">
                        ${comic.latest_chapters && comic.latest_chapters.length > 0 ? comic.latest_chapters.map(ch => `
                            <div class="text-xs text-blue-600 truncate border border-blue-100 rounded px-2 py-1 bg-blue-50">Ch. ${ch.chapter_number}</div>
                        `).join('') : '<div class="text-xs text-gray-400">No chapters</div>'}
                    </div>
                </div>
            </div>
        `).join('');
    }

    function renderPagination(totalPages) {
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

    // Expose to window for inline onclick
    window.changePage = function(page) {
        currentPage = page;
        loadComics();
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
});