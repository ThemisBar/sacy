<?php

abstract class ExternalProcessor{
    abstract protected function getCommandLine();

    function transform($in){
        $s = array(
            0 => array('pipe', 'r'),
            1 => array('pipe', 'w'),
            2 => array('pipe', 'w')
        );
        $cmd = $this->getCommandLine();
        $p = proc_open($cmd, $s, $pipes);
        if (!is_resource($p))
            throw new Exception("Failed to execute $cmd");

        fwrite($pipes[0], $in);
        fclose($pipes[0]);

        $out = stream_get_contents($pipes[1]);
        $err = stream_get_contents($pipes[2]);

        fclose($pipes[1]);
        fclose($pipes[2]);

        $r = proc_close($p);

        if ($r != 0){
            throw new Exception("Command returned $r: $err");
        }
        return $out;
    }
}

class ExternalProcessorRegistry{
    private static $transformers;
    private static $compressors;

    public static function registerTransformer($type, $cls){
        self::$transformers[$type] = $cls;
    }

    public static function registerCompressor($type, $cls){
        self::$compressors[$type] = $cls;
    }

    private static function lookup($type, $in){
        return (isset($in[$type])) ? new $in[$type]() : null;
    }

    public static function typeIsSupported($type){
        return isset(self::$transformers[$type]) ||
            isset(self::$compressors[$type]);
    }

    /**
     * @static
     * @param $type mime type of input
     * @return ExternalProcessor
     */
    public static function getTransformerForType($type){
        return self::lookup($type, self::$transformers);
    }

    /**
     * @static
     * @param $type mime type of input
     * @return ExternalProcessor
     */
    public static function getCompressorForType($type){
        return self::lookup($type, self::$compressors);
    }

}

class ProcessorUglify extends ExternalProcessor{
    protected function getCommandLine(){
        if (!is_executable(SACY_COMPRESSOR_UGLIFY)){
            throw new Exception('SACY_COMPRESSOR_UGLIFY defined but not executable');
        }
        return SACY_COMPRESSOR_UGLIFY;
    }
}

class ProcessorCoffee extends ExternalProcessor{
    protected function getCommandLine(){
        if (!is_executable(SACY_TRANSFORMER_COFFEE)){
            throw new Exception('SACY_TRANSFORMER_COFFEE defined but not executable');
        }
        return sprintf('%s -c -s', SACY_TRANSFORMER_COFFEE);
    }
}


if (defined('SACY_COMPRESSOR_UGLIFY')){
    ExternalProcessorRegistry::registerCompressor('text/javascript', 'ProcessorUglify');
}

if (defined('SACY_TRANSFORMER_COFFEE')){
    ExternalProcessorRegistry::registerTransformer('text/coffeescript', 'ProcessorCoffee');
}
