/**
 * @sw-package framework
 */

// eslint-disable-next-line import/no-named-default
import { type RouteLocation, type Router } from 'vue-router';
import * as amplitude from '@amplitude/analytics-browser';
import { BaseEvent, EventOptions } from '@amplitude/analytics-browser/lib/esm/types';

// todo: fetch user consent
const userConsent = true;

let currentRoute: RouteLocation | null = null;

/**
 * @private
 */
export default function initializeTracking(): void {
    if (!userConsent) {
        return;
    }

    // Wait until the view is initialized
    void Shopware.Application.viewInitialized.then(() => {
        // todo: check for consent
        // todo: identify user

        amplitude.init('', undefined, {
            autocapture: false,
            appVersion: Shopware.Store.get('context').app.config.version as string,
            trackingOptions: {
                ipAddress: false,
            },
            serverUrl: 'https://jwib85zthc.execute-api.eu-central-1.amazonaws.com/amplitude2'
        });

        const router = Shopware.Application.view?.router as Router;

        router.afterEach((to: RouteLocation, from: RouteLocation) => {
            if (from.name === to.name) {
                return;
            }

            console.log('[Product Analytics] afterEach', to, from);

            currentRoute = to;

            amplitude.track('Page Viewed', { sw_route_from: from.name, sw_route_to: to.name, ...defaultEventProperties() });
        });
    });
}

export function track(event: BaseEvent | string, properties?: Record<string, any>, options?: EventOptions) {
    if (!Shopware.Application.view?.router) {
        console.warn('[Product Analytics] Tracker is not yet initialized.');

        return;
    }

    amplitude.track(event, { ...(properties || {}), ...defaultEventProperties() }, options);
}

function defaultEventProperties(): Record<string, any> {
    return {
        sw_version: Shopware.Store.get('context').app.config.version,
        // @ts-expect-error
        sw_module: currentRoute?.meta.$module.name,
        sw_route: currentRoute?.name,
        sw_user_language: Shopware.Store.get('session').currentLocale,
        sw_user_is_admin: Shopware.Store.get('session').currentUser?.admin === true,
        sw_user_timezone: Shopware.Store.get('session').currentUser?.timeZone,
        sw_shop_id: Shopware.Store.get('context').app.config.shopId,
        sw_environment: Shopware.Store.get('context').app.environment,
    };
}

// Function to handle button clicks
function handleButtonClick(event: MouseEvent) {
    let target = event.target as HTMLElement | null;

    // Walk up the DOM tree until a <button> is found
    while (target && target !== document.body) {
        if (target.tagName === 'BUTTON') {
            console.log('[Product Analytics] Button Clicked:', target);

            track('Button Clicked', {
                sw_button_text: target.innerText,
                sw_button_action: target.getAttribute('data-product-analytics-button-action') || undefined,
                sw_button_id: target.getAttribute('data-product-analytics-button-id') || target.id || undefined,
            });

            break;
        }

        target = target.parentElement;
    }
}

// Add a global listener for click events
document.addEventListener('click', handleButtonClick);