/**
 * Analytics events, fanned out to Google Analytics and the Meta pixel.
 *
 * `gtag` and `fbq` exist only when the root Blade view rendered their tags
 * (`GOOGLE_TAG_ID` / `META_PIXEL_ID` set), so every call is a no-op locally,
 * in tests and on the SSR server. Event names are GA4 key events on the
 * property: sign_up, pet_posted, conversation_started, share. Two of them
 * also reach Meta under the names in `META_EVENTS`: the standard
 * CompleteRegistration lets ads optimise on registrations, the custom
 * listing_published keeps the marketing plan's vocabulary.
 */
declare global {
    interface Window {
        gtag?: (
            command: 'event',
            name: string,
            params?: Record<string, unknown>,
        ) => void;
        fbq?: (
            command: 'track' | 'trackCustom',
            name: string,
            params?: Record<string, unknown>,
        ) => void;
    }
}

const META_EVENTS: Record<
    string,
    { command: 'track' | 'trackCustom'; name: string }
> = {
    sign_up: { command: 'track', name: 'CompleteRegistration' },
    pet_posted: { command: 'trackCustom', name: 'listing_published' },
};

export function trackEvent(
    name: string,
    params: Record<string, unknown> = {},
): void {
    if (typeof window === 'undefined') {
        return;
    }

    window.gtag?.('event', name, params);

    const meta = META_EVENTS[name];

    if (meta) {
        window.fbq?.(meta.command, meta.name, params);
    }
}
