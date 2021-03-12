<?php

namespace Fico7489\Es;

use GuzzleHttp\Client;

trait EsEloquentBuilderTrait
{
    public function getMultiMatchIds($model, $q, $textFields)
    {
        $options = [
            'json' => [
                'query' => [
                    'multi_match' => [
                        'query' => $q,
                        'fields' => $textFields,
                        'fuzziness' => 'AUTO',
                    ],
                ],
            ],
        ];

        $ids = $this->fetchEsIds($model, $options);

        return collect($ids);
    }

    public function getPrefixIds($model, $q, $textFields)
    {
        $options = [
            'json' => [
                'query' => [
                    'match_bool_prefix' => [
                        $textFields => $q,
                    ],
                ],
            ],
        ];

        $ids = $this->fetchEsIds($model, $options);

        return collect($ids);
    }

    private function fetchEsIds($model, $options): array
    {
        $options['headers'] = ['Content-Type' => 'application/json'];

        $client = new Client([
            'base_uri' => 'http://'.config('database.elasticsearch.host').':'.config('database.elasticsearch.port').'/',
            'timeout' => config('database.elasticsearch.timeout'),
        ]);

        $response = $client->request('GET', $model->getEsIndexName().'/_search', $options);

        $content = $response->getBody()->getContents();
        $content = json_decode($content, true);

        $ids = [];
        if (isset($content['hits']['hits'])) {
            $hits = $content['hits']['hits'];

            foreach ($hits as $hit) {
                $ids[$hit['_id']] = $hit['_score'];
            }
        }

        return $ids;
    }

    private function filterEsIds($model, $ids)
    {
        $tableBase = $model->getTable();
        $tableEs = 'es_'.$tableBase.'_'.sha1(time());
        \DB::statement('CREATE TEMPORARY TABLE IF NOT EXISTS  '.$tableEs.' (
                            `id` int(10) unsigned NOT NULL,
                            `_score` DOUBLE DEFAULT 0,
                            CONSTRAINT PRIMARY KEY (id)
                        ) ENGINE = MEMORY;');

        $data = [];
        foreach ($ids as $id => $score) {
            $data[] = ['id' => $id, '_score' => $score];
        }
        \DB::table($tableEs)->insert($data);

        return $this
            ->addSelect([$tableBase.'.*', $tableEs.'._score'])
            ->join($tableEs, $tableBase.'.id', '=', $tableEs.'.id')
            ->groupBy($tableBase.'.id')
            ->whereIn($tableBase.'.id', array_keys($ids));
    }
}
