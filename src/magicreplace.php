<?php
declare(strict_types=1);

namespace vielhuber\magicreplace;
final class magicreplace
{
    public static function run(string $input, string $output, array $search_replace, bool $progress = false): void
    {
        clearstatcache();
        if(!is_file($input) || !is_readable($input)) { self::fail('missing input'); }
        $input_size = filesize($input);
        if($input_size === false) { self::fail(); }
        if($input_size === 0) {
            if(file_put_contents($output, '') !== 0) { self::fail(); }
            return;
        }
        // split source file in several files
        // so that we don't get a memory limit error in php
        // on mac we don't use "gsplit" here, since it has a weird bug
        // where corrupted characters get introduced after splitting
        // instead we use split but use another argument (-C is not available)
        $command = 'split';       
        if( self::getOs() === 'mac' ) { $command .= ' -l 10000 -a 4'; }
        elseif ( self::getOs() === 'windows' || self::getOs() === 'linux' ) { $command .= ' -C 1m -a 4'; }
        else { self::fail('unknown operating system'); }
        $split_prefix = $input . '-SPLITTED';
        $filenames = glob($split_prefix . '*') ?: [];
        foreach($filenames as $filenames__value) {
            if(!is_file($filenames__value) || !unlink($filenames__value)) { self::fail(); }
        }
        $command_output = [];
        $command_exit_code = 0;
        exec(
            $command . ' ' . escapeshellarg($input) . ' ' . escapeshellarg($split_prefix) . ' 2>&1',
            $command_output,
            $command_exit_code
        );
        if($command_exit_code !== 0) { self::fail(); }
        $filenames = glob($split_prefix . '*') ?: [];
        if(empty($filenames)) { self::fail(); }
        sort($filenames);
        $json_search_replace = [];
        foreach($search_replace as $search_replace__key => $search_replace__value) {
            $json_search_replace[$search_replace__key] = [
                'search' => self::jsonEncodedString($search_replace__key),
                'replace' => self::jsonEncodedString($search_replace__value)
            ];
        }
        foreach( $filenames as $filenames__key=>$filenames__value )
        {
            self::runPart($filenames__value, $filenames__value, $search_replace, $json_search_replace);
            if( $progress === true ) {
                echo self::progressBar($filenames__key + 1,count($filenames));
            }
        }
        // join files
        $output_handle = fopen($output, 'wb');
        if($output_handle === false) { self::fail(); }
        foreach( $filenames as $filenames__value ) {
            $input_size = filesize($filenames__value);
            if($input_size === false) {
                fclose($output_handle);
                self::fail();
            }
            $input_handle = fopen($filenames__value, 'rb');
            if($input_handle === false) {
                fclose($output_handle);
                self::fail();
            }
            $copied_bytes = stream_copy_to_stream($input_handle, $output_handle);
            fclose($input_handle);
            if($copied_bytes !== $input_size || !unlink($filenames__value)) {
                fclose($output_handle);
                self::fail();
            }
        }
        if(!fclose($output_handle)) { self::fail(); }
    }

