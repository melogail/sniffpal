/**
 * Google Analytics events. `gtag` exists only when the root Blade view rendered
 * the Google tag (`GOOGLE_TAG_ID` set), so every call is a no-op locally, in
 * tests and on the SSR server. Event names are GA4 key events on the property:
 * sign_up, pet_posted, conversation_started, share.
 */
declare global {
    interface Window {
        gtag?: (
            command: 'event',
            name: string,
            params?: Record<string, unknown>,
        ) => void;
    }
}

export function trackEvent(
    name: string,
    params: Record<string, unknown> = {},
): void {
    if (typeof window !== 'undefined') {
        window.gtag?.('event', name, params);
    }
}
