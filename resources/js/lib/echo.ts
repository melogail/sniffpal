import { configureEcho } from '@laravel/echo-vue';

/**
 * Imported for its side effect by every component that subscribes. Living
 * here rather than in `app.ts` keeps `laravel-echo` and `pusher-js` out of
 * the entry chunk a guest downloads: the socket only matters once a
 * signed-in component mounts. Echo reads its Reverb connection from the
 * VITE_REVERB_* env; the socket is only opened by the first subscriber.
 */
configureEcho({ broadcaster: 'reverb' });
