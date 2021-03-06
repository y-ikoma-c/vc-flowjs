<?php

require_once __DIR__.'/../vendor/autoload.php';
require_once __DIR__.'/Constants.php';

use Webmozart\PathUtil\Path;
use Icicle\Awaitable\Promise;
use Icicle\Loop;
use Macromill\CORe\VC\SSEUtil;

// --------------------
// Logs
// --------------------
$logger = \Macromill\CORe\VC\LoggerFactory::create('sse');

// --------------------
// Params
// --------------------
$request = new \Flow\Request();
$params = [
    'TMP_DIR' => TMP_DIR,
    'fileIdentifier' => \Flow\Config::hashNameCallback($request),
    'destination' => Path::join(DST_DIR, $request->getFileName()),
];
// --------------------
// メイン
// --------------------
header("Content-Type: text/event-stream");

// --------------------
// メイン
// --------------------
Loop\periodic(1, function () use($logger) {
    // ping
    SSEUtil::flush(['time' => microtime(true)], 'ping');
});
$promise = new Promise(function (callable $resolve, callable $reject) use($params, $logger) {
    // 結合処理
    $fileId = $params['fileIdentifier'];
    $logger->info("start:{$fileId}");
    $wholePath = Path::join($params['TMP_DIR'], "{$fileId}_whole");
    if (!file_exists($wholePath)) {
        $reject(new Exception("$fileId is invalid."));
    }
    $serialized = file_get_contents($wholePath);
    $flowFile = unserialize($serialized);
    if ($flowFile->validateFile() && $flowFile->save($params['destination'])) {
        $resolve($fileId);
    } else {
        $reject(new Exception("$fileId is invalid."));
    }
});
$promise->done(
    function ($data) use($logger){
        // resolve
        $logger->info(" done:{$data}");
        SSEUtil::flush(['data' => $data], 'done');
    },
    function (\Exception $ex) use($logger){
        // reject
        $logger->error(" fail:{$ex->getMessgae()}:{$ex->getTraceAsString()}");
        SSEUtil::flush(['data' => $ex->getMessage()], 'fail');
    }
);
Loop\run();
