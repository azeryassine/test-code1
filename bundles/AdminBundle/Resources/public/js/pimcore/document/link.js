/**
 * Pimcore
 *
 * This source file is available under two different licenses:
 * - GNU General Public License version 3 (GPLv3)
 * - Pimcore Enterprise License (PEL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 * @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 * @license    http://www.pimcore.org/license     GPLv3 and PEL
 */

pimcore.registerNS("pimcore.document.link");
pimcore.document.link = Class.create(pimcore.document.document, {

    initialize: function (id, options) {

        this.options = options;
        this.id = intval(id);
        this.setType("link");
        this.addLoadingPanel();

        pimcore.plugin.broker.fireEvent("preOpenDocument", this, "link");
        this.getData();
    },

    init: function () {

        var user = pimcore.globalmanager.get("user");

        if (this.isAllowed("properties")) {
            this.properties = new pimcore.document.properties(this, "document");
        }

        if (this.isAllowed("settings")) {
            this.scheduler = new pimcore.element.scheduler(this, "document", {
                supportsVersions: false
            });
        }

        if (user.isAllowed("notes_events")) {
            this.notes = new pimcore.element.notes(this, "document");
        }

        this.dependencies = new pimcore.element.dependencies(this, "document");
        this.tagAssignment = new pimcore.element.tag.assignment(this, "document");
        this.workflows = new pimcore.element.workflows(this, "document");
    },

    getSaveData: function (only) {
        var parameters = {};
        parameters.id = this.id;

        // get only scheduled tasks
        if (only === "scheduler") {
            try {
                parameters.scheduler = Ext.encode(this.scheduler.getValues());
                return parameters;
            }
            catch (e) {
                console.log("scheduler not available");
                return;
            }
        }

        var values = this.panel.getForm().getFieldValues();
        values.published = this.data.published;
        parameters.data = Ext.encode(values);

        if (this.isAllowed("properties")) {
            // properties
            try {
                parameters.properties = Ext.encode(this.properties.getValues());
            }
            catch (e) {
                //console.log(e);
            }
        }

        if (this.isAllowed("settings")) {
            // scheduler
            try {
                parameters.scheduler = Ext.encode(this.scheduler.getValues());
            }
            catch (e5) {
                //console.log(e5);
            }
        }

        return parameters;
    },

    addTab: function () {

        var tabTitle = this.data.key;
        this.tabPanel = Ext.getCmp("pimcore_panel_tabs");
        var tabId = "document_" + this.id;
        this.tab = new Ext.Panel({
            id: tabId,
            title: tabTitle,
            closable: true,
            layout: "border",
            items: [
                this.getLayoutToolbar(),
                this.getTabPanel()
            ],
            iconCls: "pimcore_icon_" + this.data.type,
            document: this
        });

        this.tab.on("beforedestroy", function () {
            Ext.Ajax.request({
                url: "/admin/element/unlock-element",
                method: 'PUT',
                params: {
                    id: this.data.id,
                    type: "document"
                }
            });
        }.bind(this));

        // remove this instance when the panel is closed
        this.tab.on("destroy", function () {
            pimcore.globalmanager.remove("document_" + this.id);
            pimcore.helpers.forgetOpenTab("document_" + this.id + "_link");
        }.bind(this));

        this.tab.on("activate", function () {
            this.tab.updateLayout();
            pimcore.layout.refresh();
        }.bind(this));

        this.tab.on("afterrender", function (tabId) {
            this.tabPanel.setActiveItem(tabId);
            pimcore.plugin.broker.fireEvent("postOpenDocument", this, "link");
        }.bind(this, tabId));

        this.removeLoadingPanel();

        this.addToMainTabPanel();

        // recalculate the layout
        pimcore.layout.refresh();
    },

    getLayoutToolbar: function () {

        if (!this.toolbar) {
            this.toolbarButtons = {};

            this.toolbarButtons.publish = new Ext.SplitButton({
                text: t('save_and_publish'),
                iconCls: "pimcore_icon_save_white",
                cls: "pimcore_save_button",
                scale: "medium",
                handler: this.publish.bind(this),
                menu: [
                    {
                        text: t('save_pubish_close'),
                        iconCls: "pimcore_icon_save",
                        handler: this.publishClose.bind(this)
                    },
                    {
                        text: t('save_only_scheduled_tasks'),
                        iconCls: "pimcore_icon_save",
                        handler: this.save.bind(this, "scheduler", "scheduler")
                    }
                ]
            });

            this.toolbarButtons.unpublish = new Ext.Button({
                text: t('unpublish'),
                iconCls: "pimcore_icon_unpublish",
                scale: "medium",
                handler: this.unpublish.bind(this)
            });

            this.toolbarButtons.remove = new Ext.Button({
                tooltip: t('delete'),
                iconCls: "pimcore_icon_delete",
                scale: "medium",
                handler: this.remove.bind(this)
            });

            this.toolbarButtons.rename = new Ext.Button({
                tooltip: t('rename'),
                iconCls: "pimcore_icon_key pimcore_icon_overlay_go",
                scale: "medium",
                handler: this.rename.bind(this)
            });

            var buttons = [];

            if (this.isAllowed("publish")) {
                buttons.push(this.toolbarButtons.publish);
            }
            if (this.isAllowed("unpublish") && !this.data.locked) {
                buttons.push(this.toolbarButtons.unpublish);
            }

            buttons.push("-");

            if (this.isAllowed("delete") && !this.data.locked) {
                buttons.push(this.toolbarButtons.remove);
            }
            if (this.isAllowed("rename") && !this.data.locked) {
                buttons.push(this.toolbarButtons.rename);
            }

            buttons.push({
                tooltip: t('reload'),
                iconCls: "pimcore_icon_reload",
                scale: "medium",
                handler: this.reload.bind(this)
            });

            if (pimcore.elementservice.showLocateInTreeButton("document")) {
                buttons.push({
                    tooltip: t('show_in_tree'),
                    iconCls: "pimcore_icon_show_in_tree",
                    scale: "medium",
                    handler: this.selectInTree.bind(this)
                });
            }

            buttons.push(this.getTranslationButtons());

            buttons.push("-");
            buttons.push({
                xtype: 'tbtext',
                text: t("id") + " " + this.data.id,
                scale: "medium"
            });

            this.toolbar = new Ext.Toolbar({
                id: "document_toolbar_" + this.id,
                region: "north",
                border: false,
                cls: "main-toolbar",
                items: buttons,
                overflowHandler: 'scroller'
            });

            this.toolbar.on("afterrender", function () {
                window.setTimeout(function () {
                    // it's not possible to delete the root-node
                    if (this.id == 1) {
                        this.toolbarButtons.remove.hide();
                    }

                    if (!this.data.published) {
                        this.toolbarButtons.unpublish.hide();
                    }
                }.bind(this), 500);
            }.bind(this));
        }

        return this.toolbar;
    },

    getTabPanel: function () {

        var items = [];
        var user = pimcore.globalmanager.get("user");

        items.push(this.getLayoutForm());

        if (this.isAllowed("properties")) {
            items.push(this.properties.getLayout());
        }

        items.push(this.dependencies.getLayout());

        if (this.isAllowed("settings")) {
            items.push(this.scheduler.getLayout());
        }

        if (user.isAllowed("notes_events")) {
            items.push(this.notes.getLayout());
        }

        if (user.isAllowed("tags_assignment")) {
            items.push(this.tagAssignment.getLayout());
        }

        if (user.isAllowed("workflow_details") && this.data.workflowManagement && this.data.workflowManagement.hasWorkflowManagement === true) {
            items.push(this.workflows.getLayout());
        }

        this.tabbar = new Ext.TabPanel({
            tabPosition: "top",
            region: 'center',
            deferredRender: true,
            enableTabScroll: true,
            border: false,
            items: items,
            activeTab: 0
        });

        return this.tabbar;
    },

    getLayoutForm: function () {

        if (!this.panel) {
            var internalTypeField = new Ext.form.Hidden({
                fieldLabel: 'internalType',
                value: this.data.internalType,
                name: 'internalType',
                readOnly: true,
                width: 520
            });

            var linkTypeField = new Ext.form.Hidden({
                fieldLabel: 'linktype',
                value: this.data.linktype,
                name: 'linktype',
                readOnly: true,
                width: 520
            });


            var path = "";
            if (this.data.rawHref) {
                path = this.data.rawHref;
            }

            var pathField = new Ext.form.TextField({
                name: "path",
                fieldLabel: t("path"),
                value: path,
                fieldCls: "input_drop_target",
                width: 500
            });

            pathField.on("render", function (el) {
                var dd = new Ext.dd.DropZone(el.getEl().dom.parentNode.parentNode, {
                    ddGroup: "element",

                    getTargetFromEvent: function (e) {
                        return this.getEl();
                    },

                    onNodeOver: function (target, dd, e, data) {
                        if (data.records.length === 1 && data.records[0].data.elementType === "document" && data.records[0].data.type !== "folder") {
                            return Ext.dd.DropZone.prototype.dropAllowed;
                        }
                    },

                    onNodeDrop: function (target, dd, e, data) {

                        if(!pimcore.helpers.dragAndDropValidateSingleItem(data)) {
                            return false;
                        }

                        data = data.records[0].data;
                        if (data.type !== "folder" && data.elementType === "document") {
                            internalTypeField.setValue(data.elementType);
                            linkTypeField.setValue('internal');
                            pathField.setValue(data.path);
                            return true;
                        }
                    }.bind(this)
                });
            });

            var openButton = new Ext.Button({
                iconCls: "pimcore_icon_open",
                style: "margin-left: 5px",
                handler: function() {
                    if (linkTypeField.getValue() == "internal") {
                        pimcore.helpers.openElement(pathField.getValue(), internalTypeField.getValue());
                    } else {
                        window.open(pathField.getValue(), "_blank");
                    }
                }.bind(this)
            });

            this.panel = new Ext.form.FormPanel({
                title: t('settings'),
                iconCls: "pimcore_icon_settings",
                autoHeight: true,
                labelWidth: 200,
                defaultType: 'textfield',
                bodyStyle: 'padding:10px;',
                region: "center",
                items: [
                    internalTypeField,
                    linkTypeField,
                    {
                        xtype: 'fieldcontainer',
                        layout: 'hbox',
                        items: [
                            pathField,
                            openButton
                        ]

                    },
                    new Ext.toolbar.Spacer({
                        height: 50
                    })
                ]
            });
        }

        return this.panel;
    },

    rename: function () {
        if (this.isAllowed("rename") && !this.data.locked) {
            var options = {
                elementType: "document",
                elementSubType: this.getType(),
                id: this.id,
                default: this.data.key
            }
            pimcore.elementservice.editElementKey(options);
        }
    }
});

