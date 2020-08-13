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
 *
 * @deprecated since v6.8 and will be removed in 7. Use {@link pimcore.document.editables.relations} instead.
 */

pimcore.registerNS("pimcore.document.tags.relations");
pimcore.document.tags.relations = Class.create(pimcore.document.editables.relations, {
});

// @TODO BC layer, to be removed in v7.0
pimcore.document.tags.multihref = pimcore.document.tags.relations;