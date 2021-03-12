<?php

namespace Fico7489\Es;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class EsModelObserver
{
    /** @var EsIndexManager */
    private $esIndexManager;

    public function __construct(EsIndexManager $esIndexManager)
    {
        $this->esIndexManager = $esIndexManager;
    }

    public function saved(Model $model)
    {
        if (0 == count($model->getChanges())) {
            return;
        }

        if ($model instanceof EsInterface && $model->getIsForSync()) {
            $this->esIndexManager->upsertModel($model);
        }

        $classes = EsIndexManager::getClassNamesForIndexes();
        foreach ($classes as $class) {
            $relatedUpdate = (new $class())::getRelatedUpdate();
            foreach ($relatedUpdate as $modelRelated => $relation) {
                if ($model instanceof $modelRelated) {
                    foreach ($model->{$relation} as $modelBase) {
                        $this->saved($modelBase);
                    }
                }
            }
        }
    }

    public function deleted(Model $model)
    {
        $useSoftDelete = in_array(SoftDeletes::class, class_uses_recursive($model));

        if ($model instanceof EsInterface) {
            if ($useSoftDelete && $model->getStoreSoftDeleted()) {
                $this->esIndexManager->upsertModel($model);
            } else {
                $this->esIndexManager->deleteModel($model);
            }
        }
    }

    public function forceDeleted(Model $model)
    {
        if ($model instanceof EsInterface) {
            $this->esIndexManager->deleteModel($model);
        }
    }

    public function restored(Model $model)
    {
        $this->saved($model);
    }
}
