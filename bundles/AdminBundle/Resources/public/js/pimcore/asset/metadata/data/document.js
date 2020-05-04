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

pimcore.registerNS("pimcore.asset.metadata.data.document");
pimcore.asset.metadata.data.document = Class.create(pimcore.asset.metadata.data.data, {

    type: "document",


    initialize: function (treeNode, initData) {
        this.type = "document";

        this.initData(initData);

        this.treeNode = treeNode;
    },

    getTypeName: function () {
        return t("document");
    },

    getIconClass: function () {
        return "pimcore_icon_document";
    },

    getLayout: function ($super) {

        $super();

        this.specificPanel.removeAll();
        var specificItems = this.getSpecificPanelItems(this.datax);
        this.specificPanel.add(specificItems);

        return this.layout;
    }
});
