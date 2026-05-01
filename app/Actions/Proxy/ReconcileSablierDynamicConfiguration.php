<?php

namespace App\Actions\Proxy;

use App\Enums\ProxyTypes;
use App\Models\Server;
use App\Services\SablierDynamicConfiguration;
use Lorisleiva\Actions\Concerns\AsAction;

class ReconcileSablierDynamicConfiguration
{
    use AsAction;

    public function handle(Server $server, array $excludeApplicationIds = []): void
    {
        if ($server->proxyType() !== ProxyTypes::TRAEFIK->value) {
            return;
        }

        $dynamicPath = rtrim($server->proxyPath(), '/').'/dynamic';
        $archivePath = rtrim($server->proxyPath(), '/').'/dynamic-archive';
        $generatedFile = $dynamicPath.'/'.SablierDynamicConfiguration::GENERATED_FILENAME;

        $configuration = SablierDynamicConfiguration::build(
            $server->applications()
                ->reject(fn ($application) => in_array($application->id, $excludeApplicationIds, true))
                ->filter(fn ($application) => $application->isSablierEnabled())
        );

        $escapedDynamicPath = escapeshellarg($dynamicPath);
        $escapedArchivePath = escapeshellarg($archivePath);
        $escapedGeneratedFile = escapeshellarg($generatedFile);

        $commands = [
            "mkdir -p {$escapedDynamicPath} {$escapedArchivePath}",
            "find {$escapedDynamicPath} -maxdepth 1 -type f \\( -name 'sablier-*.bak' -o -name 'sablier-*.disabled' -o -name 'sablier-apps.yml.*' \\) -exec mv -f {} {$escapedArchivePath}/ \\; 2>/dev/null || true",
        ];

        if ($configuration === null) {
            $commands[] = "rm -f {$escapedGeneratedFile}";
        } else {
            $encodedConfiguration = base64_encode($configuration);
            $commands[] = "echo '{$encodedConfiguration}' | base64 -d | tee {$escapedGeneratedFile} > /dev/null";
        }

        instant_remote_process($commands, $server, false);
    }
}
