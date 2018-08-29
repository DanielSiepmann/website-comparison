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
 *
 */
class UrlListDto
{
    protected $finishedUrls = [];

    protected $upcomingUrls = [];

    public function addUrl(string $link)
    {
        if ($this->isUrlKnown($link)) {
            return;
        }

        $this->upcomingUrls[] = $link;
    }

    public function getNextUrl(): string
    {
        return reset($this->upcomingUrls) ?? '';
    }

    public function markUrlAsFinished(string $link)
    {
        $upcomingEntry = array_search($link, $this->upcomingUrls);

        unset($this->upcomingUrls[$upcomingEntry]);

        $this->finishedUrls[] = $link;
    }

    public function isUrlKnown(string $link): bool
    {
        return in_array($link, $this->finishedUrls) || in_array($link, $this->upcomingUrls);
    }
}
