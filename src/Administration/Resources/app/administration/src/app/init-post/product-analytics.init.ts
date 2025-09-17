/**
 * @sw-package framework
 * @private
 */
export default function initializeTracking(): Promise<void> {
    Shopware.Telemetry.initialize();


    // TODO Remove debug code
    Shopware.Telemetry.addListener((event) => {
        console.log(event.detail);
    })

    return Promise.resolve();
}