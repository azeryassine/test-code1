<?php
declare(strict_types=1);

/**
 * Pimcore
 *
 * This source file is available under two different licenses:
 * - GNU General Public License version 3 (GPLv3)
 * - Pimcore Commercial License (PCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 *  @license    http://www.pimcore.org/license     GPLv3 and PCL
 */

namespace Pimcore\Model\Asset;

use Pimcore\Config;
use Pimcore\Event\AssetEvents;
use Pimcore\Event\Model\AssetEvent;
use Pimcore\Loader\ImplementationLoader\Exception\UnsupportedException;
use Pimcore\Model;
use Pimcore\Model\Asset;
use Pimcore\Model\Asset\MetaData\ClassDefinition\Data\Data;
use Pimcore\Model\Element;
use Pimcore\Model\Element\ElementInterface;
use Pimcore\Model\Tool\TmpStore;
use Pimcore\Model\Asset\Video\ImageThumbnail as VideoImageThumbnail;
use Pimcore\Model\Asset\Document\ImageThumbnail as DocumentImageThumbnail;
use Pimcore\Model\Asset\Image\Thumbnail as ImageThumbnail;
use Symfony\Component\Routing\Route;

/**
 * @method \Pimcore\Model\Asset\Dao getDao()
 */
class Service extends Model\Element\Service
{
    /**
     * @internal
     *
     * @var array
     */
    public const GRID_SYSTEM_COLUMNS = ['preview', 'id', 'type', 'fullpath', 'filename', 'creationDate', 'modificationDate', 'size'];

    /**
     * @internal
     *
     * @var Model\User|null
     */
    protected ?Model\User $_user;

    /**
     * @internal
     *
     * @var array
     */
    protected array $_copyRecursiveIds;

    /**
     * @param Model\User|null $user
     */
    public function __construct(Model\User $user = null)
    {
        $this->_user = $user;
    }

    /**
     * @param Asset $target
     * @param Asset $source
     *
     * @return Asset|Folder|null copied asset
     *
     * @throws \Exception
     */
    public function copyRecursive(Asset $target, Asset $source): Asset|Folder|null
    {
        // avoid recursion
        if (!$this->_copyRecursiveIds) {
            $this->_copyRecursiveIds = [];
        }
        if (in_array($source->getId(), $this->_copyRecursiveIds)) {
            return null;
        }

        $source->getProperties();

        // triggers actions before asset cloning
        $event = new AssetEvent($source, [
            'target_element' => $target,
        ]);
        \Pimcore::getEventDispatcher()->dispatch($event, AssetEvents::PRE_COPY);
        $target = $event->getArgument('target_element');

        /** @var Asset $new */
        $new = Element\Service::cloneMe($source);
        $new->setObjectVar('id', null);
        if ($new instanceof Asset\Folder) {
            $new->setChildren(null);
        }

        $new->setFilename(Element\Service::getSafeCopyName($new->getFilename(), $target));
        $new->setParentId($target->getId());
        $new->setUserOwner($this->_user ? $this->_user->getId() : 0);
        $new->setUserModification($this->_user ? $this->_user->getId() : 0);
        $new->setDao(null);
        $new->setLocked(null);
        $new->setCreationDate(time());
        $new->setStream($source->getStream());
        $new->save();

        // add to store
        $this->_copyRecursiveIds[] = $new->getId();

        foreach ($source->getChildren() as $child) {
            $this->copyRecursive($new, $child);
        }

        if ($target instanceof Asset\Folder) {
            $this->updateChildren($target, $new);
        }

        // triggers actions after the complete asset cloning
        $event = new AssetEvent($new, [
            'base_element' => $source, // the element used to make a copy
        ]);
        \Pimcore::getEventDispatcher()->dispatch($event, AssetEvents::POST_COPY);

        return $new;
    }

