<?php
namespace vielhuber\magicreplace;
class magicreplace
{
    public static function run($input, $output, $search_replace, $progress = false)
    {
        clearstatcache();
        if( filesize( $input ) === 0 ) {
            file_put_contents($output, '');
            return;
        }
        // split source file in several files
        if( self::getOs() === 'mac' ) { $command = 'gsplit'; }
        elseif ( self::getOs() === 'windows' || self::getOs() === 'linux' ) { $command = 'split'; }
        else { die('unknown operating system'); }
        exec($command . ' -C 1m "'.$input.'" "'.$input.'-SPLITTED"');
        $filenames = glob($input.'-SPLITTED*');
        foreach( $filenames as $filenames__key=>$filenames__value )
        {
            magicreplace::runPart($filenames__value, $filenames__value, $search_replace);
            if( $progress === true ) {
                echo self::progressBar($filenames__key,count($filenames));
            }
        }
        // join files
        exec('cat "'.$input.'-SPLITTED"* > "'.$output.'"');
        exec('rm "'.$input.'-SPLITTED"*');
    }

    private static function runPart($input, $output, $search_replace)
    {
        if( !file_exists($input) ) { die('error'); }
        $data = file_get_contents($input);

        // (ugly) betheme support
        $betheme_opening_tag = '\'mfn-page-items\',\'';
        $betheme_support = strpos($data, $betheme_opening_tag ) !== false;
        if( $betheme_support ) {
            preg_match_all('/'.($betheme_opening_tag.'(.*?)\'\);(\r\n|\r|\n)').'/', $data, $positions, PREG_OFFSET_CAPTURE);
            $position_offset = 0;
            if(!empty($positions) && !empty($positions[1])) {
                foreach($positions[1] as $positions__value) {
                    $pos_begin = $positions__value[1] + $position_offset;
                    $pos_end = $pos_begin + strlen($positions__value[0]);
                    $base64_encoded = $positions__value[0];
                    $base64_decoded = base64_decode($base64_encoded);
                    $base64_decoded = self::mysql_escape_mimic($base64_decoded);
                    $data = substr($data, 0, $pos_begin).$base64_decoded.substr($data, $pos_end);
                    $position_offset += (strlen($base64_decoded) - strlen($positions__value[0]));
                }
            }
        }

        foreach($search_replace as $search_replace__key=>$search_replace__value)
        {
            // first find all occurences of the string to replace
            // this matches serialized and perhaps non serialized occurences
            // i've spend hours of finding an efficient regex (tried s:\d.*, negative lookaheads, ...); they are either too slow or reach nesting limits
            // the following regex finds all strings to replace that are followed by "; - important is the non greedy operator "?"
            // the initial pointer is also set to to " (and it goes outside to the left and right)
            // we cannot start from the beginning, because "'https://tld.com', 'a:1:{s:4:\"home\";s:15:\"https://tld.com\";}'" starts before the serialized string
            preg_match_all('/'.preg_quote($search_replace__key, '/').'.*?(\"\;)/', $data, $positions, PREG_OFFSET_CAPTURE);
            $position_offset = 0;
            if(!empty($positions) && !empty($positions[1])) {
            foreach($positions[1] as $positions__value) {
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
            $data = str_replace($search_replace__key,$search_replace__value,$data);
            // revert changes from above (if something went wrong)
            $data = str_replace(md5($search_replace__key.(strlen($search_replace__key)*42)),$search_replace__key,$data);
        }

        // betheme revert
        if( $betheme_support ) {
            preg_match_all('/'.($betheme_opening_tag.'(.*?)\'\);(\r\n|\r|\n)').'/', $data, $positions, PREG_OFFSET_CAPTURE);
            $position_offset = 0;
            if(!empty($positions) && !empty($positions[1])) {
                foreach($positions[1] as $positions__value) {
                    $pos_begin = $positions__value[1] + $position_offset;
                    $pos_end = $pos_begin + strlen($positions__value[0]);
                    $base64_decoded = $positions__value[0];
                    $base64_decoded = self::mysql_escape_mimic($base64_decoded, true);
                    $base64_encoded = base64_encode($base64_decoded);
                    $data = substr($data, 0, $pos_begin).$base64_encoded.substr($data, $pos_end);
                    $position_offset += (strlen($base64_encoded) - strlen($positions__value[0]));
                }
            }
        }

        file_put_contents($output, $data);
    }

    private static function mask($data)
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

    private static function string($data, $search_replace, $serialized = false, $level = 0)
    {
        // special case: if data is boolean false (unserialize would return false)
        if( $data === 'b:0;' ) { $data = self::string(unserialize($data), $search_replace, true, $level+1); }
        // special case: class cannot be unserialized (sometimes yoast serializes data at runtime when a class is available), return empty string
        elseif( is_string($data) && strpos($data,'C:') === 0 ) { return ''; }
        // if this is normal serialized data
        elseif( self::is_serialized($data) ) { $unserialize = unserialize($data); $data = self::string($unserialize, $search_replace, true, $level+1); }
        // special case: if data contains new lines and is recognized after replacing them AND/OR if data contains double quotes and is recognized after replacing them
        elseif( is_string($data) && self::is_serialized(self::mask($data)) ) { $unserialize = unserialize(self::mask($data)); $data = self::string($unserialize, $search_replace, true, $level+1); }
        elseif( is_array($data) )
        {
            $tmp = [];
            foreach ( $data as $data__key => $data__value ) { $tmp[ self::string( $data__key, $search_replace, false, $level+1 ) ] = self::string( $data__value, $search_replace, false, $level+1 ); }
            $data = $tmp; unset( $tmp );
        }
        elseif ( is_object( $data ) )
        {
            $tmp = $data; $props = get_object_vars( $data );
            foreach ( $props as $data__key => $data__value ) { $tmp->{ self::string( $data__key, $search_replace, false, $level+1 ) } = self::string( $data__value, $search_replace, false, $level+1 ); }
            $data = $tmp; unset( $tmp );
        }
        elseif( is_string($data) )
        {
            foreach($search_replace as $search_replace__key => $search_replace__value ) { $data = str_replace($search_replace__key, $search_replace__value, $data); }
        }
        if( $serialized === true ) { return serialize($data); }
        return $data;
    }

    private static function getOs()
    {
        if( stristr(PHP_OS, 'DAR') ) { return 'mac'; }
        if( stristr(PHP_OS, 'WIN') || stristr(PHP_OS, 'CYGWIN') ) { return 'windows'; }
        if( stristr(PHP_OS, 'LINUX') ) { return 'linux'; }
        return 'unknown';
    }

    private static function progressBar($done, $total, $info="", $width=50) {
        $perc = round(($done * 100) / $total);
        $bar = round(($width * $perc) / 100);
        return sprintf("%s%%[%s>%s]%s\r", $perc, str_repeat("=", $bar), str_repeat(" ", $width-$bar), $info);
    }

    private static function strrev($str)
    {
        if (!is_string($str) || $str == '') {
            return $str;
        }
        $r = '';
        for ($i = mb_strlen($str); $i >= 0; $i--) {
            $r .= mb_substr($str, $i, 1);
        }
        return $r;
    }

    private static function is_serialized($data)
    {
        if (!is_string($data) || $data == '') {
            return false;
        }
        set_error_handler(function ($errno, $errstr) {});
        $unserialized = unserialize($data);
        restore_error_handler();
        if ($data !== 'b:0;' && $unserialized === false) {
            return false;
        }
        return true;
    }

    private static function mysql_escape_mimic($inp, $reverse = false) {
        $_1 = ['\\', "\0", "\n", "\r", "'", '"', "\x1a"];
        $_2 = ['\\\\', '\\0', '\\n', '\\r', "\\'", '\\"', '\\Z'];
        if(!empty($inp) && is_string($inp)) {
            return $reverse === false ? str_replace($_1, $_2, $inp) : str_replace($_2, $_1, $inp);
        }
        return $inp;
    }
}

// cli usage
if (php_sapi_name() == 'cli' && isset($argv) && !empty($argv) && isset($argv[1]))
{
    if (!isset($argv) || empty($argv) || !isset($argv[1]) || !isset($argv[2]) || !isset($argv[3]) || !isset($argv[4])) { die('missing options'); }
    $root = getcwd() . '/';
    if (!file_exists($root . $argv[1])) { $root = ''; }
    if (!file_exists($root . $argv[1])) { die('missing input'); }
    $input = $root . $argv[1];
    if (!file_exists($root . $argv[2])) { touch($root . $argv[2]); }
    $output = $root . $argv[2];
    $search_replace = [];
    foreach ($argv as $argv__key => $argv__value)
    {
        if ($argv__key <= 2) { continue; }
        if ($argv__key % 2 == 1 && !isset($argv[ $argv__key + 1 ])) { continue; }
        if ($argv__key % 2 == 0) { continue; }
        $search_replace[ $argv[ $argv__key ] ] = $argv[ $argv__key + 1 ];
    }
    magicreplace::run($input, $output, $search_replace, true);
    die('done...');
}