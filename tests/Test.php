<?php
use vielhuber\magicreplace\magicreplace;

class Test extends \PHPUnit\Framework\TestCase
{
    protected $settings;

    public function testAll()
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
                        ($output[$input__key] !== $expected[$input__key]) &
                        !in_array($input[$input__key], $whitelist)
                    ) {
                        $this_failed = true;
                        break;
                    }
                }
                if ($this_failed === true) {
                    $failed[] = $folders__value;
                } else {
                    @unlink($folders__value . '/expected.sql');
                    $this->assertTrue(true);
                }
            }
        }

        if (!empty($failed)) {
            $this->logCli('failed tests: ' . implode(', ', $failed));
            $this->assertTrue(false);
        }
    }

    private function dump($filename)
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

    private function restore($filename)
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

    private function replaceWithInterconnect($search, $replace)
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

    private function logCli($msg)
    {
        fwrite(STDERR, print_r($msg . PHP_EOL, true));
    }
}
