/**
 * Pimcore
 *
 * LICENSE
 *
 * This source file is subject to the new BSD license that is bundled
 * with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://www.pimcore.org/license
 *
 * @copyright  Copyright (c) 2009-2010 elements.at New Media Solutions GmbH (http://www.elements.at)
 * @license    http://www.pimcore.org/license     New BSD License
 */

pimcore.registerNS("pimcore.document.emails.logs");
pimcore.document.emails.logs = Class.create({


    initialize: function(document) {
        this.document = document;
    },

    load: function () {
    },

    getLayout: function () {

        if (this.layout == null) {

            this.grid = this.getGrid();

            this.layout = new Ext.Panel({
                title: t('email_logs'),
                border: false,
                layout: "fit",
                items: [this.grid],

                iconCls: "pimcore_icon_email_transfer",
                listeners: {
                    activate: function() {
                        this.store.load();
                        this.grid.getView().refresh();
                    }.bind(this)
                }
            });
        }

        return this.layout;
    },

    getGrid: function () {

        var iFrameSettings = { width : 700, height : 500};

        var itemsPerPage = 20;

        var gridColumns = [{
            header: "ID",
            dataIndex: "id",
            width: 40,
            hidden: true
        },{
            header: "Document Id",
            dataIndex: "documentId",
            width: 130,
            hidden: true
        },
        {
            header: t('email_log_sent_Date'),
            dataIndex: "sentDate",
            width: 130,
            hidden: false,
            renderer: function (d) {
                var date = new Date(intval(d) * 1000);
                return date.format("Y-m-d H:i:s");
            }
        },
        {
            header: t('email_log_from'),
            dataIndex: "from",
            width: 120,
            hidden: false
        },
        {
            header: t('email_log_to'),
            dataIndex: "to",
            width: 120,
            hidden: false
        },
        {
            header: t('email_log_cc'),
            dataIndex: "cc",
            width: 120,
            hidden: false
        },
        {
            header: t('email_log_bcc'),
            dataIndex: "bcc",
            width: 120,
            hidden: false
        },
        {
            header: t('email_log_subject'),
            dataIndex: "subject",
            width: 220,
            hidden: false
        },
        {
            xtype: 'actioncolumn',
            width: 50,
            dataIndex: "emailLogExistsHtml",
            header: t('email_log_html'),
            items : [{
                tooltip: t('email_log_show_html_email'),
                icon: "/pimcore/static/img/icon/email_open.png",
                handler: function(grid, rowIndex){
                    var rec = grid.getStore().getAt(rowIndex);
                    var iframe = new Ext.Window({
                        title: t("email_log_iframe_title_html"),
                        width: iFrameSettings.width,
                        height: iFrameSettings.height,
                        layout: 'fit',
                        items : [{
                                xtype : "box",
                                autoEl: {tag: 'iframe', src: "/admin/email/show-email-log/?id=" + rec.get('id') + "&type=html"}
                            }]
                    });
                    iframe.show();
                }.bind(this),
                getClass: function(v, meta, rec) {
                    if(!rec.get('emailLogExistsHtml')){
                        return "pimcore_hidden";
                    }
                }
            }]
        },
        {
            xtype: 'actioncolumn',
            width: 50,
            dataIndex: "emailLogExistsText",
            header: t('email_log_text'),
            items : [{
                tooltip: t('email_log_show_text_email'),
                icon: "/pimcore/static/img/icon/text_align_justify.png",
                handler: function(grid, rowIndex){
                    var rec = grid.getStore().getAt(rowIndex);
                    var iframe = new Ext.Window({
                        title: t("email_log_iframe_title_text"),
                        width: iFrameSettings.width,
                        height: iFrameSettings.height,
                        layout: 'fit',
                        items : [{
                                xtype : "box",
                                autoEl: {tag: 'iframe', src: "/admin/email/show-email-log/?id=" + rec.get('id') + "&type=text"}
                            }]
                    });
                    iframe.show();
                }.bind(this),
                getClass: function(v, meta, rec) {
                    if(!rec.get('emailLogExistsText')){
                        return "pimcore_hidden";
                    }
                }
            }]
        },
        {
            xtype: 'actioncolumn',
            width: 120,
            dataIndex: "params",
            hidden: true,
            header: t('email_log_params'),
            items : [{
                tooltip: t('email_log_show_text_params'),
                icon: "/pimcore/static/img/icon/information.png",
                handler: function(grid, rowIndex){
                    var rec = grid.getStore().getAt(rowIndex);
                    var iframe = new Ext.Window({
                        title: t("email_log_iframe_title_params"),
                        width: iFrameSettings.width,
                        height: iFrameSettings.height,
                        layout: 'fit',
                        items : [{
                                xtype : "box",
                                autoEl: {tag: 'iframe', src: "/admin/email/show-email-log/?id=" + rec.get('id') + "&type=params"}
                            }]
                    });
                    iframe.show();
                }.bind(this)
            }]
        },
         {
            xtype: 'actioncolumn',
            width: 30,
            items: [{
                tooltip: t('delete'),
                icon: "/pimcore/static/img/icon/cross.png",
                handler: function (grid, rowIndex) {
                    var rec = grid.getStore().getAt(rowIndex);
                    Ext.Ajax.request({
                        url: '/admin/email/delete-email-log/',
                        success: function(response){
                            var data = Ext.decode( response.responseText );
                            if(!data.success){
                                alert("Could not delete email log");
                            }
                        },
                        failure: function () {
                            alert("Could not delete email log");
                        }, 
                        params: { id : rec.get('id') }
                    });
                    grid.getStore().removeAt(rowIndex);
                }.bind(this)
            }]
        },

        ];

       var storeFields = ["id","documentId","subject","emailLogExistsHtml","params","sentDate","params","modificationDate","requestUri","from","to","cc","bcc","emailLogExistsHtml","emailLogExistsText"];



       this.store = new Ext.data.JsonStore({
            restful: false,
            idProperty: 'id',
            remoteSort: true,
            root: "data",
            url: "/admin/email/email-logs/",
            baseParams: {
                limit: itemsPerPage,
                documentId: this.document.id
            },
            fields: storeFields
        });
        this.store.load();

        this.pagingtoolbar = new Ext.PagingToolbar({
            pageSize: itemsPerPage,
            store: this.store,
            displayInfo: true,
            displayMsg: '{0} - {1} / {2}',
            emptyMsg: t("no_objects_found")
        });

        this.grid = new Ext.grid.GridPanel({
            frame: false,
            store: this.store,
            columns : gridColumns,
            columnLines: true,
            stripeRows: true,
            border: true,
            trackMouseOver: true,
            loadMask: true,
            viewConfig: {
                forceFit: false
            },
            bbar: [this.pagingtoolbar]
        });
       // this.grid.on("rowcontextmenu", this.onRowContextmenu);

        return this.grid;
    },

    reload: function () {

        this.grid.store.reload();
        this.grid.getView().refresh();

    }
});