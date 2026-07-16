<?php
declare(strict_types=1);

use vielhuber\magicreplace\magicreplace;

final class Test extends \PHPUnit\Framework\TestCase
{
    protected ?\stdClass $settings = null;

    public function testAll(): void
    {
        $rii = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator('./tests/data', RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST,
            RecursiveIteratorIterator::CATCH_GET_CHILD
        );
        $folders = [];
        foreach ($rii as $rii__value) {
            if ($rii__value->isDir()) {
                $folders[] = $rii__value->getPathname();
            }
        }

        $failed = [];

        foreach ($folders as $folders__value) {
            if (!file_exists($folders__value . '/settings.json')) {
                continue;
            }

            $this->logCli('testing ' . $folders__value . '...');

            $this->settings = json_decode(file_get_contents($folders__value . '/settings.json'));

            $replace = (array) $this->settings->replace;

            $type =
                property_exists($this->settings, 'source') &&
                !(file_exists($folders__value . '/input.sql') && file_exists($folders__value . '/output.sql'))
                    ? 'db'
                    : 'file';

            if ($type === 'db') {
                $this->logCli('dumping...');
                $this->dump($folders__value . '/input.sql');
                foreach ($replace as $replace__key => $replace__value) {
                    $this->logCli('replace with interconnect...');
                    $this->replaceWithInterconnect($replace__key, $replace__value);
                }
                $this->logCli('restoring...');
                $this->dump($folders__value . '/output.sql');
                // undo changes
                $this->logCli('undo...');
                $this->restore($folders__value . '/input.sql');
            }

            magicreplace::run($folders__value . '/input.sql', $folders__value . '/expected.sql', $replace);

            $input = explode(PHP_EOL, file_get_contents($folders__value . '/input.sql'));
            $output = explode(PHP_EOL, file_get_contents($folders__value . '/output.sql'));
            $expected = explode(PHP_EOL, file_get_contents($folders__value . '/expected.sql'));
            $whitelist = [];
            if (file_exists($folders__value . '/whitelist.sql')) {
                $whitelist = explode(PHP_EOL, file_get_contents($folders__value . '/whitelist.sql'));
            }

            if ($type === 'db') {
                // remove common lines
                foreach ($input as $input__key => $input__value) {
                    if ($output[$input__key] === $expected[$input__key] || in_array($input[$input__key], $whitelist)) {
                        unset($input[$input__key]);
                        unset($output[$input__key]);
                        unset($expected[$input__key]);
                    }
                }
                file_put_contents($folders__value . '/input.sql', implode(PHP_EOL, $input));
                file_put_contents($folders__value . '/output.sql', implode(PHP_EOL, $output));
                file_put_contents($folders__value . '/expected.sql', implode(PHP_EOL, $expected));
                if (!empty($input)) {
                    $failed[] = $folders__value;
                } else {
                    $this->assertTrue(true);
                }
            }

            if ($type === 'file') {
                $this_failed = false;
                foreach ($input as $input__key => $input__value) {
                    if (
                        ($output[$input__key] !== $expected[$input__key]) &&
                        !in_array($input[$input__key], $whitelist)
                    ) {
                        $this_failed = true;
                        break;
                    }
                }
                if ($this_failed === true) {
                    $failed[] = $folders__value;
                } else {
                    if (file_exists($folders__value . '/expected.sql')) {
                        unlink($folders__value . '/expected.sql');
                    }
                    $this->assertTrue(true);
                }
            }
        }

        if (!empty($failed)) {
            $this->logCli('failed tests: ' . implode(', ', $failed));
            $this->assertTrue(false);
        }
    }