    private static function runPart(
        string $input,
        string $output,
        array $search_replace,
        array $json_search_replace
    ): void
    {
        if( !file_exists($input) ) { self::fail(); }
        $data = file_get_contents($input);
        if($data === false) { self::fail(); }

        preg_match_all('/\'([A-Za-z0-9+\/]{16,}={0,2})\'/', $data, $positions, PREG_OFFSET_CAPTURE);
        $position_offset = 0;
        if(!empty($positions) && !empty($positions[1])) {
            foreach($positions[1] as $positions__value) {
                $base64_encoded = $positions__value[0];
                if(strlen($base64_encoded) % 4 !== 0) { continue; }
                $base64_decoded = base64_decode($base64_encoded, true);
                if($base64_decoded === false) { continue; }
                if(self::is_serialized($base64_decoded)) { $base64_replaced = self::string($base64_decoded, $search_replace); }
                else {
                    if(!self::isText($base64_decoded)) { continue; }
                    $base64_replaced = $base64_decoded;
                    foreach($search_replace as $search_replace__key => $search_replace__value) {
                        $base64_replaced = str_replace($search_replace__key, $search_replace__value, $base64_replaced);
                        $search_replace__key_json = $json_search_replace[$search_replace__key]['search'];
                        $search_replace__value_json = $json_search_replace[$search_replace__key]['replace'];
                        if($search_replace__key_json !== null && $search_replace__value_json !== null) {
                            $base64_replaced = str_replace($search_replace__key_json, $search_replace__value_json, $base64_replaced);
                        }
                    }
                }
                if(!is_string($base64_replaced) || $base64_replaced === $base64_decoded) { continue; }
                $base64_replaced = base64_encode($base64_replaced);
                $pos_begin = $positions__value[1] + $position_offset;
                $pos_end = $pos_begin + strlen($positions__value[0]);
                $data = substr($data, 0, $pos_begin).$base64_replaced.substr($data, $pos_end);
                $position_offset += strlen($base64_replaced) - strlen($positions__value[0]);
            }
        }

        foreach($search_replace as $search_replace__key=>$search_replace__value)
        {
            $search_replace__exists = strpos($data, $search_replace__key) !== false;
            // first find all occurences of the string to replace
            // this matches serialized and perhaps non serialized occurences
            // i've spend hours of finding an efficient regex (tried s:\d.*, negative lookaheads, ...); they are either too slow or reach nesting limits
            // the following regex finds all strings to replace that are followed by "; - important is the non greedy operator "?"
            // the initial pointer is also set to to " (and it goes outside to the left and right)
            // we cannot start from the beginning, because "'https://tld.com', 'a:1:{s:4:\"home\";s:15:\"https://tld.com\";}'" starts before the serialized string
            $positions = [];
            if($search_replace__exists) {
                preg_match_all('/'.preg_quote($search_replace__key, '/').'.*?(\"\;)/', $data, $positions, PREG_OFFSET_CAPTURE);
            }
            $position_offset = 0;
            $processed_position_end = -1;
            if(!empty($positions) && !empty($positions[1])) {
            foreach($positions[1] as $positions__value) {
                if($positions__value[1] < $processed_position_end) { continue; }
                // determine begin and end of (potentially serialized) string
                $data_length = strlen($data);

                $pointer = $positions__value[1]-1+$position_offset;
                // slow version
                //while($pointer >= 1 && !($data[$pointer] == '\'' && $data[$pointer-1] != '\\' && $data[$pointer-1] != '\'' && $data[$pointer+1] != '\'')) { $pointer--; }
                // fast version
                $data_inverted = strrev($data);
                $pointer_inverted = $data_length - $pointer;
                if( preg_match('/([^\\\']|^)\'([^\\\\\']|$)/', $data_inverted, $matches, PREG_OFFSET_CAPTURE, $pointer_inverted) ) {
                    $pointer = $data_length - $matches[0][1] - 2;
                }
                else {
                    $pointer = 0;
                }
                $pos_begin = $pointer+1;

                $pointer = $positions__value[1]-1+$position_offset;
                // slow version
                //while($pointer < $data_length && !($data[$pointer] == '\'' && $data[$pointer-1] != '\\' && $data[$pointer-1] != '\'' && ($pointer+1 === $data_length || $data[$pointer+1] != '\''))) { $pointer++; }
                // fast version
                if( preg_match('/([^\\\\\']|^)\'([^\\\']|$)/', $data, $matches, PREG_OFFSET_CAPTURE, $pointer) ) {
                    $pointer = $matches[0][1] + 1;
                }
                else {
                    $pointer = $data_length;
                }
                $pos_end = $pointer;
                $processed_position_end = $pos_end - $position_offset;

                // string
                $string_before = substr($data, $pos_begin, $pos_end-$pos_begin);
                // string after replacement
                $string_after = self::string($string_before, $search_replace);
                // prepare final string
                $string_final = $string_before;

                // we cannot simply take the replaced serialized string, because we needed to change some data (new lines, double quotes, very long integers, ...)
                // to overcome this, we simply replace all full digits, that have been changed and do a simple search and replace afterwards
                $numbers_offset = 0;
                preg_match_all('/s:\d+:/', $string_before, $numbers_before, PREG_OFFSET_CAPTURE);
                preg_match_all('/s:\d+:/', $string_after, $numbers_after, PREG_OFFSET_CAPTURE);

                // there is another special case: sometimes there are references inside arrays (that cannot be preserved)
                // therefore we first resolve those references (by calling the whole procedure with an empty replace
                if( strpos($string_before,'R:') !== false && (!isset($numbers_before[0]) || !isset($numbers_after[0]) || count($numbers_before[0]) != count($numbers_after[0])) )
                {
                    $string_before = self::string($string_before, ['NONE'=>'NONE']);
                    preg_match_all('/s:\d+:/', $string_before, $numbers_before, PREG_OFFSET_CAPTURE);
                    $string_final = $string_before;
                }

                // detect if something went wrong (the occurence of digits is different before and after replacement)
                // we undo the replacement (with the help of a temporary string)
                if( !isset($numbers_before[0]) || !isset($numbers_after[0]) || count($numbers_before[0]) != count($numbers_after[0]) )
                {
                    $string_final = str_replace($search_replace__key, md5($search_replace__key.(strlen($search_replace__key)*42)), $string_final);
                }

                // if everything went well, replace the digits (not the string itself, because we do this completely afterwards)
                else
                {
                    foreach($numbers_before[0] as $numbers_before__key=>$numbers_before__value)
                    {
                        if( $numbers_before__value[0] == $numbers_after[0][$numbers_before__key][0] ) { continue; }
                        $numbers_begin = $numbers_before__value[1]+$numbers_offset;
                        $numbers_end = $numbers_before__value[1]+$numbers_offset+strlen($numbers_before__value[0]);
                        $string_final = substr($string_final, 0, $numbers_begin).$numbers_after[0][$numbers_before__key][0].substr($string_final, $numbers_end);
                        $numbers_offset += strlen($numbers_after[0][$numbers_before__key][0])-strlen($numbers_before__value[0]);
                    }
                }
                $data = substr($data, 0, $pos_begin).$string_final.substr($data, $pos_end);
                $position_offset += strlen($string_final) - strlen($string_before);
            }
            }
            // finally replace all occurences (inside and outside of serialized strings)
            if($search_replace__exists) {
                $data = str_replace($search_replace__key,$search_replace__value,$data);
            }
            // also replace json-escaped-slash variants (e.g. revslider stores "https:\/\/...", which mysqldump doubles to "https:\\/\\/...")
            if( strpos($search_replace__key, '/') !== false ) {
                foreach( ['\/', '\\\\/'] as $escaped_slash ) {
                    $data = str_replace(
                        str_replace('/', $escaped_slash, $search_replace__key),
                        str_replace('/', $escaped_slash, $search_replace__value),
                        $data
                    );
                }
            }
            // revert changes from above (if something went wrong)
            $data = str_replace(md5($search_replace__key.(strlen($search_replace__key)*42)),$search_replace__key,$data);
        }

        if(file_put_contents($output, $data) !== strlen($data)) { self::fail(); }
    }

