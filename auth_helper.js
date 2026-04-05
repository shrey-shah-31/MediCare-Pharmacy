/**
 * auth_helper.js
 * Globally attaches authentication tokens using the HTTP Interceptor pattern.
 * Safely refreshes the core Firebase auth token via REST API if expired.
 */

const FIREBASE_API_KEY = 'AIzaSyB6sdvexQIxMKihrUJAEwBHl71_Znagv1U';

window.getValidToken = async function(forceRefresh = false) {
    let token = localStorage.getItem('idToken');
    let refreshToken = localStorage.getItem('refreshToken');
    let expiresAt = localStorage.getItem('tokenExpiresAt');
    
    const now = Date.now();
    const isExpired = !expiresAt || now >= (parseInt(expiresAt) - 300000);
    
    if ((!token || isExpired || forceRefresh) && refreshToken) {
        console.log("Token missing/expired. Refreshing via REST API...");
        try {
            const url = `https://securetoken.googleapis.com/v1/token?key=${FIREBASE_API_KEY}`;
            const res = await fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `grant_type=refresh_token&refresh_token=${refreshToken}`
            });
            const data = await res.json();
            
            if (data.id_token) {
                token = data.id_token;
                localStorage.setItem('idToken', token);
                localStorage.setItem('tokenExpiresAt', Date.now() + (parseInt(data.expires_in) * 1000));
                
                if (data.refresh_token) {
                    localStorage.setItem('refreshToken', data.refresh_token);
                }
            }
        } catch(e) {
            console.error("Network error during token refresh", e);
        }
    }
    
    if (!token) {
        // Redirection if absolutely no valid token can be acquired
        window.location.href = 'auth.html';
        return null;
    }
    
    return token;
};

window.fetchWithAuth = async function(url, options = {}, retries = 1) {
    let token = await window.getValidToken();

    if (!token) {
        console.warn('No valid auth token found! Proceeding anonymously.');
    }

    // Attach Bearer Token Header securely
    const headers = options.headers ? new Headers(options.headers) : new Headers();
    if (token) {
        headers.set('Authorization', `Bearer ${token}`);
    }
    
    // Explicitly enforce application/json if sending a stringified body
    if (options.body && typeof options.body === 'string' && !headers.has('Content-Type')) {
        headers.set('Content-Type', 'application/json');
    }

    options.headers = headers;

    let response = await fetch(url, options);

    // If unauthorized, attempt exactly one Token Refresh Retry Loop.
    if (response.status === 401 && retries > 0) {
        console.warn('Received 401, forcing token refresh and retrying...');
        token = await window.getValidToken(true); // force refresh
        
        if (token) {
            headers.set('Authorization', `Bearer ${token}`);
            options.headers = headers;
            response = await fetch(url, options);
        }
    }

    return response;
};
