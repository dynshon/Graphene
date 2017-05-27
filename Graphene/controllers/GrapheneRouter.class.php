<?php

namespace Graphene\controllers;

use Graphene\controllers\http\GraphRequest;
use Graphene\controllers\http\GraphResponse;
use Graphene\models\Module;
use Graphene\Graphene;
use Graphene\utils\Paths;
use Graphene\utils\Strings;

//use \Log;

/**
 * Router di Graphene:
 * Questa classe cattura l'url di una richiesta restful e lo compara con
 * quelle della propria tabella di routing.
 * Questa operazione si divide in 5 fasi:
 * - Indicizzazione dei Moduli
 * - Creazione e salvataggio della tabella di routing
 * - Matching dell' indirizzo richiesto
 * - Instanziazione del servizio richiesto
 * - Dispatching della richiesta al servizio
 *
 * @author Marco Magnetti <marcomagnetti@gmail.com>
 *
 */
class GrapheneRouter
{

    private $modules;
    private $baseUrl;
    private $modStack;
    private $request;
    private $modulesDir;
    private $injectionDir;
    private $nativePath;
    private $logger;

    public function __construct()
    {
        $logger = Graphene::getLogger();
        $settings = Graphene::getInstance()->getSettings();
        $this->baseUrl = Graphene::getDirectory();

        $this->modulesDir = Paths::path($settings->get('modulesPath', 'modules'));
        $logger->info("modules directory: " . $this->modulesDir);
        $this->nativePath = $this->baseUrl . '/native';
        $this->injectionDir = $this->baseUrl . '/injections';
        $this->routeTable = array();
        $this->modStack = array();
        $this->loadModules();
    }

    /**
     * Carica i moduli presenti nella cartella preimpostata
     *
     * @return void
     */
    private function loadModules()
    {
        $modules = [];
        try {
            $mods = scandir($this->modulesDir);
        } catch (\Exception $e) {
            $mods = [];
        }
        foreach ($mods as $key => $moduleDir) {
            if (is_dir($this->modulesDir . "/" . $moduleDir) && !Strings::startsWith($moduleDir, '.')) {
                $module = new Module($this->modulesDir . "/" . $moduleDir);
                if ($module != null) {
                    $modules[$module->getName()] = $module;
                }
            }
        }
        $sysMods = scandir($this->nativePath);
        foreach ($sysMods as $key => $moduleDir) {
            if (is_dir($this->nativePath . '/' . $moduleDir) && !Strings::startsWith($moduleDir, '.')) {
                $module = new Module($this->nativePath . "/" . $moduleDir);
                if ($module != null) {
                    $modules[$module->getName()] = $module;
                }
            }
        }
        $this->modules = $this->checkModulesDipendences($modules);
    }

    /**
     * @param $modules
     * @return array
     */
    private function checkModulesDipendences($modules)
    {
        $available = array();
        $ret = array();
        foreach ($modules as $name => $module) {
            $available[$name] = $module->getDipendences();
        }
        do {
            $completed = true;
            foreach ($available as $name => $dips) {
                foreach ($dips as $dip) {
                    if (!array_key_exists($dip, $available)) {
                        $completed = false;
                        //Log::err('unable to load '.$name. ' module because dipendency '.$dip.' is not installed');
                    }
                }
                if (!$completed) {
                    unset ($available[$name]);
                    break;
                }
            }
        } while (!$completed);

        foreach ($available as $name => $dp) {
            $ret[$name] = $modules[$name];
        }
        return $ret;
    }

    /**
     * Dirotta la richiesta al servizio che corrisponde al matching, ritornando
     * una risposta
     *
     * @param GraphRequest $request
     * @return GraphResponse
     */
    public function dispatch(GraphRequest $request)
    {
        $request->setContextPar('dispatchingId', uniqid());
        Graphene::getInstance()->startStat('DispatchingTime', $request->getMethod() . ' ' . $request->getUrl() . ' ' . $request->getContextPar('dispatchingId'));
        $response = null;
        $url = Paths::urlTrimAndClean($request->getUrl());
        foreach ($this->modules as $dir => $module) {
            $domain = (string)$module->getDomain();
            if (Strings::startsWith($url, strtolower($domain))) {
                $this->pushModule($module);
                $response = $module->exec($request);
                $this->popModule();
                if ($response != null) {
                    break;
                }
            }
        }
        if ($response === null) {

        }

        return $this->getSafeResponse($response);
    }

    private function pushModule($module)
    {
        array_push($this->modStack, $module);
    }

    private function popModule()
    {
        $pop = array_pop($this->modStack);
    }

    /**
     * Crea una risposta sicura (Modulo non trovato se non e pervenuta una
     * risposta)
     *
     * @param  GraphResponse | null $response
     * @return GraphResponse
     */
    private function getSafeResponse($response)
    {
        $filterManager = Graphene::getInstance()->getFilterManager();

        if ($response === null) {
            $response = new GraphResponse();
            $response->setHeader('content-type', 'application/json');
            if ($filterManager->haveErrors()) {
                $response->setBody($filterManager->serializeErrors());
                $ff = $filterManager->getFailedFilter();
                $response->setBody(json_encode(array(
                    "error" => array(
                        "message" => '[' . $ff['name'] . '] ' . $ff['message'],
                        "code" => $ff['status']
                    )
                ), JSON_PRETTY_PRINT));
                $response->setStatusCode($ff['status']);
            } else {
                $response->setBody(json_encode(array(
                    "error" => array(
                        "message" => "action not found",
                        "code" => "400"
                    )
                ), JSON_PRETTY_PRINT));
                $response->setStatusCode(400);
            }
        }
        return $response;
    }

    public function getInjectionDir()
    {
        return $this->injectionDir;
    }

    public function getModuleByActionName($actionName)
    {
        $modules = $this->getInstalledModules();
        foreach ($modules as $mod) {
        }
    }

    public function getInstalledModules()
    {
        $ret = [];
        foreach ($this->modules as $md => $mod) {
            $ret[] = $mod;
        }

        return $ret;
    }

    public function getModuleByNamespace($namespace)
    {
        $modules = $this->getInstalledModules();
        foreach ($modules as $mod) {
            if (strcasecmp($mod->getNamespace(), $namespace) == 0)
                return $mod;
        }
        return null;
    }

    public function getCurrentModule()
    {
        $current = end($this->modStack);
        return $current;
    }

    public function getStackModuleNames()
    {
        $ret = '';
        foreach ($this->modStack as $mod)
            $ret = $ret . '/' . $mod->getNamespace();
        return $ret;
    }

    public function getModuleStackLevel()
    {
        return count($this->modStack);
    }

    public function getModuleStack()
    {
        return $this->modStack;
    }
}