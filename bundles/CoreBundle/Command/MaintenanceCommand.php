<?php
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

namespace Pimcore\Bundle\CoreBundle\Command;

use Pimcore\Console\AbstractCommand;
use Pimcore\Event\System\MaintenanceEvent;
use Pimcore\Event\SystemEvents;
use Pimcore\Maintenance\CallableTask;
use Pimcore\Maintenance\ExecutorInterface;
use Pimcore\Model\Schedule;
use Pimcore\Model\Schedule\Maintenance\Job;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class MaintenanceCommand extends AbstractCommand
{
    /**
     * @var ExecutorInterface
     */
    private $maintenanceExecutor;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param ExecutorInterface $maintenanceExecutor
     * @param LoggerInterface $logger
     */
    public function __construct(ExecutorInterface $maintenanceExecutor, LoggerInterface $logger)
    {
        $this->maintenanceExecutor = $maintenanceExecutor;
        $this->logger = $logger;

        parent::__construct();
    }


    protected function configure()
    {
        $description = 'Asynchronous maintenance jobs of pimcore (needs to be set up as cron job)';

        $help = $description . '. Valid jobs are: ' . "\n\n";
        $help .= '  <comment>*</comment> any bundle class name handling maintenance (e.g. <comment>PimcoreEcommerceFrameworkBundle</comment>)' . "\n";

        foreach ($this->maintenanceExecutor->getTaskNames() as $taskName) {
            $help .= '  <comment>*</comment> ' . $taskName . "\n";
        }

        $this
            ->setName('pimcore:maintenance')
            ->setAliases(['maintenance'])
            ->setDescription($description)
            ->setHelp($help)
            ->addOption(
                'job',
                'j',
                InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED,
                'Call just a specific job(s) (see <comment>--help</comment> for a list of valid jobs)'
            )
            ->addOption(
                'excludedJobs',
                'J',
                InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED,
                'Exclude specific job(s) (see <comment>--help</comment> for a list of valid jobs)'
            )
            ->addOption(
                'force',
                'f',
                InputOption::VALUE_NONE,
                'Run the jobs, regardless if they\'re locked or not'
            )
        ;
    }

    /**
     * @inheritDoc
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $validJobs = $this->getArrayOptionValue($input, 'job');
        $excludedJobs = $this->getArrayOptionValue($input, 'excludedJobs');

        $manager = $this->getContainer()->get(Schedule\Manager\Procedural::class);
        $manager->setValidJobs($validJobs);
        $manager->setExcludedJobs($excludedJobs);
        $manager->setForce((bool) $input->getOption('force'));

        $event = new MaintenanceEvent($manager);
        \Pimcore::getEventDispatcher()->dispatch(SystemEvents::MAINTENANCE, $event);

        foreach ($manager->getJobs() as $job) {
            @trigger_error(
                sprintf('Job with ID %s is registered using the deprecated %s Event, please use a service tag instead', $job->getId(), SystemEvents::MAINTENANCE),
                E_USER_DEPRECATED
            );

            $trackerReflector = new \ReflectionClass(Job::class);
            $callableProperty = $trackerReflector->getProperty('callable');
            $callableProperty->setAccessible(true);

            $argumentsProperty = $trackerReflector->getProperty('arguments');
            $argumentsProperty->setAccessible(true);

            $callable = $callableProperty->getValue($job);
            $arguments = $argumentsProperty->getValue($job);

            if (is_callable($callable)) {
                $this->maintenanceExecutor->registerTask($job->getId(), CallableTask::fromCallable($callable, $arguments ?? []));
            }
        }

        $this->maintenanceExecutor->executeMaintenance($validJobs, $excludedJobs, (bool) $input->getOption('force'));

        $this->logger->info('All maintenance-jobs finished!');
    }

    /**
     * Get an array option value, but still support the value being comma-separated for backwards compatibility
     *
     * @param InputInterface $input
     * @param string $name
     *
     * @return array
     */
    private function getArrayOptionValue(InputInterface $input, string $name): array
    {
        $value = $input->getOption($name);
        $result = [];

        if (!empty($value)) {
            foreach ($value as $val) {
                foreach (explode(',', $val) as $part) {
                    $part = trim($part);
                    if (!empty($part)) {
                        $result[] = $part;
                    }
                }
            }
        }

        $result = array_unique($result);

        return $result;
    }
}