    /**
     * @param Asset $target
     * @param Asset $source
     *
     * @return Asset|Folder copied asset
     *
     * @throws \Exception
     */
    public function copyAsChild(Asset $target, Asset $source): Asset|Folder
    {
        $source->getProperties();

        // triggers actions before asset cloning
        $event = new AssetEvent($source, [
            'target_element' => $target,
        ]);
        \Pimcore::getEventDispatcher()->dispatch($event, AssetEvents::PRE_COPY);
        $target = $event->getArgument('target_element');

        /** @var Asset $new */
        $new = Element\Service::cloneMe($source);
        $new->setId(null);

        if ($new instanceof Asset\Folder) {
            $new->setChildren(null);
        }
        $new->setFilename(Element\Service::getSafeCopyName($new->getFilename(), $target));
        $new->setParentId($target->getId());
        $new->setUserOwner($this->_user ? $this->_user->getId() : 0);
        $new->setUserModification($this->_user ? $this->_user->getId() : 0);
        $new->setDao(null);
        $new->setLocked(null);
        $new->setCreationDate(time());
        $new->setStream($source->getStream());
        $new->save();

        if ($target instanceof Asset\Folder) {
            $this->updateChildren($target, $new);
        }

        // triggers actions after the complete asset cloning
        $event = new AssetEvent($new, [
            'base_element' => $source, // the element used to make a copy
        ]);
        \Pimcore::getEventDispatcher()->dispatch($event, AssetEvents::POST_COPY);

        return $new;
    }

    /**
     * @param Asset $target
     * @param Asset $source
     *
     * @return Asset
     *
     * @throws \Exception
     */
    public function copyContents(Asset $target, Asset $source): Asset
    {
        // check if the type is the same
        if (get_class($source) != get_class($target)) {
            throw new \Exception('Source and target have to be the same type');
        }

        if (!$source instanceof Asset\Folder) {
            $target->setStream($source->getStream());
            $target->setCustomSettings($source->getCustomSettings());
        }

        $target->setUserModification($this->_user ? $this->_user->getId() : 0);
        $target->setProperties(self::cloneProperties($source->getProperties()));
        $target->save();

        return $target;
    }

    /**
     * @param Asset $asset
     * @param array|null $fields
     * @param string|null $requestedLanguage
     * @param array $params
     *
     * @return array
     *
     * @internal
     */
    public static function gridAssetData(Asset $asset, array $fields = null, string $requestedLanguage = null, array $params = []): array
    {
        $data = Element\Service::gridElementData($asset);
        $loader = null;

        if ($asset instanceof Asset && !empty($fields)) {
            $data = [
                'id' => $asset->getId(),
                'id~system' => $asset->getId(),
                'type~system' => $asset->getType(),
                'fullpath~system' => $asset->getRealFullPath(),
                'filename~system' => $asset->getKey(),
                'creationDate~system' => $asset->getCreationDate(),
                'modificationDate~system' => $asset->getModificationDate(),
                'idPath~system' => Element\Service::getIdPath($asset),
            ];

            $requestedLanguage = str_replace('default', '', $requestedLanguage);

            foreach ($fields as $field) {
                $fieldDef = explode('~', $field);
                if (isset($fieldDef[1]) && $fieldDef[1] === 'system') {
                    if ($fieldDef[0] === 'preview') {
                        $data[$field] = self::getPreviewThumbnail($asset, ['treepreview' => true, 'width' => 108, 'height' => 70, 'frame' => true]);
                    } elseif ($fieldDef[0] === 'size') {
                        $size = $asset->getFileSize();
                        $data[$field] = formatBytes($size);
                    }
                } else {
                    if (isset($fieldDef[1])) {
                        $language = ($fieldDef[1] === 'none' ? '' : $fieldDef[1]);
                        $rawMetaData = $asset->getMetadata($fieldDef[0], $language, true, true);
                    } else {
                        $rawMetaData = $asset->getMetadata($field, $requestedLanguage, true, true);
                    }

                    $metaData = $rawMetaData['data'] ?? null;

                    if ($rawMetaData) {
                        $type = $rawMetaData['type'];
                        if (!$loader) {
                            $loader = \Pimcore::getContainer()->get('pimcore.implementation_loader.asset.metadata.data');
                        }

                        $metaData = $rawMetaData['data'] ?? null;

                        try {
                            /** @var Data $instance */
                            $instance = $loader->build($type);
                            $metaData = $instance->getDataForListfolderGrid($rawMetaData['data'] ?? null, $rawMetaData);
                        } catch (UnsupportedException $e) {
                        }
                    }

                    $data[$field] = $metaData;
                }
            }
        }

        return $data;
    }

