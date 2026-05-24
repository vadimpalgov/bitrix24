<?php

namespace Bit\Mcp;

use PhpMcp\Server\Server;
use PhpMcp\Server\Transports\StdioServerTransport;
use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;

/**
 * Простой stderr-логгер — не трогает stdout (который занят JSON-RPC).
 */
class StderrLogger extends AbstractLogger
{
    public function log($level, \Stringable|string $message, array $context = []): void
    {
        // Разворачиваем исключения в читаемый текст
        foreach ($context as $k => $v) {
            if ($v instanceof \Throwable) {
                $context[$k] = get_class($v) . ': ' . $v->getMessage() . ' in ' . $v->getFile() . ':' . $v->getLine();
            }
        }
        $ctx = $context ? ' ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '';
        fwrite(STDERR, '[MCP ' . strtoupper($level) . '] ' . $message . $ctx . PHP_EOL);
    }
}

class ServerFactory
{
    public static function run(string $basePath): void
    {
        // Сбрасываем все буферы Bitrix — они не должны попасть в stdout
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        $logger = new StderrLogger();

        $server = Server::make()
            ->withServerInfo('bitrix24-mcp', '0.1.0')
            ->withLogger($logger)
            ->build();

        $server->discover(
            basePath: $basePath,
            scanDirs: ['local/modules/bit.mcp/lib/Tools'],
            excludeDirs: []
        );

        $server->listen(new StdioServerTransport());
    }
}
