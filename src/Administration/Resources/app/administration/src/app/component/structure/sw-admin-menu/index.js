import template from './sw-admin-menu.html.twig';
import './sw-admin-menu.scss';

const { Mixin } = Shopware;
const { dom } = Shopware.Utils;

/**
 * @sw-package framework
 *
 * @private
 */
export default {
    template,

    inject: [
        'menuService',
        'loginService',
        'userService',
        'appModulesService',
        'feature',
        'customEntityDefinitionService',
    ],

    mixins: [
        Mixin.getByName('notification'),
    ],

    props: {
        mouseLocationsTracked: {
            type: Number,
            required: false,
            default() {
                return 3;
            },
        },
        subMenuDelay: {
            type: Number,
            required: false,
            default() {
                return 150;
            },
        },
    },

    data() {
        return {
            subMenuTimer: null,
            mouseLocations: [],
            lastDelayLocation: null,
            activeEntry: null,
            isOffCanvasShown: false,
            isUserActionsActive: false,
            subMenuOpen: false,
            scrollbarOffset: '',
            isUserLoading: true,
        };
    },

    computed: {
        currentUser() {
            return Shopware.Store.get('session').currentUser;
        },

        isExpanded() {
            return this.adminMenuStore.isExpanded;
        },

        userTitle() {
            if (this.currentUser && this.currentUser.admin) {
                return this.$tc('global.sw-admin-menu.administrator');
            }

            if (this.currentUser && this.currentUser.title && this.currentUser.title.length > 0) {
                return this.currentUser.title;
            }

            if (this.currentUser && this.currentUser.aclRoles && this.currentUser.aclRoles.length > 0) {
                return this.currentUser.aclRoles[0].name;
            }

            if (this.currentUser && this.currentUser.title) {
                return this.currentUser.title;
            }

            return '';
        },

        currentLocale() {
            return Shopware.Store.get('session').currentLocale;
        },

        currentExpandedMenuEntries() {
            return this.adminMenuStore.expandedEntries;
        },

        adminModuleNavigation() {
            const adminModuleNavigationEntries = this.adminMenuStore.adminModuleNavigation;

            // Throw an console error if navigation entry is on level 4 or higher. Also remove the navigation entry from menu
            return adminModuleNavigationEntries.filter((entry) => {
                const levelOneParent = adminModuleNavigationEntries.find((e) => entry.parent && e.id === entry.parent);
                // eslint-disable-next-line max-len
                const levelTwoParent = adminModuleNavigationEntries.find(
                    (e) => levelOneParent?.parent && e.id === levelOneParent?.parent,
                );
                // eslint-disable-next-line max-len
                const levelThreeParent = adminModuleNavigationEntries.find(
                    (e) => levelTwoParent?.parent && e.id === levelTwoParent?.parent,
                );

                if (levelThreeParent) {
                    Shopware.Utils.debug.error(
                        new Error(
                            `The navigation entry "${entry.id}" is nested on level 4 or higher.\
The admin menu only supports up to three levels of nesting.`,
                        ),
                    );

                    return false;
                }

                return true;
            });
        },

        appModuleNavigation() {
            return this.adminMenuStore.appModuleNavigation;
        },

        navigationEntries() {
            return [
                ...this.adminModuleNavigation,
                ...this.appModuleNavigation,
                ...this.extensionModuleNavigation,
                ...this.customEntityDefinitionService.getMenuEntries(),
            ];
        },

        mainMenuEntries() {
            const tree = new Shopware.Helper.FlatTreeHelper((first, second) => first.position - second.position);

            this.navigationEntries.forEach((module) => tree.add(module));

            return tree.convertToTree();
        },

        sidebarCollapseIcon() {
            return this.isExpanded ? 'regular-chevron-circle-left' : 'regular-chevron-circle-right';
        },

        userActionsToggleIcon() {
            return this.isUserActionsActive ? 'regular-chevron-down-xs' : 'regular-chevron-up-xs';
        },

        scrollbarOffsetStyle() {
            return {
                right: this.scrollbarOffset,
                'margin-left': this.scrollbarOffset,
            };
        },

        adminMenuClasses() {
            return {
                'is--expanded': this.isExpanded,
                'is--collapsed': !this.isExpanded,
                'is--off-canvas-shown': this.isOffCanvasShown,
            };
        },

        userName() {
            if (!this.currentUser) {
                return '';
            }

            return `${this.currentUser.firstName} ${this.currentUser.lastName}`;
        },

        avatarUrl() {
            if (this.currentUser && this.currentUser.avatarMedia) {
                return this.currentUser.avatarMedia.url;
            }

            return null;
        },

        firstName() {
            return this.currentUser ? this.currentUser.firstName : '';
        },

        lastName() {
            return this.currentUser ? this.currentUser.lastName : '';
        },

        extensionMenuItems() {
            return Shopware.Store.get('menuItem').menuItems;
        },

        extensionModuleNavigation() {
            return this.extensionMenuItems.map((extensionMenuItem) => {
                return {
                    id: Shopware.Utils.createId(),
                    label: extensionMenuItem.label,
                    position: extensionMenuItem.position ?? 110,
                    parent: extensionMenuItem.parent ?? 'sw-extension',
                    moduleType: 'plugin',
                    path: 'sw.extension.sdk.index',
                    params: {
                        id: extensionMenuItem.moduleId,
                    },
                };
            });
        },

        adminMenuStore() {
            return Shopware.Store.get('adminMenu');
        },
    },

    watch: {
        isExpanded() {
            this.toggleSidebar();
        },
    },

    created() {
        this.createdComponent();
    },

    mounted() {
        this.mountedComponent();
    },

    beforeUnmount() {
        document.removeEventListener('mousemove', this.onMouseMoveDocument.bind(this));

        this.beforeUnmountedComponent();
    },

    methods: {
        createdComponent() {
            this.loginService.notifyOnLoginListener();

            this.collapseMenuOnSmallViewports();
            this.getUser();

            Shopware.Utils.EventBus.on('sw-admin-menu/toggle-offcanvas', this.onToggleCanvas);

            this.initNavigation();
        },

        beforeUnmountedComponent() {
            Shopware.Utils.EventBus.off('sw-admin-menu/toggle-offcanvas', this.onToggleCanvas);
        },

        onToggleCanvas(state) {
            this.isOffCanvasShown = state;
        },

        initNavigation() {
            this.adminMenuStore.adminModuleNavigation = this.menuService.getNavigationFromAdminModules();

            this.refreshApps();
        },

        refreshApps() {
            return this.appModulesService.fetchAppModules().then((modules) => {
                Shopware.Store.get('shopwareApps').apps = modules;
            });
        },

        collapseAdminMenu() {
            this.adminMenuStore.collapseSidebar();
        },

        expandAdminMenu() {
            this.adminMenuStore.expandSidebar();
        },

        mountedComponent() {
            const that = this;

            this.$device.onResize({
                listener() {
                    that.collapseMenuOnSmallViewports();
                },
                component: this,
            });

            document.addEventListener('mousemove', this.onMouseMoveDocument.bind(this));

            this.addScrollbarOffset();
        },

        getUser() {
            this.isUserLoading = true;

            this.userService.getUser().then((response) => {
                const userData = response.data;
                delete userData.password;

                Shopware.Store.get('session').setCurrentUser(userData);

                this.isUserLoading = false;
            });
        },

        collapseMenuOnSmallViewports() {
            if (this.$device.getViewportWidth() <= 1200 && this.$device.getViewportWidth() >= 500) {
                this.collapseAdminMenu();
            }

            if (this.$device.getViewportWidth() <= 500) {
                this.expandAdminMenu();
            }
        },

        isActiveItem(menuItem) {
            return this.isExpanded && menuItem.classList.contains('router-link-active');
        },

        onToggleSidebar() {
            if (this.isExpanded) {
                this.collapseAdminMenu();
            } else {
                this.expandAdminMenu();
            }

            this.toggleSidebar();
        },

        toggleSidebar() {
            if (!this.isExpanded) {
                this.removeClassesFromElements(
                    Array.from(this.$el.querySelectorAll('.sw-admin-menu__navigation-list-item')),
                    ['is--entry-expanded'],
                );

                const currentActiveElement = this.$el.querySelector('a.router-link-active');
                const currentActiveParentElement = currentActiveElement?.parentElement;
                const parentIsFirstLevel = currentActiveParentElement?.classList?.contains('navigation-list-item__level-1');

                const ignoreElementsList = [currentActiveParentElement];

                if (currentActiveElement && !parentIsFirstLevel) {
                    const mainMenuListItem = currentActiveElement.closest(
                        '.navigation-list-item__level-1.navigation-list-item__has-children',
                    );
                    if (mainMenuListItem?.firstElementChild) {
                        ignoreElementsList.push(mainMenuListItem.firstElementChild);
                    }
                }

                this.removeClassesFromElements(
                    Array.from(
                        this.$el.querySelectorAll(
                            '.navigation-list-item__level-1.navigation-list-item__has-children > .router-link-active',
                        ),
                    ),
                    ['router-link-active'],
                    ignoreElementsList,
                );
            }

            this.isUserActionsActive = false;
        },

        onToggleUserActions() {
            if (this.isUserLoading) {
                return false;
            }
            this.isUserActionsActive = !this.isUserActionsActive;
            return true;
        },

        openUserActions() {
            if (this.isExpanded || this.isUserLoading) {
                return;
            }

            this.isUserActionsActive = true;
        },

        closeUserActions() {
            if (this.isExpanded) {
                return;
            }

            this.isUserActionsActive = false;
        },

        onLogoutUser() {
            this.loginService.logout();
            this.adminMenuStore.clearExpandedMenuEntries();
            Shopware.Store.get('session').removeCurrentUser();
            Shopware.Store.get('notification').clearGrowlNotificationsForCurrentUser();
            Shopware.Store.get('notification').clearNotificationsForCurrentUser();
            this.$router.push({
                name: 'sw.login.index',
            });
        },

        addScrollbarOffset() {
            const offset = dom.getScrollbarWidth(this.$refs.swAdminMenuBody);

            this.scrollbarOffset = `-${offset}px`;
        },

        onMouseMoveDocument(event) {
            this.mouseLocations.push({
                x: event.pageX,
                y: event.pageY,
            });

            // Mouse locations array exceeds the configured threshold
            if (this.mouseLocations.length > this.mouseLocationsTracked) {
                this.mouseLocations.shift();
            }
        },

        onMenuItemClick(entry, eventTarget) {
            const target = eventTarget.closest('.sw-admin-menu__navigation-list-item');
            const level = entry.level;

            if (!this.isExpanded) {
                this.expandAdminMenu();
            }

            // Clear previous delay of the menu
            if (this.subMenuTimer) {
                window.clearTimeout(this.subMenuTimer);
            }

            if (level > 1 || !target.classList.contains('navigation-list-item__has-children')) {
                return;
            }

            const firstChild = target.firstChild;
            this.removeClassesFromElements(
                Array.from(this.$el.querySelectorAll('.sw-admin-menu__navigation-list-item')),
                ['is--entry-expanded'],
                [
                    target,
                    firstChild,
                ],
            );

            const isEntryExpanded = target.classList.contains('is--entry-expanded');

            if (isEntryExpanded) {
                this.adminMenuStore.collapseMenuEntry(entry);

                firstChild.classList.remove('is--entry-expanded');
            } else {
                this.adminMenuStore.clearExpandedMenuEntries();
                this.adminMenuStore.expandMenuEntry(entry);

                target.classList.add('is--entry-expanded');
            }
        },

        getChildren(entry) {
            return entry.children.filter((child) => {
                if (!child.privilege) {
                    return true;
                }

                return this.acl.can(child.privilege);
            });
        },

        deactivatePreviousMenuItem() {
            if (this.activeEntry && this.activeEntry.target) {
                this.activeEntry.target.classList.remove('is--flyout-enabled');
            }
            this.activeEntry = [];
        },

        removeClassesFromElements(elements, classList, ignoreElementsList = []) {
            elements.forEach((element) => {
                if (ignoreElementsList.includes(element)) {
                    return;
                }
                element.classList.remove(classList);
            });
        },
    },
};