    /**
     * @param Asset $asset
     * @param array $params
     * @param bool $onlyMethod
     *
     * @return string|null
     *
     * @internal
     */
    public static function getPreviewThumbnail(Asset $asset, array $params = [], bool $onlyMethod = false): ?string
    {
        $thumbnailMethod = '';
        $thumbnailUrl = null;

        if ($asset instanceof Asset\Image) {
            $thumbnailMethod = 'getThumbnail';
        } elseif ($asset instanceof Asset\Video && \Pimcore\Video::isAvailable()) {
            $thumbnailMethod = 'getImageThumbnail';
        } elseif ($asset instanceof Asset\Document && \Pimcore\Document::isAvailable()) {
            $thumbnailMethod = 'getImageThumbnail';
        }

        if ($onlyMethod) {
            return $thumbnailMethod;
        }

        if (!empty($thumbnailMethod)) {
            $thumbnailUrl = '/admin/asset/get-' . $asset->getType() . '-thumbnail?id=' . $asset->getId();
            if (count($params) > 0) {
                $thumbnailUrl .= '&' . http_build_query($params);
            }
        }

        return $thumbnailUrl;
    }

    /**
     * @static
     *
     * @param string $path
     * @param string|null $type
     *
     * @return bool
     */
    public static function pathExists(string $path, string $type = null): bool
    {
        if (!$path) {
            return false;
        }

        $path = Element\Service::correctPath($path);

        try {
            $asset = new Asset();

            if (self::isValidPath($path, 'asset')) {
                $asset->getDao()->getByPath($path);

                return true;
            }
        } catch (\Exception $e) {
        }

        return false;
    }

    /**
     * @internal
     *
     * @param Element\ElementInterface $element
     *
     * @return Element\ElementInterface
     */
    public static function loadAllFields(Element\ElementInterface $element): Element\ElementInterface
    {
        $element->getProperties();

        return $element;
    }

    /**
     * Rewrites id from source to target, $rewriteConfig contains
     * array(
     *  "document" => array(
     *      SOURCE_ID => TARGET_ID,
     *      SOURCE_ID => TARGET_ID
     *  ),
     *  "object" => array(...),
     *  "asset" => array(...)
     * )
     *
     * @param Asset $asset
     * @param array $rewriteConfig
     *
     * @return Asset
     *
     * @internal
     */
    public static function rewriteIds(Asset $asset, array $rewriteConfig): Asset
    {
        // rewriting properties
        $properties = $asset->getProperties();
        foreach ($properties as &$property) {
            $property->rewriteIds($rewriteConfig);
        }
        $asset->setProperties($properties);

        return $asset;
    }

    /**
     * @param array $metadata
     * @param string $mode
     *
     * @return array
     *
     * @internal
     */
    public static function minimizeMetadata(array $metadata, string $mode): array
    {
        if (!is_array($metadata)) {
            return $metadata;
        }

        $result = [];
        foreach ($metadata as $item) {
            $loader = \Pimcore::getContainer()->get('pimcore.implementation_loader.asset.metadata.data');

            try {
                /** @var Data $instance */
                $instance = $loader->build($item['type']);

                if ($mode == 'grid') {
                    $transformedData = $instance->getDataFromListfolderGrid($item['data'] ?? null, $item);
                } else {
                    $transformedData = $instance->getDataFromEditMode($item['data'] ?? null, $item);
                }

                $item['data'] = $transformedData;
            } catch (UnsupportedException $e) {
            }

            $result[] = $item;
        }

        return $result;
    }

