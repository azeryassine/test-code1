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

namespace Pimcore\Console\Traits;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\LockInterface;
use Webmozarts\Console\Parallelization\Parallelization as WebmozartParallelization;

trait Parallelization
{
    /** @var LockInterface|null */
    private $lock;

    use WebmozartParallelization
    {
        WebmozartParallelization::configureParallelization as parentConfigureParallelization;
    }

    /**
     * @deprecated Deprecated since webmozarts/console-parallelization 2.0.0 and will be removed in Pimcore 11.
     */
    protected static function configureParallelization(Command $command): void
    {
        // we need to override WebmozartParallelization::configureParallelization here
        // because some existing commands are already using the `p` option, and would therefore
        // causes collisions
        $command
            ->addArgument(
                'item',
                InputArgument::OPTIONAL,
                'The item to process. Can be used in commands where simple IDs are processed. Otherwise it is for internal use.'
            )
            ->addOption(
                'processes',
                null,
                //'p', avoid collisions with already existing Pimcore command options
                InputOption::VALUE_OPTIONAL,
                'The number of parallel processes to run',
                '1'
            )
            ->addOption(
                'child',
                null,
                InputOption::VALUE_NONE,
                'Set on child processes. For internal use only.'
            )->addOption(
                'batch-size',
                null,
                InputOption::VALUE_OPTIONAL,
                'Sets the number of items to process per child process or in a batch',
                '50'
            )
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function getSegmentSize(): int
    {
        return (int)$this->input->getOption('batch-size');
    }

    /**
     * {@inheritdoc}
     */
    protected function runBeforeFirstCommand(InputInterface $input, OutputInterface $output): void
    {
        // checking if the p option is used
        // TODO Remove in Pimcore 11
        if(isset($_SERVER['argv'])) {
            if(in_array('-p', $_SERVER['argv'])) {
                $output->writeln([
                    "<comment>=================================================================</>",
                    "<comment>You are using the shortcut p with another option than processes</>",
                    "<comment>This will be removed with Pimcore 11. Please replace the shortcut</>",
                    "<comment>=================================================================</>"
                ]);
            }
        }

        if (!$this->lock()) {
            $this->writeError('The command is already running.');
            exit(1);
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function runAfterBatch(InputInterface $input, OutputInterface $output, array $items): void
    {
        if ((int)$this->input->getOption('processes') <= 1) {
            if ($output->isVeryVerbose()) {
                $output->writeln('Collect garbage.');
            }
            \Pimcore::collectGarbage();
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function runAfterLastCommand(InputInterface $input, OutputInterface $output): void
    {
        $this->release(); //release the lock
    }

    /**
     * {@inheritdoc}
     */
    protected function getItemName(int $count): string
    {
        return $count <= 1 ? 'item' : 'items';
    }

    /**
     * {@inheritdoc}
     */
    protected function getContainer()
    {
        return \Pimcore::getKernel()->getContainer();
    }

    /**
     * Locks a command.
     *
     * @param string|null $name
     * @param bool|null $blocking
     *
     * @return bool
     */
    private function lock($name = null, $blocking = false)
    {
        $this->lock = \Pimcore::getContainer()->get(LockFactory::class)->createLock($name ?: $this->getName(), 86400);

        if (!$this->lock->acquire($blocking)) {
            $this->lock = null;

            return false;
        }

        return true;
    }

    /**
     * Releases the command lock if there is one.
     */
    private function release()
    {
        if ($this->lock) {
            $this->lock->release();
            $this->lock = null;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getConsolePath(): string
    {
        return PIMCORE_PROJECT_ROOT . '/bin/console';
    }
}
