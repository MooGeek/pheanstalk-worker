pheanstalk-worker
==========

Pheanstalk is a pure PHP 7.1+ client for the [beanstalkd workqueue][1].  It has
been actively developed, and used in production by many, since late 2008.

Created by [Gordon Heydon][2], Pheanstalk Worker has ben created as a best of
bread worker. Started in Jan 2015 as stable implementation of a worker for
[Pheanstalk][3] to either to be used as a proper example of how a worker should
be created or as an implementation that can be used in a production system.

**This fork is just a version bump of every dependency plus mild refactoring
and refurbishing, at least for now.**

  [1]: https://beanstalkd.github.io/
  [2]: http://heydon.com.au/
  [3]: https://github.com/pheanstalk/pheanstalk


Installation with Composer
-------------

Install pheanstalk-worker as a dependency with composer:

```bash
composer require moogeek/pheanstalk-worker
```


Creating a worker
-----------------

A worker is a process which when runs takes the next job off a set of queues which are being watched and then executes
the job with the defined tasks.

The worker process runs the following steps:

1. Watch all registered tubes
2. Reserve the next job
3. Once job is reserved, invoke the registered handler based on the tube name
4. If no exceptions occur, delete the job (success)
5. If 'retry_on' exceptions occur, call 'release' (retry)
6. If other exception occurs, call 'bury' (error)
7. Repeat steps 2-6

To create a worker use the following example.

```
<?php

// Again hopefully you are using Composers autoloading
use Pheanstalk\Pheanstalk;
use Pheanstalk\Job;
use Moogeek\PheanstalkWorker\Worker;

$pheanstalk = Pheanstalk::create('127.0.0.1');
$worker = new Worker($pheanstalk);

// ----------------------------------------
// register functions to be called for each queue

$worker->register('testtube', function (Job $job) {
    echo $job->getData();
});

// You can register multiple tubes to be watched by a single worker
$worker->register('testtube2', function (Job $job) {
    echo $job->getData();
});

// If you Exception class is specified the job will be released instead of buried.
$worker->register('testtube3', function (Job $job) {
    echo $job->getData();
}, 'SomeException');

// -----------------------------------------
// Start the worker.

$worker->process();
```

Running the tests
-----------------

There is a section of the test suite which depends on a running beanstalkd
at 127.0.0.1:11300, which was previously opt-in via `--with-server`.
Since porting to PHPUnit, all tests are run at once. Feel free to submit
a pull request to rectify this.

```
# ensure you have Composer set up
$ wget http://getcomposer.org/composer.phar
$ php composer.phar install

# ensure you have PHPUnit
$ composer install --dev

$ ./vendor/bin/phpunit
PHPUnit 4.0.19 by Sebastian Bergmann.

Configuration read from /Users/pda/code/pheanstalk/phpunit.xml.dist

................................................................. 65 / 83 ( 78%)
..................

Time: 239 ms, Memory: 6.00Mb

OK (83 tests, 378 assertions)
```


Licence
-------

© Gordon Heydon, Stanislav Zakratskiy

Released under the [The MIT License](http://www.opensource.org/licenses/mit-license.php)
