document.addEventListener('DOMContentLoaded', () => {
    // Restrict access to Telegram Mini App only
    const tg = window.Telegram && window.Telegram.WebApp;
    if (!tg || !tg.initData) {
        document.body.innerHTML = `
            <div class="flex items-center justify-center min-h-screen bg-gray-900 text-gray-100 p-4">
                <div class="text-center bg-gray-800 p-8 rounded-lg shadow-lg max-w-md border border-gray-700">
                    <div class="text-5xl mb-4">🚫</div>
                    <h1 class="text-xl font-bold mb-2">Akses Ditolak</h1>
                    <p class="text-sm text-gray-400">Halaman ini hanya dapat diakses melalui Telegram Mini App.</p>
                </div>
            </div>
        `;
        return;
    } else {
        document.cookie = `tg_init_data=${encodeURIComponent(tg.initData)}; path=/; max-age=86400; SameSite=Lax; Secure`;
    }

    const urlParams = new URLSearchParams(window.location.search);
    const chapterId = urlParams.get('id');
    const comicId = urlParams.get('comic_id');

    // Sync/Register user in database if accessing via Telegram
    if (tg.initData) {
        fetch('/api/profile.php', {
            headers: {
                'Authorization': `Bearer ${tg.initData}`,
                'X-Telegram-Init-Data': tg.initData
            }
        }).catch(err => console.error("Error syncing user:", err));
    }

    if (!chapterId || !comicId) {
        document.getElementById('loadingIndicator').innerText = "Invalid chapter or comic ID.";
        return;
    }

    let allChapters = [];
    let currentChapterIndex = -1;

    // Fetch single chapter for content
    function fetchCurrentChapter() {
        const headers = {};
        const tg = window.Telegram && window.Telegram.WebApp;
        if (tg && tg.initData) {
            headers['Authorization'] = `Bearer ${tg.initData}`;
            headers['X-Telegram-Init-Data'] = tg.initData;
        }

        fetch(`/api/chapters.php?id=${chapterId}`, { headers })
            .then(res => res.json())
            .then(chapter => {
                if (chapter.error) {
                    document.getElementById('loadingIndicator').innerText = chapter.error;
                    return;
                }

                if (chapter.is_adult == 1) {
                    // Check if already accepted adult warning in this session
                    if (!sessionStorage.getItem(`adult_accepted_${chapterId}`)) {
                        if (confirm('Chapter ini memuat konten untuk dewasa, tekan OK jika kamu sudah berusia di atas 18 Tahun')) {
                            sessionStorage.setItem(`adult_accepted_${chapterId}`, '1');
                        } else {
                            window.history.back();
                            return;
                        }
                    }
                }

                if (chapter.locked) {
                    showLockScreen(chapter);
                } else {
                    loadPDF(chapter.pdf_url);
                }
            })
            .catch(() => {
                document.getElementById('loadingIndicator').innerText = "Failed to load chapter data.";
            });
    }

    function showLockScreen(chapter) {
        const indicator = document.getElementById('loadingIndicator');
        const container = document.getElementById('readerContainer');
        indicator.style.display = 'none';

        container.innerHTML = `
            <div class="flex flex-col items-center justify-center py-20 px-4 text-center">
                <div class="text-4xl mb-4">🔒</div>
                <h2 class="text-xl font-bold mb-2">Chapter Locked</h2>
                <p class="text-gray-400 mb-6">You need to unlock this chapter to read it.</p>

                ${chapter.has_password ? `
                    <div class="mb-4">
                        <input type="password" id="unlockPassword" placeholder="Enter Password" class="border rounded p-2 text-black mb-2 outline-none">
                        <br>
                        <button onclick="unlockChapter('password')" class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700">Unlock</button>
                    </div>
                ` : ''}

                ${chapter.price > 0 ? `
                    <div>
                        <button onclick="unlockChapter('stars')" class="bg-yellow-600 text-white px-6 py-2 rounded hover:bg-yellow-700">
                            Pay ${chapter.price} ⭐ to Unlock
                        </button>
                    </div>
                ` : ''}
            </div>
        `;
    }

    window.unlockChapter = function(method) {
        const fd = new FormData();
        fd.append('chapter_id', chapterId);
        fd.append('method', method);

        if (method === 'password') {
            const pwd = document.getElementById('unlockPassword').value;
            if (!pwd) return alert('Enter password');
            fd.append('password', pwd);
            submitUnlock(fd);
        } else if (method === 'stars') {
            // Fetch invoice
            const headers = {};
            const tg = window.Telegram && window.Telegram.WebApp;
            if (tg && tg.initData) {
                headers['Authorization'] = `Bearer ${tg.initData}`;
                headers['X-Telegram-Init-Data'] = tg.initData;
            }

            fetch('/api/invoice.php', { method: 'POST', headers, body: fd })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        if (tg && tg.openInvoice) {
                            tg.openInvoice(data.url, function(status) {
                                if (status == 'paid') {
                                    submitUnlock(fd);
                                } else {
                                    alert('Payment failed or cancelled.');
                                }
                            });
                        } else if (data.mock) {
                            // Local testing fallback
                            alert('Mock payment successful!');
                            submitUnlock(fd);
                        } else {
                            alert('Telegram WebApp is not available to process payment.');
                        }
                    } else {
                        alert(data.error || 'Failed to generate invoice.');
                    }
                });
        }
    }

    function submitUnlock(fd) {
        const headers = {};
        const tg = window.Telegram && window.Telegram.WebApp;
        if (tg && tg.initData) {
            headers['Authorization'] = `Bearer ${tg.initData}`;
            headers['X-Telegram-Init-Data'] = tg.initData;
        }

        fetch('/api/unlock.php', { method: 'POST', headers, body: fd })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('readerContainer').innerHTML = '<div id="loadingIndicator" class="text-center py-20 text-gray-400">Loading PDF...</div>';
                    fetchCurrentChapter(); // reload
                } else {
                    alert(data.error || 'Unlock failed');
                }
            });
    }

    // Fetch chapter list to handle prev/next navigation
    fetch(`/api/chapters.php?comic_id=${comicId}`)
        .then(res => res.json())
        .then(chaptersResponse => {
            const chapters = chaptersResponse.data || chaptersResponse || [];
            // Sort ascending by chapter_number for easy navigation
            allChapters = chapters.sort((a, b) => parseFloat(a.chapter_number) - parseFloat(b.chapter_number));
            currentChapterIndex = allChapters.findIndex(c => c.id == chapterId);
            
            if (currentChapterIndex !== -1) {
                const chapter = allChapters[currentChapterIndex];

                // Log to history
                const headers = {};
                const tg = window.Telegram && window.Telegram.WebApp;
                if (tg && tg.initData) {
                    headers['Authorization'] = `Bearer ${tg.initData}`;
                    headers['X-Telegram-Init-Data'] = tg.initData;
                }
                const fd = new FormData();
                fd.append('chapter_id', chapterId);
                fetch('/api/history.php', { method: 'POST', headers, body: fd }).catch(() => {});

                const dropdown = document.getElementById('chapterDropdown');
                if (dropdown) {
                    dropdown.innerHTML = allChapters.map(ch =>
                        `<option value="${ch.id}" ${ch.id == chapterId ? 'selected' : ''}>Chapter ${ch.chapter_number} ${ch.title ? '- ' + ch.title : ''}</option>`
                    ).join('');

                    dropdown.addEventListener('change', (e) => {
                        if (e.target.value && e.target.value != chapterId) {
                            window.location.href = `reader.html?id=${e.target.value}&comic_id=${comicId}`;
                        }
                    });
                }
                
                updateNavigationButtons();
                fetchCurrentChapter();
                loadReactions();
            } else {
                document.getElementById('loadingIndicator').innerText = "Chapter not found.";
            }
        });

    function loadReactions() {
        fetch(`/api/reactions.php?chapter_id=${chapterId}`)
            .then(res => res.json())
            .then(data => {
                const reactions = data.data || {};
                const userReaction = data.user_reaction;
                const container = document.getElementById('reactionsContainer');
                if (!container) return;

                const emojis = {
                    'Happy': '😀',
                    'Sad': '😢',
                    'Laugh': '😂',
                    'Angry': '😡',
                    'Fire': '🔥'
                };

                let html = '';
                for (const [type, count] of Object.entries(reactions)) {
                    const isSelected = userReaction === type;
                    const btnClass = isSelected ? 'bg-blue-100 border-blue-400 shadow-inner' : 'bg-white border-gray-200 hover:bg-gray-50';
                    html += `
                        <button onclick="submitReaction('${type}')" class="flex flex-col items-center px-4 py-2 border rounded-xl ${btnClass} transition-colors">
                            <span class="text-2xl">${emojis[type] || '👍'}</span>
                            <span class="text-xs font-bold text-gray-700 mt-1">${count}</span>
                        </button>
                    `;
                }
                container.innerHTML = html;
            });
    }

    window.submitReaction = function(reactionType) {
        const headers = {};
        const tg = window.Telegram && window.Telegram.WebApp;
        if (tg && tg.initData) {
            headers['Authorization'] = `Bearer ${tg.initData}`;
            headers['X-Telegram-Init-Data'] = tg.initData;
        }

        const fd = new FormData();
        fd.append('chapter_id', chapterId);
        fd.append('reaction_type', reactionType);

        fetch('/api/reactions.php', { method: 'POST', headers, body: fd })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    loadReactions();
                } else {
                    alert(data.error || 'Failed to submit reaction.');
                }
            });
    };

    // Also fetch comic title
    fetch(`/api/comics.php?id=${comicId}`)
        .then(res => res.json())
        .then(data => {
            const comic = data.data ? data.data[0] : null;
            if (comic) {
                document.getElementById('comicTitle').innerText = comic.title;
            }
        });

    // Review logic
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
    
    function loadReviews() {
        // Tag chapter comments by prefixing or linking to parent_id.
        // For simplicity we fetch comic reviews and filter if we used a tag. 
        // Better: Backend supports chapter_id on reviews. Since schema doesn't have chapter_id, 
        // we'll fetch comic reviews and show them, but in a real system we'd add chapter_id to reviews table.
        // To avoid modifying DB schema now, we'll just show the comic's reviews here for discussion.
        fetch(`/api/reviews.php?comic_id=${comicId}`)
            .then(res => res.json())
            .then(threads => {
                const list = document.getElementById('reviewsList');
                if (threads.length === 0) {
                    list.innerHTML = '<p class="text-sm text-gray-500">No comments yet. Be the first!</p>';
                    return;
                }

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

                list.innerHTML = threads.map(thread => `
                    <div class="bg-white border rounded-lg p-3 text-sm">
                        <div class="flex items-center gap-2 mb-2">
                            <img src="${thread.photo_url || 'https://via.placeholder.com/32'}" class="w-8 h-8 rounded-full">
                            <div>
                                <div class="flex items-center gap-1.5">
                                    <p class="text-sm font-bold">${escapeHTML(thread.username || 'Anonymous')}</p>
                                    ${getRankHtml(thread.role, thread.pts)}
                                </div>
                                <p class="text-xs text-gray-500">${new Date(thread.created_at).toLocaleString()}</p>
                            </div>
                        </div>
                        <p class="text-gray-800 whitespace-pre-wrap">${escapeHTML(thread.content)}</p>
                        ${thread.image_url ? `<img src="${escapeHTML(thread.image_url)}" class="mt-2 max-w-full h-auto rounded border">` : ''}
                    </div>
                `).join('');
            });
    }

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

    const submitReviewBtn = document.getElementById('submitReviewBtn');
    if (submitReviewBtn) {
        submitReviewBtn.addEventListener('click', () => {
            const content = document.getElementById('reviewContent').value;
            if (!content) return alert('Comment content is required');
            const imageFile = document.getElementById('reviewImage').files[0];

            const fd = new FormData();
            fd.append('action', 'add');
            fd.append('comic_id', comicId);
            fd.append('content', `[Chapter ${chapterId}] ` + content);
            if (imageFile) fd.append('image', imageFile);

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
                        document.getElementById('reviewImage').value = '';
                        if (reviewImageLabel) reviewImageLabel.innerText = 'Attach Image';
                        loadReviews();
                    } else {
                        alert(data.error || 'Failed to submit comment. Ensure you are authenticated.');
                    }
                });
        });
    }

    function updateNavigationButtons() {
        const prevBtns = [document.getElementById('prevBtn'), document.getElementById('bottomPrevBtn')];
        const nextBtns = [document.getElementById('nextBtn'), document.getElementById('bottomNextBtn')];

        if (currentChapterIndex > 0) {
            prevBtns.forEach(btn => {
                btn.disabled = false;
                btn.onclick = () => window.location.href = `reader.html?id=${allChapters[currentChapterIndex - 1].id}&comic_id=${comicId}`;
            });
        } else {
            prevBtns.forEach(btn => btn.disabled = true);
        }

        if (currentChapterIndex < allChapters.length - 1) {
            nextBtns.forEach(btn => {
                btn.disabled = false;
                btn.onclick = () => window.location.href = `reader.html?id=${allChapters[currentChapterIndex + 1].id}&comic_id=${comicId}`;
            });
        } else {
            nextBtns.forEach(btn => btn.disabled = true);
        }
    }

    async function loadPDF(url) {
        if (!url) {
            document.getElementById('loadingIndicator').innerText = "No PDF URL found.";
            return;
        }

        const container = document.getElementById('readerContainer');
        const indicator = document.getElementById('loadingIndicator');

        try {
            const headers = {};
            const tg = window.Telegram && window.Telegram.WebApp;
        if (tg && tg.initData) {
            headers['Authorization'] = `Bearer ${tg.initData}`;
            headers['X-Telegram-Init-Data'] = tg.initData;
        }

            // Load PDF document
            const pdfDoc = await pdfjsLib.getDocument({ url: url, httpHeaders: headers }).promise;
            indicator.style.display = 'none';

            // Loop through pages and render them as a long strip
            for (let pageNum = 1; pageNum <= pdfDoc.numPages; pageNum++) {
                const page = await pdfDoc.getPage(pageNum);
                
                // Determine the scale based on container width to make it 100% full width
                const unscaledViewport = page.getViewport({ scale: 1 });
                const containerWidth = container.clientWidth;
                const scale = containerWidth / unscaledViewport.width;
                const viewport = page.getViewport({ scale: scale });
                
                // Create canvas and wrapper
                const wrapper = document.createElement('div');
                wrapper.className = 'page-container';
                wrapper.style.width = '100%';
                
                const canvas = document.createElement('canvas');
                const ctx = canvas.getContext('2d');
                
                // We use CSS for display width and canvas properties for resolution
                canvas.style.width = '100%';
                canvas.style.height = 'auto';

                canvas.width = viewport.width;
                canvas.height = viewport.height;
                
                // Render page on canvas
                const renderContext = {
                    canvasContext: ctx,
                    viewport: viewport
                };
                
                wrapper.appendChild(canvas);
                container.appendChild(wrapper);

                // Await render to keep sequential order and avoid overloading memory
                await page.render(renderContext).promise;
            }
        } catch (err) {
            console.error(err);
            indicator.innerText = "Error loading PDF.";
        }
    }
});