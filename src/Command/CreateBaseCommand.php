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

use Codappix\WebsiteComparison\Model\UrlListDto;
use Codappix\WebsiteComparison\Model\UrlListDtoFactory;
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

class CreateBaseCommand extends Command
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
            ->setName('comparison:createbase')
            ->setDescription('Creates the base for comparison.')
            ->setHelp('Crawls and screenshots the original website, as a base for future comparison.')

            ->addOption(
                'screenshotDir',
                null,
                InputOption::VALUE_OPTIONAL,
                'Define the sub directory to use for storing created Screenshots.',
                'output/base'
            )
            ->addOption(
                'screenshotWidth',
                null,
                InputOption::VALUE_OPTIONAL,
                'The width for screen resolution and screenshots.',
                3840
            )
            ->addOption(
                'recoverFile',
                null,
                InputOption::VALUE_OPTIONAL,
                'Path to json-File with state of stopped process, used to recover process.',
                ''
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
        $baseUrl = $input->getArgument('baseUrl');
        $screenshotDir = $input->getOption('screenshotDir');

        $screenshotService = new Service(
            $this->eventDispatcher,
            $screenshotDir,
            $input->getOption('screenshotWidth')
        );

        $screenshotCrawler = new CrawlerService(
            $this->webDriver,
            $screenshotService,
            $baseUrl
        );

        $linkList = $this->getLinkList($baseUrl, $input->getOption('recoverFile'));

        $this->registerEvents($output);
        try {
            $screenshotCrawler->crawl($linkList);
        } catch (\Exception $e) {
            file_put_contents($this->getJsonFilePath($screenshotService, $baseUrl), json_encode($linkList));
            $output->writeln(sprintf(
                '<comment>Saved current state for recovering in "%s".</comment>',
                $this->getJsonFilePath($screenshotService, $baseUrl)
            ));
            throw $e;
        }
    }

    protected function getLinkList(
        string $baseUrl,
        string $recoverFile = ''
    ): UrlListDto {
        $factory = new UrlListDtoFactory();

        if (trim($recoverFile) !== '') {
            return $factory->createWithByConfigurationFile($recoverFile);
        }

        return $factory->createWithBaseUrl($baseUrl);
    }

    protected function getJsonFilePath(Service $screenshotService, string $baseUrl): string
    {
        return $screenshotService->getScreenshotDir() .
            DIRECTORY_SEPARATOR .
            $screenshotService->getScreenshotTarget($baseUrl, 'json')
            ;
    }

    protected function registerEvents(OutputInterface $output)
    {
        if ($output->isVerbose()) {
            $this->eventDispatcher->addListener(
                'service.screenshot.created',
                function (GenericEvent $event) use ($output) {
                    $output->writeln(sprintf(
                        '<info>Created screenshot "%s" for url "%s".</info>',
                        $event->getArgument('screenshot'),
                        $event->getArgument('url')
                    ));
                }
            );
        }

        $this->eventDispatcher->addListener(
            'screenshot.service.error',
            function (GenericEvent $event) use ($output) {
                $output->writeln(sprintf(
                    '<error>"%s"</error>',
                    $event->getArgument('e')->getMessage()
                ));
            }
        );
    }
}
