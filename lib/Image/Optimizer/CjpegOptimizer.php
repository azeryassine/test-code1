<?php

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
 *  @license    http://www.pimcore.org/license     GPLv3 and PEL
 */

namespace Pimcore\Image\Optimizer;

use Symfony\Component\HttpFoundation\File\MimeType\MimeTypeGuesser;
use Symfony\Component\HttpFoundation\File\MimeType\MimeTypeGuesserInterface;

final class CjpegOptimizer extends AbstractCommandOptimizer
{
    /**
     * @var MimeTypeGuesserInterface
     */
    private $mimeTypeGuesser;

    public function __construct()
    {
        $this->mimeTypeGuesser = MimeTypeGuesser::getInstance();
    }

    /**
     * {@inheritdoc}
     */
    protected function getExecutable(): string
    {
        return 'cjpeg';
    }

    /**
     * {@inheritdoc}
     */
    protected function getCommandArray(string $executable, string $input, string $output): array
    {
        return [$executable, '-outfile', $output, $input];
    }

    /**
     * @deprecated
     * {@inheritdoc}
     */
    protected function getCommand(string $executable, string $input, string $output): string
    {
        return implode(' ', $this->getCommandArray($executable, $input, $output));
    }

    /**
     * {@inheritdoc}
     */
    public function supports(string $input): bool
    {
        return $this->mimeTypeGuesser->guess($input) === 'image/jpeg';
    }
}
