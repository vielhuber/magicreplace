<?php
namespace vielhuber\magicreplace;
class magicreplace
{
	public static function getOs()
	{
		if( stristr(PHP_OS, 'DAR') ) { return 'mac'; }
		if( stristr(PHP_OS, 'WIN') || stristr(PHP_OS, 'CYGWIN') ) { return 'windows'; }
		if( stristr(PHP_OS, 'LINUX') ) { return 'linux'; }
		return 'unknown';
	}

    public static function run($input, $output, $search_replace)
    {
		// split source file in several files
		if( self::getOs() === 'mac' ) { $command = 'gsplit'; }
		elseif ( self::getOs() === 'windows' || self::getOs() === 'linux' ) { $command = 'split'; }
		else { die('unknown operating system'); }
        exec($command . ' -C 1m "'.$input.'" "'.$input.'-SPLITTED"');
        foreach( glob($input.'-SPLITTED*') as $filename )
        {
            magicreplace::runPart($filename, $filename, $search_replace);
        }
        // join files
        exec('cat "'.$input.'-SPLITTED"* > "'.$output.'"');
        exec('rm "'.$input.'-SPLITTED"*');
    }

    public static function runPart($input, $output, $search_replace)
	{
		if( !file_exists($input) ) { die('error'); } $data = file_get_contents($input);
		foreach($search_replace as $search_replace__key=>$search_replace__value)
		{
		    // first find all critical serialized occurences in a very fast and efficient way
            // match shortest string that begins with string to replace and ends with ";
            // we also catch completely wrong matches (like *STRING', 'a:1:{s:4:\"OTHER\";*, but we sort this out later)
            $regex_start = preg_quote($search_replace__key, '/');
            $regex_end = '\"\;';
            $regex = '/'.$regex_start.'((?!'.$regex_start.').)*'.$regex_end.'/';
		    preg_match_all($regex, $data, $positions, PREG_OFFSET_CAPTURE);
		    $position_offset = 0;
		    if(!empty($positions) && !empty($positions[0])) {
		    foreach($positions[0] as $positions__value) {
		        $pointer = $positions__value[1]+strpos($positions__value[0],$search_replace__key)+$position_offset;
		        while($pointer >= 1 && !($data{$pointer} == '\'' && $data{$pointer-1} != '\\' && $data{$pointer-1} != '\'' && $data{$pointer+1} != '\'')) { $pointer--; }
		        $pos_begin = $pointer+1;
		        $pointer = $positions__value[1]+strpos($positions__value[0],$search_replace__key)+$position_offset;
		        while($pointer < strlen($data) && !($data{$pointer} == '\'' && $data{$pointer-1} != '\\' && $data{$pointer-1} != '\'' && ($pointer+1 === strlen($data) || $data{$pointer+1} != '\''))) { $pointer++; }
		        $pos_end = $pointer;
		        $string_before = substr($data, $pos_begin, $pos_end-$pos_begin);
		        $string_after = self::string($string_before, $search_replace);
		        $string_final = $string_before;

		        // strategy: unserialize the string (with some tricks, replace it, serialize it again)
		        // we cannot simply take this new string, because we needed to changed some data (new lines, double quotes, very long integers, ...)
		        // we simply replace all full digits, that have been changed and do a simple search and replace afterwards
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

				// something went wrong: replace search term temporarily (is changed later on again)
				if( !isset($numbers_before[0]) || !isset($numbers_after[0]) || count($numbers_before[0]) != count($numbers_after[0]) )
				{
					$string_final = str_replace($search_replace__key, md5($search_replace__key.(strlen($search_replace__key)*42)), $string_final);
				}
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
					//$string_final = str_replace($search_replace__key,$search_replace__value,$string_final);
				}
				$data = substr($data, 0, $pos_begin).$string_final.substr($data, $pos_end);
				$position_offset += strlen($string_final) - strlen($string_before);
		    }
			}
		    // then replace all other occurences
		    $data = str_replace($search_replace__key,$search_replace__value,$data);
		    // revert changes from above
		    $data = str_replace(md5($search_replace__key.(strlen($search_replace__key)*42)),$search_replace__key,$data);
		}
		file_put_contents($output, $data);
	}

	public static function mask($data)
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

	public static function string($data, $search_replace, $serialized = false, $level = 0)
	{
		// special case: if data is boolean false (unserialize would return false)
		if( $data === 'b:0;' ) { $data = self::string(unserialize($data), $search_replace, true, $level+1); }
		// special case: class cannot be unserialized (sometimes yoast serializes data at runtime when a class is available), return empty string
		elseif( is_string($data) && strpos($data,'C:') === 0 ) { return ''; }
		// if this is normal serialized data
		elseif( @unserialize($data) !== false ) { $data = self::string(unserialize($data), $search_replace, true, $level+1); }
		// special case: if data contains new lines and is recognized after replacing them AND/OR if data contains double quotes and is recognized after replacing them
		elseif( is_string($data) && @unserialize(self::mask($data)) !== false ) { $data = self::string(unserialize(self::mask($data)), $search_replace, true, $level+1); }
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
	magicreplace::run($input, $output, $search_replace);
	die('done...');
}