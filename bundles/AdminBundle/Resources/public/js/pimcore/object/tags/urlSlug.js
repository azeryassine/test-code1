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

pimcore.registerNS("pimcore.object.tags.urlSlug");
pimcore.object.tags.urlSlug = Class.create(pimcore.object.tags.abstract, {

    type: "urlSlug",

    initialize: function (data, fieldConfig) {

        this.data = "";
        this.usedSiteIds = [];
        this.elements = {};
        this.dirty = false;

        if (data) {
            this.data = data;
        }
        this.fieldConfig = fieldConfig;
    },

    getGridColumnEditor: function (field) {
        var editorConfig = {};

        if (field.config) {
            if (field.config.width) {
                if (intval(field.config.width) > 10) {
                    editorConfig.width = field.config.width;
                }
            }
        }

        if (field.layout.noteditable) {
            return null;
        }
        return new Ext.form.TextField(editorConfig);
    },

    getGridColumnFilter: function (field) {
        return {type: 'string', dataIndex: field.key};
    },

    getLayoutEdit: function () {
        this.component = new Ext.Panel();

        this.addFallbackSlug();
        if (this.data.length > 0) {
            for (var i = 0; i < this.data.length; i++) {
                this.addSiteElement(this.data[i]);
            }
        }

        return this.component;
    },

    addFallbackSlug: function () {
        var needed = false;
        if (this.data.length > 0) {
            let firstElement = this.data[0];
            if (firstElement['siteId'] > 0) {
                needed = true;
            }
        } else {
            needed = true;
        }

        if (needed) {
            this.addSiteElement({               // fallback slug must be always there, even if empty
                siteId: 0
            });

        }
    },

    updateSiteFilter: function () {
        var showCombo = false;
        this.siteCombo.setFilters([
            function (item) {
                var siteId = item.get("id");
                if (this.elements[siteId]) {
                    return false;
                }
                showCombo = true;
                return true;
            }.bind(this)
        ]);
        if (showCombo) {
            this.siteCombo.show();
        } else {
            this.siteCombo.hide();
        }
    },

    addSiteElement: function (siteData) {

        Ext.suspendLayouts();

        var fieldContainer = new Ext.form.FieldContainer({
            layout: 'hbox',
        });


        var domain;
        this.usedSiteIds.push(siteData['siteId']);

        if (siteData['siteId'] > 0) {
            domain = siteData['domain'];
        } else {
            domain = t('fallback');
        }

        var title = this.fieldConfig.title ? this.fieldConfig.title : this.fieldConfig.name;

        var textConfig = {
            xtype: "textfield",
            fieldLabel: title + " (" + domain + ")",
            name: "slug",
            labelWidth: 100,
            value: siteData['slug'],
            componentCls: "object_field",
            regex: new RegExp("^(\\/\\w+)+(\\.)?\\w+(\\?(\\w+=[\\w\\d]+(&\\w+=[\\w\\d]+)*)+){0,1}$")
        };
        if (this.fieldConfig.width) {
            textConfig.width = this.fieldConfig.width;
        } else {
            textConfig.width = 350;
        }

        if (this.fieldConfig.labelWidth) {
            textConfig.labelWidth = this.fieldConfig.labelWidth;
        }

        // data type allows to configure a field-level label width, otherwise the parent label width gets applied.
        if (this.fieldConfig.domainLabelWidth) {
            textConfig.labelWidth = this.fieldConfig.domainLabelWidth;
        }

        textConfig.width += textConfig.labelWidth;

        var text = new Ext.form.TextField(textConfig);

        var containerItems = [text];

        if (siteData['siteId'] > 0) {
            containerItems.push({
                xtype: "button",
                iconCls: "pimcore_icon_delete",
                handler: function (fieldContainer, siteId) {
                    this.dirty = true;
                    this.component.remove(fieldContainer);
                    delete this.elements[siteId];
                    this.updateSiteFilter();

                }.bind(this, fieldContainer, siteData['siteId'])
            });
        } else {
            let siteData = [];
            let allSitesStore = pimcore.globalmanager.get("sites");
            allSitesStore.each(function (record, id) {
                let siteId = record.get("id");
                if (siteId !== "default") {
                    if (this.fieldConfig.availableSites !== null && this.fieldConfig.availableSites.length > 0
                        && !in_array(siteId, this.fieldConfig.availableSites)) {
                        return;
                    }

                    siteData.push([id, record.get("domain")]);
                }
            }.bind(this));

            if (siteData.length > 0) {
                // only show combo if something to select which is not the case if there are no sites at all
                this.siteCombo = new Ext.form.ComboBox({
                    triggerAction: "all",
                    editable: true,
                    selectOnFocus: true,
                    queryMode: 'local',
                    typeAhead: true,
                    forceSelection: true,
                    fieldLabel: t("add_site"),
                    store: new Ext.data.ArrayStore({
                        fields: [
                            'id',
                            'domain'
                        ],
                        data: siteData
                    }),
                    listeners: {
                        select: function (combo, record, eOpts) {
                            combo.setValue(null);
                            var siteId = record.getId();
                            if (this.elements[siteId]) {
                                return;
                            }

                            this.addSiteElement({
                                siteId: siteId,
                                domain: record.get('domain')
                            });
                            this.dirty = true;
                            this.updateSiteFilter();

                        }.bind(this)
                    },
                    valueField: 'id',
                    displayField: 'domain',
                });
                containerItems.push(this.siteCombo);
            }
        }

        this.elements[siteData['siteId']] = text;
        fieldContainer.add(containerItems);
        this.component.insert(1, fieldContainer);
        Ext.resumeLayouts();
    },

    getLayoutShow: function () {
        var layout = this.getLayoutEdit();
        this.component.setReadOnly(true);
        return layout;
    },

    getValue: function () {
        var value = [];

        for (key in this.elements) {
            if (this.elements.hasOwnProperty(key)) {
                let textfield = this.elements[key];
                value.push(textfield.getValue());
            }
        }

        return value;
    },

    getName: function () {
        return this.fieldConfig.name;
    },

    getGridColumnConfig: function (field) {
        return {
            text: ts(field.label), sortable: false, dataIndex: field.key,
            editor: this.getGridColumnEditor(field)
        };
    },

    isDirty: function () {
        var dirty = this.dirty;

        for (key in this.elements) {
            if (this.elements.hasOwnProperty(key)) {
                let textfield = this.elements[key];
                if (textfield.isDirty()) {
                    return true;
                }
            }
        }

        return dirty;
    }
});