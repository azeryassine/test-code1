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

namespace Pimcore\Console;

use Pimcore\Console\Style\PimcoreStyle;
use Pimcore\Tool\Admin;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Base command class setting up some defaults (e.g. the ignore-maintenance-mode switch and the VarDumper component).
 * @internal
 *
 * @method Application getApplication()
 */
abstract class AbstractCommand extends Command
{
    /**
     * @var PimcoreStyle
     */
    protected $io;

    /**
     * @var InputInterface
     */
    protected $input;

    /**
     * @var OutputInterface
     */
    protected $output;

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        parent::initialize($input, $output);

        $this->io = new PimcoreStyle($input, $output);
        $this->input = $input;
        $this->output = $output;

        // skip if maintenance mode is on and the flag is not set
        if (Admin::isInMaintenanceMode() && !$input->getOption('ignore-maintenance-mode')) {
            throw new \RuntimeException('In maintenance mode - set the flag --ignore-maintenance-mode to force execution!');
        }
    }

    /**
     * @param mixed $data
     */
    protected function dump($data)
    {
        $this->doDump($data);
    }

    /**
     * @param mixed $data
     */
    protected function dumpVerbose($data)
    {
        if ($this->output->isVerbose()) {
            $this->doDump($data);
        }
    }

    private function doDump($data)
    {
        if(function_exists('dump')) {
            dump($data);
        } else {
            var_dump($data);
        }
    }

    /**
     * @param string $message
     */
    protected function writeError($message)
    {
        $this->output->writeln(sprintf('<error>ERROR: %s</error>', $message));
    }

    /**
     * @param string $message
     */
    protected function writeInfo($message)
    {
        $this->output->writeln(sprintf('<info>INFO: %s</info>', $message));
    }

    /**
     * @param string $message
     */
    protected function writeComment($message)
    {
        $this->output->writeln(sprintf('<comment>COMMENT: %s</comment>', $message));
    }

    /**
     * @param string $message
     */
    protected function writeQuestion($message)
    {
        $this->output->writeln(sprintf('<question>QUESTION: %s</question>', $message));
    }
}
