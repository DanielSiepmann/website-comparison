<?php

namespace Codappix\WebsiteComparison\Service;

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
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\Remote\RemoteWebElement;
use Facebook\WebDriver\WebDriverBy;
use GuzzleHttp\Psr7\Uri;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

/**
 *
 */
class ScreenshotCrawlerService
{
    /**
     * @var OutputInterface
     */
    protected $output;

    /**
     * @var RemoteWebDriver
     */
    protected $driver;

    /**
     * @var string
     */
    protected $baseUrl = '';

    /**
     * @var string
     */
    protected $screenshotDir = '';

    /**
     * @var int
     */
    protected $screenshotWidth = 3840;

    public function __construct(
        OutputInterface $output,
        RemoteWebDriver $driver,
        string $baseUrl,
        string $screenshotDir = 'output/',
        int $screenshotWidth = 3840
    ) {
        $this->output = $output;
        $this->driver = $driver;
        $this->baseUrl = rtrim($baseUrl, '/') . '/';
        $this->screenshotDir = implode(DIRECTORY_SEPARATOR, [
            dirname(dirname(dirname(__FILE__))),
            rtrim($screenshotDir, '/')
        ]) . DIRECTORY_SEPARATOR;
        $this->screenshotWidth = $screenshotWidth;
    }

    public function crawl()
    {
        $this->createScreenshotDirIfNecessary();

        $linkList = new UrlListDto();
        $linkList->addUrl($this->baseUrl);

        while ($url = $linkList->getNextUrl()) {
            $this->driver->get($url);
            $screenshotHeight = $this->driver->findElement(WebDriverBy::cssSelector('body'))
                ->getSize()
                ->getHeight();
            $this->createScreenshot($this->driver->getCurrentURL(), $screenshotHeight);

            $linkList->markUrlAsFinished($url);
            array_map([$linkList, 'addUrl'], $this->fetchFurtherLinks(
                $this->driver->findElements(WebDriverBy::cssSelector('a'))
            ));
        }
    }

    /**
     * @throws \Exception If folder could not be created.
     */
    protected function createScreenshotDirIfNecessary(string $subPath = '')
    {
        $dir = $this->screenshotDir;
        if ($subPath !== '') {
            $dir = $dir . DIRECTORY_SEPARATOR . trim($subPath, DIRECTORY_SEPARATOR);
        }
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        if (!is_dir($this->screenshotDir)) {
            throw new \Exception('Could not create screenshot dir: "' . $dir . '".', 1535528875);
        }
    }

    protected function createScreenshot(string $url, int $height)
    {
        $screenshotTarget = $this->getScreenshotTarget($url);
        $this->createScreenshotDirIfNecessary(dirname($screenshotTarget));

        $screenshotProcess = new Process([
            'chromium-browser',
            '--headless',
            '--disable-gpu',
            '--window-size=' . $this->screenshotWidth . ',' . $height,
            '--screenshot=' . $this->screenshotDir . $screenshotTarget,
            $url
        ]);
        // TODO: Check for success
        $screenshotProcess->run();

        if ($this->output->isVerbose()) {
            $this->output->writeln(sprintf(
                '<info>Created screenshot "%s" for url "%s".</info>',
                $this->screenshotDir . $screenshotTarget,
                $url
            ));
        }
    }

    protected function getScreenshotTarget(string $url)
    {
        $uri = new Uri($url);

        return implode(
            DIRECTORY_SEPARATOR,
            array_filter(
                [
                    $uri->getScheme(),
                    $uri->getHost(),
                    trim($uri->getPath(), '/'),
                    $uri->getQuery(),
                ],
                function (string $string) {
                    return trim($string, ' /') !== '';
                }
            )
        ) . '.png';
    }

    protected function fetchFurtherLinks(array $webElements): array
    {
        $links = [];
        foreach ($webElements as $webElement) {
            try {
                $link = $this->fetchLinkFromElement($webElement);
            } catch (\Exception $e) {
                continue;
            }

            $links[] = $link;
        }

        return $links;
    }

    protected function fetchLinkFromElement(RemoteWebElement $element): string
    {
        $uri = null;
        $href = $element->getAttribute('href');
        if (is_string($href)) {
            $uri = new Uri($href);
        }

        if ($uri === null) {
            throw new \Exception('Did not get a Uri for element.', 1535530859);
        }

        if ($this->isInternalLink($uri)) {
            return (string) $uri;
        }

        throw new \Exception('Was external link.', 1535639056);
    }

    protected function isInternalLink(Uri $uri): bool
    {
        $validHosts = [
            '',
            (new Uri($this->baseUrl))->getHost(),
        ];

        return in_array($uri->getHost(), $validHosts);
    }
}
