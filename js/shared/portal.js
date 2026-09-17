(() => {
    'use strict';

    let csrfToken = '';
    const logout = async button => {
        button.disabled = true;
        const originalText = button.textContent;
        button.textContent = 'Signing out…';
        try {
            await axios.post('api/auth.php?action=logout', {}, { headers: { 'X-CSRF-Token': csrfToken } });
        } finally {
            button.textContent = originalText;
            window.location.replace('./');
        }
    };

    axios.get('api/auth.php?action=session')
        .then(async response => {
            if (!response.data.authenticated || !response.data.user) {
                window.location.replace('./');
                return;
            }

            csrfToken = response.data.csrf_token;
            document.querySelector('[data-name]').textContent = response.data.user.first_name || response.data.user.username;
            document.querySelector('[data-role]').textContent = response.data.user.role || 'Account';
            await window.RequiredPasswordGate.open(response.data.user, csrfToken);
        })
        .catch(() => window.location.replace('./'));

    document.querySelector('[data-logout]').addEventListener('click', event => logout(event.currentTarget));
})();
