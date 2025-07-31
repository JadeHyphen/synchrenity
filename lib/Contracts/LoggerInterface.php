<?php

declare(strict_types=1);

namespace Synchrenity\Contracts;

/**
 * Logger interface for Synchrenity logging implementations
 */
interface LoggerInterface
{
    /**
     * Log a message with level and context
     */
    public function log(string $level, string $message, array $context = []): void;

    /**
     * Debug level log
     */
    public function debug(string $message, array $context = []): void;

    /**
     * Info level log
     */
    public function info(string $message, array $context = []): void;

    /**
     * Warning level log
     */
    public function warning(string $message, array $context = []): void;

    /**
     * Error level log
     */
    public function error(string $message, array $context = []): void;

    /**
     * Critical level log
     */
    public function critical(string $message, array $context = []): void;
}