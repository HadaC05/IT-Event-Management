(() => {
    'use strict';

    let csrfToken = '';

    axios.get('api/auth.php?action=session')
        .then(response => {
            if (!response.data.authenticated || !response.data.user) {
                window.location.replace('./');
                return;
            }

            csrfToken = response.data.csrf_token;
            document.querySelector('[data-name]').textContent = response.data.user.first_name || response.data.user.username;
            document.querySelector('[data-role]').textContent = response.data.user.role || 'Account';
        })
        .catch(() => window.location.replace('./'));

    document.querySelector('[data-logout]').addEventListener('click', async event => {
        const button = event.currentTarget;
        button.disabled = true;
        button.textContent = 'Signing out…';

        try {
            await axios.post('api/auth.php?action=logout', {}, { headers: { 'X-CSRF-Token': csrfToken } });
        } finally {
            window.location.replace('./');
        }
    });
})();
