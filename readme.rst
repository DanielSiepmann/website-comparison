Website Comparison
==================

A small command line tool that will screenshot a whole website. On the next run
there can be a comparison to the base that was created before.

Installation
------------

Run ``composer install``.

Also ``chromedriver`` is needed. Right now the pat his hardcoded to
``/usr/lib/chromium-browser/chromedriver`` in file ``comparison``.

Also the ``chromium-browser`` binary has to be inside the ``$PATH``.

Usage
-----

First run ``./comparison comparison:createbase`` to create the "base" version of the
website. This way we have the base to compare against later.

Second run ``./comparison comparison:comparetobase`` to screenshot website again and
inform about differences.

For further possible options add ``--help``.
