<?php

namespace Codappix\WebsiteComparison\Model;

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

/**
 * List of urls with two states.
 *
 * Allows to have a single queue of urls to work on.
 */
class UrlListDto implements \JsonSerializable
{
    protected $finishedUrls = [];

    protected $upcomingUrls = [];

    public function addUrl(string $url)
    {
        if ($this->isUrlKnown($url)) {
            return;
        }

        $this->upcomingUrls[] = $url;
    }

    public function addFinishedUrl(string $url)
    {
        if ($this->isUrlKnown($url)) {
            return;
        }

        $this->finishedUrls[] = $url;
    }

    public function getNextUrl(): string
    {
        return reset($this->upcomingUrls) ?? '';
    }

    public function markUrlAsFinished(string $url)
    {
        $upcomingEntry = array_search($url, $this->upcomingUrls);

        unset($this->upcomingUrls[$upcomingEntry]);

        $this->finishedUrls[] = $url;
    }

    public function isUrlKnown(string $url): bool
    {
        return in_array($url, $this->finishedUrls) || in_array($url, $this->upcomingUrls);
    }

    public function jsonSerialize()
    {
        return [
            'finishedUrls' => $this->finishedUrls,
            'upcomingUrls' => $this->upcomingUrls,
        ];
    }
}
