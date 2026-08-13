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
        // Parse category from URL if present
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('category')) {
            currentCategory = urlParams.get('category');
        }

        loadCategories();
        loadComics();

        // Search Handlers
        document.getElementById('searchBtn').addEventListener('click', () => {
            currentSearch = document.getElementById('searchInput').value;
            currentPage = 1;
            loadComics();
        });
    }

    function escapeHTML(str) {
        if (!str) return '';
        return str.toString().replace(/[&<>'"]/g,
            tag => ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                "'": '&#39;',
                '"': '&quot;'
            }[tag] || tag)
        );
    }

    function loadCategories() {
        const nav = document.getElementById('categoryNav');
        if (!nav) return;

        fetch('/api/categories.php')
            .then(res => res.json())
            .then(data => {
                const categories = data.data || [];
                let html = `<button class="category-btn ${currentCategory === '' ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700'} px-4 py-1 rounded-full whitespace-nowrap text-sm font-medium hover:bg-gray-300" data-category="">All</button>`;

                categories.forEach(c => {
                    const isActive = currentCategory === c.name;
                    html += `<button class="category-btn ${isActive ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700'} px-4 py-1 rounded-full whitespace-nowrap text-sm font-medium hover:bg-gray-300" data-category="${escapeHTML(c.name)}">${escapeHTML(c.name)}</button>`;
                });

                nav.innerHTML = html;

                // Attach event listeners
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

                        // Update URL without reloading
                        const newUrl = new URL(window.location);
                        if (currentCategory) {
                            newUrl.searchParams.set('category', currentCategory);
                        } else {
                            newUrl.searchParams.delete('category');
                        }
                        window.history.pushState({}, '', newUrl);

                        loadComics();
                    });
                });
            })
            .catch(err => console.error("Error loading categories", err));
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

        comicGrid.innerHTML = comics.map(comic => {
            let statusColor = 'bg-green-500'; // Default Ongoing
            if (comic.status === 'Completed') statusColor = 'bg-blue-500';
            else if (comic.status === 'On Hold') statusColor = 'bg-yellow-500';
            else if (comic.status === 'Hiatus') statusColor = 'bg-black';
            else if (comic.status === 'Dropped') statusColor = 'bg-red-500';

            return `
            <div class="bg-white rounded-lg shadow overflow-hidden flex flex-col cursor-pointer relative" onclick="window.location.href='detail.html?id=${comic.id}'">
                <div class="absolute top-2 left-2 ${statusColor} text-white text-[10px] font-bold px-2 py-0.5 rounded shadow z-10">
                    ${escapeHTML(comic.status || 'Ongoing')}
                </div>
                <div class="aspect-[3/4] bg-gray-200 relative">
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
        `}).join('');
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