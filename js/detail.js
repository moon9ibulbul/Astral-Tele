document.addEventListener('DOMContentLoaded', () => {
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
                document.getElementById('headerTitle').innerText = comic.title;
                document.getElementById('comicTitle').innerText = comic.title;
                document.getElementById('comicAltTitle').innerText = comic.alternative_title || '';
                document.getElementById('comicRating').innerText = comic.average_rating;
                document.getElementById('comicCategory').innerText = comic.category || 'Uncategorized';
                document.getElementById('comicAuthor').innerText = comic.author || '-';
                document.getElementById('comicArtist').innerText = comic.artist || '-';
                document.getElementById('comicPublisher').innerText = comic.publisher || '-';
                document.getElementById('comicSynopsis').innerText = comic.synopsis || 'No synopsis available.';
                
                const thumb = document.getElementById('comicThumb');
                if (comic.thumbnail_url) {
                    thumb.src = comic.thumbnail_url;
                    thumb.classList.remove('hidden');
                }
            }
        });

    // Fetch Chapters
    fetch(`/api/chapters.php?comic_id=${comicId}`)
        .then(res => res.json())
        .then(chapters => {
            const list = document.getElementById('chapterList');
            if (chapters.length === 0) {
                list.innerHTML = '<p class="text-sm text-gray-500">No chapters available.</p>';
                return;
            }

            list.innerHTML = chapters.map(ch => `
                <a href="reader.html?id=${ch.id}&comic_id=${comicId}" class="block bg-white border rounded-lg p-3 hover:bg-gray-50 transition flex justify-between items-center">
                    <div>
                        <span class="font-bold text-sm">Chapter ${ch.chapter_number}</span>
                        ${ch.title ? `<span class="text-xs text-gray-500 ml-2">- ${ch.title}</span>` : ''}
                    </div>
                    <span class="text-xs text-gray-400">${new Date(ch.created_at).toLocaleDateString()}</span>
                </a>
            `).join('');
        });

    // Fetch Reviews
    loadReviews();

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
            if (tg && tg.initData) headers['Authorization'] = `Bearer ${tg.initData}`;

            fetch('/api/reviews.php', { method: 'POST', headers, body: fd })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('reviewContent').value = '';
                        document.getElementById('reviewRating').value = '';
                        document.getElementById('reviewImage').value = '';
                        loadReviews();
                    } else {
                        alert(data.error || 'Failed to submit review. Ensure you are authenticated.');
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
        if (tg && tg.initData) headers['Authorization'] = `Bearer ${tg.initData}`;

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
        if (tg && tg.initData) headers['Authorization'] = `Bearer ${tg.initData}`;

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
                if (threads.length === 0) {
                    list.innerHTML = '<p class="text-sm text-gray-500">No reviews yet. Be the first!</p>';
                    return;
                }

                list.innerHTML = threads.map(thread => `
                    <div class="bg-white border rounded-lg p-3">
                        <div class="flex items-center gap-2 mb-2">
                            <img src="${thread.photo_url || 'https://via.placeholder.com/32'}" class="w-8 h-8 rounded-full">
                            <div>
                                <p class="text-sm font-bold">${escapeHTML(thread.username || 'Anonymous')}</p>
                                <p class="text-xs text-gray-500">${new Date(thread.created_at).toLocaleString()}</p>
                            </div>
                            ${thread.rating ? `<div class="ml-auto text-yellow-500 text-sm">★ ${escapeHTML(thread.rating)}</div>` : ''}
                        </div>
                        <p class="text-sm text-gray-800 whitespace-pre-wrap">${escapeHTML(thread.content)}</p>
                        ${thread.image_url ? `<img src="${escapeHTML(thread.image_url)}" class="mt-2 max-w-full h-auto rounded border">` : ''}
                        
                        <div class="mt-2 flex gap-4 text-xs text-gray-500">
                            <button class="hover:text-blue-600" onclick="voteReview(${thread.id}, 'like')">👍 ${thread.likes || 0}</button>
                            <button class="hover:text-blue-600" onclick="voteReview(${thread.id}, 'dislike')">👎 ${thread.dislikes || 0}</button>
                            <button class="hover:text-blue-600" onclick="document.getElementById('replyBox-${thread.id}').classList.toggle('hidden')">Reply</button>
                        </div>

                        <!-- Replies -->
                        <div class="ml-8 mt-3 space-y-2 border-l-2 pl-3">
                            ${(thread.replies || []).map(reply => `
                                <div>
                                    <div class="flex items-center gap-2">
                                        <p class="text-xs font-bold">${escapeHTML(reply.username || 'Anonymous')}</p>
                                        <span class="text-xs text-gray-400">${new Date(reply.created_at).toLocaleString()}</span>
                                    </div>
                                    <p class="text-sm text-gray-700">${escapeHTML(reply.content)}</p>
                                </div>
                            `).join('')}
                            
                            <!-- Reply Input -->
                            <div id="replyBox-${thread.id}" class="hidden mt-2 flex gap-2">
                                <input type="text" id="replyInput-${thread.id}" class="flex-1 border rounded px-2 py-1 text-sm outline-none focus:ring-1 focus:ring-blue-500" placeholder="Write a reply...">
                                <button class="bg-blue-600 text-white px-3 py-1 rounded text-xs hover:bg-blue-700" onclick="submitReply(${thread.id})">Send</button>
                            </div>
                        </div>
                    </div>
                `).join('');
            });
    }
});