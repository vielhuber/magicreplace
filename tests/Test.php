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

        foreach ($folders as $folders__value) {
            if (!file_exists($folders__value . '/settings.json')) {
                continue;
            }

            $this->settings = json_decode(file_get_contents($folders__value . '/settings.json'));
            $replace = (array) $this->settings->replace;

            if (property_exists($this->settings, 'target')) {
                // replace backwards (because we copied all settings from syncdb production-local profiles)
                $replace_backwards = [];
                foreach ($replace as $replace__key => $replace__value) {
                    $replace_backwards[$replace__value] = $replace__key;
                }
                $replace = $replace_backwards;
                $this->dump($folders__value . '/input.sql');
                foreach ($replace as $replace__key => $replace__value) {
                    $this->replaceWithInterconnect($replace__key, $replace__value);
                }
                $this->dump($folders__value . '/output.sql');
                // undo changes
                $this->restore($folders__value . '/input.sql');
            }

            magicreplace::run($folders__value . '/input.sql', $folders__value . '/output-test.sql', $replace);

            if (
                file_get_contents($folders__value . '/output.sql') !==
                file_get_contents($folders__value . '/output-test.sql')
            ) {
                $this->assertTrue(false);
            } else {
                $this->assertTrue(true);
                @unlink($folders__value . '/output-test.sql');
            }
        }
    }

    private function dump($filename)
    {
        exec(
            'mysqldump --extended-insert=false --skip-comments -h ' .
                $this->settings->target->host .
                ' --port ' .
                $this->settings->target->port .
                ' -u ' .
                $this->settings->target->username .
                ' -p"' .
                $this->settings->target->password .
                '" ' .
                $this->settings->target->database .
                ' > ' .
                $filename
        );
    }

    private function restore($filename)
    {
        exec(
            'mysql -h ' .
                $this->settings->target->host .
                ' --port ' .
                $this->settings->target->port .
                ' -u ' .
                $this->settings->target->username .
                ' -p"' .
                $this->settings->target->password .
                '" --default-character-set=utf8 ' .
                $this->settings->target->database .
                ' < ' .
                $filename
        );
    }

    private function replaceWithInterconnect($search, $replace)
    {
        echo shell_exec(
            'php ' .
                __DIR__ .
                '/tools/interconnect-search-replace/4.1.1/srdb.cli.php --host ' .
                $this->settings->target->host .
                ' --name ' .
                $this->settings->target->database .
                ' --user ' .
                $this->settings->target->username .
                ' --pass ' .
                $this->settings->target->password .
                ' --port ' .
                $this->settings->target->port .
                ' --search "' .
                $search .
                '" --replace "' .
                $replace .
                '"'
        );
    }
}
