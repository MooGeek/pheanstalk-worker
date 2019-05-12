<?php

namespace PheanstalkWorker;

use Pheanstalk\Pheanstalk;
use Pheanstalk\Job;
use PHPUnit\Framework\TestCase;

final class WorkerTest extends TestCase
{
    const SERVER_HOST = 'localhost';
    const SERVER_PORT = '11300';

    /**
     * @throws Exception\WorkerException
     */
    public function testWorkerRuns(): void
    {
        $testWorkerRuns = $this;
        $tube = 'worker_tube_'.rand(53, 504);
        $data = 'worker_value_'.rand(95, 3000);

        $pheanstalk = Pheanstalk::create(self::SERVER_HOST, self::SERVER_PORT);
        $worker = new Worker($pheanstalk);

        $job = $pheanstalk->useTube($tube)
            ->put($data);

        $worker->register($tube, function (Job $job) use ($testWorkerRuns, $data) {
            $testWorkerRuns->assertEquals($data, $job->getData());
        });

        $processedJob = $worker->processOne($pheanstalk->reserve());

        $stats = $pheanstalk->statsTube($tube);
        $this->assertEquals($stats['total-jobs'], 1);
    }

    /**
     * When null is passed to ::processOne() it should return quitely.
     *
     * @throws Exception\WorkerException
     */
    public function testWorkerNoJobs(): void
    {
        $testWorkerRuns = $this;
        $tube = 'worker_tube_'.rand(53, 504);
        $data = 'worker_value_'.rand(95, 3000);

        $pheanstalk = Pheanstalk::create(self::SERVER_HOST, self::SERVER_PORT);
        $worker = new Worker($pheanstalk);

        $worker->register($tube, function (Job $job) use ($testWorkerRuns, $data) {
            $testWorkerRuns->assertEquals($data, $job->getData());
        });

        $processedJob = $worker->processOne($pheanstalk->reserveWithTimeout(0));

        $stats = $pheanstalk->statsTube($tube);
        $this->assertEquals($stats['total-jobs'], 0);
    }
}
