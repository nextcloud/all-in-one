<?php
declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

use AIO\Data\ConfigurationManager;

require __DIR__ . '/../src/Data/ConfigurationManager.php';
require __DIR__ . '/../src/Data/InvalidSettingConfigurationException.php';

// Redirect configuration paths while testing the real getters, setters and file writes.
final class TestDataConst {
    public static string $directory;

    public static function GetDataDirectory(): string {
        return self::$directory;
    }

    public static function GetConfigFile(): string {
        return self::$directory . '/configuration.json';
    }
}

class_alias(TestDataConst::class, 'AIO\Data\DataConst');
TestDataConst::$directory = sys_get_temp_dir() . '/aio-talk-bitrates-' . bin2hex(random_bytes(8));
mkdir(TestDataConst::$directory, 0700);
register_shutdown_function(static function (): void {
    foreach ([TestDataConst::GetConfigFile(), TestDataConst::GetConfigFile() . '.tmp'] as $file) {
        if (is_file($file)) {
            unlink($file);
        }
    }
    rmdir(TestDataConst::$directory);
});

function expectSame(mixed $expected, mixed $actual): void {
    if ($actual !== $expected) {
        throw new RuntimeException('Expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
}

$settings = [
    'TALK_PORT' => ['talkPort', 'talk_port', '3478', '3479', '3480'],
    'TALK_MAX_STREAM_BITRATE' => ['talkMaxStreamBitrate', 'talk_max_stream_bitrate', '1048576', '4194304', '8388608'],
    'TALK_MAX_SCREEN_BITRATE' => ['talkMaxScreenBitrate', 'talk_max_screen_bitrate', '2097152', '8388608', '4194304'],
];
$config = ['isTalkEnabled' => false];
file_put_contents(TestDataConst::GetConfigFile(), json_encode($config, JSON_THROW_ON_ERROR));

// Apply the same persistence and reset checks to Talk port and both bitrates.
foreach ($settings as $variable => [$property, $configKey, $default, $initial, $changed]) {
    $original = getenv($variable);
    try {
        foreach ([
            [false, $default, false],
            ['', '', false],
            [$initial, $initial, $initial],
            [$changed, $changed, $changed],
            [false, $changed, $changed],
            ['', '', ''],
            [false, $default, ''],
        ] as [$value, $expected, $saved]) {
            putenv($value === false ? $variable : $variable . '=' . $value);
            $manager = new ConfigurationManager();
            expectSame($expected, $manager->$property);
            expectSame($variable . '=' . $expected, $manager->replaceEnvPlaceholders($variable . '=%' . $variable . '%'));
            expectSame(false, $manager->isTalkEnabled);
            if ($saved !== false) {
                $config[$configKey] = $saved;
            }
            expectSame($config, json_decode(file_get_contents(TestDataConst::GetConfigFile()), true, 512, JSON_THROW_ON_ERROR));
        }

        foreach ([$initial, ''] as $value) {
            $manager->$property = $value;
            $config[$configKey] = $value;
            expectSame($config, json_decode(file_get_contents(TestDataConst::GetConfigFile()), true, 512, JSON_THROW_ON_ERROR));
            $manager = new ConfigurationManager();
            expectSame($value === '' ? $default : $value, $manager->$property);
        }
    } finally {
        putenv($original === false ? $variable : $variable . '=' . $original);
    }
}

$variables = ['TALK_MAX_STREAM_BITRATE', 'TALK_MAX_SCREEN_BITRATE'];
$containers = json_decode(file_get_contents(__DIR__ . '/../containers.json'), true, 512, JSON_THROW_ON_ERROR);
foreach ($containers['aio_services_v1'] as $container) {
    foreach ($variables as $variable) {
        $entries = array_values(array_filter($container['environment'] ?? [], static fn(string $entry): bool => str_starts_with($entry, $variable . '=')));
        expectSame($container['container_name'] === 'nextcloud-aio-talk' ? [$variable . '=%' . $variable . '%'] : [], $entries);
    }
}

echo "Talk bitrate placeholder tests passed.\n";