    public function testStaleSplitFilesAreRemoved(): void
    {
        $input = tempnam(sys_get_temp_dir(), 'magicreplace-input-');
        $output = tempnam(sys_get_temp_dir(), 'magicreplace-output-');
        $this->assertIsString($input);
        $this->assertIsString($output);
        $stale_split_file = $input . '-SPLITTEDzzzz';
        file_put_contents($input, "INSERT INTO test VALUES ('old.example');\n");
        file_put_contents($stale_split_file, "stale data\n");

        magicreplace::run($input, $output, ['old.example' => 'new.example']);

        $this->assertSame("INSERT INTO test VALUES ('new.example');\n", file_get_contents($output));
        $this->assertFileDoesNotExist($stale_split_file);
        unlink($input);
        unlink($output);
    }

    public function testCliSupportsAbsoluteOutputPaths(): void
    {
        $input = tempnam(sys_get_temp_dir(), 'magicreplace-cli-input-');
        $output = tempnam(sys_get_temp_dir(), 'magicreplace-cli-output-');
        $this->assertIsString($input);
        $this->assertIsString($output);
        unlink($output);
        file_put_contents($input, "INSERT INTO test VALUES ('old.example');\n");
        $command_output = [];
        $command_exit_code = 0;

        exec(
            escapeshellarg(PHP_BINARY) .
                ' ' .
                escapeshellarg(__DIR__ . '/../src/magicreplace.php') .
                ' ' .
                escapeshellarg($input) .
                ' ' .
                escapeshellarg($output) .
                ' ' .
                escapeshellarg('old.example') .
                ' ' .
                escapeshellarg('new.example') .
                ' 2>&1',
            $command_output,
            $command_exit_code
        );

        $this->assertSame(0, $command_exit_code);
        $this->assertSame("INSERT INTO test VALUES ('new.example');\n", file_get_contents($output));
        unlink($input);
        unlink($output);
    }

    public function testCliReturnsAnErrorCode(): void
    {
        $input = tempnam(sys_get_temp_dir(), 'magicreplace-cli-missing-');
        $output = tempnam(sys_get_temp_dir(), 'magicreplace-cli-output-');
        $this->assertIsString($input);
        $this->assertIsString($output);
        unlink($input);
        unlink($output);
        $command_output = [];
        $command_exit_code = 0;

        exec(
            escapeshellarg(PHP_BINARY) .
                ' ' .
                escapeshellarg(__DIR__ . '/../src/magicreplace.php') .
                ' ' .
                escapeshellarg($input) .
                ' ' .
                escapeshellarg($output) .
                ' ' .
                escapeshellarg('old.example') .
                ' ' .
                escapeshellarg('new.example') .
                ' 2>&1',
            $command_output,
            $command_exit_code
        );

        $this->assertSame(1, $command_exit_code);
        $this->assertSame(['missing input'], $command_output);
        $this->assertFileDoesNotExist($output);
    }

    private function dump(string $filename): void
    {
        exec(
            'mysqldump --extended-insert=false --skip-comments -h ' .
                $this->settings->source->host .
                ' --port ' .
                $this->settings->source->port .
                ' -u ' .
                $this->settings->source->username .
                ' -p"' .
                $this->settings->source->password .
                '" ' .
                $this->settings->source->database .
                ' > ' .
                $filename
        );
    }

    private function restore(string $filename): void
    {
        exec(
            'mysql -h ' .
                $this->settings->source->host .
                ' --port ' .
                $this->settings->source->port .
                ' -u ' .
                $this->settings->source->username .
                ' -p"' .
                $this->settings->source->password .
                '" --default-character-set=utf8 ' .
                $this->settings->source->database .
                ' < ' .
                $filename
        );
    }

    private function replaceWithInterconnect(string $search, string $replace): void
    {
        shell_exec(
            'php ' .
                __DIR__ .
                '/tools/interconnect-search-replace/4.1.2/srdb.cli.php --host ' .
                $this->settings->source->host .
                ' --name ' .
                $this->settings->source->database .
                ' --user ' .
                $this->settings->source->username .
                ' --pass ' .
                $this->settings->source->password .
                ' --port ' .
                $this->settings->source->port .
                ' --search "' .
                $search .
                '" --replace "' .
                $replace .
                '"'
        );
    }

    private function logCli(string $message): void
    {
        fwrite(STDERR, $message . PHP_EOL);
    }
}
