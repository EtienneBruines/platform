import { Component } from 'src/core/shopware';
import template from './sw-admin-menu-item.html.twig';

Component.register('sw-admin-menu-item', {
    template,

    props: {
        entry: {
            type: Object,
            required: true
        },
        displayIcon: {
            type: Boolean,
            default: true,
            required: false
        },
        collapsibleText: {
            type: Boolean,
            default: true,
            required: false
        },
        sidebarExpanded: {
            type: Boolean,
            default: true,
            required: false
        }
    },

    methods: {
        getIconName(name) {
            return `${name}`;
        },

        getItemName(menuItemName) {
            return menuItemName.replace(/\./g, '-');
        }
    }
});
