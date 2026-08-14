document.addEventListener('DOMContentLoaded', () => {
    // Restrict access to Telegram Mini App only
    const tg = window.Telegram && window.Telegram.WebApp;
    if (!tg || !tg.initData) {
        document.body.innerHTML = `
            <div class="flex items-center justify-center min-h-screen bg-gray-100 text-gray-900 p-4">
                <div class="text-center bg-white p-8 rounded-lg shadow-lg max-w-md border border-gray-200">
                    <div class="text-5xl mb-4">🚫</div>
                    <h1 class="text-xl font-bold mb-2">Akses Ditolak</h1>
                    <p class="text-sm text-gray-600">Halaman ini hanya dapat diakses melalui Telegram Mini App.</p>
                </div>
            </div>
        `;
        return;
    }

    const urlParams = new URLSearchParams(window.location.search);
    const comicId = urlParams.get('id');

    if (!comicId) {
        document.body.innerHTML = '<p class="text-center mt-10">Invalid Comic ID</p>';
        return;
    }

    // Load Comic Details (piggybacking on the chapters API initially, but we need the comic details API)
    // We should implement a single comic fetch, but for now we can fetch all and filter or add an endpoint.
    // Let's assume we can fetch all comics and find ours for simplicity, or better, add a specific endpoint.
    // Actually, our GET /api/comics.php returns list. We'll add a quick fix in api/comics.php if needed, 
    // or just fetch by search. For proper design, let's fetch chapter list first.
    
    // We will do a generic fetch for now and assume we'll update the backend to support GET by ID.
    // To not modify backend right now, we can pass search param if title is known, but ID is better.
    // Let's modify comics.php to handle id or just fetch here.
    
    // I'll quickly update api/comics.php to support ID fetch via `run_in_bash_session` if needed, 
    // but assuming standard REST, we might just need to fetch the list and find it. 
    // For large DBs this is bad, so I will ensure api/comics.php supports id.
    
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

    // Fetch comic metadata
    fetch(`/api/comics.php?id=${comicId}`)
        .then(res => res.json())
        .then(data => {
            // If the API returns a list (because we didn't explicitly code ID fetching in comics.php GET block earlier)
            // Let's find it.
            let comic;
            if (data.data) {
                comic = data.data.find(c => c.id == comicId);
            } else {
                comic = data;
            }

            if (comic) {
                document.title = comic.title + " - Astral-Tele";
                document.getElementById('headerTitle').innerText = comic.title;
                document.getElementById('comicTitle').innerText = comic.title;
                document.getElementById('comicAltTitle').innerText = comic.alternative_title || '';
                document.getElementById('comicRating').innerText = comic.average_rating;

                const catContainer = document.getElementById('comicCategories');
                if (comic.categories && comic.categories.length > 0) {
                    catContainer.innerHTML = comic.categories.map(cat =>
                        `<a href="index.html?category=${encodeURIComponent(cat.name)}" class="bg-gray-100 hover:bg-gray-200 px-2 py-0.5 rounded text-gray-600 cursor-pointer block">${escapeHTML(cat.name)}</a>`
                    ).join('');
                } else {
                    catContainer.innerHTML = `<span class="bg-gray-100 px-2 py-0.5 rounded text-gray-600">Uncategorized</span>`;
                }

                document.getElementById('comicAuthor').innerText = comic.author || '-';
                document.getElementById('comicArtist').innerText = comic.artist || '-';
                document.getElementById('comicPublisher').innerText = comic.publisher || '-';
            document.getElementById('comicYear').innerText = comic.year || '-';
            document.getElementById('comicStatus').innerText = comic.status || '-';
            document.getElementById('comicViews').innerText = comic.views || '0';

                const synopsisEl = document.getElementById('comicSynopsis');
                synopsisEl.innerText = comic.synopsis || 'No synopsis available.';

                // Logic to detect if we need "View More"
                // Check after render if the text is truncated by line-clamp
                setTimeout(() => {
                    const isTruncated = synopsisEl.scrollHeight > synopsisEl.clientHeight;
                    const viewMoreBtn = document.getElementById('viewMoreBtn');
                    if (isTruncated && viewMoreBtn) {
                        viewMoreBtn.classList.remove('hidden');
                        viewMoreBtn.addEventListener('click', () => {
                            if (synopsisEl.classList.contains('line-clamp-3')) {
                                synopsisEl.classList.remove('line-clamp-3');
                                viewMoreBtn.innerText = 'View Less';
                            } else {
                                synopsisEl.classList.add('line-clamp-3');
                                viewMoreBtn.innerText = 'View More';
                            }
                        });
                    }
                }, 50);

                
                const thumb = document.getElementById('comicThumb');
                if (comic.thumbnail_url) {
                    thumb.src = comic.thumbnail_url;
                    thumb.classList.remove('hidden');
                }
            }
        });

    const tg = window.Telegram && window.Telegram.WebApp;
    const headers = {};
    if (tg && tg.initData) {
        headers['Authorization'] = `Bearer ${tg.initData}`;
        headers['X-Telegram-Init-Data'] = tg.initData;
    }

    // Bookmark Toggle Logic
    const bookmarkBtn = document.getElementById('bookmarkBtn');
    const bookmarkText = document.getElementById('bookmarkText');
    if (bookmarkBtn) {
        // Fetch current bookmark status
        fetch(`/api/bookmarks.php?comic_id=${comicId}`, { headers })
            .then(res => res.json())
            .then(data => {
                if (data.bookmarked) {
                    setBookmarkActive(true);
                }
            });

        bookmarkBtn.addEventListener('click', () => {
            const fd = new FormData();
            fd.append('comic_id', comicId);
            fd.append('action', 'toggle');

            fetch('/api/bookmarks.php', { method: 'POST', headers, body: fd })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        setBookmarkActive(data.bookmarked);
                    } else {
                        alert('Failed to toggle bookmark: ' + (data.error || 'Unauthorized'));
                    }
                });
        });
    }

    function setBookmarkActive(isActive) {
        if (isActive) {
            bookmarkBtn.classList.remove('text-gray-700', 'bg-gray-50', 'hover:bg-gray-100');
            bookmarkBtn.classList.add('text-white', 'bg-blue-600', 'hover:bg-blue-700');
            bookmarkText.innerText = 'Bookmarked';
        } else {
            bookmarkBtn.classList.add('text-gray-700', 'bg-gray-50', 'hover:bg-gray-100');
            bookmarkBtn.classList.remove('text-white', 'bg-blue-600', 'hover:bg-blue-700');
            bookmarkText.innerText = 'Bookmark';
        }
    }

    // Fetch Chapters and Reading History
    Promise.all([
        fetch(`/api/chapters.php?comic_id=${comicId}`).then(res => res.json()),
        fetch(`/api/history.php?comic_id=${comicId}`, { headers }).then(res => res.ok ? res.json() : {data: []})
    ]).then(([chaptersResponse, historyData]) => {
            // Adjust for new object response structure if needed
            const chapters = chaptersResponse.data || chaptersResponse || [];

            const readChapterIds = historyData.data || [];
            const list = document.getElementById('chapterList');
            if (chapters.length === 0) {
                list.innerHTML = '<p class="text-sm text-gray-500">No chapters available.</p>';
                return;
            }

            list.innerHTML = chapters.map(ch => {
                let icons = '';
                if (ch.is_adult == 1) icons += ' 🔞';
                if (ch.has_password == 1) icons += ' 🔒';
                if (ch.price > 0) icons += ' ⭐';

                const isRead = readChapterIds.includes(ch.id);
                const bgClass = isRead ? 'bg-gray-100' : 'bg-white';
                const textClass = isRead ? 'text-gray-500' : 'text-gray-900';

                return `
                <a href="reader.html?id=${ch.id}&comic_id=${comicId}" class="block ${bgClass} border rounded-lg p-3 hover:bg-gray-50 transition flex justify-between items-center ${textClass}">
                    <div>
                        <span class="font-bold text-sm">Chapter ${ch.chapter_number}${icons}</span>
                        ${ch.title ? `<span class="text-xs text-gray-500 ml-2">- ${ch.title}</span>` : ''}
                    </div>
                    <span class="text-xs text-gray-400">${new Date(ch.created_at).toLocaleDateString()}</span>
                </a>
                `;
            }).join('');
        });

    // Fetch Reviews
    loadReviews();

    const reviewImageInput = document.getElementById('reviewImage');
    const reviewImageLabel = document.getElementById('reviewImageLabel');
    if (reviewImageInput && reviewImageLabel) {
        reviewImageInput.addEventListener('change', (e) => {
            if (e.target.files && e.target.files.length > 0) {
                reviewImageLabel.innerText = e.target.files[0].name;
            } else {
                reviewImageLabel.innerText = 'Attach Image';
            }
        });
    }


    let currentUserId = null;

    // Attempt to get user ID
    const detailProfileHeaders = {};
    if (window.Telegram && window.Telegram.WebApp && window.Telegram.WebApp.initData) {
        detailProfileHeaders['Authorization'] = `Bearer ${window.Telegram.WebApp.initData}`;
        detailProfileHeaders['X-Telegram-Init-Data'] = window.Telegram.WebApp.initData;
    }
    fetch('/api/profile.php', {
        headers: detailProfileHeaders
    })
    .then(res => res.ok ? res.json() : {})
    .then(data => {
        if(data.id) currentUserId = data.id;
    }).catch(() => {});

    const submitReviewBtn = document.getElementById('submitReviewBtn');
    if (submitReviewBtn) {
        submitReviewBtn.addEventListener('click', () => {
            const content = document.getElementById('reviewContent').value;
            const rating = document.getElementById('reviewRating').value;
            const imageFile = document.getElementById('reviewImage').files[0];

            if (!content) return alert('Review content is required');

            const fd = new FormData();
            fd.append('action', 'add');
            fd.append('comic_id', comicId);
            fd.append('content', content);
            if (rating) fd.append('rating', rating);
            if (imageFile) fd.append('image', imageFile);

            // Using mock headers if needed, otherwise rely on cookie/session, but our auth uses Bearer token.
            const headers = {};
            const tg = window.Telegram && window.Telegram.WebApp;
            if (tg && tg.initData) {
                headers['Authorization'] = `Bearer ${tg.initData}`;
                headers['X-Telegram-Init-Data'] = tg.initData;
            }

            fetch('/api/reviews.php', { method: 'POST', headers, body: fd })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('reviewContent').value = '';
                        document.getElementById('reviewRating').value = '';
                        document.getElementById('reviewImage').value = '';
                        if (reviewImageLabel) reviewImageLabel.innerText = 'Attach Image';
                        loadReviews();
                    } else {
                        alert(data.error || 'Failed to submit review. Ensure you are authenticated.');
                        if (data.error && data.error.includes('already reviewed')) {
                            const submitSection = document.getElementById('submitReviewBtn')?.closest('.bg-white');
                            if(submitSection) submitSection.style.display = 'none';
                        }
                    }
                });
        });
    }

    window.submitReply = function(parentId) {
        const input = document.getElementById(`replyInput-${parentId}`);
        const content = input.value;
        if (!content) return;

        const fd = new FormData();
        fd.append('action', 'add');
        fd.append('comic_id', comicId);
        fd.append('content', content);
        fd.append('parent_id', parentId);

        const headers = {};
        const tg = window.Telegram && window.Telegram.WebApp;
        if (tg && tg.initData) {
            headers['Authorization'] = `Bearer ${tg.initData}`;
            headers['X-Telegram-Init-Data'] = tg.initData;
        }

        fetch('/api/reviews.php', { method: 'POST', headers, body: fd })
            .then(res => res.json())
            .then(data => {
                if (data.success) loadReviews();
                else alert(data.error || 'Failed to submit reply. Ensure you are authenticated.');
            });
    }

    window.voteReview = function(reviewId, action) {
        const fd = new FormData();
        fd.append('action', action);
        fd.append('review_id', reviewId);

        const headers = {};
        const tg = window.Telegram && window.Telegram.WebApp;
        if (tg && tg.initData) {
            headers['Authorization'] = `Bearer ${tg.initData}`;
            headers['X-Telegram-Init-Data'] = tg.initData;
        }

        fetch('/api/reviews.php', { method: 'POST', headers, body: fd })
            .then(res => res.json())
            .then(data => {
                if (data.success) loadReviews();
                else alert(data.error || 'Failed to vote. Ensure you are authenticated.');
            });
    }


    function loadReviews() {
        fetch(`/api/reviews.php?comic_id=${comicId}`)
            .then(res => res.json())
            .then(threads => {
                const list = document.getElementById('reviewsList');

                function getRankHtml(role, pts) {
                    pts = parseInt(pts) || 0;
                    if (role === 'admin') {
                        return '<span class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-yellow-100 text-yellow-700 border border-yellow-200">Admin</span>';
                    }
                    if (pts > 2500) {
                        return '<span class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-gradient-to-r from-red-500 via-yellow-500 to-blue-500 text-white shadow-sm">Aeon</span>';
                    } else if (pts >= 1000) {
                        return '<span class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-red-100 text-red-700 border border-red-200">Emanator</span>';
                    } else if (pts >= 501) {
                        return '<span class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-yellow-50 text-yellow-600 border border-yellow-200">The Nameless</span>';
                    } else if (pts >= 251) {
                        return '<span class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-blue-100 text-blue-700 border border-blue-200">Passenger</span>';
                    } else if (pts >= 101) {
                        return '<span class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-green-100 text-green-700 border border-green-200">Pathstrider</span>';
                    } else {
                        return '<span class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-gray-100 text-gray-800 border border-gray-300">NPC</span>';
                    }
                }

                let hasReviewed = false;

                if (threads.length === 0) {
                    list.innerHTML = '<p class="text-sm text-gray-500">No reviews yet. Be the first!</p>';
                } else {
                    list.innerHTML = threads.map(thread => {
                        const isOwner = currentUserId && thread.user_id == currentUserId;
                        if (isOwner) hasReviewed = true;
                        
                        const editedLabel = thread.created_at !== thread.updated_at ? '<span class="text-[10px] text-gray-400 ml-1">(Edited)</span>' : '';

                        return `
                        <div class="bg-white border rounded-lg p-3">
                            <div class="flex items-center gap-2 mb-2">
                                <img src="${thread.photo_url || 'https://via.placeholder.com/32'}" class="w-8 h-8 rounded-full">
                                <div>
                                    <div class="flex items-center gap-1.5">
                                        <p class="text-sm font-bold">${escapeHTML(thread.username || 'Anonymous')}</p>
                                        ${getRankHtml(thread.role, thread.pts)}
                                    </div>
                                    <p class="text-xs text-gray-500">${new Date(thread.created_at).toLocaleString()} ${editedLabel}</p>
                                </div>
                                ${thread.rating ? `<div class="ml-auto text-yellow-500 text-sm">★ ${escapeHTML(thread.rating)}</div>` : ''}
                            </div>
                            <p class="text-sm text-gray-800 whitespace-pre-wrap">${escapeHTML(thread.content)}</p>
                            ${thread.image_url ? `<img src="${thread.image_url}" class="mt-2 max-w-full rounded h-32 object-cover">` : ''}

                            <div class="flex gap-4 mt-3 text-xs text-gray-500">
                                <button class="hover:text-blue-600" onclick="voteReview(${thread.id}, 'like')">👍 ${thread.likes || 0}</button>
                                <button class="hover:text-blue-600" onclick="voteReview(${thread.id}, 'dislike')">👎 ${thread.dislikes || 0}</button>
                                <button class="hover:text-blue-600" onclick="document.getElementById('replyBox-${thread.id}').classList.toggle('hidden')">Reply</button>
                                ${isOwner ? `<button class="hover:text-blue-600 text-blue-500 font-medium" onclick="editReview(${thread.id}, '${encodeURIComponent(thread.content)}', '${thread.rating || ''}')">Edit</button>` : ''}
                            </div>

                            <!-- Replies -->
                            ${(thread.replies || []).map(r => {
                                const isReplyOwner = currentUserId && r.user_id == currentUserId;
                                const replyEdited = r.created_at !== r.updated_at ? '<span class="text-[10px] text-gray-400 ml-1">(Edited)</span>' : '';
                                return `
                                <div class="ml-6 mt-3 border-l-2 pl-3">
                                    <div class="flex items-center gap-2 mb-1">
                                        <img src="${r.photo_url || 'https://via.placeholder.com/24'}" class="w-6 h-6 rounded-full">
                                        <div>
                                            <div class="flex items-center gap-1.5">
                                                <p class="text-xs font-bold">${escapeHTML(r.username || 'Anonymous')}</p>
                                                ${getRankHtml(r.role, r.pts)}
                                            </div>
                                            <p class="text-[10px] text-gray-500">${new Date(r.created_at).toLocaleString()} ${replyEdited}</p>
                                        </div>
                                    </div>
                                    <p class="text-sm text-gray-700">${escapeHTML(r.content)}</p>
                                    <div class="flex gap-4 mt-1 text-[10px] text-gray-500">
                                        ${isReplyOwner ? `<button class="hover:text-blue-600 text-blue-500" onclick="editReview(${r.id}, '${encodeURIComponent(r.content)}', '')">Edit</button>` : ''}
                                    </div>
                                </div>
                                `;
                            }).join('')}
                            
                            <!-- Reply Input -->
                            <div id="replyBox-${thread.id}" class="hidden mt-2 flex gap-2">
                                <input type="text" id="replyInput-${thread.id}" class="flex-1 border rounded px-2 py-1 text-sm outline-none focus:ring-1 focus:ring-blue-500" placeholder="Write a reply...">
                                <button class="bg-blue-600 text-white px-3 py-1 rounded text-xs hover:bg-blue-700" onclick="submitReply(${thread.id})">Send</button>
                            </div>
                        </div>
                    </div>
                    `;
                    }).join('');
                }

                // Hide main review form if already reviewed
                const submitSection = document.getElementById('submitReviewBtn')?.closest('.bg-white');
                if (submitSection) {
                    if (hasReviewed) {
                        submitSection.style.display = 'none';
                    } else {
                        submitSection.style.display = 'block';
                    }
                }
            });
    }

    window.editReview = function(id, encodedContent, currentRating) {
        const content = decodeURIComponent(encodedContent);
        const newContent = prompt("Edit your review/comment:", content);
        if (newContent === null) return; // User cancelled
        if (!newContent.trim()) {
            alert("Content cannot be empty.");
            return;
        }

        let newRating = currentRating;
        // If it's a top-level review (has rating or allowed to have rating)
        if (currentRating !== '') {
            const promptRating = prompt("Edit your rating (1-5):", currentRating);
            if (promptRating !== null) {
                const r = parseInt(promptRating);
                if (r >= 1 && r <= 5) newRating = r;
            }
        }

        const fd = new FormData();
        fd.append('action', 'edit');
        fd.append('review_id', id);
        fd.append('content', newContent);
        if (newRating) fd.append('rating', newRating);

        const headers = {};
        const tg = window.Telegram && window.Telegram.WebApp;
        if (tg && tg.initData) {
            headers['Authorization'] = `Bearer ${tg.initData}`;
            headers['X-Telegram-Init-Data'] = tg.initData;
        }

        fetch('/api/reviews.php', { method: 'POST', headers, body: fd })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    loadReviews();
                } else {
                    alert(data.error || 'Failed to edit.');
                }
            });
    }
});
