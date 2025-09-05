/**
 * @sw-package framework
 */

// eslint-disable-next-line import/no-named-default
import type { RouteLocation, Router } from 'vue-router';
import Analytics from 'analytics'
import amplitudePlugin from '@analytics/amplitude'

/**
 * @private
 */
export default function initializeTracking(): void {
    const analytics = Analytics({
        app: 'shopware/administration',
        plugins: [
            amplitudePlugin({
                apiKey: 'd7a37694a5abd58224663408d109ec84',
                options: {
                    autocapture: false,
                    trackingOptions: {
                        ip_address: false
                    }
                }
            })
        ]
    });

    // Wait until the view is initialized
    void Shopware.Application.viewInitialized.then(() => {
        // todo: check for consent
        // todo: identify user

        const router = Shopware.Application.view?.router as Router;

        router.beforeEach((to: RouteLocation, from: RouteLocation) => {
            console.log('[Tracking] beforeEach', to, from);
            analytics.page({ from: from.name, to: to.name });
        });
    });
}

// todo: add global event listeners