    /**
     * @param array $metadata
     *
     * @return array
     *
     * @internal
     */
    public static function expandMetadataForEditmode(array $metadata): array
    {
        if (!is_array($metadata)) {
            return $metadata;
        }

        $result = [];
        foreach ($metadata as $item) {
            $loader = \Pimcore::getContainer()->get('pimcore.implementation_loader.asset.metadata.data');
            $transformedData = $item['data'];

            try {
                /** @var Data $instance */
                $instance = $loader->build($item['type']);
                $transformedData = $instance->getDataForEditMode($item['data'], $item);
            } catch (UnsupportedException $e) {
            }

            $item['data'] = $transformedData;
            //get the config from an predefined property-set (eg. select)
            $predefined = Model\Metadata\Predefined::getByName($item['name']);
            if ($predefined && $predefined->getType() == $item['type'] && $predefined->getConfig()) {
                $item['config'] = $predefined->getConfig();
            }

            $key = $item['name'] . '~' . $item['language'];
            $result[$key] = $item;
        }

        return $result;
    }

    public static function getUniqueKey(ElementInterface $element, int $nr = 0): string
    {
        $list = new Listing();
        $key = Element\Service::getValidKey($element->getKey(), 'asset');
        if (!$key) {
            throw new \Exception('No item key set.');
        }
        if ($nr) {
            if ($element->getType() == 'folder') {
                $key = $key . '_' . $nr;
            } else {
                $keypart = substr($key, 0, strrpos($key, '.'));
                $extension = str_replace($keypart, '', $key);
                $key = $keypart . '_' . $nr . $extension;
            }
        }

        $parent = $element->getParent();
        if (!$parent) {
            throw new \Exception('You have to set a parent folder to determine a unique Key');
        }

        if (!$element->getId()) {
            $list->setCondition('parentId = ? AND `filename` = ? ', [$parent->getId(), $key]);
        } else {
            $list->setCondition('parentId = ? AND `filename` = ? AND id != ? ', [$parent->getId(), $key, $element->getId()]);
        }
        $check = $list->loadIdList();
        if (!empty($check)) {
            $nr++;
            $key = self::getUniqueKey($element, $nr);
        }

        return $key;
    }

