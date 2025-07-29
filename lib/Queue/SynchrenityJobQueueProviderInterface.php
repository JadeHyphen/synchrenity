<?php

declare(strict_types=1);

namespace Synchrenity\Queue;

interface SynchrenityJobQueueProviderInterface
{
    // Core job queue operations
    public function dispatch($job, $delay = 0, $retries = 0, $priority = 0, $dependencies = []);
    public function process();
    public function getJobs($status = null);
    public function getJob($jobId);
    public function cancelJob($jobId);
    public function pause();
    public function resume();
    public function isPaused();

    // Advanced job management
    public function setPriority($jobId, $priority);
    public function addDependency($jobId, $dependsOnJobId);
    public function getDependencies($jobId);
    public function getDeadLetterQueue();
    public function retryJob($jobId);
    public function updateJob($jobId, $data);
    public function jobExists($jobId);
    public function getJobStatus($jobId);
    public function getJobResult($jobId);

    // Metrics and introspection
    public function getQueueLength($status = null);
    public function getStats();
    public function getNextScheduledJob();
    public function getFailedJobs();
    public function getCompletedJobs();
}
