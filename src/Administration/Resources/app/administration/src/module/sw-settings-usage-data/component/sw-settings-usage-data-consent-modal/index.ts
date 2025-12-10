/**
 * @sw-package framework
 */
import { MtModal, MtModalAction, MtModalRoot } from '@shopware-ag/meteor-component-library';
import useConsentStore from 'src/core/consent/consent.store';
import template from './sw-settings-usage-data-consent-modal.html.twig';
import './sw-settings-usage-data-consent-modal.scss';

import SwSettingsUsageDataStoreDataConsentCard from './subcomponents/sw-settings-usage-data-store-data-consent-card';
import SwSettingsUsageDataUserDataConsentCard from './subcomponents/sw-settings-usage-data-user-data-consent-card';
import SwSettingsUsageDataConsentCheckList from './subcomponents/sw-settings-usage-data-consent-check-list';

/**
 * @private
 */
export default Shopware.Component.wrapComponentConfig({
    template,

    components: {
        MtModal,
        MtModalRoot,
        MtModalAction,
        SwSettingsUsageDataStoreDataConsentCard,
        SwSettingsUsageDataUserDataConsentCard,
        SwSettingsUsageDataConsentCheckList,
    },

    inject: ['acl'],

    data() {
        return {
            unionPath: Shopware.Filter.getByName('asset')(
                '/administration/administration/static/img/data-sharing/union.svg',
            ),
            initialStoreDataConsent: false,
            initialUserDataConsent: false,
            storeDataConsent: false,
            userDataConsent: false,
        };
    },

    created() {
        const consentStore = useConsentStore();

        this.initialStoreDataConsent = consentStore.isAccepted('backend_data_consent');
        this.storeDataConsent = this.initialStoreDataConsent;

        this.initialUserDataConsent = consentStore.isAccepted('tracking_consent');
        this.userDataConsent = this.initialUserDataConsent;
    },

    computed: {
        showConsentModal() {
            return true;
        },

        showStoreDataConsent() {
            if (this.initialStoreDataConsent) {
                return false;
            }

            if (!this.acl.can('system.system_config')) {
                return false;
            }

            return true;
        },

        showSavePreferences() {
            if (!this.showStoreDataConsent) {
                return true;
            }

            if (this.storeDataConsent === true || this.userDataConsent === true) {
                return true;
            }

            return false;
        },
    },

    methods: {
        async savePreferences(done: () => void) {
            if (this.storeDataConsent !== this.initialStoreDataConsent) {
                if (this.storeDataConsent) {
                    await useConsentStore().accept('backend_data_consent');
                } else {
                    await useConsentStore().revoke('backend_data_consent');
                }
            }

            if (this.userDataConsent !== this.initialUserDataConsent) {
                if (this.userDataConsent) {
                    await useConsentStore().accept('tracking_consent');
                } else {
                    await useConsentStore().revoke('tracking_consent');
                }
            }

            done()
        },

        async shareAll(done: () => void) {
            const consentStore = useConsentStore();

            await consentStore.accept('tracking_consent');
            await consentStore.accept('backend_data_consent');

            done()
        },

        async shareNothing(done: () => void) {
            const consentStore = useConsentStore();

            await consentStore.revoke('tracking_consent');
            await consentStore.revoke('backend_data_consent');

            done()
        },
    },
});
