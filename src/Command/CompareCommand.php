<?php

namespace Codappix\WebsiteComparison\Command;

/*
 * Copyright (C) 2018 Daniel Siepmann <coding@daniel-siepmann.de>
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA
 * 02110-1301, USA.
 */

use Codappix\WebsiteComparison\Service\Screenshot\CompareService;
use Codappix\WebsiteComparison\Service\Screenshot\CrawlerService;
use Codappix\WebsiteComparison\Service\Screenshot\Service;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\GenericEvent;

class CompareCommand extends Command
{
    /**
     * @var EventDispatcherInterface
     */
    protected $eventDispatcher;

    /**
     * @var RemoteWebDriver
     */
    protected $webDriver;

    public function __construct(
        EventDispatcherInterface $eventDispatcher,
        RemoteWebDriver $webDriver
    ) {
        parent::__construct(null);

        $this->eventDispatcher = $eventDispatcher;
        $this->webDriver = $webDriver;
    }

    protected function configure()
    {
        $this
            ->setName('comparison:comparetobase')
            ->setDescription('Compare curent state against saved base.')
            ->setHelp('Crawls and screenshots the original website, as a base for future comparison.')

            ->addOption(
                'screenshotDir',
                null,
                InputOption::VALUE_OPTIONAL,
                'Define the sub directory containing original Screenshots for comparison.',
                'output/base'
            )
            ->addOption(
                'compareDir',
                null,
                InputOption::VALUE_OPTIONAL,
                'Define the sub directory to use for storing created Screenshots.',
                'output/compare'
            )
            ->addOption(
                'diffResultDir',
                null,
                InputOption::VALUE_OPTIONAL,
                'Define the sub directory to use for storing created diffs.',
                'output/diffResult'
            )
            ->addOption(
                'screenshotWidth',
                null,
                InputOption::VALUE_OPTIONAL,
                'The width for screen resolution and screenshots.',
                3840
            )

            ->addArgument(
                'baseUrl',
                InputArgument::REQUIRED,
                'E.g. https://typo3.org/ the base url of the website to crawl.'
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {

        $screenshotService = new Service(
            $this->eventDispatcher,
            $input->getOption('compareDir'),
            $input->getOption('screenshotWidth')
        );

        $screenshotCrawler = new CrawlerService(
            $this->webDriver,
            $screenshotService,
            $input->getArgument('baseUrl')
        );

        $compareService = new CompareService(
            $this->eventDispatcher,
            $screenshotService,
            $input->getOption('screenshotDir'),
            $input->getOption('diffResultDir')
        );
        $this->registerEvents($output, $compareService);

        $screenshotCrawler->crawl();

        if ($compareService->hasDifferences()) {
            return 255;
        }
    }

    protected function registerEvents(OutputInterface $output, CompareService $compareService)
    {
        $this->eventDispatcher->addListener(
            'service.screenshot.created',
            function (GenericEvent $event) use ($output, $compareService) {
                $output->writeln(sprintf(
                    '<info>Comparing Screenshot for url "%s".</info>',
                    $event->getArgument('url')
                ));
                $compareService->compareScreenshot(
                    $event->getArgument('screenshot')
                );
            }
        );

        if ($output->isVerbose()) {
            $this->eventDispatcher->addListener(
                'service.screenshot.isSame',
                function (GenericEvent $event) use ($output) {
                    $output->writeln(sprintf(
                        '<info>Screenshot "%s" is as expected.</info>',
                        $event->getArgument('screenshot')
                    ));
                }
            );
        }

        $this->eventDispatcher->addListener(
            'service.screenshot.isDifferent',
            function (GenericEvent $event) use ($output) {
                $output->writeln(sprintf(
                    '<error>Screenshot "%s" is different, created diff at "%s".</error>',
                    $event->getArgument('screenshot'),
                    $event->getArgument('diff')
                ));
            }
        );

        $this->eventDispatcher->addListener(
            'service.screenshot.error',
            function (GenericEvent $event) use ($output) {
                $output->writeln(sprintf(
                    '<error>"%s"</error>',
                    $event->getArgument('e')->getMessage()
                ));
            }
        );
    }
}
