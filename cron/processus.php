<?php
ini_set('memory_limit', '2G');

use StimData\Core\Entities\Processus;
use StimData\Core\Services\ProcessusService;
use StimData\Core\StimDataApp;
use StimData\Core\StimDataRepository;

require_once(__DIR__ . '/../configs/loader_nextgen.php');

$container = StimDataApp::i()->getContainer();
$container->set('session', null);

$processusService = StimDataApp::getFromContainer(ProcessusService::class);

$processusService->init();

$processusService->run();
