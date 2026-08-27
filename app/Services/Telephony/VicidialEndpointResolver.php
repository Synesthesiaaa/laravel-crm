<?php

namespace App\Services\Telephony;

use App\Models\VicidialServer;

class VicidialEndpointResolver
{
    public const NON_AGENT_API = 'non_agent_api';

    public const AGENT_API = 'agent_api';

    public const REALTIME_REPORT = 'realtime_report';

    public const AGENT_REPORT = 'agent_report';

    public const HISTORICAL_REPORT = 'historical_report';

    /**
     * Resolve a known VICIdial endpoint from one campaign server.
     *
     * The resolver deliberately accepts only the server passed by the caller.
     * It never searches for another server when a URL is absent.
     */
    public function resolve(VicidialServer $server, string $endpoint): string
    {
        $explicit = $this->explicitEndpoint($server, $endpoint);
        if ($explicit !== '') {
            return $explicit;
        }

        return match ($endpoint) {
            self::NON_AGENT_API => $this->siblingFromAgentApi($server->api_url, 'non_agent_api.php'),
            self::AGENT_API => trim((string) $server->api_url),
            self::REALTIME_REPORT => $this->siblingFromReference($server, 'AST_timeonVDADall.php'),
            self::AGENT_REPORT => $this->siblingFromReference($server, 'AST_agent_stats.php'),
            self::HISTORICAL_REPORT => $this->siblingFromReference($server, 'AST_Vicidial_Campaigns.php'),
            default => '',
        };
    }

    public function nonAgentApi(VicidialServer $server): string
    {
        return $this->resolve($server, self::NON_AGENT_API);
    }

    public function agentApi(VicidialServer $server): string
    {
        return $this->resolve($server, self::AGENT_API);
    }

    public function realtimeReport(VicidialServer $server): string
    {
        return $this->resolve($server, self::REALTIME_REPORT);
    }

    public function agentReport(VicidialServer $server): string
    {
        return $this->resolve($server, self::AGENT_REPORT);
    }

    public function historicalReport(VicidialServer $server): string
    {
        return $this->resolve($server, self::HISTORICAL_REPORT);
    }

    private function explicitEndpoint(VicidialServer $server, string $endpoint): string
    {
        $attribute = match ($endpoint) {
            self::NON_AGENT_API => 'non_agent_api_url',
            self::AGENT_API => 'api_url',
            default => null,
        };

        if ($attribute === null) {
            return '';
        }

        return trim((string) $server->getAttribute($attribute));
    }

    private function siblingFromReference(VicidialServer $server, string $filename): string
    {
        $reference = $this->agentApi($server);
        if ($reference === '') {
            $reference = $this->nonAgentApi($server);
        }

        return $this->siblingFromAgentApi($reference, $filename);
    }

    private function siblingFromAgentApi(?string $reference, string $filename): string
    {
        $reference = trim((string) $reference);
        if ($reference === '') {
            return '';
        }

        $parts = parse_url($reference);
        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])) {
            return '';
        }

        $path = (string) ($parts['path'] ?? '');
        $directory = str_ends_with($path, '/') ? rtrim($path, '/') : rtrim((string) dirname($path), '/\\');
        if (str_ends_with($path, '/agc/api.php') || str_ends_with($path, '/non_agent_api.php')) {
            $directory = rtrim((string) dirname($path), '/\\');
            if (str_ends_with($directory, '/agc')) {
                $directory = rtrim((string) dirname($directory), '/\\');
            }
        }

        $basePath = $directory === '' || $directory === '.' ? '' : '/'.$directory;
        $basePath = preg_replace('#/+#', '/', $basePath) ?: '';
        $port = isset($parts['port']) ? ':'.$parts['port'] : '';

        return sprintf(
            '%s://%s%s%s/%s',
            $parts['scheme'],
            $parts['host'],
            $port,
            rtrim($basePath, '/'),
            ltrim($filename, '/'),
        );
    }
}
