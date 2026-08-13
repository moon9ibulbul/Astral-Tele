document.addEventListener('DOMContentLoaded', () => {
    const tg = window.Telegram && window.Telegram.WebApp;
    if (tg) tg.expand();

    const headers = {};
    if (tg && tg.initData) headers['Authorization'] = `Bearer ${tg.initData}`;

    const profileImg = document.getElementById('profileImg');
    const usernameDisplay = document.getElementById('usernameDisplay');
    const fullNameDisplay = document.getElementById('fullNameDisplay');
    const usernameInput = document.getElementById('usernameInput');
    const photoInput = document.getElementById('profilePhotoInput');
    const form = document.getElementById('profileForm');
    const message = document.getElementById('profileMessage');

    // Load initial profile data
    fetch('/api/profile.php', { headers })
        .then(res => res.json())
        .then(data => {
            if (data.error) {
                fullNameDisplay.innerText = "Error loading profile";
                return;
            }

            if (data.photo_url) {
                profileImg.src = data.photo_url;
            }

            const fullName = [data.first_name, data.last_name].filter(Boolean).join(' ') || 'User';
            fullNameDisplay.innerText = fullName;

            if (data.username) {
                usernameDisplay.innerText = `@${data.username}`;
                usernameInput.value = data.username;
            } else {
                usernameDisplay.innerText = "No username set";
            }
        })
        .catch(err => console.error("Error fetching profile", err));

    // Image preview
    photoInput.addEventListener('change', (e) => {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = (e) => profileImg.src = e.target.result;
            reader.readAsDataURL(file);
        }
    });

    // Save profile
    form.addEventListener('submit', (e) => {
        e.preventDefault();

        const fd = new FormData();
        const username = usernameInput.value.trim();
        if (username) fd.append('username', username);

        const file = photoInput.files[0];
        if (file) fd.append('photo', file);

        message.classList.remove('hidden', 'text-green-600', 'text-red-600');
        message.innerText = 'Saving...';
        message.classList.add('text-gray-500');

        fetch('/api/profile.php', {
            method: 'POST',
            headers,
            body: fd
        })
        .then(res => res.json())
        .then(data => {
            message.classList.remove('text-gray-500');
            if (data.success) {
                message.innerText = 'Profile updated successfully!';
                message.classList.add('text-green-600');
                if (data.username) {
                    usernameDisplay.innerText = `@${data.username}`;
                }
            } else {
                message.innerText = data.error || 'Failed to update profile.';
                message.classList.add('text-red-600');
            }

            setTimeout(() => {
                message.classList.add('hidden');
            }, 3000);
        })
        .catch(err => {
            message.classList.remove('text-gray-500');
            message.innerText = 'An error occurred.';
            message.classList.add('text-red-600');
        });
    });
});