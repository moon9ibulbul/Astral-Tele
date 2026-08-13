document.addEventListener('DOMContentLoaded', () => {
    const tg = window.Telegram.WebApp;
    tg.expand();
    const initData = tg.initData;

    // Simple mock auth bypass for local testing if not in Telegram (for demonstration purposes)
    const mockAuth = true; 
    let headers = {};

    if (initData) {
        headers['Authorization'] = `Bearer ${initData}`;
        authenticate();
    } else if (mockAuth) {
        // Warning: this is just to allow UI view when not in Telegram during testing.
        // In reality, backend blocks without real Telegram auth.
        document.getElementById('authGate').classList.add('hidden');
        document.getElementById('adminPanel').classList.remove('hidden');
        initAdminPanel();
    } else {
        document.getElementById('authError').innerText = "initData not found. Open in Telegram.";
        document.getElementById('authError').classList.remove('hidden');
    }

    function authenticate() {
        // A real app might hit a /api/auth.php endpoint to verify and check role
        // For now, we attach headers to all API calls. 
        // If API calls fail with 401/403, we know auth failed.
        document.getElementById('authGate').classList.add('hidden');
        document.getElementById('adminPanel').classList.remove('hidden');
        initAdminPanel();
    }

    function initAdminPanel() {
        // Tab Switching
        document.querySelectorAll('aside nav button').forEach(btn => {
            btn.addEventListener('click', (e) => {
                document.querySelectorAll('aside nav button').forEach(b => {
                    b.classList.remove('bg-gray-700', 'active-tab');
                });
                e.target.classList.add('bg-gray-700', 'active-tab');

                document.querySelectorAll('.tab-content').forEach(tab => {
                    tab.classList.add('hidden');
                });
                const targetId = e.target.getAttribute('data-target');
                document.getElementById(targetId).classList.remove('hidden');

                if (targetId === 'categoriesTab') loadCategories();
            });
        });

        loadComics();

        // Event Listeners for Selects
        document.getElementById('chapterComicSelect').addEventListener('change', (e) => {
            loadChapters(e.target.value);
        });
        document.getElementById('reviewComicSelect').addEventListener('change', (e) => {
            loadReviews(e.target.value);
        });
        
        // Comic Modal logic
        const comicModal = document.getElementById('comicModal');
        const comicForm = document.getElementById('comicForm');
        let allCategories = [];

        function populateCategoriesSelect(selectedIds = []) {
            const select = document.getElementById('comicCategoriesInput');
            select.innerHTML = allCategories.map(c =>
                `<option value="${c.id}" ${selectedIds.includes(String(c.id)) ? 'selected' : ''}>${escapeHTML(c.name)}</option>`
            ).join('');
        }

        // Fetch categories once for the modal
        fetch('/api/categories.php', { headers })
            .then(res => res.json())
            .then(catData => {
                allCategories = catData.data || [];
                populateCategoriesSelect();
            });

        document.getElementById('addComicBtn').addEventListener('click', () => {
            document.getElementById('comicModalTitle').innerText = 'Add Comic';
            comicForm.reset();
            document.getElementById('comicId').value = '';
            populateCategoriesSelect();
            comicModal.classList.remove('hidden');
        });

        document.getElementById('closeComicModalBtn').addEventListener('click', () => {
            comicModal.classList.add('hidden');
        });

        comicForm.addEventListener('submit', (e) => {
            e.preventDefault();
            const fd = new FormData(comicForm);

            // Collect multiple categories
            const selectedCategories = Array.from(document.getElementById('comicCategoriesInput').selectedOptions).map(opt => opt.value);
            fd.set('categories', selectedCategories.join(','));

            const isEdit = !!document.getElementById('comicId').value;
            fd.append('action', isEdit ? 'edit' : 'add');

            fetch('/api/comics.php', { method: 'POST', headers, body: fd })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        comicModal.classList.add('hidden');
                        loadComics();
                    } else {
                        alert('Error: ' + data.error);
                    }
                });
        });

        // Add Chapter
        document.getElementById('addChapterBtn').addEventListener('click', () => {
            const comicId = document.getElementById('chapterComicSelect').value;
            if(!comicId) return alert("Select a comic first.");
            
            document.getElementById('chapterForm').reset();
            document.getElementById('chapterComicIdInput').value = comicId;
            document.getElementById('chapterModal').classList.remove('hidden');
        });

        document.getElementById('closeChapterModalBtn').addEventListener('click', () => {
            document.getElementById('chapterModal').classList.add('hidden');
        });

        document.getElementById('chapterForm').addEventListener('submit', (e) => {
            e.preventDefault();
            const comicId = document.getElementById('chapterComicIdInput').value;

            const fd = new FormData(e.target);
            if (!document.getElementById('chapterAdultInput').checked) {
                fd.append('is_adult', '0');
            }

            fetch('/api/chapters.php', { method: 'POST', headers, body: fd })
                .then(res => res.json())
                .then(data => {
                    if(data.success) {
                        document.getElementById('chapterModal').classList.add('hidden');
                        loadChapters(comicId);
                    } else {
                        alert('Error: ' + data.error);
                    }
                });
        });
    }

    function loadComics() {
        fetch('/api/comics.php?limit=100', { headers })
            .then(res => res.json())
            .then(data => {
                const list = document.getElementById('adminComicsList');
                const comics = data.data || [];
                
                // Populate selects
                const options = '<option value="">Select Comic</option>' + comics.map(c => `<option value="${c.id}">${c.title}</option>`).join('');
                document.getElementById('chapterComicSelect').innerHTML = options;
                document.getElementById('reviewComicSelect').innerHTML = options;

                list.innerHTML = comics.map(c => {
                    const catNames = (c.categories || []).map(cat => cat.name).join(', ') || '-';
                    return `
                    <tr class="border-b hover:bg-gray-50">
                        <td class="p-3">${c.id}</td>
                        <td class="p-3"><img src="${c.thumbnail_url || ''}" class="w-10 h-14 object-cover bg-gray-200"></td>
                        <td class="p-3">${escapeHTML(c.title)}</td>
                        <td class="p-3">${escapeHTML(catNames)}</td>
                        <td class="p-3 space-x-2">
                            <button class="text-blue-600 hover:underline text-sm" onclick="editComic(${c.id})">Edit</button>
                            <button class="text-red-600 hover:underline text-sm" onclick="deleteComic(${c.id})">Delete</button>
                        </td>
                    </tr>
                `}).join('');
            });
    }

    window.editComic = function(id) {
        // Fetch full comic details to populate the form
        fetch(`/api/comics.php?id=${id}`, { headers })
            .then(res => res.json())
            .then(data => {
                let comic;
                if (data.data) {
                    comic = data.data.find(c => c.id == id) || data.data[0];
                } else {
                    comic = data;
                }

                if (!comic) return alert('Comic not found');

                document.getElementById('comicModalTitle').innerText = 'Edit Comic';
                document.getElementById('comicId').value = comic.id;
                document.getElementById('comicTitleInput').value = comic.title || '';
                document.getElementById('comicAltTitleInput').value = comic.alternative_title || '';
                document.getElementById('comicAuthorInput').value = comic.author || '';
                document.getElementById('comicArtistInput').value = comic.artist || '';
                document.getElementById('comicPublisherInput').value = comic.publisher || '';
                document.getElementById('comicSynopsisInput').value = comic.synopsis || '';
                document.getElementById('comicYearInput').value = comic.year || '';
                document.getElementById('comicStatusInput').value = comic.status || 'Ongoing';

                const catIds = (comic.categories || []).map(cat => String(cat.id));
                // We need to re-fetch or use allCategories
                fetch('/api/categories.php', { headers })
                    .then(res => res.json())
                    .then(catData => {
                        const allCategories = catData.data || [];
                        const select = document.getElementById('comicCategoriesInput');
                        select.innerHTML = allCategories.map(c =>
                            `<option value="${c.id}" ${catIds.includes(String(c.id)) ? 'selected' : ''}>${escapeHTML(c.name)}</option>`
                        ).join('');
                        document.getElementById('comicModal').classList.remove('hidden');
                    });
            });
    }

    window.deleteComic = function(id) {
        if(confirm("Are you sure you want to delete this comic and all its chapters/reviews?")) {
            fetch(`/api/comics.php?id=${id}`, { method: 'DELETE', headers })
                .then(res => res.json())
                .then(data => {
                    if(data.success) loadComics();
                });
        }
    }

    function loadChapters(comicId) {
        if(!comicId) return;
        fetch(`/api/chapters.php?comic_id=${comicId}`, { headers })
            .then(res => res.json())
            .then(chapters => {
                const list = document.getElementById('adminChaptersList');
                if(chapters.length === 0) {
                    list.innerHTML = '<tr><td colspan="5" class="p-3 text-center text-gray-500">No chapters found.</td></tr>';
                    return;
                }
                list.innerHTML = chapters.map(c => `
                    <tr class="border-b hover:bg-gray-50">
                        <td class="p-3">${c.id}</td>
                        <td class="p-3">${c.chapter_number}</td>
                        <td class="p-3">${c.title || '-'}</td>
                        <td class="p-3 truncate max-w-xs">${c.pdf_url}</td>
                        <td class="p-3 space-x-2">
                            <button class="text-red-600 hover:underline text-sm" onclick="deleteChapter(${c.id}, ${comicId})">Delete</button>
                        </td>
                    </tr>
                `).join('');
            });
    }

    window.deleteChapter = function(id, comicId) {
        if(confirm("Are you sure you want to delete this chapter?")) {
            fetch(`/api/chapters.php?id=${id}`, { method: 'DELETE', headers })
                .then(res => res.json())
                .then(data => {
                    if(data.success) loadChapters(comicId);
                });
        }
    }

    function loadReviews(comicId) {
        if(!comicId) return;
        // In a real app, admin endpoint should fetch ALL reviews including hidden/spam.
        // Our GET endpoint currently filters by active. We'd need an admin-specific fetch.
        // For simplicity, assuming backend handles admin auth on GET appropriately to show all.
        fetch(`/api/reviews.php?comic_id=${comicId}&all=1`, { headers })
            .then(res => res.json())
            .then(threads => {
                const list = document.getElementById('adminReviewsList');
                // Flatten threads
                let allReviews = [];
                threads.forEach(t => {
                    allReviews.push(t);
                    if(t.replies) allReviews = allReviews.concat(t.replies);
                });

                if(allReviews.length === 0) {
                    list.innerHTML = '<tr><td colspan="5" class="p-3 text-center text-gray-500">No reviews found.</td></tr>';
                    return;
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

                list.innerHTML = allReviews.map(r => `
                    <tr class="border-b hover:bg-gray-50">
                        <td class="p-3">${r.id}</td>
                        <td class="p-3">${escapeHTML(r.username)}</td>
                        <td class="p-3 text-sm truncate max-w-xs">${escapeHTML(r.content)}</td>
                        <td class="p-3">
                            <select onchange="updateReviewStatus(${r.id}, this.value, ${comicId})" class="border rounded p-1 text-sm outline-none">
                                <option value="active" ${r.status === 'active' ? 'selected' : ''}>Active</option>
                                <option value="hidden" ${r.status === 'hidden' ? 'selected' : ''}>Hidden</option>
                                <option value="spam" ${r.status === 'spam' ? 'selected' : ''}>Spam</option>
                            </select>
                        </td>
                        <td class="p-3">
                            <button class="text-red-600 hover:underline text-sm" onclick="deleteReview(${r.id}, ${comicId})">Delete</button>
                        </td>
                    </tr>
                `).join('');
            });
    }

    window.updateReviewStatus = function(id, status, comicId) {
        // PUT request using URL encoding or fetch payload
        fetch(`/api/reviews.php?id=${id}`, {
            method: 'PUT',
            headers: {
                ...headers,
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: `status=${status}`
        }).then(res => res.json())
          .then(data => {
              if(!data.success) alert("Failed to update status");
          });
    }

    window.deleteReview = function(id, comicId) {
        if(confirm("Are you sure you want to delete this review?")) {
            fetch(`/api/reviews.php?id=${id}`, { method: 'DELETE', headers })
                .then(res => res.json())
                .then(data => {
                    if(data.success) loadReviews(comicId);
                });
        }
    }

    // --- Categories CRUD ---
    const addCategoryBtn = document.getElementById('addCategoryBtn');
    if (addCategoryBtn) {
        addCategoryBtn.addEventListener('click', () => {
            const name = prompt("Enter Category Name:");
            if (!name) return;

            const fd = new FormData();
            fd.append('name', name);

            fetch('/api/categories.php', { method: 'POST', headers, body: fd })
                .then(res => res.json())
                .then(data => {
                    if (data.success) loadCategories();
                    else alert('Error: ' + data.error);
                });
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
        fetch('/api/categories.php', { headers })
            .then(res => res.json())
            .then(data => {
                const list = document.getElementById('adminCategoriesList');
                const categories = data.data || [];

                if (categories.length === 0) {
                    list.innerHTML = '<tr><td colspan="3" class="p-3 text-center text-gray-500">No categories found.</td></tr>';
                    return;
                }

                list.innerHTML = categories.map(c => `
                    <tr class="border-b hover:bg-gray-50">
                        <td class="p-3">${c.id}</td>
                        <td class="p-3">${escapeHTML(c.name)}</td>
                        <td class="p-3 space-x-2">
                            <button class="text-blue-600 hover:underline text-sm" onclick="editCategory(${c.id}, '${escapeHTML(c.name).replace(/'/g, "\\'")}')">Edit</button>
                            <button class="text-red-600 hover:underline text-sm" onclick="deleteCategory(${c.id})">Delete</button>
                        </td>
                    </tr>
                `).join('');
            });
    }

    window.editCategory = function(id, oldName) {
        const name = prompt("Edit Category Name:", oldName);
        if (!name) return;

        fetch(`/api/categories.php?id=${id}`, {
            method: 'PUT',
            headers: {
                ...headers,
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: `name=${encodeURIComponent(name)}`
        }).then(res => res.json())
          .then(data => {
              if (data.success) loadCategories();
              else alert('Error updating category');
          });
    }

    window.deleteCategory = function(id) {
        if(confirm("Are you sure you want to delete this category?")) {
            fetch(`/api/categories.php?id=${id}`, { method: 'DELETE', headers })
                .then(res => res.json())
                .then(data => {
                    if(data.success) loadCategories();
                });
        }
    }
});