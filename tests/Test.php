<?php
use vielhuber\magicreplace\magicreplace;

class Test extends \PHPUnit\Framework\TestCase
{
    protected $var;

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
            if (
                !file_exists($folders__value . '/input.sql') ||
                !file_exists($folders__value . '/output.sql') ||
                !file_exists($folders__value . '/replace.json')
            ) {
                continue;
            }

            $replace = file_get_contents($folders__value . '/replace.json');
            $replace = json_decode($replace);
            $replace = (array) $replace;

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
}
