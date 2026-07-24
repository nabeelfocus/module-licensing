<?php
declare(strict_types=1);

/**
 * Copyright © Focus. All rights reserved.
 * @author Focus Team
 * @package Focus_Licensing
 */

namespace Focus\Licensing\Plugin\Enforcement;

use Focus\Licensing\Model\Enforcement\ModuleLabelResolver;
use Focus\Licensing\Model\Enforcement\EnforcementGuard;
use Magento\Framework\Console\Cli;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CommandGuardPlugin
{
    /**
     * @param EnforcementGuard $enforcementGuard
     * @param ModuleLabelResolver $labelResolver
     */
    public function __construct(
        private readonly EnforcementGuard $enforcementGuard,
        private readonly ModuleLabelResolver $labelResolver
    ) {}

    /**
     * @param Command $subject
     * @param callable $proceed
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    public function aroundRun(Command $subject, callable $proceed, InputInterface $input, OutputInterface $output): int
    {
        $blockedModule = $this->enforcementGuard->getBlockedModuleForClass($subject::class);
        if ($blockedModule === null) {
            return $proceed($input, $output);
        }

        $output->writeln(sprintf(
            '<error>%s is not licensed on this store — command refused. '
            . 'Verify the license under Stores > Configuration > Focus > Licensing or contact Focus support.</error>',
            $this->labelResolver->getLabel($blockedModule)
        ));

        return Cli::RETURN_FAILURE;
    }
}
