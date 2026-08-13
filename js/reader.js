document.addEventListener('DOMContentLoaded', () => {
    const urlParams = new URLSearchParams(window.location.search);
    const chapterId = urlParams.get('id');
    const comicId = urlParams.get('comic_id');

    if (!chapterId || !comicId) {
        document.getElementById('loadingIndicator').innerText = "Invalid chapter or comic ID.";
        return;
    }

    let allChapters = [];
    let currentChapterIndex = -1;

    // Fetch chapter list to handle prev/next navigation
    fetch(`/api/chapters.php?comic_id=${comicId}`)
        .then(res => res.json())
        .then(chapters => {
            // Sort ascending by chapter_number for easy navigation
            allChapters = chapters.sort((a, b) => parseFloat(a.chapter_number) - parseFloat(b.chapter_number));
            currentChapterIndex = allChapters.findIndex(c => c.id == chapterId);
            
            if (currentChapterIndex !== -1) {
                const chapter = allChapters[currentChapterIndex];
                document.getElementById('chapterTitle').innerText = `Chapter ${chapter.chapter_number} ${chapter.title ? '- ' + chapter.title : ''}`;
                
                updateNavigationButtons();
                loadPDF(chapter.pdf_url);
            } else {
                document.getElementById('loadingIndicator').innerText = "Chapter not found.";
            }
        });

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

                list.innerHTML = threads.map(thread => `
                    <div class="bg-white border rounded-lg p-3 text-sm">
                        <div class="flex items-center gap-2 mb-2">
                            <img src="${thread.photo_url || 'https://via.placeholder.com/32'}" class="w-8 h-8 rounded-full">
                            <div>
                                <p class="font-bold">${escapeHTML(thread.username || 'Anonymous')}</p>
                                <p class="text-xs text-gray-500">${new Date(thread.created_at).toLocaleString()}</p>
                            </div>
                        </div>
                        <p class="text-gray-800 whitespace-pre-wrap">${escapeHTML(thread.content)}</p>
                    </div>
                `).join('');
            });
    }

    loadReviews();

    const submitReviewBtn = document.getElementById('submitReviewBtn');
    if (submitReviewBtn) {
        submitReviewBtn.addEventListener('click', () => {
            const content = document.getElementById('reviewContent').value;
            if (!content) return alert('Comment content is required');

            const fd = new FormData();
            fd.append('action', 'add');
            fd.append('comic_id', comicId);
            fd.append('content', `[Chapter ${chapterId}] ` + content);

            const headers = {};
            const tg = window.Telegram && window.Telegram.WebApp;
            if (tg && tg.initData) headers['Authorization'] = `Bearer ${tg.initData}`;

            fetch('/api/reviews.php', { method: 'POST', headers, body: fd })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('reviewContent').value = '';
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
            // Load PDF document
            const pdfDoc = await pdfjsLib.getDocument(url).promise;
            indicator.style.display = 'none';

            // Loop through pages and render them as a long strip
            for (let pageNum = 1; pageNum <= pdfDoc.numPages; pageNum++) {
                const page = await pdfDoc.getPage(pageNum);
                
                // Set scale based on container width or use fixed scale
                const viewport = page.getViewport({ scale: 1.5 }); // Base scale
                
                // Create canvas and wrapper
                const wrapper = document.createElement('div');
                wrapper.className = 'page-container';
                
                const canvas = document.createElement('canvas');
                const ctx = canvas.getContext('2d');
                
                canvas.height = viewport.height;
                canvas.width = viewport.width;
                
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