    public static function getImageThumbnailByUri(string $uri): null|ImageThumbnail|VideoImageThumbnail|DocumentImageThumbnail
    {
        $config = self::extractThumbnailInfoFromUri($uri);
        if (!$config) {
            return null;
        }

        $asset = Asset::getById($config['asset_id']);
        if (!$asset) {
            return null;
        }

        $prefix = preg_replace('@^cache-buster\-[\d]+\/@', '', $config['asset_path'] ?? '');
        $prefix = preg_replace('@' . $asset->getId() . '/$@', '', $prefix);

        if ($asset->getPath() === $prefix) {
            // just check if the thumbnail exists -> throws exception otherwise
            $thumbnailConfigClass = 'Pimcore\\Model\\Asset\\' . ucfirst($config['type']) . '\\Thumbnail\Config';
            $thumbnailConfig = $thumbnailConfigClass::getByName($config['thumbnail_config_name']);

            if (!$thumbnailConfig) {
                // check if there's an item in the TmpStore
                // remove an eventually existing cache-buster prefix first (eg. when using with a CDN)
                $pathInfo = preg_replace('@^/cache-buster\-[\d]+@', '', $uri);
                $deferredConfigId = 'thumb_' . $config['asset_id'] . '__' . md5(urldecode($pathInfo));

                if ($thumbnailConfigItem = TmpStore::get($deferredConfigId)) {
                    $thumbnailConfig = $thumbnailConfigItem->getData();
                    TmpStore::delete($deferredConfigId);

                    if (!$thumbnailConfig instanceof $thumbnailConfigClass) {
                        throw new \Exception('Deferred thumbnail config file doesn\'t contain a valid '.$thumbnailConfigClass.' object');
                    }
                } elseif (Config::getSystemConfiguration()['assets'][$config['type']]['thumbnails']['status_cache']) {
                    // Delete Thumbnail Name from Cache so the next call can generate a new TmpStore entry
                    $asset->getDao()->deleteFromThumbnailCache($config['thumbnail_config_name']);
                }
            }

            if (!$thumbnailConfig) {
                return null;
            }

            if ($config['type'] == 'image' && strcasecmp($thumbnailConfig->getFormat(), 'SOURCE') === 0) {
                $formatOverride = $config['thumbnail_extension'];
                if (in_array($config['thumbnail_extension'], ['jpg', 'jpeg'])) {
                    $formatOverride = 'pjpeg';
                }
                $thumbnailConfig->setFormat($formatOverride);
            }

            if ($asset instanceof Asset\Video) {
                $time = 1;
                if (preg_match("|~\-~time\-(\d+)\.|", $config['thumbnail_name'], $matchesThumbs)) {
                    $time = (int)$matchesThumbs[1];
                }

                return $asset->getImageThumbnail($thumbnailConfig, $time);
            } elseif ($asset instanceof Asset\Document) {
                $page = 1;
                if (preg_match("|~\-~page\-(\d+)\.|", $config['thumbnail_name'], $matchesThumbs)) {
                    $page = (int)$matchesThumbs[1];
                }

                $thumbnailConfig->setName(preg_replace("/\-[\d]+/", '', $thumbnailConfig->getName()));
                $thumbnailConfig->setName(str_replace('document_', '', $thumbnailConfig->getName()));

                return $asset->getImageThumbnail($thumbnailConfig, $page);
            } elseif ($asset instanceof Asset\Image) {
                //check if high res image is called

                preg_match("@([^\@]+)(\@[0-9.]+x)?\.([a-zA-Z]{2,5})@", $config['thumbnail_name'], $matches);

                if (empty($matches) || !isset($matches[1])) {
                    return null;
                }
                if (array_key_exists(2, $matches) && $matches[2]) {
                    $highResFactor = (float)str_replace(['@', 'x'], '', $matches[2]);
                    $thumbnailConfig->setHighResolution($highResFactor);
                }

                // check if a media query thumbnail was requested
                if (preg_match("#~\-~media\-\-(.*)\-\-query#", $matches[1], $mediaQueryResult)) {
                    $thumbnailConfig->selectMedia($mediaQueryResult[1]);
                }

                return $asset->getThumbnail($thumbnailConfig);
            }
        }

        return null;
    }

    /**
     * @param string $uri
     * @return array{'thumbnail_extension': string, 'thumbnail_name': string, 'thumbnail_config_name': string, 'asset_id': string, 'asset_path': string, 'type': string}|null
     */
    public static function extractThumbnailInfoFromUri(string $uri): ?array
    {
        $parsedUrl = parse_url($uri);
        $path = urldecode($parsedUrl['path']);
        $parts = explode('/', $path);
        $totalCount = count($parts);
        // Valid uri must have at least 4 parts
        if ($totalCount < 4) {
            return null;
        }

        $fileName = $parts[$totalCount - 1 ];
        $thumbnailPart = $parts[$totalCount - 2];
        $assetId = $parts[$totalCount - 3];
        $assetPath = implode('/', array_slice($parts, 0, $totalCount - 3));

        // If the uri does not contain thumb__, the url is invalid
        if (!str_contains($thumbnailPart, '-thumb__')) {
            return null;
        }
        $thumbnailParts = explode('__', $thumbnailPart);

        // Config name is the last one after the __assetId__
        $configName = $thumbnailParts[count($thumbnailParts) - 1];
        $type = str_contains($thumbnailParts[0], 'image') ? 'image' : 'video';

        return [
            'thumbnail_extension' => pathinfo($fileName, PATHINFO_EXTENSION),
            'thumbnail_name' => $fileName,
            'thumbnail_config_name' => $configName,
            'asset_id' => $assetId,
            'asset_path' => implode('/', [$assetPath, $assetId, '']),
            'type' => $type,
        ];
    }
}
