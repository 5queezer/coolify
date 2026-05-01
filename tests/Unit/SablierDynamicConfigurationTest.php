<?php

use App\Services\SablierDynamicConfiguration;
use Symfony\Component\Yaml\Yaml;

it('does not emit empty traefik yaml when no sablier routes exist', function () {
    expect(SablierDynamicConfiguration::build([]))->toBeNull();

    expect(SablierDynamicConfiguration::build([
        [
            'name' => 'Disabled App',
            'fqdn' => 'https://disabled.example.com',
            'ports_exposes' => '3000',
            'custom_labels' => base64_encode("sablier.enable=false\nsablier.group=disabled"),
        ],
    ]))->toBeNull();
});

it('generates persistent file-provider routes from current sablier applications only', function () {
    $yaml = SablierDynamicConfiguration::build([
        [
            'name' => 'Pearl Dashboard',
            'fqdn' => 'https://pearl.example.com,http://pearl.local',
            'ports_exposes' => '3000',
            'custom_labels' => base64_encode(implode("\n", [
                'sablier.enable=true',
                'sablier.group=pearl',
                'sablier.alias=pearl-sablier',
                'sablier.session_duration=20m',
                'sablier.timeout=90s',
            ])),
        ],
        [
            'name' => 'Stale Nexus',
            'fqdn' => 'https://nexus.example.com',
            'ports_exposes' => '8081',
            'custom_labels' => base64_encode("sablier.enable=false\nsablier.group=nexus-crm"),
        ],
    ]);

    expect($yaml)->not->toBeNull()
        ->and($yaml)->toContain('pearl.example.com')
        ->and($yaml)->toContain('pearl.local')
        ->and($yaml)->not->toContain('nexus-crm')
        ->and($yaml)->not->toContain('nexus.example.com');

    $parsed = Yaml::parse(str($yaml)->after("\n")->value());

    expect($parsed['http']['routers'])->toHaveCount(2)
        ->and($parsed['http']['middlewares']['sablier-pearl']['plugin']['sablier']['group'])->toBe('pearl')
        ->and($parsed['http']['middlewares']['sablier-pearl']['plugin']['sablier']['sessionDuration'])->toBe('20m')
        ->and($parsed['http']['middlewares']['sablier-pearl']['plugin']['sablier']['timeout'])->toBe('90s')
        ->and($parsed['http']['services']['sablier-pearl']['loadBalancer']['servers'][0]['url'])->toBe('http://pearl-sablier:3000');
});

it('skips incomplete sablier applications instead of generating broken routers', function () {
    $yaml = SablierDynamicConfiguration::build([
        [
            'name' => 'No Domain',
            'fqdn' => null,
            'ports_exposes' => '3000',
            'custom_labels' => base64_encode("sablier.enable=true\nsablier.group=no-domain\nsablier.alias=no-domain-sablier"),
        ],
        [
            'name' => 'No Port',
            'fqdn' => 'https://no-port.example.com',
            'ports_exposes' => null,
            'custom_labels' => base64_encode("sablier.enable=true\nsablier.group=no-port\nsablier.alias=no-port-sablier"),
        ],
    ]);

    expect($yaml)->toBeNull();
});
