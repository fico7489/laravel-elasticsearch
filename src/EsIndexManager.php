<?php

namespace Fico7489\Es;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use Illuminate\Console\OutputStyle;
use Illuminate\Database\Eloquent\SoftDeletes;

class EsIndexManager
{
    /** @var Client */
    private $client;

    /** @var OutputStyle */
    private $output;

    public function __construct()
    {
        $this->client = new Client([
            'base_uri' => 'http://'.config('database.elasticsearch.host').':'.config('database.elasticsearch.port').'/',
            'timeout' => config('database.elasticsearch.timeout'),
        ]);
    }

    public function recreateIndexAll(array $classNames = [])
    {
        if (0 == count($classNames)) {
            $classNames = self::getClassNamesForIndexes();
        }

        foreach ($classNames as $className) {
            $this->recreateIndex($className);
        }

        return true;
    }

    public function importDataAll(array $classNames = [])
    {
        if (0 == count($classNames)) {
            $classNames = self::getClassNamesForIndexes();
        }

        foreach ($classNames as $className) {
            $this->importData($className);
        }

        $this->log(PHP_EOL);

        return true;
    }

    public function recreateIndex(string $className)
    {
        $model = new $className();
        $indexName = $model->getEsIndexName();

        $exists = $this->checkIfIndexExists($model);

        if ($exists) {
            $this->log('Index "'.$indexName.'" for model "'.get_class($model).'" EXIST, recreating.');
            $this->deleteIndex($className);
        } else {
            $this->log('Index "'.$indexName.'" for model "'.get_class($model).'" NOT EXIST, creating.');
        }

        $this->createIndex($className);

        return true;
    }

    public function importData(string $className)
    {
        $this->log(PHP_EOL.'Import for class='.$className);

        $model = new $className();
        $indexName = $model->getEsIndexName();

        $count = \DB::table($model->getTable())->count();
        $bar = $this->output->createProgressBar($count);

        \DB::table($model->getTable())->orderBy($model->getKeyName(), 'asc')->chunk(100, function ($objects) use ($indexName, $model, $bar) {
            $bulkJsonString = "\r\n";

            foreach ($objects as $object) {
                $query = $model->query();
                if(method_exists($model, 'forceDelete')){
                    $query = $query->withTrashed();
                }
                $model = $query->find($object->id);
                $data = $model->getEsData();

                $bulkJsonString .= json_encode(['update' => ['_id' => $model->getKey(), '_index' => $indexName]])."\r\n";
                $bulkJsonString .= json_encode(['doc' => $data, 'doc_as_upsert' => true])."\r\n";

                $bar->advance();
            }

            $bulkJsonString .= " \r\n";

            $this->callEsApi('POST ', '_bulk?pretty', $bulkJsonString, true);
        });

        $bar->finish();
    }

    public function checkIfIndexExists(EsInterface $modelInstance)
    {
        $indexName = $modelInstance->getEsIndexName();

        try {
            $response = $this->client->request('GET', $indexName);
        } catch (ClientException $exception) {
            if (404 == $exception->getCode()) {
                return false;
            }
            $this->notifyException($exception);
        }

        return true;
    }

    public function createIndex(string $className)
    {
        $model = new $className();
        $indexName = $model->getEsIndexName();

        try {
            $response = $this->client->request('PUT', $indexName);
        } catch (ClientException $exception) {
            $message = str_replace(
                rtrim($exception->getMessage()),
                (string) $exception->getResponse()->getBody(),
                (string) $exception
            );

            throw new \Exception($message);
        }

        return true;
    }

    public function deleteIndex(string $className)
    {
        $model = new $className();
        $indexName = $model->getEsIndexName();

        $this->callEsApi('DELETE', $indexName);

        return true;
    }

    public function upsertModel(EsInterface $esModel)
    {
        //TMP
        return;
        $indexName = $esModel->getEsIndexName();
        $this->callEsApi('PUT', $indexName.'/_doc/'.$esModel->getEsId(), $esModel->getEsData());
    }

    public function deleteModel(EsInterface $esModel)
    {
        $indexName = $esModel->getEsIndexName();
        $this->callEsApi('DELETE', $indexName.'/_doc/'.$esModel->getEsId());
    }

    public static function getClassNamesForIndexes()
    {
        //TODO
        $path = app_path('../Modules/Product/Entities');
        $namespace = 'Modules\\Product\\Entities\\';

        $classNames = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(
                $path
            ),
            \RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($iterator as $item) {
            /** @var \SplFileInfo $item */
            if ($item->isReadable() && $item->isFile() && 'php' === mb_strtolower($item->getExtension())) {
                $name = $item->getRealPath();
                $pos = strrpos($name, '/');
                $name = substr($name, $pos + 1);
                $name = str_replace('.php', '', $name);
                $name = $namespace.$name;
                $classNames[] = $name;
            }
        }

        $classNamesFiltered = [];
        foreach ($classNames as $className) {
            if (false !== strpos($className, 'Traits\\')) {
                continue;
            }

            if ((new $className()) instanceof EsInterface) {
                $classNamesFiltered[] = $className;
            }
        }

        return $classNamesFiltered;
    }

    public function setOutput(OutputStyle $output): void
    {
        $this->output = $output;
    }

    protected function notifyException(ClientException $exception)
    {
        $message = str_replace(
            rtrim($exception->getMessage()),
            (string) $exception->getResponse()->getBody(),
            (string) $exception
        );

        throw new \Exception($message);
    }

    private function callEsApi($method, $url, $json = [], $bulk = false)
    {
        $options = [];
        if ($bulk) {
            $options += ['body' => $json];
            $options['headers']['Content-Type'] = 'application/json';
        } else {
            if ($json) {
                $options += [\GuzzleHttp\RequestOptions::JSON => $json];
            }
        }

        try {
            $response = $this->client->request($method, $url, $options);
        } catch (ClientException $exception) {
            $message = str_replace(
                rtrim($exception->getMessage()),
                (string) $exception->getResponse()->getBody(),
                (string) $exception
            );

            throw new \Exception($message);
        }

        if (!in_array($response->getStatusCode(), [200, 201])) {
            throw new \Exception($response['error']);
        }
    }

    private function log($text)
    {
        $this->output->text($text);
    }
}
