import Echo from 'laravel-echo'
import Pusher from 'pusher-js'

window.Pusher = Pusher

function getAuthToken() {
    return localStorage.getItem('coach_token') || localStorage.getItem('token') || null
}

const echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: import.meta.env.VITE_REVERB_PORT ?? 443,
    wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
    enabledTransports: ['ws', 'wss'],
    authEndpoint: `${import.meta.env.VITE_API_URL}/api/broadcasting/auth`,
    auth: {
        headers: {
            Authorization: `Bearer ${getAuthToken()}`,
            Accept: 'application/json',
        },
    },
})

// 登入後更新 auth header 並強制重連，確保 Reverb 用新 token 授權 channel
export function updateEchoToken() {
    const token = getAuthToken()
    const bearer = `Bearer ${token}`
    echo.options.auth.headers.Authorization = bearer
    if (echo.connector?.pusher?.config?.auth?.headers) {
        echo.connector.pusher.config.auth.headers.Authorization = bearer
    }
    echo.disconnect()
    echo.connect()
}

export default echo
