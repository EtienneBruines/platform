import { Component, State } from 'src/core/shopware';
import dom from 'src/core/service/utils/dom.utils';
import template from './sw-admin-menu.html.twig';
import './sw-admin-menu.less';

Component.register('sw-admin-menu', {
    template,

    inject: ['menuService', 'loginService'],

    data() {
        return {
            isExpanded: true,
            isUserActionsActive: false,
            flyoutEntries: [],
            flyoutStyle: {},
            flyoutLabel: '',
            subMenuOpen: false,
            scrollbarOffset: ''
        };
    },

    computed: {
        localeStore() {
            return State.getStore('adminLocale');
        },

        mainMenuEntries() {
            return this.menuService.getMainMenu();
        },

        sidebarCollapseIcon() {
            return this.isExpanded ? 'default-arrow-circle-left' : 'default-arrow-circle-right';
        },

        userActionsToggleIcon() {
            return this.isUserActionsActive ? 'small-arrow-medium-down' : 'small-arrow-medium-up';
        },

        scrollbarOffsetStyle() {
            return {
                right: this.scrollbarOffset,
                'margin-left': this.scrollbarOffset
            };
        }
    },

    mounted() {
        this.addScrollbarOffset();
    },

    methods: {
        openSubMenu(entry, currentTarget) {
            this.subMenuOpen = !this.subMenuOpen;

            if (this.isExpanded) {
                this.flyoutEntries = [];
            }

            this.changeActiveItem(currentTarget.querySelector('.sw-admin-menu__navigation-link'));
        },

        changeActiveItem(target) {
            const mainMenuElement = target.parentNode.parentNode;
            const activeClass = 'router-link-active';
            const listElements = mainMenuElement.querySelectorAll('.sw-admin-menu__navigation-link');

            listElements.forEach((listItem) => {
                listItem.classList.remove(activeClass);
            });

            target.classList.add(activeClass);
        },

        isActiveItem(menuItem) {
            return this.isExpanded && menuItem.classList.contains('router-link-active');
        },

        openFlyout(entry, currentTarget) {
            if (!currentTarget) {
                return false;
            }

            this.flyoutEntries = [];

            const menuItem = currentTarget.querySelector('.sw-admin-menu__navigation-link');

            if (this.isActiveItem(menuItem)) {
                return false;
            }

            this.flyoutEntries = entry.children;
            this.flyoutLabel = entry.label;

            if (!this.isExpanded) {
                this.changeActiveItem(menuItem);
            }

            this.flyoutStyle = {
                top: `${currentTarget.getBoundingClientRect().top}px`,
                'border-color': entry.color
            };

            return true;
        },

        closeFlyout() {
            this.flyoutEntries = [];
        },

        onChangeLanguage() {
            const lastLocale = this.$root.$i18n.locale;
            const newLocale = (lastLocale === 'de-DE' ? 'en-GB' : 'de-DE');

            this.$root.$i18n.locale = newLocale;
            this.localeStore.setLocale(newLocale);
        },

        onToggleSidebar() {
            this.isExpanded = !this.isExpanded;

            if (!this.isExpanded) {
                this.closeFlyout();
            }
        },

        onToggleUserActions() {
            this.isUserActionsActive = !this.isUserActionsActive;
        },

        openUserActions() {
            if (this.isExpanded) {
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
            this.loginService.clearBearerAuthentication();
            this.$router.push({
                name: 'sw.login.index'
            });
        },

        addScrollbarOffset() {
            const offset = dom.getScrollbarWidth(this.$refs.swAdminMenuBody);

            this.scrollbarOffset = `-${offset}px`;
        }
    }
});
