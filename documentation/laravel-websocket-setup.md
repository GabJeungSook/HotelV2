# Laravel WebSocket Setup

## Packages Required

```bash
# WebSocket server (self-hosted Pusher alternative)
composer require beyondcode/laravel-websockets -w

# Pusher PHP SDK (already installed)
composer require pusher/pusher-php-server -W
```

## Environment Variables (.env)

```env
BROADCAST_DRIVER=pusher

PUSHER_APP_ID=local
PUSHER_APP_KEY=local
PUSHER_APP_SECRET=local
PUSHER_HOST=127.0.0.1
PUSHER_PORT=6001
PUSHER_SCHEME=http
PUSHER_APP_CLUSTER=mt1
```

## Running WebSocket Server

```bash
php artisan websockets:serve
```

Dashboard available at: `http://localhost:8000/laravel-websockets`

## References

- Video Tutorial: https://www.youtube.com/watch?v=QOq_4SYzCmA
- Laravel Echo + Livewire: https://github.com/livewire/livewire/discussions/4831

## Frontend Setup (resources/js/bootstrap.js)

```javascript
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

window.Echo = new Echo({
    broadcaster: 'pusher',
    key: import.meta.env.VITE_PUSHER_APP_KEY,
    cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER,
    wsHost: import.meta.env.VITE_PUSHER_HOST ?? `ws-${import.meta.env.VITE_PUSHER_APP_CLUSTER}.pusher.com`,
    wsPort: import.meta.env.VITE_PUSHER_PORT ?? 80,
    wssPort: import.meta.env.VITE_PUSHER_PORT ?? 443,
    forceTLS: false,
    enabledTransports: ['ws', 'wss'],
    disableStats: true,
});
```
