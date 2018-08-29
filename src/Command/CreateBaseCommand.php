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

use Codappix\WebsiteComparison\Service\ScreenshotCrawlerService;
use Facebook\WebDriver\Chrome\ChromeDriver;
use Facebook\WebDriver\Chrome\ChromeDriverService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

/**
 *
 */
class CreateBaseCommand extends Command
{
    /**
     * @var Process
     */
    protected $chromeProcess;

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
                'output'
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
        $screenshotCrawler = new ScreenshotCrawlerService(
            $output,
            $this->getDriver(),
            $input->getArgument('baseUrl'),
            $input->getOption('screenshotDir'),
            $input->getOption('screenshotWidth')
        );
        $screenshotCrawler->crawl();
    }

    protected function getDriver(): ChromeDriver
    {
        $chromeDriverService = new ChromeDriverService(
            '/usr/lib/chromium-browser/chromedriver',
            9515,
            [
                '--port=9515',
                '--headless',
            ]
        );
        $driver = ChromeDriver::start(null, $chromeDriverService);

        return $driver;
    }
}
