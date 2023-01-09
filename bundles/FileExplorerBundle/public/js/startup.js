pimcore.registerNS("pimcore.bundle.file_explorer.startup");

pimcore.bundle.file_explorer.startup = Class.create({

    initialize: function () {
        document.addEventListener(pimcore.events.preMenuBuild, this.preMenuBuild.bind(this));
    },

    preMenuBuild: function (event) {
        const menu = event.detail.menu;
        this.user = pimcore.globalmanager.get('user');
        this.toolbar = pimcore.globalmanager.get('layout_toolbar');
        const systemInfoMenuItems = this.getSystemInfoMenu();

        const filteredMenu = menu.extras.items.filter(function (item) {
            return item.itemId === 'pimcore_menu_extras_system_info';
        });

        if (filteredMenu.length > 0) {
            const systemInfoMenu = filteredMenu.shift();
            systemInfoMenuItems.map(function(item) {
                systemInfoMenu.menu.items.push(item);
            });
        } else {
            menu.extras.items.push({
                text: t("system_infos_and_tools"),
                iconCls: "pimcore_nav_icon_info",
                hideOnClick: false,
                itemId: 'pimcore_menu_extras_system_info',
                menu: {
                    cls: "pimcore_navigation_flyout",
                    shadow: false,
                    items: systemInfoMenuItems
                }
            })
        }
    },

    getSystemInfoMenu: function () {
        const items = [];

        const user = pimcore.globalmanager.get('user');
        var perspectiveCfg = pimcore.globalmanager.get("perspective");

        if (
            user.admin &&
            perspectiveCfg.inToolbar("extras") &&
            perspectiveCfg.inToolbar("extras.systemtools") &&
            perspectiveCfg.inToolbar("extras.systemtools.fileexplorer")
        ) {
            items.push({
                text: t("server_fileexplorer"),
                iconCls: "pimcore_nav_icon_fileexplorer",
                itemId: 'pimcore_menu_extras_system_info_server_fileexplorer',
                handler: this.showFileExplorer
            });
        }

        return items;
    },

    pimcoreReady: function(e) {
        const user = pimcore.globalmanager.get('user');
        var perspectiveCfg = pimcore.globalmanager.get("perspective");

        var toolbar = pimcore.globalmanager.get('layout_toolbar');

        if (
            user.admin &&
            perspectiveCfg.inToolbar("extras") &&
            perspectiveCfg.inToolbar("extras.systemtools") &&
            perspectiveCfg.inToolbar("extras.systemtools.fileexplorer")
        ) {
            const index = toolbar.extrasMenu.items.keys.indexOf('pimcore_menu_extras_system_info');
            const systemInfoMenu  = toolbar.extrasMenu.items.items[index];

            systemInfoMenu.getMenu().add(
                {
                    text: t("server_fileexplorer"),
                    iconCls: "pimcore_nav_icon_fileexplorer",
                    itemId: 'pimcore_menu_extras_system_info_server_file_explorer',
                    handler: this.showFileExplorer
                }
            )
        }
    },

    showFileExplorer: function () {
        try {
            pimcore.globalmanager.get("file_explorer").activate();
        } catch (e) {
            pimcore.globalmanager.add("file_explorer", new pimcore.bundle.file_explorer.settings.explorer());
        }
    },
})

const fileexplorer = new pimcore.bundle.file_explorer.startup();