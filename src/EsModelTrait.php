<?php

namespace Fico7489\Es;

use Illuminate\Database\Eloquent\Collection;

trait EsModelTrait
{
    public static function bootEsModelTrait()
    {
        static::observe(app(EsModelObserver::class));
    }

    public function getEsIndexPrefix(): string
    {
        return config('database.elasticsearch.prefix');
    }

    public function getEsIndexName(): string
    {
        return $this->getEsIndexPrefix().''.$this->getTable().'_index';
    }

    public function getEsData(): array
    {
        $relations = $this->getEsDataRelations();
        $this->load($relations);

        foreach ($relations as $relation) {
            if ($this->{$relation} instanceof Collection) {
                foreach ($this->{$relation} as $k => $relationOne) {
                    $relationOne->setAppends($relationOne->getMutatedAttributes());
                }
            } else {
                if ($this->{$relation}) {
                    $this->{$relation}->setAppends($this->{$relation}->getMutatedAttributes());
                }
            }
        }

        return $this->toArray();
    }

    public function getEsDataRelations(): array
    {
        return [];
    }

    public function getEsId(): int
    {
        return $this->id;
    }

    public function getIsForSync(): bool
    {
        return true;
    }

    public function getStoreSoftDeleted(): bool
    {
        return true;
    }

    public static function getRelatedUpdate(): array
    {
        return [];
    }
}
