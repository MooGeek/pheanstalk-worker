<?php

namespace PheanstalkWorker;

/*
 * Default implementation of a php worker.
 *
 * @author Gordon Heydon
 * @author Stanislav Zakratskiy
 * @package Pheanstalk Worker
 * @licence http://www.opensource.org/licenses/mit-license.php
 */

use Pheanstalk\Pheanstalk;
use Pheanstalk\Job;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class Worker
{
    private $_pheanstalk;
    private $_callbacks = array();
    private $_logger = null;

    /**
     * @param Pheanstalk      $pheanstalk
     * @param LoggerInterface $logger
     */
    public function __construct(Pheanstalk $pheanstalk, LoggerInterface $logger = null)
    {
        $this->_pheanstalk = $pheanstalk;

        if ($logger) {
            $this->_logger = $logger;
        } else {
            $this->_logger = new NullLogger();
        }

        $this->_logger->notice('Worker initiated.');
    }

    /**
     * @param string   $tube
     * @param callable $callable will be executed upon receiving appropriate a job
     * @param string   $retryOn  name of an exception class which will trigger job retry instead of burying it
     */
    public function register(string $tube, callable $callable, string $retryOn = ''): void
    {
        $this->_callbacks[$tube] = array(
            'callable' => $callable,
            'retryOn' => $retryOn,
        );
        $this->_pheanstalk->watch($tube);

        $this->_logger->notice('Callback registered', array('tube' => $tube));
    }

    /**
     * Process jobs forever.
     */
    public function process(): void
    {
        $this->_logger->notice('Start processing jobs', array('tubes' => array_keys($this->_callbacks)));

        while ($job = $this->_pheanstalk->reserve()) {
            $this->processOne($job);
        }
    }

    /**
     * Process reserved job.
     *
     * @param Job $job
     *
     * @throws Exception\WorkerException if a job is reserved which has no registered callable then throw this error and stop the processing.
     *                                   This should never occur as we are only watching tubes with registered handlers.
     */
    public function processOne(?Job $job): void
    {
        if ($job === null) {
            return;
        }

        // Get the job stats so we know which tube this was received from.
        $statJob = $this->_pheanstalk->statsJob($job);
        $tube = $statJob['tube'];

        if (isset($this->_callbacks[$tube])) {
            try {
                // get  starting stats for later comparision.
                $startTime = microtime(true);
                $startMem = memory_get_usage();
                $this->_callbacks[$tube]['callable']($job);
                $this->_pheanstalk->delete($job);

                $this->_logger->notice('Job '.$job->getId().' complete. Time taken: '.(microtime(true) - $startTime).' Memory Used: '.(memory_get_usage() - $startMem));
            } catch (Exception $e) {
                if (!empty($this->_callbacks[$tube]['retryOn']) && is_a($e, $this->_callbacks['retryOn'])) {
                    $this->_logger->warning('Job '.$job->getId().' failed. Releasing Job and retrying again.', array('trace' => $e->getTraceAsString()));

                    $this->_pheanstalk->release($job);
                } else {
                    $this->_logger->error('Job '.$job->getId().' failed. Burying job.', array('trace' => $e->getTraceAsString()));

                    $this->_pheanstalk->bury($job);
                }
            }
        } elseif ($tube == 'default') {
            // if we receive a job from the "default" queue and there is no registered function for the default queue then ignore it and move on.
            $this->_pheanstalk->release($job);
            $this->_pheanstalk->ignore('default');

            $this->_logger->warning('Job reserved from default tube. Releasing job and ignoring default tube.', $statJob);
        } else {
            // we know nothing about this job and what we should do with it. We should not have received this so something is really not right.
            $this->_pheanstalk->release($job);

            $this->_logger->error("Job fetched for unknown tube '$tube'", array('id' => $job->getId()));

            throw new Exception\WorkerException(sprintf('Job fetched for unknown tube "%s"', $tube));
        }
    }
}
