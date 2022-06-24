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
 *  @license    http://www.pimcore.org/license     GPLv3 and PCL
 */

namespace Pimcore\Bundle\CoreBundle\Command;

use Pimcore\Console\AbstractCommand;
use Pimcore\Model\DataObject\ClassDefinition;
use Pimcore\Model\DataObject\ClassDefinition\ClassLayoutDefinitionManager;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @internal
 */
class CustomLayoutRebuildCommand extends AbstractCommand
{
    /**
     * @var ClassLayoutDefinitionManager
     */
    protected $classLayoutDefinitionManager;

    protected function configure()
    {
        $this
            ->setName('pimcore:deployment:custom-layouts-rebuild')
            ->setDescription('rebuilds db structure for custom layouts based on updated var/classes/customlayouts/definition_*.php files')
            ->addOption(
                'create-custom-layouts',
                'c',
                InputOption::VALUE_NONE,
                'Create missing custom layouts (custom layouts that exists in var/classes/customlayouts but not in the database)'
            )
            ->addOption(
                'delete-custom-layouts',
                'd',
                InputOption::VALUE_NONE,
                'Delete missing custom layouts (custom layouts that don\'t exists in var/classes/customlayouts anymore but in the database)'
            );
    }

    /**
     * @param ClassLayoutDefinitionManager $classLayoutDefinitionManager
     * @required
     */
    public function setClassLayoutDefinitionManager(ClassLayoutDefinitionManager $classLayoutDefinitionManager)
    {
        $this->classLayoutDefinitionManager = $classLayoutDefinitionManager;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        trigger_deprecation(
            'pimcore/pimcore',
            '10.5',
            'Running Custom Layout Rebuild Command is deprecated in favor of LocationAwareConfigRepository and will be removed from Pimcore in 11.',
            __CLASS__
        );

        if ($input->getOption('delete-custom-layouts')) {
            $this->io->warning(
                '<error>Nothing to delete! Custom Layouts are not managed in the database anymore. </error>',
            );

        }

        $list = new ClassDefinition\CustomLayout\Listing();

        if ($output->isVerbose()) {
            $output->writeln('---------------------');
            $output->writeln('Saving all custom layouts');
        }

        if ($input->getOption('create-custom-layouts')) {
            foreach ($this->classLayoutDefinitionManager->createOrUpdateLayoutDefinitions() as $changes) {
                if ($output->isVerbose()) {
                    [$layout, $id, $action] = $changes;
                    $output->writeln(sprintf('%s [%s] %s', $layout, $id, $action));
                }
            }
        } else {
            foreach ($list->getLayoutDefinitions() as $layout) {
                if ($layout instanceof ClassDefinition\CustomLayout) {
                    if ($output->isVerbose()) {
                        $output->writeln(sprintf('%s [%s] created', $layout->getName(), $layout->getId()));
                    }

                    $layout->save(false);
                }
            }
        }

        return 0;
    }
}
