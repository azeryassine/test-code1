pimcore.registerNS("pimcore.simpleBackendSearch.layout.quickSearch");

Ext.define('pimcore.simpleBackendSearch.layout.quickSearch', {
    constructor: function () {
        this.createShortcut();
        this.createQuickSearch();
    },

    createQuickSearch: function () {
        const quickSearchStore = this.getQuickSearchStore();
        const quickSearchTpl = this.getQuickSearchTpl();

        const quickSearchContainer = Ext.get('pimcore_quicksearch');
        let quickSearchCombo = Ext.create('Ext.form.ComboBox', {
            width: 900,
            hideTrigger: true,
            border: false,
            shadow: false,
            tpl: quickSearchTpl,
            listConfig: {
                shadow: false,
                border: false,
                cls: 'pimcore_quicksearch_picker',
                navigationModel: 'quicksearch.boundlist',
                listeners: {
                    "highlightitem": function (view, node, opts) {
                        var record = quickSearchStore.getAt(node.dataset.recordindex);
                        if (!record.get('preview')) {
                            Ext.Ajax.request({
                                url: Routing.generate('pimcore_admin_searchadmin_search_quicksearch_by_id'),
                                method: 'GET',
                                params: {
                                    "id": record.get('id'),
                                    "type": record.get('type')
                                },
                                success: function (response) {
                                    var result = Ext.decode(response.responseText);

                                    record.preview = result.preview;
                                    Ext.get('pimcore_quicksearch_preview').setHtml(result.preview);
                                },
                                failure: function () {
                                    var previewHtml = '<div class="no_preview">' + t('preview_not_available') + '</div>';

                                    Ext.get('pimcore_quicksearch_preview').setHtml(previewHtml);
                                }
                            });
                        } else {
                            let previewHtml = record.get('preview');
                            if(!previewHtml) {
                                previewHtml = '<div class="no_preview">' + t('preview_not_available') + '</div>';
                            }

                            Ext.get('pimcore_quicksearch_preview').setHtml(previewHtml);
                        }
                    }
                }
            },
            id: 'quickSearchCombo',
            store: quickSearchStore,
            loadingText: t('searching'),
            queryDelay: 100,
            minChars: 4,
            renderTo: quickSearchContainer,
            enableKeyEvents: true,
            displayField: 'fullpath',
            valueField: "id",
            typeAhead: true,
            listeners: {
                "expand": function (combo) {
                    if(!document.getElementById('pimcore_quicksearch_preview')) {
                        combo.getPicker().getEl().insertHtml('beforeEnd', '<div id="pimcore_quicksearch_preview"></div>');
                    }
                },
                "keyup": function (field) {
                    if(field.getValue()) {
                        quickSearchContainer.addCls('filled');
                    }
                },
                "select": function (combo, record, index) {
                    pimcore.helpers.openElement(record.get('id'), record.get('type'), record.get('subtype'));
                    pimcore.simpleBackendSearch.layout.quickSearch.hide();
                }
            }
        });

        Ext.getBody().on('click', function (event) {
            // hide on click outside
            if (quickSearchContainer && !quickSearchContainer.isAncestor(event.target)) {
                var pickerEl = quickSearchCombo.getPicker().getEl();
                if (!pickerEl || !pickerEl.isAncestor(event.target)) {
                    quickSearchCombo.hide();
                }
            }
        });
    },

    createShortcut: function () {
        new Ext.util.KeyMap({
            target: document,
            binding: [{
                key:  Ext.event.Event.ESC,
                fn: function () {
                    pimcore.simpleBackendSearch.layout.quickSearch.hide();
                }
            }, {
                key: Ext.event.Event.SPACE,
                ctrl: true,
                fn: function (keyCode, e) {
                    e.stopEvent();
                    pimcore.simpleBackendSearch.layout.quickSearch.show();
                }
            }]
        });

        pimcore.helpers.keyBindingMapping.quickSearch = pimcore.simpleBackendSearch.layout.quickSearch.show;
    },

    getQuickSearchStore: function () {
        if(!this.quicksearchStore) {
            this.quicksearchStore = new Ext.data.Store({
                proxy: {
                    type: 'ajax',
                    url: Routing.generate('pimcore_admin_searchadmin_search_quicksearch'),
                    reader: {
                        type: 'json',
                        rootProperty: 'data'
                    }
                },
                listeners: {
                    "beforeload": function (store) {
                        var previewEl = Ext.get('pimcore_quicksearch_preview');
                        if (previewEl) {
                            previewEl.setHtml('');
                        }

                        store.getProxy().abort();
                    }
                },
                fields: ["id", 'type', "subtype", "className", "fullpath"]
            });
        }

        return this.quicksearchStore;
    },

    getQuickSearchTpl: function () {
        if(!this.quickSearchTpl){
            this.quickSearchTpl = new Ext.XTemplate(
                '<tpl for=".">',
                '<li role="option" unselectable="on" class="x-boundlist-item">' +
                '<div class="list-icon {iconCls}"><tpl if="icon"><img class="class-icon" src="{icon}"></tpl></div>' +
                '<div class="list-path" title="{fullpath}">{fullpathList}</div>' +
                '</li>',
                '</tpl>'
            );
        }

        return this.quickSearchTpl;
    },

    statics: {
        show: function () {
            // close all windows, tooltips and previews
            // we use each() because .hideAll() doesn't hide the modal (seems to be an ExtJS bug)
            Ext.WindowManager.each(function (win) {
                win.close();
            });
            pimcore.helpers.treeNodeThumbnailPreviewHide();
            pimcore.helpers.treeToolTipHide();

            const quicksearchContainer = Ext.get('pimcore_quicksearch');
            quicksearchContainer.show();
            quicksearchContainer.removeCls('filled');

            const combo = Ext.getCmp('quickSearchCombo');
            combo.reset();
            combo.focus();
            combo.show(); //why do i need that - the old version doesnt

            Ext.get('pimcore_body').addCls('blurry');
            Ext.get('pimcore_sidebar').addCls('blurry');
            const elem = document.createElement('div');
            elem.id = 'pimcore_quickSearch_overlay';
            elem.style.cssText = 'position:absolute;width:100vw;height:100vh;z-index:100;top:0;left:0;opacity:0';
            elem.addEventListener('click', function(e) {
                document.body.removeChild(elem);
                pimcore.simpleBackendSearch.layout.quickSearch.hide();
            });
            document.body.appendChild(elem);
        },

        hide: function () {
            let quicksearchContainer = Ext.get('pimcore_quicksearch');
            quicksearchContainer.hide();
            Ext.get('pimcore_body').removeCls('blurry');
            Ext.get('pimcore_sidebar').removeCls('blurry');
            if (Ext.get('pimcore_quickSearch_overlay')) {
                Ext.get('pimcore_quickSearch_overlay').remove();
            }
        },
    }
});