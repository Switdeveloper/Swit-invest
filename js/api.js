const API_BASE = 'api';

const api = {
    async proxy(endpoint, body) {
        const url = `${API_BASE}/proxy.php?endpoint=${encodeURIComponent(endpoint)}`;
        const res = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(body)
        });
        return res;
    },

    async getProviders(token) {
        const res = await fetch(`${API_BASE}/settings.php?action=list`, {
            headers: { 'Authorization': `Bearer ${token}` }
        });
        return res.json();
    },

    async saveProvider(token, data) {
        const res = await fetch(`${API_BASE}/settings.php?action=save`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${token}`
            },
            body: JSON.stringify(data)
        });
        return res.json();
    },

    async deleteProvider(token, id) {
        const res = await fetch(`${API_BASE}/settings.php?action=delete&id=${id}`, {
            method: 'DELETE',
            headers: { 'Authorization': `Bearer ${token}` }
        });
        return res.json();
    },

    async authenticate(password) {
        const res = await fetch(`${API_BASE}/settings.php?action=auth`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ password })
        });
        return res.json();
    },

    async checkAuth(token) {
        const res = await fetch(`${API_BASE}/settings.php?action=check&token=${encodeURIComponent(token)}`);
        return res.json();
    }
};
