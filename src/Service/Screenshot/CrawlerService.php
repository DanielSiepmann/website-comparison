<?php

namespace Codappix\WebsiteComparison\Service\Screenshot;

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

class CrawlerService
{
    /**
     * @var RemoteWebDriver
     */
    protected $driver;

    /**
     * @var Service
     */
    protected $screenshotService;

    /**
     * @var string
     */
    protected $baseUrl = '';

    public function __construct(
        RemoteWebDriver $driver,
        Service $screenshotService,
        string $baseUrl
    ) {
        $this->driver = $driver;
        $this->screenshotService = $screenshotService;
        $this->baseUrl = rtrim($baseUrl, '/') . '/';
    }

    public function crawl()
    {
        $linkList = new UrlListDto();
        $linkList->addUrl($this->baseUrl);

        while ($url = $linkList->getNextUrl()) {
            $this->driver->get($url);
            $screenshotHeight = $this->driver->findElement(WebDriverBy::cssSelector('body'))
                ->getSize()
                ->getHeight();
            $this->screenshotService->createScreenshot(
                $this->driver->getCurrentURL(),
                $screenshotHeight
            );

            $linkList->markUrlAsFinished($url);
            array_map([$linkList, 'addUrl'], $this->fetchFurtherLinks(
                $this->driver->findElements(WebDriverBy::cssSelector('a'))
            ));
        }
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