    private static function mask(string $data): string
    {
        $data = str_replace('\\\\"',md5('NOREPLACE1'),$data);
        $data = str_replace('\\\\n',md5('NOREPLACE2'),$data);
        $data = str_replace('\\\\r',md5('NOREPLACE3'),$data);
        $data = str_replace('\n',"\n",$data);
        $data = str_replace('\r',"\r",$data);
        $data = str_replace('\\\\','\\',$data);
        $data = str_replace('\'\'','\'',$data);
        $data = str_replace('\\\'','\'',$data);
        $data = str_replace('\\"','"',$data);
        $data = str_replace(md5('NOREPLACE3'),'\\r',$data);
        $data = str_replace(md5('NOREPLACE2'),'\\n',$data);
        $data = str_replace(md5('NOREPLACE1'),'\\"',$data);
        return $data;
    }

    private static function string(mixed $data, array $search_replace, bool $serialized = false): mixed
    {
        // special case: if data is boolean false (unserialize would return false)
        if( $data === 'b:0;' ) { $data = self::string(self::unserialize($data), $search_replace, true); }
        // special case: class cannot be unserialized (sometimes yoast serializes data at runtime when a class is available), return empty string
        elseif( is_string($data) && strpos($data,'C:') === 0 ) { return ''; }
        // if this is normal serialized data
        elseif( self::is_serialized($data) ) { $unserialize = self::unserialize($data); $data = self::string($unserialize, $search_replace, true); }
        // special case: if data contains new lines and is recognized after replacing them AND/OR if data contains double quotes and is recognized after replacing them
        elseif( is_string($data) )
        {
            $masked_data = self::mask($data);
            if(self::is_serialized($masked_data)) {
                $unserialize = self::unserialize($masked_data);
                $data = self::string($unserialize, $search_replace, true);
            }
            else {
                foreach($search_replace as $search_replace__key => $search_replace__value ) { $data = str_replace($search_replace__key, $search_replace__value, $data); }
            }
        }
        elseif( is_array($data) )
        {
            $tmp = [];
            foreach ( $data as $data__key => $data__value ) { $tmp[ self::string( $data__key, $search_replace ) ] = self::string( $data__value, $search_replace ); }
            $data = $tmp; unset( $tmp );
        }
        elseif ( $data instanceof \__PHP_Incomplete_Class ) { return ''; }
        elseif ( $data instanceof \stdClass )
        {
            $tmp = new \stdClass(); $props = get_object_vars( $data );
            foreach ( $props as $data__key => $data__value ) { $tmp->{ self::string( $data__key, $search_replace ) } = self::string( $data__value, $search_replace ); }
            $data = $tmp; unset( $tmp );
        }
        if( $serialized === true ) { return serialize($data); }
        return $data;
    }

