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

// 登入後更新 auth header，讓 presence channel 授權帶正確 token
export function updateEchoToken() {
    const token = getAuthToken()
    const bearer = `Bearer ${token}`
    echo.options.auth.headers.Authorization = bearer
    // 同步更新 Pusher 內部 config（避免 Pusher.js 持有舊的 header copy）
    if (echo.connector?.pusher?.config?.auth?.headers) {
        echo.connector.pusher.config.auth.headers.Authorization = bearer
    }
    // 不 disconnect/connect：避免中斷 BookingChat 已訂閱的 presence channel
}

export default echo