    private static function getOs(): string
    {
        if( stristr(PHP_OS, 'DAR') ) { return 'mac'; }
        if( stristr(PHP_OS, 'WIN') || stristr(PHP_OS, 'CYGWIN') ) { return 'windows'; }
        if( stristr(PHP_OS, 'LINUX') ) { return 'linux'; }
        return 'unknown';
    }

    private static function progressBar(int $done, int $total, string $info = "", int $width = 50): string {
        $perc = (int) round(($done * 100) / $total);
        $bar = (int) round(($width * $perc) / 100);
        return sprintf("%s%%[%s>%s]%s\r", $perc, str_repeat("=", $bar), str_repeat(" ", $width-$bar), $info);
    }

    private static function is_serialized(mixed $data): bool
    {
        if (!is_string($data) || $data == '') {
            return false;
        }
        set_error_handler(function (int $errno, string $errstr): bool {
            return true;
        });
        try {
            $unserialized = self::unserialize($data);
        }
        finally {
            restore_error_handler();
        }
        if ($data !== 'b:0;' && $unserialized === false) {
            return false;
        }
        return true;
    }

    private static function isText(string $data): bool
    {
        return preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $data) !== 1;
    }

    private static function jsonEncodedString(mixed $data): ?string
    {
        $encoded = json_encode((string) $data);
        if(!is_string($encoded) || strlen($encoded) < 2) {
            return null;
        }
        return substr($encoded, 1, -1);
    }

    private static function unserialize(string $data): mixed
    {
        return unserialize($data, ['allowed_classes' => ['stdClass']]);
    }

    private static function fail(string $message = 'error'): never
    {
        throw new \RuntimeException($message);
    }

}

// cli usage
if (php_sapi_name() == 'cli' && isset($argv) && !empty($argv) && isset($argv[1]))
{
    if (count($argv) < 5 || (count($argv) - 3) % 2 !== 0) {
        fwrite(STDERR, 'missing options' . PHP_EOL);
        exit(1);
    }
    $input = $argv[1];
    if (!file_exists($input)) { $input = getcwd() . '/' . $input; }
    if (!file_exists($input)) {
        fwrite(STDERR, 'missing input' . PHP_EOL);
        exit(1);
    }
    $output = $argv[2];
    if (!str_starts_with($output, '/') && preg_match('/^[A-Za-z]:[\\\\\/]/', $output) !== 1) {
        $output = getcwd() . '/' . $output;
    }
    $search_replace = [];
    foreach ($argv as $argv__key => $argv__value)
    {
        if ($argv__key <= 2) { continue; }
        if ($argv__key % 2 == 0) { continue; }
        $search_replace[$argv__value] = $argv[$argv__key + 1];
    }
    try {
        magicreplace::run($input, $output, $search_replace, true);
    }
    catch(\RuntimeException $exception) {
        fwrite(STDERR, $exception->getMessage() . PHP_EOL);
        exit(1);
    }
    echo 'done...';
}
